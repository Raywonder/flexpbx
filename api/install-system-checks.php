<?php
/**
 * FlexPBX System Integration Enhancements
 * Add these methods to the FlexPBXInstaller class in install.php
 *
 * Purpose: Comprehensive system service checks, directory setup, and post-install validation
 * Based on GPT notes and user requirements for stable system after reboots
 */

/**
 * Enhanced directory structure creation with proper permissions
 * Creates all necessary directories for FlexPBX operation
 */
private function createComprehensiveDirectoryStructure() {
    $baseDir = '/home/flexpbxuser';

    $directories = [
        // Application directories (relative to public_html)
        '../config' => ['perms' => 0750, 'owner' => 'flexpbxuser', 'group' => 'nobody'],
        '../cron' => ['perms' => 0750, 'owner' => 'flexpbxuser', 'group' => 'nobody'],
        '../logs' => ['perms' => 0750, 'owner' => 'flexpbxuser', 'group' => 'nobody'],
        '../uploads' => ['perms' => 0750, 'owner' => 'flexpbxuser', 'group' => 'nobody'],
        '../backups' => ['perms' => 0750, 'owner' => 'flexpbxuser', 'group' => 'nobody'],
        '../dashboard' => ['perms' => 0755, 'owner' => 'flexpbxuser', 'group' => 'nobody'],
        '../modules' => ['perms' => 0755, 'owner' => 'flexpbxuser', 'group' => 'nobody'],
        '../temp' => ['perms' => 0750, 'owner' => 'flexpbxuser', 'group' => 'nobody'],

        // User data directories (absolute paths)
        $baseDir . '/sms_messages' => ['perms' => 0750, 'owner' => 'flexpbxuser', 'group' => 'nobody'],
        $baseDir . '/voicemails' => ['perms' => 0750, 'owner' => 'flexpbxuser', 'group' => 'nobody'],
        $baseDir . '/recordings' => ['perms' => 0750, 'owner' => 'flexpbxuser', 'group' => 'nobody'],
        $baseDir . '/users' => ['perms' => 0750, 'owner' => 'flexpbxuser', 'group' => 'nobody'],
        $baseDir . '/bugs' => ['perms' => 0750, 'owner' => 'flexpbxuser', 'group' => 'nobody'],
        $baseDir . '/scripts' => ['perms' => 0750, 'owner' => 'flexpbxuser', 'group' => 'nobody'],

        // System log directory
        '/var/log/flexpbx' => ['perms' => 0750, 'owner' => 'flexpbxuser', 'group' => 'nobody'],
    ];

    foreach ($directories as $dir => $config) {
        if (!is_dir($dir)) {
            if (@mkdir($dir, $config['perms'], true)) {
                $this->logProgress("📁 Created directory: " . basename($dir));

                // Set proper ownership if running as root
                if (function_exists('posix_getuid') && posix_getuid() === 0) {
                    chown($dir, $config['owner']);
                    chgrp($dir, $config['group']);
                }
            } else {
                $this->warnings[] = "Could not create directory: $dir";
            }
        } else {
            // Directory exists, fix permissions
            chmod($dir, $config['perms']);
            $this->logProgress("✓ Verified directory: " . basename($dir));
        }
    }

    // Create security index.php files
    $indexContent = '<?php header("HTTP/1.1 403 Forbidden"); exit("Directory access forbidden"); ?>';
    $protectedDirs = ['../config', '../cron', '../logs', '../uploads', '../backups', '../temp'];

    foreach ($protectedDirs as $dir) {
        $indexFile = $dir . '/index.php';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, $indexContent);
        }
    }
}

/**
 * Check system services status (Asterisk, Apache, MariaDB, Exim)
 * Returns array of service statuses
 */
