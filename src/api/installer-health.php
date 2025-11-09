<?php
/**
 * FlexPBX Installer Health Check & Auto-Recovery
 * Detects broken/corrupted installers and provides automated fixes
 *
 * Usage:
 *   - GET /api/installer-health.php?check          - Check installer status
 *   - GET /api/installer-health.php?fix            - Auto-fix broken installer
 *   - GET /api/installer-health.php?reinstall      - Force reinstall latest version
 */

header('Content-Type: application/json');

// Configuration
define('DOWNLOADS_DIR', '/home/flexpbxuser/public_html/downloads');
define('INSTALLER_URL', 'https://flexpbx.devinecreations.net/downloads');
define('BUG_TRACKER_DIR', '/home/flexpbxuser/public_html/bug-tracker');
define('CURRENT_VERSION', '1.2');

class InstallerHealth {
    private $results = [];
    private $issues = [];
    private $fixes = [];

    /**
     * Check installer integrity
     */
    public function check() {
        $this->results['timestamp'] = date('Y-m-d H:i:s');
        $this->results['version'] = CURRENT_VERSION;

        // Check installer files exist
        $this->checkInstallerFiles();

        // Check checksums
        $this->checkChecksums();

        // Check for known bugs
        $this->checkKnownBugs();

        // Check file permissions
        $this->checkPermissions();

        // Check web accessibility
        $this->checkWebAccess();

        $this->results['issues'] = $this->issues;
        $this->results['status'] = empty($this->issues) ? 'healthy' : 'issues_found';
        $this->results['issue_count'] = count($this->issues);

        return $this->results;
    }

    /**
     * Check if installer files exist
     */
    private function checkInstallerFiles() {
        $required_files = [
            'FlexPBX-Master-Server-v' . CURRENT_VERSION . '.tar.gz',
            'checksums-v' . CURRENT_VERSION . '.md5',
            'install-flexpbx.sh'
        ];

        foreach ($required_files as $file) {
            $path = DOWNLOADS_DIR . '/' . $file;
            if (!file_exists($path)) {
                $this->issues[] = [
                    'type' => 'missing_file',
                    'severity' => 'critical',
                    'file' => $file,
                    'message' => "Required installer file missing: $file",
                    'fixable' => true
                ];
            } else {
                // Check file size
                $size = filesize($path);
                if ($size == 0) {
                    $this->issues[] = [
                        'type' => 'empty_file',
                        'severity' => 'critical',
                        'file' => $file,
                        'size' => $size,
                        'message' => "Installer file is empty: $file",
                        'fixable' => true
                    ];
                } elseif ($size < 1024) {
                    $this->issues[] = [
                        'type' => 'suspicious_size',
                        'severity' => 'warning',
                        'file' => $file,
                        'size' => $size,
                        'message' => "Installer file suspiciously small: $file ($size bytes)",
                        'fixable' => true
                    ];
                }
            }
        }
    }

    /**
     * Check MD5 checksums
     */
    private function checkChecksums() {
        $checksum_file = DOWNLOADS_DIR . '/checksums-v' . CURRENT_VERSION . '.md5';

        if (!file_exists($checksum_file)) {
            $this->issues[] = [
                'type' => 'missing_checksum',
                'severity' => 'high',
                'message' => 'Checksum file missing',
                'fixable' => true
            ];
            return;
        }

        $checksums = file_get_contents($checksum_file);
        $lines = explode("\n", trim($checksums));

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 2) continue;

            $expected_md5 = $parts[0];
            $filename = basename($parts[1]);
            $filepath = DOWNLOADS_DIR . '/' . $filename;

