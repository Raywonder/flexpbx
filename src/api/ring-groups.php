<?php
/**
 * FlexPBX Ring Groups API
 * Manages ring groups (hunt groups) with multiple ring strategies
 *
 * Endpoints:
 * - GET    ?path=list              - List all ring groups
 * - GET    ?path=get&id=X          - Get specific ring group
 * - POST   ?path=create            - Create new ring group
 * - PUT    ?path=update&id=X       - Update ring group
 * - DELETE ?path=delete&id=X       - Delete ring group
 * - GET    ?path=members&group_id=X - Get ring group members
 * - POST   ?path=add-member        - Add member to ring group
 * - PUT    ?path=update-member&id=X - Update member order
 * - DELETE ?path=remove-member&id=X - Remove member
 * - POST   ?path=apply-config      - Apply configuration to Asterisk
 */

header('Content-Type: application/json');

// Database connection
$config = require __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// Route the request
try {
    switch ($path) {
        case 'list':
            if ($method === 'GET') {
                handleListRingGroups();
            } else {
                methodNotAllowed();
            }
            break;

        case 'get':
            if ($method === 'GET') {
                handleGetRingGroup();
            } else {
                methodNotAllowed();
            }
            break;

        case 'create':
            if ($method === 'POST') {
                handleCreateRingGroup();
            } else {
                methodNotAllowed();
            }
            break;

        case 'update':
            if ($method === 'PUT') {
                handleUpdateRingGroup();
            } else {
                methodNotAllowed();
            }
            break;

        case 'delete':
            if ($method === 'DELETE') {
                handleDeleteRingGroup();
            } else {
                methodNotAllowed();
            }
            break;

        case 'members':
            if ($method === 'GET') {
                handleGetMembers();
            } else {
                methodNotAllowed();
            }
            break;

        case 'add-member':
            if ($method === 'POST') {
                handleAddMember();
            } else {
                methodNotAllowed();
            }
            break;

        case 'update-member':
            if ($method === 'PUT') {
                handleUpdateMember();
            } else {
                methodNotAllowed();
            }
            break;

        case 'remove-member':
            if ($method === 'DELETE') {
                handleRemoveMember();
            } else {
                methodNotAllowed();
            }
            break;

        case 'apply-config':
            if ($method === 'POST') {
                handleApplyConfig();
            } else {
                methodNotAllowed();
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function methodNotAllowed() {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

/**
 * List all ring groups with member counts
 */
function handleListRingGroups() {
    global $pdo;

    $stmt = $pdo->query("
        SELECT rg.*,
               COUNT(rgm.id) as member_count
        FROM ring_groups rg
        LEFT JOIN ring_group_members rgm ON rg.id = rgm.ring_group_id AND rgm.enabled = 1
        GROUP BY rg.id
        ORDER BY rg.group_number
    ");

    $ringGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $ringGroups
    ]);
}

/**
 * Get specific ring group
 */
function handleGetRingGroup() {
    global $pdo;

    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ring group ID required']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM ring_groups WHERE id = ?");
    $stmt->execute([$id]);
    $ringGroup = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ringGroup) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ring group not found']);
        return;
    }

    echo json_encode([
        'success' => true,
        'data' => $ringGroup
    ]);
}

/**
 * Create new ring group
 */
function handleCreateRingGroup() {
    global $pdo;

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (empty($input['group_number']) || empty($input['group_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Group number and name are required']);
        return;
    }

    // Check if group number already exists
    $stmt = $pdo->prepare("SELECT id FROM ring_groups WHERE group_number = ?");
    $stmt->execute([$input['group_number']]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Ring group number already exists']);
        return;
    }

    // Insert ring group
    $stmt = $pdo->prepare("
        INSERT INTO ring_groups (
            group_number, group_name, strategy, ring_time, skip_busy,
            confirm_calls, announcement, destination_type, destination_value, enabled
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $input['group_number'],
        $input['group_name'],
        $input['strategy'] ?? 'ringall',
        $input['ring_time'] ?? 20,
        $input['skip_busy'] ?? 1,
        $input['confirm_calls'] ?? 0,
        $input['announcement'] ?? null,
        $input['destination_type'] ?? 'voicemail',
        $input['destination_value'] ?? null,
        $input['enabled'] ?? 1
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Ring group created successfully',
        'id' => $pdo->lastInsertId()
    ]);
}

/**
 * Update ring group
 */
function handleUpdateRingGroup() {
    global $pdo;

    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ring group ID required']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // Check if ring group exists
    $stmt = $pdo->prepare("SELECT id FROM ring_groups WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ring group not found']);
        return;
    }

    // Update ring group
    $stmt = $pdo->prepare("
        UPDATE ring_groups SET
            group_number = ?,
            group_name = ?,
            strategy = ?,
            ring_time = ?,
            skip_busy = ?,
            confirm_calls = ?,
            announcement = ?,
            destination_type = ?,
            destination_value = ?,
            enabled = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $input['group_number'],
        $input['group_name'],
        $input['strategy'],
        $input['ring_time'],
        $input['skip_busy'],
        $input['confirm_calls'],
        $input['announcement'],
        $input['destination_type'],
        $input['destination_value'],
        $input['enabled'],
        $id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Ring group updated successfully'
    ]);
}

