<?php
/**
 * FlexPBX Provisioning Settings Manager
 *
 * Helper class for managing auto-provisioning settings
 * Provides easy access to configuration values from the database
 *
 * @author FlexPBX
 * @version 1.0.0
 * @created 2025-10-17
 */

require_once __DIR__ . '/db.php';

class ProvisioningSettings {

    private static $db = null;
    private static $cache = [];
    private static $cacheLoaded = false;

    /**
     * Initialize database connection
     */
    private static function initDB() {
        if (self::$db === null) {
            global $db;
            self::$db = $db;
        }
    }

    /**
     * Load all settings into cache
     */
    private static function loadCache() {
        if (self::$cacheLoaded) {
            return;
        }

        self::initDB();

        try {
            $stmt = self::$db->query("
                SELECT setting_key, setting_value, setting_type
                FROM provisioning_settings
            ");

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = self::convertValue($row['setting_value'], $row['setting_type']);
                self::$cache[$row['setting_key']] = $value;
            }

            self::$cacheLoaded = true;
        } catch (PDOException $e) {
            error_log("Failed to load provisioning settings: " . $e->getMessage());
        }
    }

    /**
     * Get a setting value
     *
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @return mixed Setting value
     */
    public static function get($key, $default = null) {
        self::loadCache();

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        // Try to fetch from database if not in cache
        self::initDB();

        try {
            $stmt = self::$db->prepare("
                SELECT setting_value, setting_type
                FROM provisioning_settings
                WHERE setting_key = ?
            ");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $value = self::convertValue($row['setting_value'], $row['setting_type']);
                self::$cache[$key] = $value;
                return $value;
            }
        } catch (PDOException $e) {
            error_log("Failed to get setting '$key': " . $e->getMessage());
        }

        return $default;
    }

