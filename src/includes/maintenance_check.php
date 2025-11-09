<?php
/**
 * FlexPBX Maintenance Mode Checker
 * Checks if the system is in maintenance mode and handles access accordingly
 *
 * Access Rules:
 * - Admins: Full access always
 * - API: Access allowed by default (configurable)
 * - Users: No access (unless limited portal enabled)
 * - Guests: No access
 *
 * @version 1.0.0
 * @date 2025-11-05
 */

// Prevent direct access
if (!defined('FLEXPBX_INIT')) {
    die('Direct access not permitted');
}

class MaintenanceMode {

    private $db;
    private $config;
    private $maintenance_settings;

    public function __construct($db_connection) {
        $this->db = $db_connection;
        $this->loadMaintenanceSettings();
    }

    /**
     * Load maintenance settings from database
     */
    private function loadMaintenanceSettings() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM system_maintenance WHERE id = 1 LIMIT 1");
            $stmt->execute();
            $this->maintenance_settings = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$this->maintenance_settings) {
                // Create default settings if not exist
                $this->createDefaultSettings();
            }
        } catch (PDOException $e) {
            error_log("Maintenance check error: " . $e->getMessage());
            $this->maintenance_settings = $this->getDefaultSettings();
        }
    }

    /**
     * Get default maintenance settings
     */
    private function getDefaultSettings() {
        return [
            'is_active' => 0,
            'maintenance_message' => 'Site is currently undergoing maintenance. Please check back later.',
            'maintenance_title' => 'Maintenance Mode',
            'allow_api_access' => 1,
            'allow_user_portal_limited' => 0
        ];
    }

    /**
     * Create default settings in database
     */
    private function createDefaultSettings() {
        $defaults = $this->getDefaultSettings();
        $stmt = $this->db->prepare("
            INSERT INTO system_maintenance (is_active, maintenance_message, maintenance_title, allow_api_access, allow_user_portal_limited)
            VALUES (:is_active, :message, :title, :api_access, :limited_portal)
        ");
        $stmt->execute([
            ':is_active' => $defaults['is_active'],
            ':message' => $defaults['maintenance_message'],
            ':title' => $defaults['maintenance_title'],
            ':api_access' => $defaults['allow_api_access'],
            ':limited_portal' => $defaults['allow_user_portal_limited']
        ]);
        $this->maintenance_settings = $defaults;
    }

    /**
     * Check if maintenance mode is active
     */
    public function isActive() {
        return (bool)$this->maintenance_settings['is_active'];
    }

    /**
     * Check if current user is an admin
     */
    private function isAdmin() {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

    /**
     * Check if current request is an API call
     */
    private function isApiRequest() {
        // Check for API key in headers or GET/POST
        if (isset($_SERVER['HTTP_X_API_KEY']) || isset($_GET['api_key']) || isset($_POST['api_key'])) {
            return true;
        }

        // Check if request path contains /api/
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($request_uri, '/api/') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Check if current user is logged in
     */
    private function isUserLoggedIn() {
        return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
    }

    /**
     * Check if user portal access is allowed in maintenance mode
     */
    public function allowUserPortalAccess() {
        return (bool)$this->maintenance_settings['allow_user_portal_limited'];
    }

    /**
     * Check if API access is allowed in maintenance mode
     */
    public function allowApiAccess() {
        return (bool)$this->maintenance_settings['allow_api_access'];
    }

    /**
     * Main check function - determines if access should be granted
     * Returns array: ['allowed' => bool, 'reason' => string]
     */
    public function checkAccess($page_type = 'general') {
        // If maintenance mode is not active, allow all access
        if (!$this->isActive()) {
            return ['allowed' => true, 'reason' => 'maintenance_inactive'];
        }

        // Admin always has access
        if ($this->isAdmin()) {
            return ['allowed' => true, 'reason' => 'admin_access'];
        }

        // API access check
        if ($this->isApiRequest() && $this->allowApiAccess()) {
            return ['allowed' => true, 'reason' => 'api_access'];
        }

        // User portal limited access
        if ($page_type === 'user_portal' && $this->allowUserPortalAccess() && $this->isUserLoggedIn()) {
            return ['allowed' => true, 'reason' => 'limited_portal_access'];
        }

        // Deny all other access
        return ['allowed' => false, 'reason' => 'maintenance_active'];
    }

    /**
     * Get maintenance message for display
     */
    public function getMaintenanceMessage() {
        return $this->maintenance_settings['maintenance_message'] ?? 'Site is under maintenance.';
    }

    /**
     * Get maintenance title for display
     */
    public function getMaintenanceTitle() {
        return $this->maintenance_settings['maintenance_title'] ?? 'Maintenance Mode';
    }

    /**
     * Enable maintenance mode
     */
    public function enable($admin_username = null) {
        $stmt = $this->db->prepare("
            UPDATE system_maintenance
            SET is_active = 1, enabled_at = NOW(), enabled_by = :admin
            WHERE id = 1
        ");
        $stmt->execute([':admin' => $admin_username]);
        $this->loadMaintenanceSettings();
        return true;
    }

    /**
     * Disable maintenance mode
     */
    public function disable() {
        $stmt = $this->db->prepare("
            UPDATE system_maintenance
            SET is_active = 0, disabled_at = NOW()
            WHERE id = 1
        ");
        $stmt->execute();
        $this->loadMaintenanceSettings();
        return true;
    }

    /**
     * Update maintenance settings
     */
    public function updateSettings($settings) {
        $allowed_fields = ['maintenance_message', 'maintenance_title', 'allow_api_access', 'allow_user_portal_limited'];
        $updates = [];
        $params = [];

        foreach ($settings as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $updates[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $sql = "UPDATE system_maintenance SET " . implode(', ', $updates) . " WHERE id = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $this->loadMaintenanceSettings();
        return true;
    }

    /**
     * Display maintenance page and exit
     */
    public function displayMaintenancePage() {
        http_response_code(503);
        $title = htmlspecialchars($this->getMaintenanceTitle());
        $message = nl2br(htmlspecialchars($this->getMaintenanceMessage()));

        include(__DIR__ . '/../templates/maintenance.php');
        exit;
    }
}

// Usage in your pages:
// require_once 'includes/maintenance_check.php';
// $maintenance = new MaintenanceMode($pdo_connection);
// $access = $maintenance->checkAccess('general'); // or 'user_portal' or 'admin'
// if (!$access['allowed']) {
//     $maintenance->displayMaintenancePage();
// }
?>
