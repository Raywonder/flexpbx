<?php
/**
 * FlexPBX Backup Module v2
 * Supports both .flx (config) and .flxx (full system) backup formats
 *
 * @package FlexPBX
 * @version 2.0.0
 */

class FlexPBX_Backup {

    private $config;
    private $backup_dir = '/var/backups/flexpbx';
    private $temp_dir = '/tmp/flexpbx_backup';

    // Backup format definitions
    const FORMAT_FLX = 'flx';    // Config-only backup
    const FORMAT_FLXX = 'flxx';  // Full system backup

    // Component categories
    private $config_components = [
        'asterisk_config',
        'flexpbx_app',
        'database',
        'cdr'
    ];

    private $data_components = [
        'voicemail',
        'moh',
        'sounds',
        'recordings',
        'fax',
        'system_logs'
    ];

    public function __construct() {
        // Try multiple config paths
        $config_paths = [
            dirname(__FILE__) . '/../../config/backup-config.json',
            dirname(__FILE__) . '/../config/backup-config.json',
            '/home/flexpbxuser/apps/flexpbx/config/backup-config.json',
            '/etc/flexpbx/backup-config.json'
        ];

        foreach ($config_paths as $config_file) {
            if (file_exists($config_file)) {
                $this->config = json_decode(file_get_contents($config_file), true);
                break;
            }
        }

        if (!is_dir($this->backup_dir)) {
            mkdir($this->backup_dir, 0750, true);
        }

        // Create subdirectories for each format
        foreach ([self::FORMAT_FLX, self::FORMAT_FLXX] as $format) {
            $dir = "{$this->backup_dir}/{$format}";
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
        }
    }