private function checkSystemServices() {
    $services = [
        'mariadb' => ['name' => 'Database (MariaDB)', 'critical' => true],
        'httpd' => ['name' => 'Web Server (Apache)', 'critical' => true],
        'asterisk' => ['name' => 'Asterisk PBX', 'critical' => true],
        'exim' => ['name' => 'Mail Server (Exim)', 'critical' => false],
    ];

    $results = [];

    foreach ($services as $service => $config) {
        $status = shell_exec("systemctl is-active $service 2>/dev/null");
        $status = trim($status);
        $isRunning = ($status === 'active');

        $results[$service] = [
            'name' => $config['name'],
            'running' => $isRunning,
            'critical' => $config['critical'],
            'status' => $status
        ];

        if ($isRunning) {
            $this->logProgress("✅ {$config['name']}: Running");
        } else if ($config['critical']) {
            $this->warnings[] = "{$config['name']} is not running. Status: $status";
            $this->logProgress("⚠️ {$config['name']}: Not running");
        }
    }

    return $results;
}

/**
 * Check Asterisk MOH (Music on Hold) server status
 * Verifies hold music files and configuration
 */
private function checkMusicOnHoldServer() {
    $mohPath = '/var/lib/asterisk/sounds/moh';
    $mohConfigPath = '/etc/asterisk/musiconhold.conf';

    $results = [
        'path_exists' => is_dir($mohPath),
        'config_exists' => file_exists($mohConfigPath),
        'file_count' => 0,
        'writable' => is_writable($mohPath)
    ];

    if ($results['path_exists']) {
        $files = glob($mohPath . '/*.{wav,ulaw,alaw,gsm,mp3}', GLOB_BRACE);
        $results['file_count'] = count($files);

        if ($results['file_count'] > 0) {
            $this->logProgress("♪ Music on Hold: {$results['file_count']} files found");
        } else {
            $this->warnings[] = "No music on hold files found in $mohPath";
        }
    } else {
        $this->warnings[] = "Music on Hold directory not found: $mohPath";
    }

    return $results;
}

/**
 * Verify system service autostart configuration
 * Ensures services will start on boot
 */
private function verifyServiceAutostart() {
    $services = ['mariadb', 'httpd', 'asterisk', 'exim'];
    $results = [];

    foreach ($services as $service) {
        $enabled = shell_exec("systemctl is-enabled $service 2>/dev/null");
        $enabled = trim($enabled);
        $isEnabled = ($enabled === 'enabled');

        $results[$service] = $isEnabled;

        if ($isEnabled) {
            $this->logProgress("🚀 $service: Enabled for autostart");
        } else {
            $this->warnings[] = "$service is not enabled for autostart. Run: systemctl enable $service";
            $this->logProgress("⚠️ $service: Not enabled for autostart");
        }
    }

    return $results;
}

/**
 * Check Asterisk graceful shutdown configuration
 * Verifies systemd override for safe shutdown
 */
private function checkAsteriskShutdownConfig() {
    $overridePath = '/etc/systemd/system/asterisk.service.d/override.conf';

    if (file_exists($overridePath)) {
        $content = file_get_contents($overridePath);

        $hasTimeout = (strpos($content, 'TimeoutStopSec') !== false);
        $hasGracefulStop = (strpos($content, 'core stop gracefully') !== false);

        if ($hasTimeout && $hasGracefulStop) {
            $this->logProgress("✅ Asterisk graceful shutdown: Configured");
            return true;
        } else {
            $this->warnings[] = "Asterisk shutdown configuration incomplete. See GPT notes for fix.";
            return false;
        }
    } else {
        $this->warnings[] = "Asterisk systemd override not found. System may hang during reboots.";
        $this->logProgress("⚠️ Asterisk graceful shutdown: Not configured");
        return false;
    }
}

/**
 * Post-installation validation
 * Comprehensive checks after installation completes
 */
