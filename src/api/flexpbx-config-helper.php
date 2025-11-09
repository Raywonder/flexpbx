<?php
/**
 * FlexPBX Configuration Helper
 * Provides mode detection and configuration access for all FlexPBX components
 */

class FlexPBXConfig {
    private static $instance = null;
    private $config = [];
    private $config_file = '/home/flexpbxuser/flexpbx-config.json';

    private function __construct() {
        $this->loadConfig();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadConfig() {
        $defaults = [
            'asterisk_mode' => 'secure',
            'allow_config_writes' => false,
            'api_mode' => 'enabled',
            'debug_mode' => false
        ];

        if (file_exists($this->config_file)) {
            $loaded = json_decode(file_get_contents($this->config_file), true);
            $this->config = array_merge($defaults, $loaded ?: []);
        } else {
            $this->config = $defaults;
        }
    }

    /**
     * Get current Asterisk integration mode
     * @return string 'secure' or 'power_user'
     */
    public function getAsteriskMode() {
        return $this->config['asterisk_mode'] ?? 'secure';
    }

    /**
     * Check if direct config file writes are allowed
     * @return bool
     */
    public function canWriteConfigs() {
        return ($this->config['allow_config_writes'] ?? false) === true;
    }

    /**
     * Check if in power user mode
     * @return bool
     */
    public function isPowerUserMode() {
        return $this->getAsteriskMode() === 'power_user';
    }

    /**
     * Check if in secure mode
     * @return bool
     */
    public function isSecureMode() {
        return $this->getAsteriskMode() === 'secure';
    }

    /**
     * Get configuration value
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get all configuration
     * @return array
     */
    public function getAll() {
        return $this->config;
    }

    /**
     * Check if a config file is writable
     * @param string $file_path
     * @return bool
     */
    public function isConfigWritable($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }

        $perms = fileperms($file_path);
        // Check owner write (0x0080) or group write (0x0010)
        return (($perms & 0x0080) || ($perms & 0x0010));
    }

    /**
     * Get recommended method for making config changes
     * @return string 'api', 'cli', or 'file'
     */
    public function getConfigMethod() {
        if ($this->isPowerUserMode() && $this->canWriteConfigs()) {
            return 'file'; // Can write directly
        } elseif ($this->get('api_mode') === 'enabled') {
            return 'api'; // Use ARI/AMI
        } else {
            return 'cli'; // Use CLI commands
        }
    }

    /**
     * Execute Asterisk CLI command (works in both modes)
     * @param string $command
     * @return array ['success' => bool, 'output' => string]
     */
    public function execAsteriskCommand($command) {
        $output = [];
        $return_var = 0;

        // Always use sudo to run as asterisk user
        $safe_command = escapeshellarg($command);
        exec("sudo -u asterisk /usr/sbin/asterisk -rx $safe_command 2>&1", $output, $return_var);

        return [
            'success' => ($return_var === 0),
            'output' => implode("\n", $output),
            'return_code' => $return_var
        ];
    }

    /**
     * Read Asterisk config file (works in both modes)
     * @param string $file_path
     * @return array ['success' => bool, 'content' => string]
     */
    public function readAsteriskConfig($file_path) {
        if (!file_exists($file_path)) {
            return ['success' => false, 'error' => 'File not found', 'content' => null];
        }

        $content = @file_get_contents($file_path);

        if ($content === false) {
            return ['success' => false, 'error' => 'Permission denied', 'content' => null];
        }

        return ['success' => true, 'content' => $content];
    }

    /**
     * Write to Asterisk config file (only works in power user mode)
     * @param string $file_path
     * @param string $content
     * @return array ['success' => bool, 'message' => string]
     */
    public function writeAsteriskConfig($file_path, $content) {
        if (!$this->canWriteConfigs()) {
            return [
                'success' => false,
                'error' => 'Config writes disabled',
                'message' => 'Enable Power User Mode in System Settings to write config files directly.',
                'hint' => 'Use API methods instead (Asterisk ARI/AMI)'
            ];
        }

        if (!$this->isConfigWritable($file_path)) {
            return [
                'success' => false,
                'error' => 'File not writable',
                'message' => 'Permissions do not allow writing. Run permission toggle script.',
                'file' => $file_path
            ];
        }

        // Create backup
        $backup_file = $file_path . '.backup.' . date('Y-m-d_H-i-s');
        if (file_exists($file_path)) {
            @copy($file_path, $backup_file);
        }

        // Write new content
        $result = @file_put_contents($file_path, $content);

        if ($result === false) {
            return [
                'success' => false,
                'error' => 'Write failed',
                'message' => 'Failed to write to file. Check permissions.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Config file updated successfully',
            'backup' => $backup_file,
            'bytes_written' => $result
        ];
    }
}

// Convenience function for global access
function flexpbx_config() {
    return FlexPBXConfig::getInstance();
}