    /**
     * Create a backup in specified format
     *
     * @param string $format 'flx' for config-only, 'flxx' for full system
     * @param array $options Additional options
     * @return array Result with backup details
     */
    public function createBackup($format = self::FORMAT_FLXX, $options = []) {
        $timestamp = date('Ymd_His');
        $hostname = gethostname();
        $backup_name = "flexpbx_{$hostname}_{$timestamp}";
        $temp_path = "{$this->temp_dir}/{$backup_name}";

        // Create temp directory
        if (!is_dir($temp_path)) {
            mkdir($temp_path, 0750, true);
        }

        $manifest = [
            'backup_name' => $backup_name,
            'format' => $format,
            'format_description' => $format === self::FORMAT_FLX ? 'Configuration Only' : 'Full System Backup',
            'created' => date('c'),
            'hostname' => $hostname,
            'version' => $this->config['version'] ?? '2.0.0',
            'flexpbx_version' => $this->getFlexPBXVersion(),
            'asterisk_version' => $this->getAsteriskVersion(),
            'contents' => [],
            'size' => 0,
            'components' => []
        ];

        // Determine which components to backup based on format
        $components_to_backup = $this->getComponentsForFormat($format, $options);

        foreach ($components_to_backup as $key => $component) {
            if ($component['enabled'] ?? false) {
                $result = $this->backupComponent($key, $component, $temp_path);
                if ($result && $result['status'] === 'success') {
                    $manifest['contents'][$key] = $result;
                    $manifest['components'][] = $key;
                }
            }
        }

        // Create manifest
        file_put_contents("{$temp_path}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT));

        // Create archive with appropriate extension
        $archive_dir = "{$this->backup_dir}/{$format}";
        $archive_path = "{$archive_dir}/{$backup_name}.{$format}";
        $this->createArchive($temp_path, $archive_path);

        // Cleanup temp
        $this->removeDirectory($temp_path);

        // Get final size
        $manifest['size'] = filesize($archive_path);
        $manifest['size_formatted'] = $this->formatBytes($manifest['size']);
        $manifest['archive_path'] = $archive_path;
        $manifest['status'] = true;

        // Upload to remote storage if enabled
        if ($options['upload_remote'] ?? false) {
            $remote_results = $this->uploadToRemote($archive_path, $manifest);
            $manifest['remote_storage'] = $remote_results;
        }

        // Cleanup old backups based on retention
        $this->cleanupOldBackups($format);

        // Log backup creation
        $this->logBackup($manifest);

        return $manifest;
    }

    /**
     * Get components based on backup format
     */
    private function getComponentsForFormat($format, $options = []) {
        $all_contents = $this->config['backup_contents'] ?? [];
        $components = [];

        // Selected components override if provided
        $selected = $options['components'] ?? null;

        foreach ($all_contents as $key => $component) {
            $include = false;

            if ($selected !== null) {
                // Use explicitly selected components
                $include = in_array($key, $selected);
            } else {
                // Use format defaults
                if ($format === self::FORMAT_FLX) {
                    // Config-only: just config components
                    $include = in_array($key, $this->config_components);
                } else {
                    // Full system: everything enabled
                    $include = $component['enabled'] ?? true;
                }
            }

            if ($include) {
                $components[$key] = array_merge($component, ['enabled' => true]);
            }
        }

        return $components;
    }

    /**
     * Backup individual component
     */
    private function backupComponent($key, $component, $temp_path) {
        $result = ['status' => 'success', 'files' => 0, 'size' => 0, 'component' => $key];

        switch ($key) {
            case 'asterisk_config':
                $result = $this->backupDirectory('/etc/asterisk', "{$temp_path}/asterisk_config", $component);
                break;

            case 'flexpbx_app':
                $app_path = $component['path'] ?? '/home/flexpbxuser/apps/flexpbx';
                $result = $this->backupDirectory($app_path, "{$temp_path}/flexpbx_app", $component, [
                    'exclude' => ['node_modules', 'dist', '.git', 'logs']
                ]);
                break;

            case 'database':
                $result = $this->backupDatabase("{$temp_path}/database");
                break;

            case 'cdr':
                $result = $this->backupCDR("{$temp_path}/cdr");
                break;

            case 'voicemail':
                $vm_path = $component['path'] ?? '/var/spool/asterisk/voicemail';
                $result = $this->backupDirectory($vm_path, "{$temp_path}/voicemail", $component);
                break;

            case 'moh':
                $moh_path = $component['path'] ?? '/var/lib/asterisk/moh';
                $result = $this->backupDirectory($moh_path, "{$temp_path}/moh", $component);
                break;

            case 'sounds':
                $sounds_path = $component['path'] ?? '/var/lib/asterisk/sounds/custom';
                $result = $this->backupDirectory($sounds_path, "{$temp_path}/sounds", $component);
                break;

            case 'recordings':
                $rec_path = $component['path'] ?? '/var/spool/asterisk/monitor';
                $result = $this->backupDirectory($rec_path, "{$temp_path}/recordings", $component, [
                    'max_age_days' => $component['max_age_days'] ?? 30
                ]);
                break;

            case 'fax':
                $fax_path = $component['path'] ?? '/var/spool/asterisk/fax';
                $result = $this->backupDirectory($fax_path, "{$temp_path}/fax", $component);
                break;

            case 'system_logs':
                $result = $this->backupSystemLogs("{$temp_path}/logs");
                break;
        }

        $result['component'] = $key;
        return $result;
    }

    /**
     * Backup a directory
     */
    private function backupDirectory($source, $dest, $component = [], $options = []) {
        $result = ['status' => 'success', 'files' => 0, 'size' => 0];

        if (!is_dir($source)) {
            $result['status'] = 'skipped';
            $result['message'] = 'Source directory does not exist';
            return $result;
        }

        mkdir($dest, 0750, true);

        // Build rsync command with excludes
        $excludes = '';
        foreach ($options['exclude'] ?? [] as $ex) {
            $excludes .= " --exclude='{$ex}'";
        }

        // Handle max age filter for recordings
        if (isset($options['max_age_days'])) {
            $days = (int)$options['max_age_days'];
            exec("find {$source} -type f -mtime -{$days} -exec cp --parents {} {$dest}/ \\; 2>/dev/null");
        } else {
            exec("rsync -a {$excludes} {$source}/ {$dest}/ 2>/dev/null");
        }

        $result['files'] = (int)trim(shell_exec("find {$dest} -type f 2>/dev/null | wc -l"));
        $result['size'] = (int)trim(shell_exec("du -sb {$dest} 2>/dev/null | cut -f1"));

        return $result;
    }

    /**
     * Backup database
     */
    private function backupDatabase($dest) {
        $result = ['status' => 'success', 'files' => 0, 'size' => 0];
        mkdir($dest, 0750, true);

        $db_config = $this->getDatabaseConfig();
        if (!$db_config) {
            $result['status'] = 'skipped';
            $result['message'] = 'Database config not found';
            return $result;
        }

        // FlexPBX database
        $dump_file = "{$dest}/flexpbx_db.sql";
        $cmd = sprintf(
            "mysqldump -h%s -u%s -p'%s' %s > %s 2>/dev/null",
            escapeshellarg($db_config['host']),
            escapeshellarg($db_config['user']),
            $db_config['pass'],
            escapeshellarg($db_config['name']),
            $dump_file
        );
        exec($cmd);

        // Asterisk database if exists
        $asterisk_dump = "{$dest}/asterisk_db.sql";
        exec("mysqldump -uroot asterisk > {$asterisk_dump} 2>/dev/null");

        // SQLite databases
        exec("cp /var/lib/asterisk/*.sqlite3 {$dest}/ 2>/dev/null");

        $result['files'] = (int)trim(shell_exec("find {$dest} -type f | wc -l"));
        $result['size'] = (int)trim(shell_exec("du -sb {$dest} | cut -f1"));

        return $result;
    }

    /**
     * Backup CDR records
     */
    private function backupCDR($dest) {
        $result = ['status' => 'success', 'files' => 0, 'size' => 0];
        mkdir($dest, 0750, true);

        $db_config = $this->getDatabaseConfig();
        if ($db_config) {
            // Export CDR table
            exec("mysqldump -h{$db_config['host']} -u{$db_config['user']} -p'{$db_config['pass']}' {$db_config['name']} cdr > {$dest}/cdr.sql 2>/dev/null");

            // Also export as CSV for easy viewing
            exec("mysql -h{$db_config['host']} -u{$db_config['user']} -p'{$db_config['pass']}' {$db_config['name']} -e 'SELECT * FROM cdr' -B > {$dest}/cdr.csv 2>/dev/null");
        }

        // Also backup asterisk CDR
        exec("mysqldump -uroot asterisk cdr > {$dest}/asterisk_cdr.sql 2>/dev/null");

        $result['files'] = (int)trim(shell_exec("find {$dest} -type f | wc -l"));
        $result['size'] = (int)trim(shell_exec("du -sb {$dest} | cut -f1"));

        return $result;
    }

    /**
     * Backup system logs
     */
    private function backupSystemLogs($dest) {
        $result = ['status' => 'success', 'files' => 0, 'size' => 0];
        mkdir($dest, 0750, true);

        // Asterisk logs (last 7 days)
        exec("find /var/log/asterisk -type f -mtime -7 -exec cp {} {$dest}/ \\; 2>/dev/null");

        // FlexPBX logs
        exec("cp -r /home/flexpbxuser/apps/flexpbx/logs/* {$dest}/ 2>/dev/null");

        $result['files'] = (int)trim(shell_exec("find {$dest} -type f | wc -l"));
        $result['size'] = (int)trim(shell_exec("du -sb {$dest} | cut -f1"));

        return $result;
    }

    /**
     * Restore from backup
     *
     * @param string $archive_path Path to .flx or .flxx file
     * @param array $options Restoration options
     * @return array Result
     */
    public function restoreBackup($archive_path, $options = []) {
        if (!file_exists($archive_path)) {
            return ['status' => false, 'error' => 'Backup file not found'];
        }

        // Determine format from extension
        $format = pathinfo($archive_path, PATHINFO_EXTENSION);
        if (!in_array($format, [self::FORMAT_FLX, self::FORMAT_FLXX])) {
            return ['status' => false, 'error' => 'Invalid backup format'];
        }

        $temp_path = "{$this->temp_dir}/restore_" . time();
        mkdir($temp_path, 0750, true);

        // Extract archive
        $this->extractArchive($archive_path, $temp_path);

        // Read manifest
        $manifest_file = "{$temp_path}/manifest.json";
        if (!file_exists($manifest_file)) {
            $this->removeDirectory($temp_path);
            return ['status' => false, 'error' => 'Invalid backup - no manifest'];
        }

        $manifest = json_decode(file_get_contents($manifest_file), true);
        $results = [
            'status' => true,
            'backup_name' => $manifest['backup_name'],
            'backup_date' => $manifest['created'],
            'format' => $format,
            'restored_components' => []
        ];

        // Stop Asterisk before restore if restoring configs
        $restart_asterisk = false;
        if ($this->shouldRestartAsterisk($options['components'] ?? array_keys($manifest['contents']))) {
            exec('systemctl stop asterisk 2>/dev/null');
            $restart_asterisk = true;
        }

        // Restore each component
        $selected_components = $options['components'] ?? array_keys($manifest['contents']);

        foreach ($manifest['contents'] as $key => $info) {
            if (!in_array($key, $selected_components)) {
                continue;
            }

            $component_result = $this->restoreComponent($key, $temp_path, $options);
            $results['restored_components'][$key] = $component_result;

            if ($component_result['status'] !== 'success') {
                $results['warnings'][] = "Component {$key}: {$component_result['message']}";
            }
        }

        // Restart Asterisk if needed
        if ($restart_asterisk) {
            exec('systemctl start asterisk 2>/dev/null');
            $results['asterisk_restarted'] = true;
        }

        // Cleanup
        $this->removeDirectory($temp_path);

        // Log restoration
        $this->logRestore($archive_path, $results);

        return $results;
    }

    /**
     * Restore individual component
     */
    private function restoreComponent($key, $temp_path, $options = []) {
        $result = ['status' => 'success', 'message' => ''];
        $source = "{$temp_path}/{$key}";

        if (!is_dir($source)) {
            return ['status' => 'skipped', 'message' => 'Component not in backup'];
        }

        $backup_existing = $options['backup_existing'] ?? true;

        switch ($key) {
            case 'asterisk_config':
                $dest = '/etc/asterisk';
                if ($backup_existing) {
                    $this->createPreRestoreBackup($dest, 'asterisk_config');
                }
                exec("rsync -a --backup --suffix=.bak.restore {$source}/ {$dest}/ 2>&1", $output, $ret);
                $result['message'] = $ret === 0 ? 'Asterisk config restored' : 'Errors during restore';
                break;

            case 'flexpbx_app':
                $dest = '/home/flexpbxuser/apps/flexpbx';
                if ($backup_existing) {
                    $this->createPreRestoreBackup($dest, 'flexpbx_app');
                }
                // Preserve node_modules and logs
                exec("rsync -a --exclude='node_modules' --exclude='logs' {$source}/ {$dest}/ 2>&1", $output, $ret);
                $result['message'] = $ret === 0 ? 'FlexPBX app restored' : 'Errors during restore';
                break;

            case 'database':
                $db_config = $this->getDatabaseConfig();
                if ($db_config) {
                    $dump_file = "{$source}/flexpbx_db.sql";
                    if (file_exists($dump_file)) {
                        // Create backup of current DB
                        if ($backup_existing) {
                            exec("mysqldump -h{$db_config['host']} -u{$db_config['user']} -p'{$db_config['pass']}' {$db_config['name']} > /tmp/pre_restore_db.sql 2>/dev/null");
                        }
                        exec("mysql -h{$db_config['host']} -u{$db_config['user']} -p'{$db_config['pass']}' {$db_config['name']} < {$dump_file} 2>&1", $output, $ret);
                        $result['message'] = $ret === 0 ? 'Database restored' : 'Database restore failed';
                    }
                }
                break;

            case 'voicemail':
                $dest = '/var/spool/asterisk/voicemail';
                exec("rsync -a {$source}/ {$dest}/ 2>&1", $output, $ret);
                exec("chown -R asterisk:asterisk {$dest}");
                $result['message'] = 'Voicemail restored';
                break;

            case 'moh':
                $dest = '/var/lib/asterisk/moh';
                exec("rsync -a {$source}/ {$dest}/ 2>&1", $output, $ret);
                exec("chown -R asterisk:asterisk {$dest}");
                $result['message'] = 'Music on hold restored';
                break;

            case 'sounds':
                $dest = '/var/lib/asterisk/sounds/custom';
                mkdir($dest, 0755, true);
                exec("rsync -a {$source}/ {$dest}/ 2>&1", $output, $ret);
                exec("chown -R asterisk:asterisk {$dest}");
                $result['message'] = 'Custom sounds restored';
                break;

            case 'recordings':
                $dest = '/var/spool/asterisk/monitor';
                exec("rsync -a {$source}/ {$dest}/ 2>&1", $output, $ret);
                exec("chown -R asterisk:asterisk {$dest}");
                $result['message'] = 'Recordings restored';
                break;

            case 'fax':
                $dest = '/var/spool/asterisk/fax';
                exec("rsync -a {$source}/ {$dest}/ 2>&1", $output, $ret);
                exec("chown -R asterisk:asterisk {$dest}");
                $result['message'] = 'Fax documents restored';
                break;

            case 'cdr':
                $db_config = $this->getDatabaseConfig();
                if ($db_config && file_exists("{$source}/cdr.sql")) {
                    exec("mysql -h{$db_config['host']} -u{$db_config['user']} -p'{$db_config['pass']}' {$db_config['name']} < {$source}/cdr.sql 2>&1", $output, $ret);
                    $result['message'] = $ret === 0 ? 'CDR restored' : 'CDR restore failed';
                }
                break;

            default:
                $result['status'] = 'skipped';
                $result['message'] = 'Unknown component';
        }

        return $result;
    }

    /**
     * Create pre-restore backup of existing data
     */
    private function createPreRestoreBackup($path, $name) {
        $timestamp = date('Ymd_His');
        $backup_path = "/tmp/flexpbx_prerestore_{$name}_{$timestamp}";
        exec("cp -r {$path} {$backup_path} 2>/dev/null");
        return $backup_path;
    }

    /**
     * Check if Asterisk should be restarted for these components
     */
    private function shouldRestartAsterisk($components) {
        $asterisk_components = ['asterisk_config', 'database', 'moh', 'sounds'];
        return !empty(array_intersect($components, $asterisk_components));
    }

    /**
     * Extract archive based on format
     */
    private function extractArchive($archive_path, $dest) {
        // Check if encrypted
        if ($this->config['encryption']['enabled'] ?? false) {
            $this->decryptFile($archive_path);
        }

        exec("tar -xzf {$archive_path} -C {$dest} 2>/dev/null");
    }

    /**
     * Create compressed archive
     */
    private function createArchive($source_dir, $archive_path) {
        $compression = $this->config['compression'] ?? ['enabled' => true, 'level' => 6];

        if ($compression['enabled']) {
            $level = $compression['level'] ?? 6;
            exec("tar -czf {$archive_path} --transform 's|^./||' -C {$source_dir} . 2>/dev/null");
        } else {
            exec("tar -cf {$archive_path} --transform 's|^./||' -C {$source_dir} . 2>/dev/null");
        }

        // Encrypt if enabled
        if ($this->config['encryption']['enabled'] ?? false) {
            $this->encryptFile($archive_path);
        }

        return file_exists($archive_path);
    }

    /**
     * List available backups
     */
    public function listBackups($format = null, $include_remote = false) {
        $backups = [];

        $formats = $format ? [$format] : [self::FORMAT_FLX, self::FORMAT_FLXX];

        foreach ($formats as $fmt) {
            $dir = "{$this->backup_dir}/{$fmt}";
            if (!is_dir($dir)) continue;

            $files = glob("{$dir}/*.{$fmt}");
            foreach ($files as $file) {
                $manifest = $this->getBackupManifest($file);

                $backups[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'format' => $fmt,
                    'format_name' => $fmt === self::FORMAT_FLX ? 'Config Only' : 'Full System',
                    'size' => filesize($file),
                    'size_formatted' => $this->formatBytes(filesize($file)),
                    'created' => date('c', filemtime($file)),
                    'created_formatted' => date('Y-m-d H:i:s', filemtime($file)),
                    'location' => 'local',
                    'components' => $manifest['components'] ?? [],
                    'hostname' => $manifest['hostname'] ?? 'unknown'
                ];
            }
        }

        // Sort by date descending
        usort($backups, function($a, $b) {
            return filemtime($b['path']) - filemtime($a['path']);
        });

        // Remote backups
        if ($include_remote && ($this->config['storage_options']['remote_cloud']['enabled'] ?? false)) {
            $remote = $this->listRemoteBackups();
            $backups = array_merge($backups, $remote);
        }

        return $backups;
    }

    /**
     * Get manifest from backup file
     */
    private function getBackupManifest($archive_path) {
        $temp = "/tmp/manifest_read_" . md5($archive_path);
        mkdir($temp, 0750, true);

        exec("tar -xzf {$archive_path} -C {$temp} manifest.json 2>/dev/null");

        $manifest = [];
        if (file_exists("{$temp}/manifest.json")) {
            $manifest = json_decode(file_get_contents("{$temp}/manifest.json"), true);
        }

        $this->removeDirectory($temp);
        return $manifest;
    }

    /**
     * Get backup details
     */
    public function getBackupDetails($archive_path) {
        if (!file_exists($archive_path)) {
            return ['error' => 'Backup not found'];
        }

        $manifest = $this->getBackupManifest($archive_path);
        $manifest['file_size'] = filesize($archive_path);
        $manifest['file_size_formatted'] = $this->formatBytes(filesize($archive_path));
        $manifest['file_date'] = date('c', filemtime($archive_path));

        return $manifest;
    }

    /**
     * Delete backup
     */
    public function deleteBackup($archive_path) {
        if (!file_exists($archive_path)) {
            return ['status' => false, 'error' => 'Backup not found'];
        }

        // Verify it's in our backup directory
        if (strpos(realpath($archive_path), realpath($this->backup_dir)) !== 0) {
            return ['status' => false, 'error' => 'Invalid backup path'];
        }

        unlink($archive_path);
        return ['status' => true, 'message' => 'Backup deleted'];
    }

    /**
     * Download backup from remote storage
     */
    public function downloadFromRemote($backup_id, $format = null) {
        $config = $this->config['storage_options']['remote_cloud'] ?? [];
        if (!$config['enabled']) {
            return ['status' => false, 'error' => 'Remote storage not enabled'];
        }

        $endpoint = $config['endpoint'];
        $api_key = $config['api_key'];

        $ch = curl_init("{$endpoint}/download/{$backup_id}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$api_key}"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            // Save to local
            $data = json_decode($response, true);
            $local_path = "{$this->backup_dir}/{$format}/{$data['filename']}";
            file_put_contents($local_path, base64_decode($data['content']));

            return ['status' => true, 'path' => $local_path];
        }

        return ['status' => false, 'error' => 'Download failed'];
    }

    /**
     * Upload to remote storage
     */
    private function uploadToRemote($archive_path, $manifest) {
        $results = [];
        $storage = $this->config['storage_options'] ?? [];

        // Devine Cloud Storage
        if ($storage['remote_cloud']['enabled'] ?? false) {
            $results['remote_cloud'] = $this->uploadToDevineCloud($archive_path, $manifest);
        }

        // S3 Compatible
        if ($storage['s3_compatible']['enabled'] ?? false) {
            $results['s3'] = $this->uploadToS3($archive_path, $manifest);
        }

        // SFTP
        if ($storage['sftp']['enabled'] ?? false) {
            $results['sftp'] = $this->uploadToSFTP($archive_path, $manifest);
        }

        return $results;
    }

    /**
     * Upload to Devine Cloud Storage
     */
    private function uploadToDevineCloud($archive_path, $manifest) {
        $config = $this->config['storage_options']['remote_cloud'];
        $endpoint = $config['endpoint'];
        $api_key = $config['api_key'] ?? '';

        if (empty($api_key)) {
            return ['status' => 'error', 'message' => 'API key not configured'];
        }

        // Check storage quota
        $quota = $this->checkRemoteQuota();
        $file_size = filesize($archive_path);

        if (($quota['used'] + $file_size) > $quota['limit']) {
            return ['status' => 'error', 'message' => 'Storage quota exceeded'];
        }

        // Upload file
        $ch = curl_init("{$endpoint}/upload");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => new CURLFile($archive_path),
            'manifest' => json_encode($manifest),
            'format' => $manifest['format']
        ]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$api_key}"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            return ['status' => 'success', 'response' => json_decode($response, true)];
        }

        return ['status' => 'error', 'http_code' => $http_code];
    }

    /**
     * Check remote storage quota
     */
    private function checkRemoteQuota() {
        $config = $this->config['storage_options']['remote_cloud'];

        $ch = curl_init("{$config['endpoint']}/quota");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$config['api_key']}"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return [
            'used' => $data['used'] ?? 0,
            'limit' => $data['limit'] ?? 5 * 1024 * 1024 * 1024 // 5GB default
        ];
    }

    /**
     * Cleanup old backups based on retention policy
     */
    private function cleanupOldBackups($format = null) {
        $formats = $format ? [$format] : [self::FORMAT_FLX, self::FORMAT_FLXX];
        $max_backups = $this->config['storage_options']['local']['max_backups'] ?? 10;

        foreach ($formats as $fmt) {
            $dir = "{$this->backup_dir}/{$fmt}";
            $files = glob("{$dir}/*.{$fmt}");

            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });

            $count = 0;
            foreach ($files as $file) {
                $count++;
                if ($count > $max_backups) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Get storage usage statistics
     */
    public function getStorageStats() {
        $stats = [
            'local' => [
                'flx' => ['count' => 0, 'size' => 0],
                'flxx' => ['count' => 0, 'size' => 0],
                'total_size' => 0,
                'total_count' => 0
            ],
            'remote' => [
                'enabled' => false,
                'used' => 0,
                'limit' => 0
            ]
        ];

        foreach ([self::FORMAT_FLX, self::FORMAT_FLXX] as $format) {
            $dir = "{$this->backup_dir}/{$format}";
            if (is_dir($dir)) {
                $files = glob("{$dir}/*.{$format}");
                foreach ($files as $file) {
                    $stats['local'][$format]['count']++;
                    $stats['local'][$format]['size'] += filesize($file);
                }
            }
            $stats['local']['total_count'] += $stats['local'][$format]['count'];
            $stats['local']['total_size'] += $stats['local'][$format]['size'];
        }

        $stats['local']['total_size_formatted'] = $this->formatBytes($stats['local']['total_size']);
        $stats['local']['flx']['size_formatted'] = $this->formatBytes($stats['local']['flx']['size']);
        $stats['local']['flxx']['size_formatted'] = $this->formatBytes($stats['local']['flxx']['size']);

        // Remote stats
        if ($this->config['storage_options']['remote_cloud']['enabled'] ?? false) {
            $quota = $this->checkRemoteQuota();
            $stats['remote'] = [
                'enabled' => true,
                'used' => $quota['used'],
                'used_formatted' => $this->formatBytes($quota['used']),
                'limit' => $quota['limit'],
                'limit_formatted' => $this->formatBytes($quota['limit']),
                'percent_used' => round(($quota['used'] / $quota['limit']) * 100, 1)
            ];
        }

        return $stats;
    }

    /**
     * Schedule automated backup
     */
    public function scheduleBackup($format, $schedule, $options = []) {
        $cron_line = $this->buildCronLine($schedule, $format, $options);

        // Add to crontab
        $cron_file = '/etc/cron.d/flexpbx-backup';
        $content = "# FlexPBX Automated Backups\n";
        $content .= "SHELL=/bin/bash\n";
        $content .= "PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin\n\n";
        $content .= $cron_line . "\n";

        file_put_contents($cron_file, $content);
        chmod($cron_file, 0644);

        return ['status' => true, 'cron' => $cron_line];
    }

    /**
     * Build cron line for backup schedule
     */
    private function buildCronLine($schedule, $format, $options) {
        $php = '/usr/bin/php';
        $script = dirname(__FILE__) . '/backup-cron.php';
        $opts = base64_encode(json_encode(array_merge($options, ['format' => $format])));

        switch ($schedule) {
            case 'hourly':
                return "0 * * * * root {$php} {$script} {$opts} >> /var/log/flexpbx-backup.log 2>&1";
            case 'daily':
                return "0 2 * * * root {$php} {$script} {$opts} >> /var/log/flexpbx-backup.log 2>&1";
            case 'weekly':
                return "0 2 * * 0 root {$php} {$script} {$opts} >> /var/log/flexpbx-backup.log 2>&1";
            case 'monthly':
                return "0 2 1 * * root {$php} {$script} {$opts} >> /var/log/flexpbx-backup.log 2>&1";
            default:
                return "0 2 * * * root {$php} {$script} {$opts} >> /var/log/flexpbx-backup.log 2>&1";
        }
    }

    // Helper methods

    private function getFlexPBXVersion() {
        $version_file = '/home/flexpbxuser/apps/flexpbx/package.json';
        if (file_exists($version_file)) {
            $pkg = json_decode(file_get_contents($version_file), true);
            return $pkg['version'] ?? 'unknown';
        }
        return 'unknown';
    }

    private function getAsteriskVersion() {
        $output = shell_exec('asterisk -V 2>/dev/null');
        return trim($output) ?: 'unknown';
    }

    private function getDatabaseConfig() {
        $config_file = dirname(__FILE__) . '/../config/database.php';
        if (file_exists($config_file)) {
            include $config_file;
            return $db_config ?? null;
        }
        return null;
    }

    public function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
    }

    private function removeDirectory($dir) {
        if (is_dir($dir)) {
            exec("rm -rf " . escapeshellarg($dir));
        }
    }

    private function logBackup($manifest) {
        $log_file = '/var/log/flexpbx-backup.log';
        $entry = date('c') . " BACKUP: {$manifest['backup_name']} ({$manifest['format']}) - {$manifest['size_formatted']}\n";
        file_put_contents($log_file, $entry, FILE_APPEND);
    }

    private function logRestore($archive_path, $results) {
        $log_file = '/var/log/flexpbx-backup.log';
        $entry = date('c') . " RESTORE: " . basename($archive_path) . " - Components: " . implode(', ', array_keys($results['restored_components'])) . "\n";
        file_put_contents($log_file, $entry, FILE_APPEND);
    }

    private function encryptFile($path) {
        $key = $this->config['encryption']['key'] ?? '';
        if (empty($key)) return;

        $encrypted = "{$path}.enc";
        exec("openssl enc -aes-256-cbc -salt -pbkdf2 -in {$path} -out {$encrypted} -pass pass:{$key} 2>/dev/null");
        if (file_exists($encrypted)) {
            rename($encrypted, $path);
        }
    }

    private function decryptFile($path) {
        $key = $this->config['encryption']['key'] ?? '';
        if (empty($key)) return;

        $decrypted = "{$path}.dec";
        exec("openssl enc -d -aes-256-cbc -pbkdf2 -in {$path} -out {$decrypted} -pass pass:{$key} 2>/dev/null");
        if (file_exists($decrypted)) {
            rename($decrypted, $path);
        }
    }

    private function uploadToS3($archive_path, $manifest) {
        $config = $this->config['storage_options']['s3_compatible']['config'] ?? [];
        $bucket = $config['bucket'] ?? '';

        if (empty($bucket)) {
            return ['status' => 'error', 'message' => 'S3 bucket not configured'];
        }

        $key = $manifest['format'] . '/' . basename($archive_path);
        $cmd = "aws s3 cp {$archive_path} s3://{$bucket}/{$key}";

        if (!empty($config['endpoint'])) {
            $cmd .= " --endpoint-url {$config['endpoint']}";
        }

        exec($cmd . " 2>&1", $output, $return_var);

        return [
            'status' => $return_var === 0 ? 'success' : 'error',
            'output' => implode("\n", $output)
        ];
    }

    private function uploadToSFTP($archive_path, $manifest) {
        $config = $this->config['storage_options']['sftp']['config'] ?? [];

        $host = $config['host'] ?? '';
        $port = $config['port'] ?? 22;
        $user = $config['username'] ?? '';
        $remote_path = $config['remote_path'] ?? '/backups';
        $filename = basename($archive_path);

        if (empty($host) || empty($user)) {
            return ['status' => 'error', 'message' => 'SFTP not configured'];
        }

        if (!empty($config['key_file'])) {
            $cmd = "scp -P {$port} -i {$config['key_file']} {$archive_path} {$user}@{$host}:{$remote_path}/{$manifest['format']}/{$filename}";
        } else {
            $cmd = "sshpass -p '{$config['password']}' scp -P {$port} {$archive_path} {$user}@{$host}:{$remote_path}/{$manifest['format']}/{$filename}";
        }

        exec($cmd . " 2>&1", $output, $return_var);

        return [
            'status' => $return_var === 0 ? 'success' : 'error',
            'output' => implode("\n", $output)
        ];
    }

    private function listRemoteBackups() {
        $config = $this->config['storage_options']['remote_cloud'] ?? [];
        if (!$config['enabled']) return [];

        $ch = curl_init("{$config['endpoint']}/list");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$config['api_key']}"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $backups = [];

        foreach ($data['backups'] ?? [] as $backup) {
            $backups[] = array_merge($backup, ['location' => 'remote']);
        }

        return $backups;
    }
}
