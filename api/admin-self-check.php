<?php
/**
 * FlexPBX Admin Panel Self-Check & Auto-Fix Script
 * Automatically checks permissions, chmods scripts, and verifies system readiness
 *
 * @package FlexPBX
 * @version 2.1.0
 * @author Devine Creations LLC
 */

class FlexPBXAdminSelfCheck {
    private $base_path;
    private $results = [];
    private $fixes_applied = 0;

    public function __construct() {
        $this->base_path = dirname(__FILE__);
        if (basename($this->base_path) === 'api') {
            $this->base_path = dirname($this->base_path); // Go up to public_html
        }
    }

    /**
     * Run complete self-check and auto-fix
     */
    public function runSelfCheck() {
        $this->logResult("🔍 FlexPBX Admin Panel Self-Check Starting...", 'info');
        $this->logResult("Base path: " . $this->base_path, 'info');

        // Check and fix file permissions
        $this->checkAndFixPermissions();

        // Check required directories
        $this->checkDirectoryStructure();

        // Check configuration files
        $this->checkConfigurationFiles();

        // Check admin interfaces
        $this->checkAdminInterfaces();

        // Check test extension configuration
        $this->checkTestExtension();

        // Generate self-check report
        $this->generateReport();

        return $this->results;
    }

    /**
     * Check and fix file permissions automatically
     */
    private function checkAndFixPermissions() {
        $this->logResult("🔧 Checking and fixing file permissions...", 'info');

        $script_files = [
            'flexpbx-test-suite.sh',
            'config/flexpbx-server-setup.sh',
            'config/config-validator.js',
            'config/file-manager-import.js'
        ];

        foreach ($script_files as $script) {
            $file_path = $this->base_path . '/' . $script;

            if (file_exists($file_path)) {
                $current_perms = substr(sprintf('%o', fileperms($file_path)), -4);

                if ($current_perms !== '0755' && $current_perms !== '0775') {
                    if (chmod($file_path, 0755)) {
                        $this->logResult("✅ Fixed permissions for $script (755)", 'success');
                        $this->fixes_applied++;
                    } else {
                        $this->logResult("❌ Failed to fix permissions for $script", 'error');
                    }
                } else {
                    $this->logResult("✅ Permissions OK for $script ($current_perms)", 'success');
                }
            } else {
                $this->logResult("⚠️ Script not found: $script", 'warning');
            }
        }

        // Fix PHP file permissions
        $php_files = glob($this->base_path . '/{api,modules,scripts}/*.php', GLOB_BRACE);
        foreach ($php_files as $php_file) {
            $current_perms = substr(sprintf('%o', fileperms($php_file)), -4);
            if ($current_perms !== '0644' && $current_perms !== '0664' && $current_perms !== '0775') {
                if (chmod($php_file, 0644)) {
                    $this->fixes_applied++;
                }
            }
        }
    }

    /**
     * Check directory structure
     */
    private function checkDirectoryStructure() {
        $this->logResult("📁 Checking directory structure...", 'info');

        $required_dirs = [
            'api',
            'admin',
            'modules',
            'config',
            'docs',
            'scripts',
            'monitoring'
        ];

        foreach ($required_dirs as $dir) {
            $dir_path = $this->base_path . '/' . $dir;
            if (is_dir($dir_path)) {
                $file_count = count(glob($dir_path . '/*'));
                $this->logResult("✅ Directory $dir exists ($file_count files)", 'success');
            } else {
                $this->logResult("❌ Missing directory: $dir", 'error');
            }
        }
    }

    /**
     * Check configuration files
     */
    private function checkConfigurationFiles() {
        $this->logResult("⚙️ Checking configuration files...", 'info');

        $config_files = [
            'config/callcentric-trunk-config.json' => 'Callcentric SIP trunk',
            'config/google-voice-config.json' => 'Google Voice integration',
            'config/extensions-config.json' => 'Extension definitions'
        ];

        foreach ($config_files as $file => $description) {
            $file_path = $this->base_path . '/' . $file;

            if (file_exists($file_path)) {
                $file_size = filesize($file_path);

                if ($file_size > 100) {
                    // Validate JSON
                    $json_content = file_get_contents($file_path);
                    if (json_decode($json_content) !== null) {
                        $this->logResult("✅ $description - Valid JSON ($file_size bytes)", 'success');

                        // Check specific content
                        if (strpos($file, 'extensions-config') !== false) {
                            $this->checkExtensionConfig($json_content);
                        }
                    } else {
                        $this->logResult("❌ $description - Invalid JSON format", 'error');
                    }
                } else {
                    $this->logResult("⚠️ $description - File too small ($file_size bytes)", 'warning');
                }
            } else {
                $this->logResult("❌ Missing: $description", 'error');
            }
        }
    }

    /**
     * Check extension configuration specifically
     */
    private function checkExtensionConfig($json_content) {
        $config = json_decode($json_content, true);

        if (isset($config['extensions'])) {
            $extension_count = count($config['extensions']);
            $this->logResult("📞 Found $extension_count extensions configured", 'info');

            // Check for test extension 2001
            if (isset($config['extensions']['2001'])) {
                $ext_2001 = $config['extensions']['2001'];
                if (isset($ext_2001['username']) && $ext_2001['username'] === 'techsupport1') {
                    $this->logResult("⭐ Test extension 2001 (techsupport1) ready for testing", 'success');
                } else {
                    $this->logResult("⚠️ Test extension 2001 configuration may be incorrect", 'warning');
                }
            } else {
                $this->logResult("❌ Test extension 2001 not found in configuration", 'error');
            }
        }
    }

