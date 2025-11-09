<?php
/**
 * FlexPBX Permission Helper
 * Automatically fixes file permissions for Asterisk configs, audio files, and web files
 *
 * This runs in the background whenever modules are accessed/modified to ensure
 * seamless operation without permission issues.
 */

class PermissionHelper {

    // Permission configurations
    private const ASTERISK_USER = 'asterisk';
    private const ASTERISK_GROUP = 'asterisk';
    private const WEB_USER = 'flexpbxuser';
    private const WEB_GROUP = 'flexpbxuser';

    // Directory and file patterns
    private const ASTERISK_CONFIG_DIR = '/etc/asterisk';
    private const ASTERISK_SOUNDS_DIR = '/var/lib/asterisk/sounds';
    private const ASTERISK_MOH_DIR = '/var/lib/asterisk/moh';
    private const ASTERISK_MONITOR_DIR = '/var/spool/asterisk/monitor';
    private const ASTERISK_VOICEMAIL_DIR = '/var/spool/asterisk/voicemail';
    private const WEB_UPLOAD_DIR = '/home/flexpbxuser/public_html/uploads';

    private $log = [];
    private $errors = [];

    /**
     * Fix all permissions - main entry point
     */
    public function fixAllPermissions($scope = 'all') {
        $this->log[] = "Starting permission fix - scope: $scope";

        switch ($scope) {
            case 'asterisk':
                $this->fixAsteriskPermissions();
                break;
            case 'audio':
                $this->fixAudioPermissions();
                break;
            case 'web':
                $this->fixWebPermissions();
                break;
            case 'all':
            default:
                $this->fixAsteriskPermissions();
                $this->fixAudioPermissions();
                $this->fixWebPermissions();
                break;
        }

        return [
            'success' => count($this->errors) === 0,
            'log' => $this->log,
            'errors' => $this->errors
        ];
    }

    /**
     * Fix Asterisk configuration file permissions
     */
    private function fixAsteriskPermissions() {
        $this->log[] = "Fixing Asterisk configuration permissions";

        // Core config files that must be readable by asterisk user
        $configFiles = [
            'extensions.conf',
            'extensions_custom.conf',
            'extensions_ivr.conf',
            'extensions_ring_groups.conf',
            'pjsip.conf',
            'voicemail.conf',
            'queues.conf',
            'musiconhold.conf',
            'confbridge.conf',
            'features.conf',
            'res_parking.conf',
            'rtp.conf'
        ];

        foreach ($configFiles as $file) {
            $path = self::ASTERISK_CONFIG_DIR . '/' . $file;
            if (file_exists($path)) {
                $this->setPermissions($path, self::ASTERISK_USER, self::ASTERISK_GROUP, 0640);
            }
        }

        // Fix directory permissions
        $this->setPermissions(self::ASTERISK_CONFIG_DIR, self::ASTERISK_USER, self::ASTERISK_GROUP, 0750);
    }

    /**
     * Fix audio file permissions (MOH, custom sounds, recordings)
     */
    private function fixAudioPermissions() {
        $this->log[] = "Fixing audio file permissions";

        // Music on Hold
        if (is_dir(self::ASTERISK_MOH_DIR)) {
            $this->recursiveChown(self::ASTERISK_MOH_DIR, self::ASTERISK_USER, self::ASTERISK_GROUP, 0644, 0755);
        }

        // Custom sounds
        if (is_dir(self::ASTERISK_SOUNDS_DIR)) {
            $this->recursiveChown(self::ASTERISK_SOUNDS_DIR, self::ASTERISK_USER, self::ASTERISK_GROUP, 0644, 0755);
        }

        // Call recordings
        if (is_dir(self::ASTERISK_MONITOR_DIR)) {
            $this->recursiveChown(self::ASTERISK_MONITOR_DIR, self::ASTERISK_USER, self::ASTERISK_GROUP, 0644, 0755);
        }

        // Voicemail recordings
        if (is_dir(self::ASTERISK_VOICEMAIL_DIR)) {
            $this->recursiveChown(self::ASTERISK_VOICEMAIL_DIR, self::ASTERISK_USER, self::ASTERISK_GROUP, 0640, 0750);
        }
    }

    /**
     * Fix web file permissions
     */
    private function fixWebPermissions() {
        $this->log[] = "Fixing web file permissions";

        // Upload directories
        if (is_dir(self::WEB_UPLOAD_DIR)) {
            $this->recursiveChown(self::WEB_UPLOAD_DIR, self::WEB_USER, self::WEB_GROUP, 0644, 0755);
        }
    }

