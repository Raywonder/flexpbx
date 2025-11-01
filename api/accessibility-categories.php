<?php
/**
 * FlexPBX Accessibility Categories API
 * Manages accessibility categories, features, and user assignments
 * Provides CRUD operations and compliance reporting
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Require authentication
$auth = checkAuth();
if (!$auth['authenticated']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'message' => 'Authentication required']);
    exit;
}

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed', 'message' => $e->getMessage()]);
    exit;
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';
$id = $_GET['id'] ?? '';

// Route requests
switch ($path) {
    // Category endpoints
    case '':
    case 'categories':
        handleCategories($method, $pdo, $auth);
        break;

    case 'category':
        handleCategory($method, $pdo, $auth, $id);
        break;

    // Feature endpoints
    case 'features':
        handleFeatures($method, $pdo, $auth);
        break;

    case 'feature':
        handleFeature($method, $pdo, $auth, $id);
        break;

    // User assignment endpoints
    case 'assignments':
        handleAssignments($method, $pdo, $auth);
        break;

    case 'assignment':
        handleAssignment($method, $pdo, $auth, $id);
        break;

    case 'user-profile':
        handleUserProfile($method, $pdo, $auth);
        break;

    case 'extension-profile':
        handleExtensionProfile($method, $pdo, $auth);
        break;

    // Request endpoints
    case 'requests':
        handleRequests($method, $pdo, $auth);
        break;

    case 'request':
        handleRequest($method, $pdo, $auth, $id);
        break;

    case 'submit-request':
        handleSubmitRequest($method, $pdo, $auth);
        break;

    case 'approve-request':
        handleApproveRequest($method, $pdo, $auth, $id);
        break;

    case 'reject-request':
        handleRejectRequest($method, $pdo, $auth, $id);
        break;

    // Compliance and reporting
    case 'compliance-summary':
        handleComplianceSummary($method, $pdo, $auth);
        break;

    case 'compliance-report':
        handleComplianceReport($method, $pdo, $auth);
        break;

    case 'audit-log':
        handleAuditLog($method, $pdo, $auth);
        break;

    // Settings
    case 'settings':
        handleSettings($method, $pdo, $auth);
        break;

    case 'setting':
        handleSetting($method, $pdo, $auth, $id);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not Found', 'message' => 'API endpoint not found']);
        break;
}

/**
 * Handle categories list
 */