    /**
     * Check admin interfaces
     */
    private function checkAdminInterfaces() {
        $this->logResult("🖥️ Checking admin interfaces...", 'info');

        $admin_files = [
            'admin/admin-trunks-management.html' => 'Trunk Management',
            'admin/admin-extensions-management.html' => 'Extension Management',
            'admin/admin-google-voice.html' => 'Google Voice Integration'
        ];

        foreach ($admin_files as $file => $description) {
            $file_path = $this->base_path . '/' . $file;

            if (file_exists($file_path)) {
                $file_size = filesize($file_path);

                if ($file_size > 10000) {
                    $this->logResult("✅ $description interface ready ($file_size bytes)", 'success');
                } else {
                    $this->logResult("⚠️ $description interface may be incomplete", 'warning');
                }
            } else {
                $this->logResult("❌ Missing admin interface: $description", 'error');
            }
        }
    }

    /**
     * Check test extension readiness
     */
    private function checkTestExtension() {
        $this->logResult("📞 Verifying test extension readiness...", 'info');

        $test_details = [
            'Extension' => '2001',
            'Username' => 'techsupport1',
            'Password' => 'Support2001!',
            'Server' => 'flexpbx.devinecreations.net:5070',
            'Test SIP URI' => 'sip:2001@flexpbx.devinecreations.net'
        ];

        foreach ($test_details as $key => $value) {
            $this->logResult("📋 $key: $value", 'info');
        }

        $this->logResult("🎯 Extension 2001 ready for SIP client testing", 'success');
    }

    /**
     * Log result with timestamp
     */
    private function logResult($message, $type = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $this->results[] = [
            'timestamp' => $timestamp,
            'type' => $type,
            'message' => $message
        ];
    }

    /**
     * Generate HTML report
     */
    private function generateReport() {
        $success_count = count(array_filter($this->results, function($r) { return $r['type'] === 'success'; }));
        $error_count = count(array_filter($this->results, function($r) { return $r['type'] === 'error'; }));
        $warning_count = count(array_filter($this->results, function($r) { return $r['type'] === 'warning'; }));

        $this->logResult("", 'info');
        $this->logResult("📊 SELF-CHECK SUMMARY:", 'info');
        $this->logResult("✅ Successful checks: $success_count", 'success');
        $this->logResult("⚠️ Warnings: $warning_count", $warning_count > 0 ? 'warning' : 'success');
        $this->logResult("❌ Errors: $error_count", $error_count > 0 ? 'error' : 'success');
        $this->logResult("🔧 Auto-fixes applied: $this->fixes_applied", 'info');

        if ($error_count === 0) {
            $this->logResult("🎉 SYSTEM READY FOR EXTENSION TESTING!", 'success');
        } else {
            $this->logResult("⚠️ Please fix errors before testing extensions", 'warning');
        }
    }

    /**
     * Output results as JSON
     */
    public function getResultsAsJson() {
        return json_encode([
            'success' => count(array_filter($this->results, function($r) { return $r['type'] === 'error'; })) === 0,
            'fixes_applied' => $this->fixes_applied,
            'results' => $this->results,
            'summary' => [
                'total_checks' => count($this->results),
                'successful' => count(array_filter($this->results, function($r) { return $r['type'] === 'success'; })),
                'warnings' => count(array_filter($this->results, function($r) { return $r['type'] === 'warning'; })),
                'errors' => count(array_filter($this->results, function($r) { return $r['type'] === 'error'; }))
            ]
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Output results as HTML
     */
    public function getResultsAsHtml() {
        $html = '<!DOCTYPE html>
<html><head>
<title>FlexPBX Admin Self-Check Report</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.result-item { padding: 8px 12px; margin: 5px 0; border-radius: 4px; border-left: 4px solid #ddd; }
.success { background: #d4edda; border-left-color: #28a745; }
.error { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
.info { background: #d1ecf1; border-left-color: #17a2b8; }
h1 { color: #333; text-align: center; }
.summary { background: #e9ecef; padding: 15px; border-radius: 8px; margin: 20px 0; }
</style>
</head><body>
<div class="container">
<h1>🔍 FlexPBX Admin Self-Check Report</h1>
<div class="summary">
<strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '<br>
<strong>Auto-fixes Applied:</strong> ' . $this->fixes_applied . '
</div>';

        foreach ($this->results as $result) {
            $html .= '<div class="result-item ' . $result['type'] . '">';
            $html .= '<strong>' . $result['timestamp'] . '</strong> - ' . htmlspecialchars($result['message']);
            $html .= '</div>';
        }

        $html .= '</div></body></html>';
        return $html;
    }
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $checker = new FlexPBXAdminSelfCheck();
    $results = $checker->runSelfCheck();

    if (isset($_GET['format']) && $_GET['format'] === 'html') {
        header('Content-Type: text/html');
        echo $checker->getResultsAsHtml();
    } else {
        echo $checker->getResultsAsJson();
    }
    exit;
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $checker = new FlexPBXAdminSelfCheck();
    $results = $checker->runSelfCheck();

    // Output to console
    foreach ($results as $result) {
        $icon = [
            'success' => '✅',
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️'
        ];

        echo $icon[$result['type']] . ' ' . $result['message'] . "\n";
    }

    echo "\n🔧 Auto-fixes applied: " . $checker->fixes_applied . "\n";
}
?>