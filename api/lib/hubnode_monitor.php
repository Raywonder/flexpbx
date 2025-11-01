<?php
/**
 * HubNode API Monitor Integration
 * Send events from FlexPBX to HubNode monitoring system
 *
 * Location: /home/flexpbxuser/public_html/api/lib/hubnode_monitor.php
 */

class HubNodeMonitor {
    private static $apiUrl = 'http://localhost:5003/events';
    private static $enabled = true;

    /**
     * Send an event to HubNode monitor
     *
     * @param string $type Event type (backup, module, system, etc.)
     * @param string $action Action performed (create, delete, install, etc.)
     * @param array $data Additional event data
     * @param bool $success Whether operation was successful
     * @return bool Whether event was logged successfully
     */
    public static function logEvent($type, $action, $data = [], $success = true) {
        if (!self::$enabled) {
            return false;
        }

        $event = [
            'type' => $type,
            'action' => $action,
            'success' => $success,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $data
        ];

        return self::sendEvent($event);
    }

    /**
     * Log backup event
     */
    public static function logBackupEvent($action, $backupType, $success = true, $additionalData = []) {
        $data = array_merge([
            'backup_type' => $backupType
        ], $additionalData);

        return self::logEvent('backup', $action, $data, $success);
    }

    /**
     * Log module event
     */
    public static function logModuleEvent($action, $moduleName, $success = true, $additionalData = []) {
        $data = array_merge([
            'module' => $moduleName
        ], $additionalData);

        return self::logEvent('module', $action, $data, $success);
    }

    /**
     * Log system event
     */
    public static function logSystemEvent($action, $success = true, $additionalData = []) {
        return self::logEvent('system', $action, $additionalData, $success);
    }

    /**
     * Send event to HubNode API
     */
    private static function sendEvent($event) {
        $ch = curl_init(self::$apiUrl);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($event),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2, // Short timeout, don't block operations
            CURLOPT_CONNECTTIMEOUT => 1
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        // Log failures silently (don't break the main operation)
        if ($httpCode !== 200 && $curlError) {
            error_log("HubNode Monitor: Failed to log event - HTTP $httpCode: $curlError");
            return false;
        }

        return true;
    }

    /**
     * Enable or disable monitoring
     */
    public static function setEnabled($enabled) {
        self::$enabled = $enabled;
    }

    /**
     * Set custom API URL
     */
    public static function setApiUrl($url) {
        self::$apiUrl = $url;
    }
}
