<?php
/**
 * FlexPBX Department Management API
 * Handles all department and team management operations
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

// Include database configuration
require_once __DIR__ . '/../config/config.php';

// Database connection
try {
    $pdo = new PDO("mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Check role authorization
$admin_role = $_SESSION['admin_role'] ?? 'user';
$allowed_roles = ['superadmin', 'super_admin', 'admin', 'manager'];
if (!in_array($admin_role, $allowed_roles)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Admin or Manager access required'
    ]);
    exit;
}

$current_username = $_SESSION['admin_username'] ?? 'unknown';

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Route to appropriate handler
try {
    switch ($action) {
        // Department operations
        case 'list_departments':
            listDepartments($pdo, $current_username, $admin_role);
            break;

        case 'get_department':
            getDepartment($pdo, $current_username, $admin_role);
            break;

        case 'create_department':
            createDepartment($pdo, $current_username);
            break;

        case 'update_department':
            updateDepartment($pdo, $current_username);
            break;

        case 'delete_department':
            deleteDepartment($pdo, $current_username);
            break;

        // Manager operations
        case 'list_managers':
            listManagers($pdo, $current_username, $admin_role);
            break;

        case 'assign_manager':
            assignManager($pdo, $current_username);
            break;

        case 'remove_manager':
            removeManager($pdo, $current_username);
            break;

        case 'get_manager_departments':
            getManagerDepartments($pdo, $current_username, $admin_role);
            break;

        // Team operations
        case 'list_teams':
            listTeams($pdo, $current_username, $admin_role);
            break;

        case 'create_team':
            createTeam($pdo, $current_username);
            break;

        case 'update_team':
            updateTeam($pdo, $current_username);
            break;

        case 'delete_team':
            deleteTeam($pdo, $current_username);
            break;

        case 'add_team_member':
            addTeamMember($pdo, $current_username);
            break;

        case 'remove_team_member':
            removeTeamMember($pdo, $current_username);
            break;

        case 'update_member_skills':
            updateMemberSkills($pdo, $current_username);
            break;

        case 'get_team_schedule':
            getTeamSchedule($pdo);
            break;

        // Statistics
        case 'get_stats':
            getStats($pdo, $current_username, $admin_role);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

// ============================================================================
// Department Functions
// ============================================================================

function listDepartments($pdo, $username, $role) {
    // Managers can only see their assigned departments
    if ($role === 'manager') {
        $sql = "SELECT d.*, p.name as parent_name,
                (SELECT COUNT(*) FROM department_managers dm WHERE dm.department_id = d.id AND dm.status = 'active') as manager_count,
                (SELECT COUNT(*) FROM teams t WHERE t.department_id = d.id AND t.status = 'active') as team_count,
                (SELECT GROUP_CONCAT(dm2.username SEPARATOR ', ') FROM department_managers dm2 WHERE dm2.department_id = d.id AND dm2.status = 'active') as managers
                FROM departments d
                LEFT JOIN departments p ON d.parent_id = p.id
                INNER JOIN department_managers dm ON d.id = dm.department_id
                WHERE dm.username = ? AND dm.status = 'active'
                ORDER BY d.parent_id, d.name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
    } else {
        $sql = "SELECT d.*, p.name as parent_name,
                (SELECT COUNT(*) FROM department_managers dm WHERE dm.department_id = d.id AND dm.status = 'active') as manager_count,
                (SELECT COUNT(*) FROM teams t WHERE t.department_id = d.id AND t.status = 'active') as team_count,
                (SELECT GROUP_CONCAT(dm.username SEPARATOR ', ') FROM department_managers dm WHERE dm.department_id = d.id AND dm.status = 'active') as managers
                FROM departments d
                LEFT JOIN departments p ON d.parent_id = p.id
                ORDER BY d.parent_id, d.name";
        $stmt = $pdo->query($sql);
    }

    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'departments' => $departments,
        'total' => count($departments)
    ]);
}

function getDepartment($pdo, $username, $role) {
    $id = $_GET['id'] ?? 0;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Department ID required']);
        return;
    }

    // Check access for managers
    if ($role === 'manager') {
        $check = $pdo->prepare("SELECT id FROM department_managers WHERE department_id = ? AND username = ? AND status = 'active'");
        $check->execute([$id, $username]);
        if (!$check->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied to this department']);
            return;
        }
    }

    // Get department details
    $sql = "SELECT d.*, ds.* FROM departments d
            LEFT JOIN department_settings ds ON d.id = ds.department_id
            WHERE d.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $dept = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dept) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Department not found']);
        return;
    }

    // Get managers
    $sql = "SELECT * FROM department_managers WHERE department_id = ? AND status = 'active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $dept['managers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get teams
    $sql = "SELECT t.*, (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id AND tm.status = 'active') as member_count
            FROM teams t WHERE t.department_id = ? AND t.status = 'active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $dept['teams'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'department' => $dept
    ]);
}

function createDepartment($pdo, $username) {
    $input = json_decode(file_get_contents('php://input'), true);

    $name = $input['name'] ?? '';
    $description = $input['description'] ?? '';
    $parent_id = $input['parent_id'] ?? null;
    $manager_type = $input['manager_type'] ?? 'single';
    $status = $input['status'] ?? 'active';
    $timezone = $input['timezone'] ?? 'America/New_York';

    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Department name is required']);
        return;
    }

    $pdo->beginTransaction();
    try {
        // Insert department
        $sql = "INSERT INTO departments (name, description, parent_id, manager_type, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $description, $parent_id, $manager_type, $status, $username]);
        $dept_id = $pdo->lastInsertId();

        // Insert default settings
        $sql = "INSERT INTO department_settings (department_id, business_hours_start, business_hours_end,
                timezone, working_days, overflow_action, voicemail_enabled)
                VALUES (?, '09:00:00', '17:00:00', ?, '[1,2,3,4,5]', 'voicemail', 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dept_id, $timezone]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Department created successfully',
            'department_id' => $dept_id
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function updateDepartment($pdo, $username) {
    $input = json_decode(file_get_contents('php://input'), true);

    $id = $input['id'] ?? 0;
    $name = $input['name'] ?? '';
    $description = $input['description'] ?? '';
    $parent_id = $input['parent_id'] ?? null;
    $manager_type = $input['manager_type'] ?? 'single';
    $status = $input['status'] ?? 'active';

    if (!$id || empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Department ID and name are required']);
        return;
    }

    $sql = "UPDATE departments SET name = ?, description = ?, parent_id = ?,
            manager_type = ?, status = ?, updated_at = NOW()
            WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $description, $parent_id, $manager_type, $status, $id]);

    echo json_encode([
        'success' => true,
        'message' => 'Department updated successfully'
    ]);
}

function deleteDepartment($pdo, $username) {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Department ID required']);
        return;
    }

    // Check for child departments
    $check = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE parent_id = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot delete department with sub-departments']);
        return;
    }

    $sql = "DELETE FROM departments WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Department deleted successfully'
    ]);
}

// ============================================================================
// Manager Functions
// ============================================================================

function listManagers($pdo, $username, $role) {
    if ($role === 'manager') {
        $sql = "SELECT dm.*, d.name as department_name
                FROM department_managers dm
                JOIN departments d ON dm.department_id = d.id
                WHERE dm.status = 'active' AND d.id IN
                (SELECT department_id FROM department_managers WHERE username = ? AND status = 'active')
                ORDER BY d.name, dm.username";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
    } else {
        $sql = "SELECT dm.*, d.name as department_name
                FROM department_managers dm
                JOIN departments d ON dm.department_id = d.id
                WHERE dm.status = 'active'
                ORDER BY d.name, dm.username";
        $stmt = $pdo->query($sql);
    }

    $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'managers' => $managers,
        'total' => count($managers)
    ]);
}

function assignManager($pdo, $username) {
    $input = json_decode(file_get_contents('php://input'), true);

    $dept_id = $input['department_id'] ?? 0;
    $mgr_username = $input['username'] ?? '';
    $extension = $input['extension'] ?? null;
    $role = $input['role'] ?? 'manager';
    $is_primary = $input['is_primary'] ?? 0;

    if (!$dept_id || empty($mgr_username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Department ID and username required']);
        return;
    }

    $pdo->beginTransaction();
    try {
        // If primary, unset other primary managers
        if ($is_primary) {
            $sql = "UPDATE department_managers SET is_primary = 0 WHERE department_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$dept_id]);
        }

        // Insert or update manager
        $sql = "INSERT INTO department_managers (department_id, username, extension, role, is_primary, assigned_by, status)
                VALUES (?, ?, ?, ?, ?, ?, 'active')
                ON DUPLICATE KEY UPDATE extension = VALUES(extension), role = VALUES(role),
                is_primary = VALUES(is_primary), status = 'active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dept_id, $mgr_username, $extension, $role, $is_primary, $username]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Manager assigned successfully',
            'assignment_id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function removeManager($pdo, $username) {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Assignment ID required']);
        return;
    }

    $sql = "UPDATE department_managers SET status = 'inactive' WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Manager removed successfully'
    ]);
}

function getManagerDepartments($pdo, $username, $role) {
    $mgr_username = $_GET['username'] ?? $username;

    // Managers can only see their own departments unless admin
    if ($role === 'manager' && $mgr_username !== $username) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }

    $sql = "SELECT dm.*, d.name as department_name, d.description,
            (SELECT COUNT(*) FROM teams t WHERE t.department_id = d.id AND t.status = 'active') as team_count,
            (SELECT COUNT(*) FROM team_members tm JOIN teams t ON tm.team_id = t.id
             WHERE t.department_id = d.id AND tm.status = 'active') as member_count
            FROM department_managers dm
            JOIN departments d ON dm.department_id = d.id
            WHERE dm.username = ? AND dm.status = 'active'
            ORDER BY dm.is_primary DESC, d.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$mgr_username]);

    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'departments' => $departments,
        'total' => count($departments)
    ]);
}

// ============================================================================
// Team Functions
// ============================================================================

function listTeams($pdo, $username, $role) {
    $dept_id = $_GET['department_id'] ?? null;

    $sql = "SELECT t.*, d.name as department_name,
            (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id AND tm.status = 'active') as current_members
            FROM teams t
            JOIN departments d ON t.department_id = d.id
            WHERE t.status = 'active'";

    $params = [];

    if ($dept_id) {
        $sql .= " AND t.department_id = ?";
        $params[] = $dept_id;
    }

    if ($role === 'manager') {
        $sql .= " AND d.id IN (SELECT department_id FROM department_managers WHERE username = ? AND status = 'active')";
        $params[] = $username;
    }

    $sql .= " ORDER BY d.name, t.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'teams' => $teams,
        'total' => count($teams)
    ]);
}

function createTeam($pdo, $username) {
    $input = json_decode(file_get_contents('php://input'), true);

    $dept_id = $input['department_id'] ?? 0;
    $name = $input['name'] ?? '';
    $description = $input['description'] ?? '';
    $team_lead = $input['team_lead_username'] ?? null;
    $team_type = $input['team_type'] ?? 'custom';
    $max_members = $input['max_members'] ?? 0;

    if (!$dept_id || empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Department ID and team name required']);
        return;
    }

    $sql = "INSERT INTO teams (department_id, name, description, team_lead_username,
            team_type, max_members, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dept_id, $name, $description, $team_lead, $team_type, $max_members, $username]);

    echo json_encode([
        'success' => true,
        'message' => 'Team created successfully',
        'team_id' => $pdo->lastInsertId()
    ]);
}

function updateTeam($pdo, $username) {
    $input = json_decode(file_get_contents('php://input'), true);

    $id = $input['id'] ?? 0;
    $name = $input['name'] ?? '';
    $description = $input['description'] ?? '';
    $status = $input['status'] ?? 'active';

    if (!$id || empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Team ID and name required']);
        return;
    }

    $sql = "UPDATE teams SET name = ?, description = ?, status = ?, updated_at = NOW()
            WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $description, $status, $id]);

    echo json_encode([
        'success' => true,
        'message' => 'Team updated successfully'
    ]);
}

function deleteTeam($pdo, $username) {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Team ID required']);
        return;
    }

    $sql = "DELETE FROM teams WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Team deleted successfully'
    ]);
}

function addTeamMember($pdo, $username) {
    $input = json_decode(file_get_contents('php://input'), true);

    $team_id = $input['team_id'] ?? 0;
    $member_username = $input['username'] ?? '';
    $extension = $input['extension'] ?? null;
    $email = $input['email'] ?? null;
    $role = $input['role'] ?? 'member';
    $skill_level = $input['skill_level'] ?? 'intermediate';

    if (!$team_id || empty($member_username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Team ID and username required']);
        return;
    }

    $sql = "INSERT INTO team_members (team_id, username, extension, email, role, skill_level, added_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$team_id, $member_username, $extension, $email, $role, $skill_level, $username]);

    echo json_encode([
        'success' => true,
        'message' => 'Team member added successfully',
        'member_id' => $pdo->lastInsertId()
    ]);
}

function removeTeamMember($pdo, $username) {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Member ID required']);
        return;
    }

    $sql = "UPDATE team_members SET status = 'inactive' WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Team member removed successfully'
    ]);
}

function updateMemberSkills($pdo, $username) {
    $input = json_decode(file_get_contents('php://input'), true);

    $member_id = $input['team_member_id'] ?? 0;
    $skills = $input['skills'] ?? [];

    if (!$member_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Team member ID required']);
        return;
    }

    $pdo->beginTransaction();
    try {
        // Delete existing skills
        $sql = "DELETE FROM team_member_skills WHERE team_member_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$member_id]);

        // Insert new skills
        $sql = "INSERT INTO team_member_skills (team_member_id, skill_name, skill_category, proficiency, certified)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        foreach ($skills as $skill) {
            $stmt->execute([
                $member_id,
                $skill['skill_name'],
                $skill['skill_category'] ?? 'general',
                $skill['proficiency'] ?? 'intermediate',
                $skill['certified'] ?? 0
            ]);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Skills updated successfully'
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function getTeamSchedule($pdo) {
    $team_id = $_GET['team_id'] ?? 0;

    if (!$team_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Team ID required']);
        return;
    }

    $sql = "SELECT ts.*, tm.username, tm.extension
            FROM team_schedules ts
            JOIN team_members tm ON ts.team_member_id = tm.id
            WHERE tm.team_id = ?
            ORDER BY ts.day_of_week, ts.start_time";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$team_id]);

    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'schedules' => $schedules
    ]);
}

// ============================================================================
// Statistics
// ============================================================================

function getStats($pdo, $username, $role) {
    $stats = [];

    if ($role === 'manager') {
        // Stats for manager's departments only
        $sql = "SELECT COUNT(DISTINCT d.id) as total_departments,
                SUM(CASE WHEN d.status = 'active' THEN 1 ELSE 0 END) as active_departments
                FROM departments d
                JOIN department_managers dm ON d.id = dm.department_id
                WHERE dm.username = ? AND dm.status = 'active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
    } else {
        // All departments stats
        $sql = "SELECT COUNT(*) as total_departments,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_departments,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_departments
                FROM departments";
        $stmt = $pdo->query($sql);
    }

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Additional stats
    $sql = "SELECT COUNT(*) as total_managers FROM department_managers WHERE status = 'active'";
    $stmt = $pdo->query($sql);
    $stats['total_managers'] = $stmt->fetchColumn();

    $sql = "SELECT COUNT(*) as total_teams FROM teams WHERE status = 'active'";
    $stmt = $pdo->query($sql);
    $stats['total_teams'] = $stmt->fetchColumn();

    $sql = "SELECT COUNT(*) as total_team_members FROM team_members WHERE status = 'active'";
    $stmt = $pdo->query($sql);
    $stats['total_team_members'] = $stmt->fetchColumn();

    $sql = "SELECT manager_type, COUNT(*) as count FROM departments GROUP BY manager_type";
    $stmt = $pdo->query($sql);
    $stats['departments_by_type'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['departments_by_type'][$row['manager_type']] = $row['count'];
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}

?>