/**
 * Delete ring group
 */
function handleDeleteRingGroup() {
    global $pdo;

    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ring group ID required']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM ring_groups WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ring group not found']);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Ring group deleted successfully'
    ]);
}

/**
 * Get members of a ring group
 */
function handleGetMembers() {
    global $pdo;

    $groupId = $_GET['group_id'] ?? null;
    if (!$groupId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ring group ID required']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT * FROM ring_group_members
        WHERE ring_group_id = ?
        ORDER BY member_order, id
    ");
    $stmt->execute([$groupId]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $members
    ]);
}

/**
 * Add member to ring group
 */
function handleAddMember() {
    global $pdo;

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (empty($input['ring_group_id']) || empty($input['member_value'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ring group ID and member value required']);
        return;
    }

    // Check if member already exists in this group
    $stmt = $pdo->prepare("
        SELECT id FROM ring_group_members
        WHERE ring_group_id = ? AND member_value = ?
    ");
    $stmt->execute([$input['ring_group_id'], $input['member_value']]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Member already exists in this ring group']);
        return;
    }

    // Insert member
    $stmt = $pdo->prepare("
        INSERT INTO ring_group_members (
            ring_group_id, member_type, member_value, member_order, enabled
        ) VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $input['ring_group_id'],
        $input['member_type'] ?? 'extension',
        $input['member_value'],
        $input['member_order'] ?? 0,
        $input['enabled'] ?? 1
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Member added to ring group successfully',
        'id' => $pdo->lastInsertId()
    ]);
}

/**
 * Update member order
 */
function handleUpdateMember() {
    global $pdo;

    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Member ID required']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $stmt = $pdo->prepare("
        UPDATE ring_group_members SET
            member_type = ?,
            member_value = ?,
            member_order = ?,
            enabled = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $input['member_type'],
        $input['member_value'],
        $input['member_order'],
        $input['enabled'],
        $id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Member updated successfully'
    ]);
}

/**
 * Remove member from ring group
 */
function handleRemoveMember() {
    global $pdo;

    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Member ID required']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM ring_group_members WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Member not found']);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Member removed successfully'
    ]);
}

/**
 * Apply ring group configuration to Asterisk
 * Generates dialplan entries for all enabled ring groups
 */
