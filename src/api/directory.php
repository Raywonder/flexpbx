<?php
/**
 * FlexPBX Extension Directory API
 * Provides directory of all extensions for easy dialing, messaging, and transfers
 */

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? 'list';
$current_extension = $_SESSION['user_extension'] ?? null;

/**
 * Get all extensions from user files
 */
function getAllExtensions() {
    $users_dir = '/home/flexpbxuser/users';
    $extensions = [];

    if (!is_dir($users_dir)) {
        return [];
    }

    $files = glob($users_dir . '/user_*.json');

    foreach ($files as $file) {
        $user_data = json_decode(file_get_contents($file), true);

        if (!$user_data) continue;

        $extension = $user_data['extension'] ?? null;
        if (!$extension) continue;

        $extensions[] = [
            'extension' => $extension,
            'username' => $user_data['username'] ?? $extension,
            'name' => $user_data['full_name'] ?? $user_data['username'] ?? "Extension {$extension}",
            'email' => $user_data['email'] ?? '',
            'role' => $user_data['role'] ?? 'user',
            'status' => $user_data['status'] ?? 'active',
            'department' => $user_data['department'] ?? '',
            'title' => $user_data['title'] ?? ''
        ];
    }

    // Sort by extension number
    usort($extensions, function($a, $b) {
        return (int)$a['extension'] - (int)$b['extension'];
    });

    return $extensions;
}

/**
 * Get SIP registration status for extensions
 */
function getRegistrationStatus($extensions) {
    exec('sudo asterisk -rx "pjsip show endpoints" 2>&1', $output);

    $status_map = [];

    foreach ($output as $line) {
        if (preg_match('/^\s*(\d+)\s+.*\s+(Avail|Unavail)/', $line, $matches)) {
            $ext = $matches[1];
            $status = ($matches[2] === 'Avail') ? 'online' : 'offline';
            $status_map[$ext] = $status;
        }
    }

    // Add status to extensions
    foreach ($extensions as &$ext) {
        $ext['sip_status'] = $status_map[$ext['extension']] ?? 'offline';
        $ext['is_online'] = ($ext['sip_status'] === 'online');
    }

    return $extensions;
}

/**
 * Search extensions
 */
function searchExtensions($query) {
    $all_extensions = getAllExtensions();
    $query = strtolower(trim($query));

    if (empty($query)) {
        return $all_extensions;
    }

    return array_filter($all_extensions, function($ext) use ($query) {
        return (
            strpos(strtolower($ext['name']), $query) !== false ||
            strpos(strtolower($ext['username']), $query) !== false ||
            strpos($ext['extension'], $query) !== false ||
            strpos(strtolower($ext['email']), $query) !== false ||
            strpos(strtolower($ext['department']), $query) !== false
        );
    });
}

/**
 * Get extension by number
 */
function getExtension($extension) {
    $users_file = "/home/flexpbxuser/users/user_{$extension}.json";

    if (!file_exists($users_file)) {
        return null;
    }

    $user_data = json_decode(file_get_contents($users_file), true);

    if (!$user_data) {
        return null;
    }

    return [
        'extension' => $user_data['extension'] ?? $extension,
        'username' => $user_data['username'] ?? $extension,
        'name' => $user_data['full_name'] ?? $user_data['username'] ?? "Extension {$extension}",
        'email' => $user_data['email'] ?? '',
        'role' => $user_data['role'] ?? 'user',
        'status' => $user_data['status'] ?? 'active',
        'department' => $user_data['department'] ?? '',
        'title' => $user_data['title'] ?? '',
        'created_at' => $user_data['created_at'] ?? '',
        'last_login' => $user_data['last_login'] ?? ''
    ];
}

/**
 * Get directory by department
 */
function getByDepartment($department = null) {
    $extensions = getAllExtensions();

    if ($department === null) {
        // Group by department
        $grouped = [];
        foreach ($extensions as $ext) {
            $dept = $ext['department'] ?: 'Unassigned';
            if (!isset($grouped[$dept])) {
                $grouped[$dept] = [];
            }
            $grouped[$dept][] = $ext;
        }
        return $grouped;
    } else {
        // Filter by specific department
        return array_filter($extensions, function($ext) use ($department) {
            return strcasecmp($ext['department'], $department) === 0;
        });
    }
}

/**
 * Get directory statistics
 */
function getDirectoryStats() {
    $extensions = getAllExtensions();
    $extensions = getRegistrationStatus($extensions);

    $stats = [
        'total_extensions' => count($extensions),
        'online' => 0,
        'offline' => 0,
        'by_role' => [],
        'by_department' => []
    ];

    foreach ($extensions as $ext) {
        if ($ext['is_online']) {
            $stats['online']++;
        } else {
            $stats['offline']++;
        }

        // Count by role
        $role = $ext['role'];
        if (!isset($stats['by_role'][$role])) {
            $stats['by_role'][$role] = 0;
        }
        $stats['by_role'][$role]++;

        // Count by department
        $dept = $ext['department'] ?: 'Unassigned';
        if (!isset($stats['by_department'][$dept])) {
            $stats['by_department'][$dept] = 0;
        }
        $stats['by_department'][$dept]++;
    }

    return $stats;
}

// Handle API requests
switch ($action) {
    case 'list':
        $include_status = isset($_GET['status']) && $_GET['status'] === 'true';
        $extensions = getAllExtensions();

        if ($include_status) {
            $extensions = getRegistrationStatus($extensions);
        }

        echo json_encode([
            'success' => true,
            'extensions' => $extensions,
            'total' => count($extensions)
        ]);
        break;

    case 'search':
        $query = $_GET['q'] ?? '';
        $extensions = searchExtensions($query);
        $extensions = getRegistrationStatus($extensions);

        echo json_encode([
            'success' => true,
            'extensions' => array_values($extensions),
            'total' => count($extensions),
            'query' => $query
        ]);
        break;

    case 'get':
        $extension = $_GET['extension'] ?? '';
        $ext_data = getExtension($extension);

        if ($ext_data) {
            // Get SIP status
            $ext_array = [$ext_data];
            $ext_array = getRegistrationStatus($ext_array);
            $ext_data = $ext_array[0];

            echo json_encode([
                'success' => true,
                'extension' => $ext_data
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Extension not found'
            ]);
        }
        break;

    case 'by_department':
        $department = $_GET['department'] ?? null;
        $result = getByDepartment($department);

        echo json_encode([
            'success' => true,
            'departments' => $result
        ]);
        break;

    case 'stats':
        $stats = getDirectoryStats();

        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        break;

    case 'online':
        $extensions = getAllExtensions();
        $extensions = getRegistrationStatus($extensions);
        $online = array_filter($extensions, function($ext) {
            return $ext['is_online'];
        });

        echo json_encode([
            'success' => true,
            'extensions' => array_values($online),
            'total' => count($online)
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