private function performPostInstallValidation() {
    $this->logProgress("🔍 Performing post-installation validation...");

    // 1. Check config file was created
    if (file_exists('../config/config.php')) {
        $this->logProgress("✅ Configuration file: Created");
    } else {
        $this->errors[] = "Configuration file not created!";
        return false;
    }

    // 2. Check all directories exist
    $criticalDirs = ['../config', '../logs', '../temp', '../modules'];
    foreach ($criticalDirs as $dir) {
        if (!is_dir($dir)) {
            $this->errors[] = "Critical directory missing: $dir";
            return false;
        }
    }
    $this->logProgress("✅ Directory structure: Complete");

    // 3. Check system services
    $services = $this->checkSystemServices();
    $criticalServicesRunning = true;
    foreach ($services as $service => $status) {
        if ($status['critical'] && !$status['running']) {
            $criticalServicesRunning = false;
            $this->errors[] = "Critical service not running: {$status['name']}";
        }
    }

    if ($criticalServicesRunning) {
        $this->logProgress("✅ Critical services: Running");
    }

    // 4. Check FlexPBX management scripts exist
    $scripts = [
        '/usr/local/bin/flexpbx-status',
        '/usr/local/bin/flexpbx-start-all',
        '/usr/local/bin/flexpbx-safe-restart'
    ];

    $scriptsExist = true;
    foreach ($scripts as $script) {
        if (!file_exists($script)) {
            $this->warnings[] = "Management script missing: $script";
            $scriptsExist = false;
        }
    }

    if ($scriptsExist) {
        $this->logProgress("✅ Management scripts: Installed");
    }

    // 5. Check Music on Hold
    $mohStatus = $this->checkMusicOnHoldServer();

    // 6. Check autostart configuration
    $this->verifyServiceAutostart();

    // 7. Check Asterisk shutdown configuration
    $this->checkAsteriskShutdownConfig();

    $this->logProgress("✅ Post-installation validation complete");

    return count($this->errors) === 0;
}

/**
 * Generate post-installation report
 * Creates a comprehensive report of system status
 */
private function generatePostInstallReport() {
    $report = "# FlexPBX Installation Report\n";
    $report .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";

    $report .= "## Installation Details\n";
    $report .= "- Installation completed successfully\n";
    $report .= "- Configuration file created\n";
    $report .= "- Directory structure initialized\n\n";

    $report .= "## System Services Status\n";
    $services = $this->checkSystemServices();
    foreach ($services as $service => $status) {
        $icon = $status['running'] ? '✅' : '❌';
        $report .= "- $icon {$status['name']}: " . ($status['running'] ? 'Running' : 'Not Running') . "\n";
    }
    $report .= "\n";

    $report .= "## Autostart Configuration\n";
    $autostart = $this->verifyServiceAutostart();
    foreach ($autostart as $service => $enabled) {
        $icon = $enabled ? '✅' : '⚠️';
        $report .= "- $icon $service: " . ($enabled ? 'Enabled' : 'Not Enabled') . "\n";
    }
    $report .= "\n";

    if (!empty($this->warnings)) {
        $report .= "## Warnings\n";
        foreach ($this->warnings as $warning) {
            $report .= "- ⚠️ $warning\n";
        }
        $report .= "\n";
    }

    $report .= "## Next Steps\n";
    $report .= "1. Access FlexPBX at: https://flexpbx.devinecreations.net/\n";
    $report .= "2. Check system status: sudo /usr/local/bin/flexpbx-status\n";
    $report .= "3. Configure your first extension in the web UI\n";
    $report .= "4. Test phone calls and SMS functionality\n";
    $report .= "5. Set up automatic backups\n\n";

    $report .= "## Important Commands\n";
    $report .= "- Check status: `sudo /usr/local/bin/flexpbx-status`\n";
    $report .= "- Start services: `sudo /usr/local/bin/flexpbx-start-all`\n";
    $report .= "- Safe restart: `sudo /usr/local/bin/flexpbx-safe-restart`\n";
    $report .= "- View logs: `tail -f /var/log/flexpbx/*.log`\n\n";

    // Save report
    $reportPath = '/home/flexpbxuser/logs/installation_report_' . date('Y-m-d_His') . '.txt';
    file_put_contents($reportPath, $report);

    $this->logProgress("📄 Installation report saved: $reportPath");

    return $report;
}

?>