function handleCategories($method, $pdo, $auth) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    try {
        $stmt = $pdo->query("
            SELECT
                ac.*,
                COUNT(DISTINCT uaa.user_id) AS assigned_users,
                COUNT(DISTINCT uaa.extension_number) AS assigned_extensions,
                COUNT(DISTINCT af.feature_id) AS total_features,
                SUM(CASE WHEN af.is_enabled = TRUE THEN 1 ELSE 0 END) AS enabled_features
            FROM accessibility_categories ac
            LEFT JOIN user_accessibility_assignments uaa ON ac.category_id = uaa.category_id AND uaa.is_active = TRUE
            LEFT JOIN accessibility_features af ON ac.category_id = af.category_id
            GROUP BY ac.category_id
            ORDER BY ac.category_name
        ");

        $categories = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'count' => count($categories),
            'categories' => $categories
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Handle single category
 */
function handleCategory($method, $pdo, $auth, $id) {
    switch ($method) {
        case 'GET':
            getCategoryDetails($pdo, $id);
            break;

        case 'POST':
            createCategory($pdo, $auth);
            break;

        case 'PUT':
            updateCategory($pdo, $auth, $id);
            break;

        case 'DELETE':
            deleteCategory($pdo, $auth, $id);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

/**
 * Get category details
 */
function getCategoryDetails($pdo, $category_id) {
    if (empty($category_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Category ID required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                ac.*,
                COUNT(DISTINCT uaa.user_id) AS assigned_users,
                COUNT(DISTINCT uaa.extension_number) AS assigned_extensions
            FROM accessibility_categories ac
            LEFT JOIN user_accessibility_assignments uaa ON ac.category_id = uaa.category_id AND uaa.is_active = TRUE
            WHERE ac.category_id = ?
            GROUP BY ac.category_id
        ");
        $stmt->execute([$category_id]);
        $category = $stmt->fetch();

        if (!$category) {
            http_response_code(404);
            echo json_encode(['error' => 'Category not found']);
            return;
        }

        // Get features
        $stmt = $pdo->prepare("
            SELECT * FROM accessibility_features
            WHERE category_id = ?
            ORDER BY feature_name
        ");
        $stmt->execute([$category_id]);
        $category['features'] = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'category' => $category
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Create new category
 */
function createCategory($pdo, $auth) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['category_id']) || empty($data['category_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'category_id and category_name are required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO accessibility_categories
            (category_id, category_name, description, icon, compliance_level, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['category_id'],
            $data['category_name'],
            $data['description'] ?? '',
            $data['icon'] ?? 'accessibility',
            $data['compliance_level'] ?? 'WCAG_2.1_AA',
            $data['is_active'] ?? true
        ]);

        logCompliance($pdo, 'assignment', null, null, $data['category_id'],
            'Created new accessibility category', $auth['username'], [
                'category_name' => $data['category_name']
            ]);

        echo json_encode([
            'success' => true,
            'message' => 'Category created successfully',
            'category_id' => $data['category_id']
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Update category
 */
function updateCategory($pdo, $auth, $category_id) {
    if (empty($category_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Category ID required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    try {
        $updates = [];
        $params = [];

        if (isset($data['category_name'])) {
            $updates[] = "category_name = ?";
            $params[] = $data['category_name'];
        }
        if (isset($data['description'])) {
            $updates[] = "description = ?";
            $params[] = $data['description'];
        }
        if (isset($data['icon'])) {
            $updates[] = "icon = ?";
            $params[] = $data['icon'];
        }
        if (isset($data['compliance_level'])) {
            $updates[] = "compliance_level = ?";
            $params[] = $data['compliance_level'];
        }
        if (isset($data['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = $data['is_active'];
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }

        $params[] = $category_id;
        $sql = "UPDATE accessibility_categories SET " . implode(', ', $updates) . " WHERE category_id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        logCompliance($pdo, 'modification', null, null, $category_id,
            'Updated accessibility category', $auth['username'], $data);

        echo json_encode([
            'success' => true,
            'message' => 'Category updated successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Delete category
 */
function deleteCategory($pdo, $auth, $category_id) {
    if (empty($category_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Category ID required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM accessibility_categories WHERE category_id = ?");
        $stmt->execute([$category_id]);

        logCompliance($pdo, 'removal', null, null, $category_id,
            'Deleted accessibility category', $auth['username'], []);

        echo json_encode([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Handle features list
 */
function handleFeatures($method, $pdo, $auth) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $category_id = $_GET['category_id'] ?? '';

    try {
        $sql = "SELECT af.*, ac.category_name FROM accessibility_features af
                JOIN accessibility_categories ac ON af.category_id = ac.category_id
                WHERE 1=1";
        $params = [];

        if (!empty($category_id)) {
            $sql .= " AND af.category_id = ?";
            $params[] = $category_id;
        }

        $sql .= " ORDER BY ac.category_name, af.feature_name";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $features = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'count' => count($features),
            'features' => $features
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Handle single feature
 */
function handleFeature($method, $pdo, $auth, $id) {
    switch ($method) {
        case 'GET':
            getFeatureDetails($pdo, $id);
            break;

        case 'POST':
            createFeature($pdo, $auth);
            break;

        case 'PUT':
            updateFeature($pdo, $auth, $id);
            break;

        case 'DELETE':
            deleteFeature($pdo, $auth, $id);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

/**
 * Get feature details
 */
function getFeatureDetails($pdo, $feature_id) {
    if (empty($feature_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Feature ID required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT af.*, ac.category_name
            FROM accessibility_features af
            JOIN accessibility_categories ac ON af.category_id = ac.category_id
            WHERE af.feature_id = ?
        ");
        $stmt->execute([$feature_id]);
        $feature = $stmt->fetch();

        if (!$feature) {
            http_response_code(404);
            echo json_encode(['error' => 'Feature not found']);
            return;
        }

        // Decode JSON configuration schema
        if (!empty($feature['configuration_schema'])) {
            $feature['configuration_schema'] = json_decode($feature['configuration_schema'], true);
        }

        echo json_encode([
            'success' => true,
            'feature' => $feature
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Create new feature
 */
function createFeature($pdo, $auth) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['category_id']) || empty($data['feature_code']) || empty($data['feature_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'category_id, feature_code, and feature_name are required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO accessibility_features
            (category_id, feature_code, feature_name, description, configuration_schema, is_enabled)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $config_schema = isset($data['configuration_schema'])
            ? json_encode($data['configuration_schema'])
            : null;

        $stmt->execute([
            $data['category_id'],
            $data['feature_code'],
            $data['feature_name'],
            $data['description'] ?? '',
            $config_schema,
            $data['is_enabled'] ?? true
        ]);

        $feature_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Feature created successfully',
            'feature_id' => $feature_id
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Update feature
 */
function updateFeature($pdo, $auth, $feature_id) {
    if (empty($feature_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Feature ID required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    try {
        $updates = [];
        $params = [];

        if (isset($data['feature_name'])) {
            $updates[] = "feature_name = ?";
            $params[] = $data['feature_name'];
        }
        if (isset($data['description'])) {
            $updates[] = "description = ?";
            $params[] = $data['description'];
        }
        if (isset($data['configuration_schema'])) {
            $updates[] = "configuration_schema = ?";
            $params[] = json_encode($data['configuration_schema']);
        }
        if (isset($data['is_enabled'])) {
            $updates[] = "is_enabled = ?";
            $params[] = $data['is_enabled'];
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }

        $params[] = $feature_id;
        $sql = "UPDATE accessibility_features SET " . implode(', ', $updates) . " WHERE feature_id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'message' => 'Feature updated successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Delete feature
 */
function deleteFeature($pdo, $auth, $feature_id) {
    if (empty($feature_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Feature ID required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM accessibility_features WHERE feature_id = ?");
        $stmt->execute([$feature_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Feature deleted successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Handle user assignments
 */
function handleAssignments($method, $pdo, $auth) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $user_id = $_GET['user_id'] ?? '';
    $extension = $_GET['extension'] ?? '';

    try {
        $sql = "SELECT * FROM vw_user_accessibility_profiles WHERE 1=1";
        $params = [];

        if (!empty($user_id)) {
            $sql .= " AND user_id = ?";
            $params[] = $user_id;
        }

        if (!empty($extension)) {
            $sql .= " AND extension_number = ?";
            $params[] = $extension;
        }

        $sql .= " ORDER BY priority DESC, category_name";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $assignments = $stmt->fetchAll();

        // Decode JSON configurations
        foreach ($assignments as &$assignment) {
            if (!empty($assignment['configuration'])) {
                $assignment['configuration'] = json_decode($assignment['configuration'], true);
            }
        }

        echo json_encode([
            'success' => true,
            'count' => count($assignments),
            'assignments' => $assignments
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Handle single assignment
 */
function handleAssignment($method, $pdo, $auth, $id) {
    switch ($method) {
        case 'POST':
            createAssignment($pdo, $auth);
            break;

        case 'PUT':
            updateAssignment($pdo, $auth, $id);
            break;

        case 'DELETE':
            deleteAssignment($pdo, $auth, $id);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

/**
 * Create assignment
 */
function createAssignment($pdo, $auth) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['category_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'category_id is required']);
        return;
    }

    if (empty($data['user_id']) && empty($data['extension_number'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Either user_id or extension_number is required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_accessibility_assignments
            (user_id, extension_number, category_id, assigned_by, assignment_reason, configuration, priority, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $config = isset($data['configuration']) ? json_encode($data['configuration']) : null;

        $stmt->execute([
            $data['user_id'] ?? null,
            $data['extension_number'] ?? null,
            $data['category_id'],
            $auth['username'],
            $data['assignment_reason'] ?? '',
            $config,
            $data['priority'] ?? 0,
            $data['is_active'] ?? true
        ]);

        $assignment_id = $pdo->lastInsertId();

        logCompliance($pdo, 'assignment', $data['user_id'] ?? null, $data['extension_number'] ?? null,
            $data['category_id'], 'Assigned accessibility category', $auth['username'], $data);

        echo json_encode([
            'success' => true,
            'message' => 'Assignment created successfully',
            'assignment_id' => $assignment_id
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Update assignment
 */
function updateAssignment($pdo, $auth, $assignment_id) {
    if (empty($assignment_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Assignment ID required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    try {
        $updates = [];
        $params = [];

        if (isset($data['configuration'])) {
            $updates[] = "configuration = ?";
            $params[] = json_encode($data['configuration']);
        }
        if (isset($data['priority'])) {
            $updates[] = "priority = ?";
            $params[] = $data['priority'];
        }
        if (isset($data['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = $data['is_active'];
        }
        if (isset($data['assignment_reason'])) {
            $updates[] = "assignment_reason = ?";
            $params[] = $data['assignment_reason'];
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }

        $params[] = $assignment_id;
        $sql = "UPDATE user_accessibility_assignments SET " . implode(', ', $updates) . " WHERE assignment_id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        logCompliance($pdo, 'modification', null, null, null,
            'Updated accessibility assignment', $auth['username'], $data);

        echo json_encode([
            'success' => true,
            'message' => 'Assignment updated successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Delete assignment
 */
function deleteAssignment($pdo, $auth, $assignment_id) {
    if (empty($assignment_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Assignment ID required']);
        return;
    }

    try {
        // Get assignment details before deletion
        $stmt = $pdo->prepare("SELECT * FROM user_accessibility_assignments WHERE assignment_id = ?");
        $stmt->execute([$assignment_id]);
        $assignment = $stmt->fetch();

        if (!$assignment) {
            http_response_code(404);
            echo json_encode(['error' => 'Assignment not found']);
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM user_accessibility_assignments WHERE assignment_id = ?");
        $stmt->execute([$assignment_id]);

        logCompliance($pdo, 'removal', $assignment['user_id'], $assignment['extension_number'],
            $assignment['category_id'], 'Removed accessibility assignment', $auth['username'], []);

        echo json_encode([
            'success' => true,
            'message' => 'Assignment deleted successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Handle user profile
 */
function handleUserProfile($method, $pdo, $auth) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $user_id = $_GET['user_id'] ?? '';

    if (empty($user_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'user_id required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM vw_user_accessibility_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $profile = $stmt->fetchAll();

        // Decode configurations
        foreach ($profile as &$item) {
            if (!empty($item['configuration'])) {
                $item['configuration'] = json_decode($item['configuration'], true);
            }
        }

        echo json_encode([
            'success' => true,
            'user_id' => $user_id,
            'accessibility_profile' => $profile
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Handle extension profile
 */
function handleExtensionProfile($method, $pdo, $auth) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $extension = $_GET['extension'] ?? '';

    if (empty($extension)) {
        http_response_code(400);
        echo json_encode(['error' => 'extension required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM vw_user_accessibility_profiles WHERE extension_number = ?");
        $stmt->execute([$extension]);
        $profile = $stmt->fetchAll();

        // Decode configurations
        foreach ($profile as &$item) {
            if (!empty($item['configuration'])) {
                $item['configuration'] = json_decode($item['configuration'], true);
            }
        }

        echo json_encode([
            'success' => true,
            'extension' => $extension,
            'accessibility_profile' => $profile
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Handle requests list
 */
function handleRequests($method, $pdo, $auth) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $status = $_GET['status'] ?? '';

    try {
        $sql = "SELECT * FROM vw_pending_accessibility_requests WHERE 1=1";
        $params = [];

        if (!empty($status)) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();

        // Decode JSON
        foreach ($requests as &$request) {
            if (!empty($request['requested_categories'])) {
                $request['requested_categories'] = json_decode($request['requested_categories'], true);
            }
        }

        echo json_encode([
            'success' => true,
            'count' => count($requests),
            'requests' => $requests
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Handle single request
 */
function handleRequest($method, $pdo, $auth, $id) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Request ID required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM user_accessibility_requests WHERE request_id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch();

        if (!$request) {
            http_response_code(404);
            echo json_encode(['error' => 'Request not found']);
            return;
        }

        // Decode JSON
        if (!empty($request['requested_categories'])) {
            $request['requested_categories'] = json_decode($request['requested_categories'], true);
        }

        echo json_encode([
            'success' => true,
            'request' => $request
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Submit new request
 */
function handleSubmitRequest($method, $pdo, $auth) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['requested_categories']) || !is_array($data['requested_categories'])) {
        http_response_code(400);
        echo json_encode(['error' => 'requested_categories array is required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_accessibility_requests
            (user_id, extension_number, email, requested_categories, special_requirements, urgency, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");

        $stmt->execute([
            $data['user_id'] ?? null,
            $data['extension_number'] ?? null,
            $data['email'] ?? '',
            json_encode($data['requested_categories']),
            $data['special_requirements'] ?? '',
            $data['urgency'] ?? 'medium'
        ]);

        $request_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Accessibility request submitted successfully',
            'request_id' => $request_id
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Approve request
 */
function handleApproveRequest($method, $pdo, $auth, $request_id) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($request_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Request ID required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    try {
        // Get request details
        $stmt = $pdo->prepare("SELECT * FROM user_accessibility_requests WHERE request_id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();

        if (!$request) {
            http_response_code(404);
            echo json_encode(['error' => 'Request not found']);
            return;
        }

        // Update request status
        $stmt = $pdo->prepare("
            UPDATE user_accessibility_requests
            SET status = 'approved', reviewed_by = ?, review_notes = ?, resolved_at = NOW()
            WHERE request_id = ?
        ");
        $stmt->execute([
            $auth['username'],
            $data['review_notes'] ?? '',
            $request_id
        ]);

        // Create assignments
        $categories = json_decode($request['requested_categories'], true);
        foreach ($categories as $category_id) {
            $stmt = $pdo->prepare("
                INSERT INTO user_accessibility_assignments
                (user_id, extension_number, category_id, assigned_by, assignment_reason, is_active)
                VALUES (?, ?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([
                $request['user_id'],
                $request['extension_number'],
                $category_id,
                $auth['username'],
                'Approved accessibility request #' . $request_id
            ]);
        }

        logCompliance($pdo, 'assignment', $request['user_id'], $request['extension_number'],
            null, 'Approved accessibility request', $auth['username'], [
                'request_id' => $request_id,
                'categories' => $categories
            ]);

        echo json_encode([
            'success' => true,
            'message' => 'Request approved and assignments created'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Reject request
 */
function handleRejectRequest($method, $pdo, $auth, $request_id) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($request_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Request ID required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    try {
        $stmt = $pdo->prepare("
            UPDATE user_accessibility_requests
            SET status = 'rejected', reviewed_by = ?, review_notes = ?, resolved_at = NOW()
            WHERE request_id = ?
        ");
        $stmt->execute([
            $auth['username'],
            $data['review_notes'] ?? '',
            $request_id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Request rejected'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Compliance summary
 */
function handleComplianceSummary($method, $pdo, $auth) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    try {
        $stmt = $pdo->query("SELECT * FROM vw_accessibility_compliance_summary");
        $summary = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'summary' => $summary
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Compliance report
 */
function handleComplianceReport($method, $pdo, $auth) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    try {
        // Get summary
        $stmt = $pdo->query("SELECT * FROM vw_accessibility_compliance_summary");
        $summary = $stmt->fetchAll();

        // Get total assignments
        $stmt = $pdo->query("
            SELECT COUNT(*) as total_assignments FROM user_accessibility_assignments WHERE is_active = TRUE
        ");
        $totals = $stmt->fetch();

        // Get pending requests
        $stmt = $pdo->query("
            SELECT COUNT(*) as pending_requests FROM user_accessibility_requests WHERE status = 'pending'
        ");
        $pending = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'report' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'total_assignments' => $totals['total_assignments'],
                'pending_requests' => $pending['pending_requests'],
                'categories_summary' => $summary
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Audit log
 */
function handleAuditLog($method, $pdo, $auth) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $limit = $_GET['limit'] ?? 100;
    $offset = $_GET['offset'] ?? 0;

    try {
        $stmt = $pdo->prepare("
            SELECT * FROM accessibility_compliance_logs
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $logs = $stmt->fetchAll();

        // Decode JSON details
        foreach ($logs as &$log) {
            if (!empty($log['details'])) {
                $log['details'] = json_decode($log['details'], true);
            }
        }

        echo json_encode([
            'success' => true,
            'count' => count($logs),
            'logs' => $logs
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Handle settings
 */
function handleSettings($method, $pdo, $auth) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    try {
        $stmt = $pdo->query("SELECT * FROM accessibility_settings WHERE is_active = TRUE");
        $settings = $stmt->fetchAll();

        // Decode JSON values
        foreach ($settings as &$setting) {
            if (!empty($setting['setting_value'])) {
                $setting['setting_value'] = json_decode($setting['setting_value'], true);
            }
        }

        echo json_encode([
            'success' => true,
            'count' => count($settings),
            'settings' => $settings
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Handle single setting
 */
function handleSetting($method, $pdo, $auth, $setting_key) {
    switch ($method) {
        case 'GET':
            getSettingDetails($pdo, $setting_key);
            break;

        case 'PUT':
            updateSetting($pdo, $auth, $setting_key);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

/**
 * Get setting details
 */
function getSettingDetails($pdo, $setting_key) {
    if (empty($setting_key)) {
        http_response_code(400);
        echo json_encode(['error' => 'Setting key required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM accessibility_settings WHERE setting_key = ?");
        $stmt->execute([$setting_key]);
        $setting = $stmt->fetch();

        if (!$setting) {
            http_response_code(404);
            echo json_encode(['error' => 'Setting not found']);
            return;
        }

        // Decode JSON value
        if (!empty($setting['setting_value'])) {
            $setting['setting_value'] = json_decode($setting['setting_value'], true);
        }

        echo json_encode([
            'success' => true,
            'setting' => $setting
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Update setting
 */
function updateSetting($pdo, $auth, $setting_key) {
    if (empty($setting_key)) {
        http_response_code(400);
        echo json_encode(['error' => 'Setting key required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['setting_value'])) {
        http_response_code(400);
        echo json_encode(['error' => 'setting_value required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE accessibility_settings
            SET setting_value = ?, updated_by = ?
            WHERE setting_key = ?
        ");
        $stmt->execute([
            json_encode($data['setting_value']),
            $auth['username'],
            $setting_key
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Setting updated successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

/**
 * Log compliance event
 */
function logCompliance($pdo, $log_type, $user_id, $extension, $category_id, $action, $performed_by, $details) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO accessibility_compliance_logs
            (log_type, user_id, extension_number, category_id, action_performed, performed_by, details)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $log_type,
            $user_id,
            $extension,
            $category_id,
            $action,
            $performed_by,
            json_encode($details)
        ]);
    } catch (PDOException $e) {
        // Log error but don't fail the main operation
        error_log("Failed to log compliance: " . $e->getMessage());
    }
}