    /**
     * Set a setting value
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param string $type Setting type (string/number/boolean/json)
     * @param string $updatedBy Username who updated the setting
     * @return bool Success status
     */
    public static function set($key, $value, $type = 'string', $updatedBy = 'system') {
        self::initDB();

        try {
            // Convert value to storage format
            $storageValue = self::convertToStorage($value, $type);

            $stmt = self::$db->prepare("
                INSERT INTO provisioning_settings (setting_key, setting_value, setting_type, updated_by)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    setting_type = VALUES(setting_type),
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP
            ");

            $stmt->execute([$key, $storageValue, $type, $updatedBy]);

            // Update cache
            self::$cache[$key] = $value;

            return true;
        } catch (PDOException $e) {
            error_log("Failed to set setting '$key': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all settings, optionally filtered by category
     *
     * @param string|null $category Category to filter by
     * @return array Associative array of settings
     */
    public static function getAll($category = null) {
        self::initDB();

        try {
            if ($category) {
                $stmt = self::$db->prepare("
                    SELECT setting_key, setting_value, setting_type, description, category
                    FROM provisioning_settings
                    WHERE category = ?
                    ORDER BY setting_key
                ");
                $stmt->execute([$category]);
            } else {
                $stmt = self::$db->query("
                    SELECT setting_key, setting_value, setting_type, description, category
                    FROM provisioning_settings
                    ORDER BY category, setting_key
                ");
            }

            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = self::convertValue($row['setting_value'], $row['setting_type']);
                $settings[$row['setting_key']] = [
                    'value' => $value,
                    'type' => $row['setting_type'],
                    'description' => $row['description'],
                    'category' => $row['category']
                ];
            }

            return $settings;
        } catch (PDOException $e) {
            error_log("Failed to get all settings: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get settings grouped by category
     *
     * @return array Settings grouped by category
     */
    public static function getAllByCategory() {
        self::initDB();

        try {
            $stmt = self::$db->query("
                SELECT setting_key, setting_value, setting_type, description, category
                FROM provisioning_settings
                ORDER BY category, setting_key
            ");

            $grouped = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = self::convertValue($row['setting_value'], $row['setting_type']);
                $category = $row['category'] ?? 'general';

                if (!isset($grouped[$category])) {
                    $grouped[$category] = [];
                }

                $grouped[$category][$row['setting_key']] = [
                    'value' => $value,
                    'type' => $row['setting_type'],
                    'description' => $row['description']
                ];
            }

            return $grouped;
        } catch (PDOException $e) {
            error_log("Failed to get settings by category: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Reset all settings to default values
     *
     * @return bool Success status
     */
    public static function reset() {
        self::initDB();

        try {
            // This assumes you have the SQL file with defaults
            // In practice, you'd re-run the INSERT statements
            self::$db->exec("TRUNCATE TABLE provisioning_settings");

            // Re-run the default inserts
            $sqlFile = '/home/flexpbxuser/update_provisioning_settings.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                self::$db->exec($sql);
            }

            // Clear cache
            self::$cache = [];
            self::$cacheLoaded = false;

            return true;
        } catch (PDOException $e) {
            error_log("Failed to reset settings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get next available extension number
     *
     * @return int Next available extension
     */
    public static function getNextExtension() {
        self::initDB();

        try {
            // Get current next extension
            $next = (int)self::get('next_available_extension', 3000);
            $start = (int)self::get('extension_range_start', 3000);
            $end = (int)self::get('extension_range_end', 9999);

            // Make sure it's within range
            if ($next < $start) {
                $next = $start;
            }

            // Check if extension is in use
            while ($next <= $end) {
                if (self::isExtensionAvailable($next)) {
                    // Update the next available extension
                    self::set('next_available_extension', $next + 1, 'number');
                    return $next;
                }
                $next++;
            }

            throw new Exception("No available extensions in range $start-$end");
        } catch (Exception $e) {
            error_log("Failed to get next extension: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if an extension number is available
     *
     * @param int $extension Extension number to check
     * @return bool True if available
     */
    public static function isExtensionAvailable($extension) {
        self::initDB();

        try {
            // Check in extensions table
            $stmt = self::$db->prepare("
                SELECT COUNT(*) as count
                FROM extensions
                WHERE extension_number = ?
            ");
            $stmt->execute([$extension]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                return false;
            }

            // Check reserved extensions
            $reserved = self::get('reserved_extensions', []);
            if (in_array($extension, $reserved)) {
                return false;
            }

            return true;
        } catch (PDOException $e) {
            error_log("Failed to check extension availability: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get extension range preview
     *
     * @return array Range information
     */
    public static function getExtensionRangeInfo() {
        $start = self::get('extension_range_start', 3000);
        $end = self::get('extension_range_end', 9999);
        $next = self::get('next_available_extension', 3000);

        self::initDB();

        try {
            $stmt = self::$db->query("SELECT COUNT(*) as count FROM extensions");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $used = $result['count'];
        } catch (PDOException $e) {
            $used = 0;
        }

        $total = $end - $start + 1;
        $available = $total - $used;

        return [
            'start' => $start,
            'end' => $end,
            'next' => $next,
            'total' => $total,
            'used' => $used,
            'available' => $available,
            'percentage_used' => $total > 0 ? round(($used / $total) * 100, 2) : 0
        ];
    }

    /**
     * Convert value from database to proper type
     *
     * @param string $value Database value
     * @param string $type Value type
     * @return mixed Converted value
     */
    private static function convertValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return ($value === 'true' || $value === '1' || $value === 1);
            case 'number':
                return is_numeric($value) ? (int)$value : 0;
            case 'json':
                return json_decode($value, true) ?? [];
            default:
                return $value;
        }
    }

    /**
     * Convert value to storage format
     *
     * @param mixed $value Value to convert
     * @param string $type Value type
     * @return string Storage value
     */
    private static function convertToStorage($value, $type) {
        switch ($type) {
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'number':
                return (string)(int)$value;
            case 'json':
                return json_encode($value);
            default:
                return (string)$value;
        }
    }

    /**
     * Clear the cache
     */
    public static function clearCache() {
        self::$cache = [];
        self::$cacheLoaded = false;
    }

    /**
     * Export settings as JSON
     *
     * @return string JSON representation of all settings
     */
    public static function exportJSON() {
        $settings = self::getAll();
        return json_encode($settings, JSON_PRETTY_PRINT);
    }

    /**
     * Import settings from JSON
     *
     * @param string $json JSON string
     * @param string $updatedBy Username who imported
     * @return bool Success status
     */
    public static function importJSON($json, $updatedBy = 'system') {
        $settings = json_decode($json, true);

        if (!is_array($settings)) {
            return false;
        }

        $success = true;
        foreach ($settings as $key => $data) {
            if (is_array($data) && isset($data['value'], $data['type'])) {
                $result = self::set($key, $data['value'], $data['type'], $updatedBy);
                $success = $success && $result;
            }
        }

        return $success;
    }
}