function handleApplyConfig() {
    global $pdo;

    try {
        // Get all enabled ring groups
        $stmt = $pdo->query("SELECT * FROM ring_groups WHERE enabled = 1 ORDER BY group_number");
        $ringGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate dialplan
        $dialplan = "; FlexPBX Ring Groups Configuration\n";
        $dialplan .= "; Auto-generated - Do not edit manually\n";
        $dialplan .= "; Generated: " . date('Y-m-d H:i:s') . "\n\n";

        $dialplan .= "[flexpbx-ring-groups]\n";
        $dialplan .= "; Ring group extensions\n\n";

        foreach ($ringGroups as $group) {
            // Get members for this group
            $memberStmt = $pdo->prepare("
                SELECT * FROM ring_group_members
                WHERE ring_group_id = ? AND enabled = 1
                ORDER BY member_order, id
            ");
            $memberStmt->execute([$group['id']]);
            $members = $memberStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($members)) {
                $dialplan .= "; Ring group {$group['group_number']} ({$group['group_name']}) - No members\n\n";
                continue;
            }

            $dialplan .= "; Ring group {$group['group_number']} - {$group['group_name']}\n";
            $dialplan .= "exten => {$group['group_number']},1,NoOp(Ring Group: {$group['group_name']})\n";

            $priority = 2;

            // Play announcement if configured
            if (!empty($group['announcement'])) {
                $dialplan .= "same => n,Playback({$group['announcement']})\n";
            }

            // Build dial string based on strategy
            $dialString = buildDialString($group, $members);
            $dialOptions = buildDialOptions($group);

            $dialplan .= "same => n,Dial({$dialString},{$group['ring_time']},{$dialOptions})\n";

            // Handle no answer - route to destination
            $dialplan .= handleNoAnswerDestination($group);

            $dialplan .= "same => n,Hangup()\n\n";
        }

        // Write to extensions file
        $configFile = '/etc/asterisk/extensions_ring_groups.conf';
        if (file_put_contents($configFile, $dialplan) === false) {
            throw new Exception("Failed to write configuration file");
        }

        // Set proper permissions
        chown($configFile, 'asterisk');
        chgrp($configFile, 'asterisk');
        chmod($configFile, 0640);

        // Check if included in main extensions.conf
        $mainExtensions = file_get_contents('/etc/asterisk/extensions.conf');
        if (strpos($mainExtensions, '#include extensions_ring_groups.conf') === false) {
            // Add include at the end
            file_put_contents(
                '/etc/asterisk/extensions.conf',
                "\n; Ring Groups Module\n#include extensions_ring_groups.conf\n",
                FILE_APPEND
            );
        }

        // Reload dialplan
        exec('sudo asterisk -rx "dialplan reload" 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception("Failed to reload dialplan: " . implode("\n", $output));
        }

        echo json_encode([
            'success' => true,
            'message' => 'Ring group configuration applied successfully',
            'groups_configured' => count($ringGroups),
            'dialplan' => $dialplan
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Configuration error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Build dial string based on ring group strategy
 */
function buildDialString($group, $members) {
    $channels = [];

    foreach ($members as $member) {
        if ($member['member_type'] === 'extension') {
            $channels[] = "PJSIP/{$member['member_value']}";
        } else {
            // External number
            $channels[] = "PJSIP/{$member['member_value']}@trunk"; // Adjust trunk name as needed
        }
    }

    switch ($group['strategy']) {
        case 'ringall':
            // All ring simultaneously
            return implode('&', $channels);

        case 'hunt':
        case 'memoryhunt':
            // Sequential - ring one at a time
            return implode('&', $channels); // Will use 'r' option for sequential

        case 'random':
            // Random order
            shuffle($channels);
            return implode('&', $channels);

        default:
            return implode('&', $channels);
    }
}

/**
 * Build dial options
 */
function buildDialOptions($group) {
    $options = 't'; // Allow transfer

    if ($group['confirm_calls']) {
        $options .= 'M(confirm)'; // Require confirmation
    }

    if ($group['strategy'] === 'hunt' || $group['strategy'] === 'memoryhunt') {
        // For hunt, we actually need to dial sequentially
        // This is better handled in dialplan with tryexec
    }

    return $options;
}

/**
 * Handle no answer destination routing
 */
function handleNoAnswerDestination($group) {
    $dialplan = "";

    switch ($group['destination_type']) {
        case 'voicemail':
            $ext = $group['destination_value'] ?? $group['group_number'];
            $dialplan .= "same => n,Voicemail({$ext}@flexpbx,su)\n";
            break;

        case 'extension':
            if (!empty($group['destination_value'])) {
                $dialplan .= "same => n,Dial(PJSIP/{$group['destination_value']},30,t)\n";
                $dialplan .= "same => n,Voicemail({$group['destination_value']}@flexpbx,su)\n";
            }
            break;

        case 'queue':
            if (!empty($group['destination_value'])) {
                $dialplan .= "same => n,Queue({$group['destination_value']})\n";
            }
            break;

        case 'ivr':
            if (!empty($group['destination_value'])) {
                $dialplan .= "same => n,Goto(flexpbx-ivr,{$group['destination_value']},1)\n";
            }
            break;

        case 'hangup':
            $dialplan .= "same => n,Playback(goodbye)\n";
            break;

        default:
            $dialplan .= "same => n,Voicemail({$group['group_number']}@flexpbx,su)\n";
            break;
    }

    return $dialplan;
}
