<?php
/**
 * FlexPBX User Management API
 * Handles user migrations, extension changes, department transfers, and queue updates
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

session_start();

// Authentication check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'migrate_user':
        migrateUser();
        break;
    case 'change_extension':
        changeExtension();
        break;
    case 'move_department':
        moveDepartment();
        break;
    case 'get_user':
        getUser();
        break;
    case 'list_users':
        listUsers();
        break;
    case 'migration_history':
        getMigrationHistory();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Complete user migration with extension change and/or department move
 */
function migrateUser() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $userId = $input['user_id'] ?? null;
    $changeExtension = isset($input['change_extension']) && $input['change_extension'] === 'on';
    $changeDepartment = isset($input['change_department']) && $input['change_department'] === 'on';
    $newExtension = $input['new_extension'] ?? null;
    $newDepartment = $input['new_department'] ?? null;
    $updateQueues = isset($input['update_queue_membership']) && $input['update_queue_membership'] === 'on';
    $reason = $input['migration_reason'] ?? '';
    $notifyUser = isset($input['notify_user']) && $input['notify_user'] === 'on';
    
    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'User ID required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get current user data
        $stmt = $pdo->prepare("SELECT * FROM extensions WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        $oldExtension = $user['extension'];
        $oldDepartment = $user['department_id'];
        $changes = [];
        
        // Handle extension change
        if ($changeExtension) {
            if (empty($newExtension)) {
                // Auto-assign next available extension
                $newExtension = getNextAvailableExtension($pdo);
            } else {
                // Validate extension is available
                if (!isExtensionAvailable($pdo, $newExtension, $userId)) {
                    throw new Exception("Extension $newExtension is already in use");
                }
            }
            
            // Update extension number in extensions table
            $stmt = $pdo->prepare("UPDATE extensions SET extension = ? WHERE id = ?");
            $stmt->execute([$newExtension, $userId]);
            
            // Update PJSIP configuration
            updatePJSIPExtension($pdo, $oldExtension, $newExtension);
            
            // Update queue memberships with new extension
            if ($updateQueues) {
                updateQueueMemberships($pdo, $oldExtension, $newExtension);
            }
            
            // Migrate voicemail
            migrateVoicemail($oldExtension, $newExtension);
            
            $changes[] = "Extension: $oldExtension → $newExtension";
        }
        
        // Handle department change
        if ($changeDepartment && $newDepartment) {
            $stmt = $pdo->prepare("UPDATE extensions SET department_id = ? WHERE id = ?");
            $stmt->execute([$newDepartment, $userId]);
            
            if ($updateQueues) {
                // Remove from old department queues
                if ($oldDepartment) {
                    removeFromDepartmentQueues($pdo, $user['extension'], $oldDepartment);
                }
                
                // Add to new department queues
                addToDepartmentQueues($pdo, $user['extension'], $newDepartment);
            }
            
            $oldDeptName = getDepartmentName($pdo, $oldDepartment);
            $newDeptName = getDepartmentName($pdo, $newDepartment);
            $changes[] = "Department: $oldDeptName → $newDeptName";
        }
        
        // Log migration
        logMigration($pdo, $userId, $oldExtension, $changeExtension ? $newExtension : $oldExtension, 
                     $oldDepartment, $changeDepartment ? $newDepartment : $oldDepartment, $reason, $changes);
        
        // Send notification email
        if ($notifyUser) {
            sendMigrationNotification($user, $oldExtension, $newExtension, $changes);
        }
        
        // Reload Asterisk configuration
        reloadAsteriskConfig();
        
        $pdo->commit();
        
        $message = "Migration completed successfully.\n" . implode("\n", $changes);
        if ($changeExtension) {
            $message .= "\n\n⚠️ User must update third-party SIP clients with new extension: $newExtension";
            $message .= "\n✅ FlexPhone and User Portal will auto-update";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'old_extension' => $oldExtension,
            'new_extension' => $changeExtension ? $newExtension : $oldExtension,
            'changes' => $changes
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Quick extension number change
 */
function changeExtension() {
    global $pdo;
    
    $userId = $_POST['user_id'] ?? null;
    $newExtension = $_POST['new_extension'] ?? null;
    $preserveVoicemail = isset($_POST['preserve_voicemail']);
    $notifyUser = isset($_POST['notify_user']);
    
    if (!$userId || !$newExtension) {
        echo json_encode(['success' => false, 'error' => 'User ID and new extension required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get current user
        $stmt = $pdo->prepare("SELECT * FROM extensions WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        $oldExtension = $user['extension'];
        
        // Check if new extension is available
        if (!isExtensionAvailable($pdo, $newExtension, $userId)) {
            throw new Exception("Extension $newExtension is already in use");
        }
        
        // Update extension
        $stmt = $pdo->prepare("UPDATE extensions SET extension = ? WHERE id = ?");
        $stmt->execute([$newExtension, $userId]);
        
        // Update PJSIP
        updatePJSIPExtension($pdo, $oldExtension, $newExtension);
        
        // Update queues
        updateQueueMemberships($pdo, $oldExtension, $newExtension);
        
        // Migrate voicemail if requested
        if ($preserveVoicemail) {
            migrateVoicemail($oldExtension, $newExtension);
        }
        
        // Log change
        logMigration($pdo, $userId, $oldExtension, $newExtension, null, null, 'Extension change only', ["Extension: $oldExtension → $newExtension"]);
        
        // Notify user
        if ($notifyUser) {
            sendExtensionChangeNotification($user, $oldExtension, $newExtension);
        }
        
        reloadAsteriskConfig();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Extension changed from $oldExtension to $newExtension",
            'old_extension' => $oldExtension,
            'new_extension' => $newExtension
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Move user to different department (extension stays same)
 */
function moveDepartment() {
    global $pdo;
    
    $userId = $_POST['user_id'] ?? null;
    $newDepartment = $_POST['new_department'] ?? null;
    $updateQueues = isset($_POST['update_queues']);
    $reason = $_POST['transfer_reason'] ?? '';
    
    if (!$userId || !$newDepartment) {
        echo json_encode(['success' => false, 'error' => 'User ID and department required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get user
        $stmt = $pdo->prepare("SELECT * FROM extensions WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $oldDepartment = $user['department_id'];
        
        // Update department
        $stmt = $pdo->prepare("UPDATE extensions SET department_id = ? WHERE id = ?");
        $stmt->execute([$newDepartment, $userId]);
        
        // Update queues
        if ($updateQueues) {
            if ($oldDepartment) {
                removeFromDepartmentQueues($pdo, $user['extension'], $oldDepartment);
            }
            addToDepartmentQueues($pdo, $user['extension'], $newDepartment);
        }
        
        $oldDeptName = getDepartmentName($pdo, $oldDepartment);
        $newDeptName = getDepartmentName($pdo, $newDepartment);
        
        logMigration($pdo, $userId, $user['extension'], $user['extension'], $oldDepartment, $newDepartment, $reason, ["Department: $oldDeptName → $newDeptName"]);
        
        reloadAsteriskConfig();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "User moved from $oldDeptName to $newDeptName",
            'old_department' => $oldDeptName,
            'new_department' => $newDeptName
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get single user data
 */
function getUser() {
    global $pdo;
    
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'User ID required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT e.*, d.name as department_name,
               (SELECT COUNT(*) FROM voicemail WHERE mailbox = e.extension) as voicemail_count
        FROM extensions e
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Get queue memberships
        $stmt = $pdo->prepare("SELECT queue_name FROM queue_members WHERE interface LIKE ?");
        $stmt->execute(["%{$user['extension']}%"]);
        $user['queues'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found']);
    }
}

/**
 * List all users
 */
function listUsers() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT e.id, e.extension, e.name, e.email, d.name as department
        FROM extensions e
        LEFT JOIN departments d ON e.department_id = d.id
        ORDER BY e.extension ASC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'users' => $users]);
}

/**
 * Get migration history
 */
function getMigrationHistory() {
    global $pdo;
    
    $filter = $_GET['filter'] ?? 'all';
    
    $query = "SELECT * FROM migration_history ORDER BY created_at DESC LIMIT 100";
    
    $stmt = $pdo->query($query);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'history' => $history]);
}

// Helper functions

function getNextAvailableExtension($pdo) {
    $stmt = $pdo->query("SELECT extension FROM extensions WHERE extension >= 2000 AND extension < 3000 ORDER BY extension ASC");
    $used = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    for ($ext = 2000; $ext < 3000; $ext++) {
        if (!in_array($ext, $used)) {
            return $ext;
        }
    }
    
    throw new Exception('No available extensions in range 2000-2999');
}

function isExtensionAvailable($pdo, $extension, $excludeUserId = null) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM extensions WHERE extension = ? AND id != ?");
    $stmt->execute([$extension, $excludeUserId ?: 0]);
    return $stmt->fetchColumn() == 0;
}

function updatePJSIPExtension($pdo, $oldExt, $newExt) {
    $stmt = $pdo->prepare("UPDATE ps_endpoints SET id = ?, auth = ? WHERE id = ?");
    $stmt->execute([$newExt, $newExt, $oldExt]);
    
    $stmt = $pdo->prepare("UPDATE ps_auths SET id = ?, username = ? WHERE id = ?");
    $stmt->execute([$newExt, $newExt, $oldExt]);
    
    $stmt = $pdo->prepare("UPDATE ps_aors SET id = ? WHERE id = ?");
    $stmt->execute([$newExt, $oldExt]);
}

function updateQueueMemberships($pdo, $oldExt, $newExt) {
    $stmt = $pdo->prepare("UPDATE queue_members SET interface = REPLACE(interface, ?, ?) WHERE interface LIKE ?");
    $stmt->execute([$oldExt, $newExt, "%$oldExt%"]);
}

function removeFromDepartmentQueues($pdo, $extension, $departmentId) {
    // Get department queues
    $stmt = $pdo->prepare("SELECT queue_name FROM department_queues WHERE department_id = ?");
    $stmt->execute([$departmentId]);
    $queues = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($queues as $queue) {
        $stmt = $pdo->prepare("DELETE FROM queue_members WHERE queue_name = ? AND interface LIKE ?");
        $stmt->execute([$queue, "%$extension%"]);
    }
}

function addToDepartmentQueues($pdo, $extension, $departmentId) {
    // Get department queues
    $stmt = $pdo->prepare("SELECT queue_name FROM department_queues WHERE department_id = ?");
    $stmt->execute([$departmentId]);
    $queues = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($queues as $queue) {
        $stmt = $pdo->prepare("INSERT INTO queue_members (queue_name, interface, membername, penalty) VALUES (?, ?, ?, 0)");
        $stmt->execute([$queue, "PJSIP/$extension", "Extension $extension"]);
    }
}

function migrateVoicemail($oldExt, $newExt) {
    $vmDir = '/var/spool/asterisk/voicemail/flexpbx';
    $oldDir = "$vmDir/$oldExt";
    $newDir = "$vmDir/$newExt";
    
    if (is_dir($oldDir)) {
        shell_exec("mv $oldDir $newDir 2>&1");
        
        // Update voicemail.conf
        $vmConf = '/etc/asterisk/voicemail.conf';
        if (file_exists($vmConf)) {
            $content = file_get_contents($vmConf);
            $content = preg_replace("/^$oldExt\s*=>/m", "$newExt =>", $content);
            file_put_contents($vmConf, $content);
        }
    }
}

function getDepartmentName($pdo, $deptId) {
    if (!$deptId) return 'None';
    $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
    $stmt->execute([$deptId]);
    return $stmt->fetchColumn() ?: 'Unknown';
}

function logMigration($pdo, $userId, $oldExt, $newExt, $oldDept, $newDept, $reason, $changes) {
    $stmt = $pdo->prepare("
        INSERT INTO migration_history 
        (user_id, old_extension, new_extension, old_department_id, new_department_id, reason, changes, admin_user, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $userId, $oldExt, $newExt, $oldDept, $newDept, $reason,
        json_encode($changes), $_SESSION['admin_username'] ?? 'system'
    ]);
}

function sendMigrationNotification($user, $oldExt, $newExt, $changes) {
    $subject = "FlexPBX Extension Update - Action Required";
    $message = "Hello {$user['name']},\n\n";
    $message .= "Your FlexPBX account has been updated:\n\n";
    $message .= implode("\n", $changes) . "\n\n";
    
    if ($oldExt != $newExt) {
        $message .= "IMPORTANT: Your extension number has changed from $oldExt to $newExt\n\n";
        $message .= "Action Required:\n";
        $message .= "1. Update your third-party SIP clients (softphones, desk phones) with new extension: $newExt\n";
        $message .= "2. Your FlexPhone web client has been automatically updated - no action needed\n";
        $message .= "3. Your user portal now shows your new extension - no action needed\n\n";
    } else {
        $message .= "Your extension number ($oldExt) remains unchanged.\n";
        $message .= "Your SIP clients will continue to work normally.\n\n";
    }
    
    $message .= "If you have any questions, please contact support.\n\n";
    $message .= "Best regards,\nFlexPBX Admin Team";
    
    mail($user['email'], $subject, $message, "From: noreply@flexpbx.devinecreations.net");
}

function sendExtensionChangeNotification($user, $oldExt, $newExt) {
    sendMigrationNotification($user, $oldExt, $newExt, ["Extension changed from $oldExt to $newExt"]);
}

function reloadAsteriskConfig() {
    // Reload PJSIP
    shell_exec('asterisk -rx "pjsip reload" 2>&1');
    
    // Reload queues
    shell_exec('asterisk -rx "queue reload all" 2>&1');
    
    // Reload voicemail
    shell_exec('asterisk -rx "voicemail reload" 2>&1');
}