    /**
     * Set permissions on a single file/directory
     */
    private function setPermissions($path, $user, $group, $mode) {
        try {
            // Use sudo for system files
            $needsSudo = strpos($path, '/etc/') === 0 ||
                        strpos($path, '/var/') === 0;

            if ($needsSudo) {
                exec("sudo chown $user:$group " . escapeshellarg($path) . " 2>&1", $output, $ret);
                if ($ret !== 0) {
                    $this->errors[] = "Failed to chown $path: " . implode(', ', $output);
                    return false;
                }

                exec("sudo chmod " . decoct($mode) . " " . escapeshellarg($path) . " 2>&1", $output, $ret);
                if ($ret !== 0) {
                    $this->errors[] = "Failed to chmod $path: " . implode(', ', $output);
                    return false;
                }
            } else {
                if (!chown($path, $user)) {
                    $this->errors[] = "Failed to chown $path";
                    return false;
                }
                if (!chgrp($path, $group)) {
                    $this->errors[] = "Failed to chgrp $path";
                    return false;
                }
                if (!chmod($path, $mode)) {
                    $this->errors[] = "Failed to chmod $path";
                    return false;
                }
            }

            $this->log[] = "Fixed: $path ($user:$group, " . decoct($mode) . ")";
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Exception fixing $path: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Recursively set permissions
     */
    private function recursiveChown($dir, $user, $group, $fileMode, $dirMode) {
        if (!is_dir($dir)) {
            return;
        }

        // Set directory itself
        $this->setPermissions($dir, $user, $group, $dirMode);

        // Use find command for efficiency
        $needsSudo = strpos($dir, '/etc/') === 0 ||
                    strpos($dir, '/var/') === 0;

        $sudo = $needsSudo ? 'sudo ' : '';

        // Fix all directories
        exec($sudo . "find " . escapeshellarg($dir) . " -type d -exec chown $user:$group {} \\; 2>&1");
        exec($sudo . "find " . escapeshellarg($dir) . " -type d -exec chmod " . decoct($dirMode) . " {} \\; 2>&1");

        // Fix all files
        exec($sudo . "find " . escapeshellarg($dir) . " -type f -exec chown $user:$group {} \\; 2>&1");
        exec($sudo . "find " . escapeshellarg($dir) . " -type f -exec chmod " . decoct($fileMode) . " {} \\; 2>&1");

        $this->log[] = "Recursively fixed: $dir";
    }

    /**
     * Fix permissions for a specific module's generated files
     */
    public function fixModulePermissions($moduleName) {
        $this->log[] = "Fixing permissions for module: $moduleName";

        switch ($moduleName) {
            case 'ivr':
                $this->setPermissions(self::ASTERISK_CONFIG_DIR . '/extensions_ivr.conf',
                    self::ASTERISK_USER, self::ASTERISK_GROUP, 0640);
                // Fix IVR audio uploads
                $ivrAudioDir = self::WEB_UPLOAD_DIR . '/ivr';
                if (is_dir($ivrAudioDir)) {
                    $this->recursiveChown($ivrAudioDir, self::WEB_USER, self::WEB_GROUP, 0644, 0755);
                }
                break;

            case 'ring-groups':
                $this->setPermissions(self::ASTERISK_CONFIG_DIR . '/extensions_ring_groups.conf',
                    self::ASTERISK_USER, self::ASTERISK_GROUP, 0640);
                break;

            case 'queues':
                $this->setPermissions(self::ASTERISK_CONFIG_DIR . '/queues.conf',
                    self::ASTERISK_USER, self::ASTERISK_GROUP, 0640);
                break;

            case 'moh':
                if (is_dir(self::ASTERISK_MOH_DIR)) {
                    $this->recursiveChown(self::ASTERISK_MOH_DIR,
                        self::ASTERISK_USER, self::ASTERISK_GROUP, 0644, 0755);
                }
                $this->setPermissions(self::ASTERISK_CONFIG_DIR . '/musiconhold.conf',
                    self::ASTERISK_USER, self::ASTERISK_GROUP, 0640);
                break;
        }

        return [
            'success' => count($this->errors) === 0,
            'log' => $this->log,
            'errors' => $this->errors
        ];
    }

    /**
     * Auto-fix permissions after file operations
     * Call this at the end of any API operation that creates/modifies files
     */
    public function autoFix($context = []) {
        if (!empty($context['config_file'])) {
            // Asterisk config file was modified
            $this->setPermissions($context['config_file'],
                self::ASTERISK_USER, self::ASTERISK_GROUP, 0640);
        }

        if (!empty($context['audio_file'])) {
            // Audio file was uploaded
            $this->setPermissions($context['audio_file'],
                self::ASTERISK_USER, self::ASTERISK_GROUP, 0644);
        }

        if (!empty($context['web_file'])) {
            // Web file was created
            $this->setPermissions($context['web_file'],
                self::WEB_USER, self::WEB_GROUP, 0644);
        }

        if (!empty($context['module'])) {
            // Module-specific fixes
            $this->fixModulePermissions($context['module']);
        }

        return [
            'success' => count($this->errors) === 0,
            'log' => $this->log,
            'errors' => $this->errors
        ];
    }
}

// API endpoint handler
header('Content-Type: application/json');

// Check if called via API or included
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    // Called directly as API endpoint
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_GET['path'] ?? '';

    $helper = new PermissionHelper();

    switch ($path) {
        case 'fix-all':
            $scope = $_GET['scope'] ?? 'all';
            $result = $helper->fixAllPermissions($scope);
            echo json_encode($result);
            break;

        case 'fix-module':
            $module = $_GET['module'] ?? '';
            if (empty($module)) {
                echo json_encode(['success' => false, 'error' => 'Module name required']);
                exit;
            }
            $result = $helper->fixModulePermissions($module);
            echo json_encode($result);
            break;

        case 'auto-fix':
            $context = json_decode(file_get_contents('php://input'), true) ?? [];
            $result = $helper->autoFix($context);
            echo json_encode($result);
            break;

        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid path',
                'available_paths' => ['fix-all', 'fix-module', 'auto-fix']
            ]);
            break;
    }
} else {
    // Included by another script - return the class for use
    return PermissionHelper::class;
}