            if (file_exists($filepath)) {
                $actual_md5 = md5_file($filepath);

                if ($actual_md5 !== $expected_md5) {
                    $this->issues[] = [
                        'type' => 'checksum_mismatch',
                        'severity' => 'critical',
                        'file' => $filename,
                        'expected_md5' => $expected_md5,
                        'actual_md5' => $actual_md5,
                        'message' => "File corrupted or modified: $filename",
                        'fixable' => true
                    ];
                }
            }
        }
    }

    /**
     * Check for known bugs from bug tracker
     */
    private function checkKnownBugs() {
        if (!is_dir(BUG_TRACKER_DIR)) {
            return;
        }

        $bug_files = glob(BUG_TRACKER_DIR . '/bug_vps_*.json');
        $installer_bugs = [];

        foreach ($bug_files as $bug_file) {
            $bug = json_decode(file_get_contents($bug_file), true);

            if (isset($bug['category']) &&
                (strpos($bug['category'], 'installer') !== false ||
                 strpos($bug['category'], 'vps') !== false)) {

                $installer_bugs[] = [
                    'bug_id' => basename($bug_file, '.json'),
                    'title' => $bug['title'] ?? 'Unknown',
                    'status' => $bug['status'] ?? 'unknown',
                    'severity' => $bug['severity'] ?? 'medium'
                ];
            }
        }

        if (!empty($installer_bugs)) {
            $open_bugs = array_filter($installer_bugs, function($bug) {
                return $bug['status'] !== 'resolved';
            });

            if (!empty($open_bugs)) {
                $this->issues[] = [
                    'type' => 'known_bugs',
                    'severity' => 'warning',
                    'count' => count($open_bugs),
                    'bugs' => $open_bugs,
                    'message' => count($open_bugs) . ' known installer bugs detected',
                    'fixable' => true
                ];
            }
        }
    }

    /**
     * Check file permissions
     */
    private function checkPermissions() {
        $files = [
            'FlexPBX-Master-Server-v' . CURRENT_VERSION . '.tar.gz' => 0644,
            'install-flexpbx.sh' => 0755
        ];

        foreach ($files as $file => $expected_perms) {
            $path = DOWNLOADS_DIR . '/' . $file;
            if (file_exists($path)) {
                $actual_perms = fileperms($path) & 0777;

                if ($actual_perms !== $expected_perms) {
                    $this->issues[] = [
                        'type' => 'wrong_permissions',
                        'severity' => 'medium',
                        'file' => $file,
                        'expected' => decoct($expected_perms),
                        'actual' => decoct($actual_perms),
                        'message' => "Wrong permissions on $file",
                        'fixable' => true
                    ];
                }
            }
        }
    }

    /**
     * Check web accessibility
     */
    private function checkWebAccess() {
        $test_url = INSTALLER_URL . '/checksums-v' . CURRENT_VERSION . '.md5';

        $ch = curl_init($test_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_NOBODY, true);

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            $this->issues[] = [
                'type' => 'web_access',
                'severity' => 'high',
                'url' => $test_url,
                'http_code' => $http_code,
                'message' => "Installer not accessible via web (HTTP $http_code)",
                'fixable' => false
            ];
        }
    }

    /**
     * Auto-fix detected issues
     */
    public function fix() {
        $this->check();

        if (empty($this->issues)) {
            return [
                'status' => 'no_issues',
                'message' => 'No issues to fix'
            ];
        }

        foreach ($this->issues as $issue) {
            if (!$issue['fixable']) {
                $this->fixes[] = [
                    'issue' => $issue['type'],
                    'status' => 'skipped',
                    'reason' => 'Not automatically fixable'
                ];
                continue;
            }

            switch ($issue['type']) {
                case 'missing_file':
                case 'empty_file':
                case 'suspicious_size':
                case 'checksum_mismatch':
                    $this->fixCorruptedFile($issue);
                    break;

                case 'wrong_permissions':
                    $this->fixPermissions($issue);
                    break;

                case 'known_bugs':
                    $this->fixKnownBugs($issue);
                    break;
            }
        }

        return [
            'status' => 'fixed',
            'fixes_applied' => count($this->fixes),
            'fixes' => $this->fixes
        ];
    }

    /**
     * Fix corrupted or missing file
     */
    private function fixCorruptedFile($issue) {
        $file = $issue['file'];

        // Rebuild installer if it's the main package
        if (strpos($file, 'FlexPBX-Master-Server') !== false) {
            $build_script = '/home/flexpbxuser/installers/build-v' . CURRENT_VERSION . '.sh';

            if (file_exists($build_script)) {
                exec("sudo -u flexpbxuser bash $build_script 2>&1", $output, $return_var);

                $this->fixes[] = [
                    'issue' => $issue['type'],
                    'file' => $file,
                    'action' => 'rebuilt',
                    'status' => $return_var === 0 ? 'success' : 'failed',
                    'output' => implode("\n", $output)
                ];
            } else {
                $this->fixes[] = [
                    'issue' => $issue['type'],
                    'file' => $file,
                    'action' => 'rebuild',
                    'status' => 'failed',
                    'reason' => 'Build script not found'
                ];
            }
        } elseif ($file === 'install-flexpbx.sh') {
            // Regenerate installer script
            $this->regenerateInstallerScript();
        }
    }

    /**
     * Fix file permissions
     */
    private function fixPermissions($issue) {
        $file = $issue['file'];
        $path = DOWNLOADS_DIR . '/' . $file;
        $expected_perms = octdec($issue['expected']);

        if (chmod($path, $expected_perms)) {
            $this->fixes[] = [
                'issue' => 'wrong_permissions',
                'file' => $file,
                'action' => 'chmod',
                'status' => 'success',
                'permissions' => $issue['expected']
            ];
        } else {
            $this->fixes[] = [
                'issue' => 'wrong_permissions',
                'file' => $file,
                'action' => 'chmod',
                'status' => 'failed',
                'reason' => 'Permission denied'
            ];
        }
    }

    /**
     * Fix known bugs
     */
    private function fixKnownBugs($issue) {
        // Rebuild installer to include bug fixes
        $this->fixes[] = [
            'issue' => 'known_bugs',
            'action' => 'rebuild_recommended',
            'status' => 'manual_action_required',
            'message' => 'Run build script to include bug fixes',
            'command' => 'bash /home/flexpbxuser/installers/build-v' . CURRENT_VERSION . '.sh'
        ];
    }

    /**
     * Regenerate installer script
     */
    private function regenerateInstallerScript() {
        // This would copy the latest install-flexpbx.sh from installers/
        $source = '/home/flexpbxuser/installers/install-flexpbx.sh';
        $dest = DOWNLOADS_DIR . '/install-flexpbx.sh';

        if (file_exists($source)) {
            if (copy($source, $dest)) {
                chmod($dest, 0755);

                $this->fixes[] = [
                    'issue' => 'missing_file',
                    'file' => 'install-flexpbx.sh',
                    'action' => 'regenerated',
                    'status' => 'success'
                ];
            }
        }
    }

    /**
     * Force reinstall latest version
     */
    public function reinstall() {
        $build_script = '/home/flexpbxuser/installers/build-v' . CURRENT_VERSION . '.sh';

        if (!file_exists($build_script)) {
            return [
                'status' => 'error',
                'message' => 'Build script not found',
                'script' => $build_script
            ];
        }

        exec("sudo -u flexpbxuser bash $build_script 2>&1", $output, $return_var);

        return [
            'status' => $return_var === 0 ? 'success' : 'failed',
            'message' => $return_var === 0 ? 'Installer rebuilt successfully' : 'Build failed',
            'output' => implode("\n", $output),
            'version' => CURRENT_VERSION
        ];
    }
}

// Handle requests
$health = new InstallerHealth();

if (isset($_GET['check'])) {
    echo json_encode($health->check(), JSON_PRETTY_PRINT);
} elseif (isset($_GET['fix'])) {
    echo json_encode($health->fix(), JSON_PRETTY_PRINT);
} elseif (isset($_GET['reinstall'])) {
    echo json_encode($health->reinstall(), JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'error' => 'Invalid action',
        'usage' => [
            'check' => '/api/installer-health.php?check',
            'fix' => '/api/installer-health.php?fix',
            'reinstall' => '/api/installer-health.php?reinstall'
        ]
    ], JSON_PRETTY_PRINT);
}
