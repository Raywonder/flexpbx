<?php
/**
 * FlexPBX Contact Management & Caller ID API
 *
 * Endpoints:
 * - contacts: Get all contacts
 * - contact: Get single contact by ID
 * - create: Create new contact
 * - update: Update contact
 * - delete: Delete contact
 * - search: Search contacts
 * - lookup: Caller ID lookup by phone number
 * - recent-calls: Get recent call history
 * - blacklist: Manage blacklist
 * - groups: Manage contact groups
 * - import: Import contacts (CSV/vCard)
 * - export: Export contacts
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load database configuration
$config_file = __DIR__ . '/config.php';
if (file_exists($config_file)) {
    $config = require $config_file;
    $db_host = $config['db_host'];
    $db_name = $config['db_name'];
    $db_user = $config['db_user'];
    $db_pass = $config['db_password'];
} else {
    $db_host = 'localhost';
    $db_name = 'flexpbx';
    $db_user = 'root';
    $db_pass = '';
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed', 'details' => $e->getMessage()]);
    exit;
}

$path = $_GET['path'] ?? 'contacts';
$method = $_SERVER['REQUEST_METHOD'];

// Route to appropriate handler
switch ($path) {
    case 'contacts':
        handleContacts($pdo, $method);
        break;
    case 'contact':
        handleContact($pdo, $method);
        break;
    case 'create':
        createContact($pdo);
        break;
    case 'update':
        updateContact($pdo);
        break;
    case 'delete':
        deleteContact($pdo);
        break;
    case 'search':
        searchContacts($pdo);
        break;
    case 'lookup':
        lookupCallerID($pdo);
        break;
    case 'recent-calls':
        getRecentCalls($pdo);
        break;
    case 'blacklist':
        handleBlacklist($pdo, $method);
        break;
    case 'whitelist':
        handleWhitelist($pdo, $method);
        break;
    case 'groups':
        handleGroups($pdo, $method);
        break;
    case 'import':
        importContacts($pdo);
        break;
    case 'export':
        exportContacts($pdo);
        break;
    case 'stats':
        getContactStats($pdo);
        break;
    case 'speed-dial':
        handleSpeedDial($pdo, $method);
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

/**
 * Get all contacts
 */
function handleContacts($pdo, $method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $extension = $_GET['extension'] ?? null;
    $type = $_GET['type'] ?? null;
    $favorites = $_GET['favorites'] ?? null;
    $limit = (int)($_GET['limit'] ?? 100);
    $offset = (int)($_GET['offset'] ?? 0);

    $query = "SELECT c.*,
              (SELECT COUNT(*) FROM contact_phone_numbers WHERE contact_id = c.id) as phone_count,
              (SELECT COUNT(*) FROM call_history WHERE contact_id = c.id) as total_calls
              FROM contacts c WHERE 1=1";

    $params = [];

    if ($extension) {
        $query .= " AND (c.owner_extension = ? OR c.shared = 1)";
        $params[] = $extension;
    }

    if ($type) {
        $query .= " AND c.contact_type = ?";
        $params[] = $type;
    }

    if ($favorites) {
        $query .= " AND c.favorite = 1";
    }

    $query .= " ORDER BY c.last_name, c.first_name LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get phone numbers for each contact
    foreach ($contacts as &$contact) {
        $stmt = $pdo->prepare("SELECT * FROM contact_phone_numbers WHERE contact_id = ?");
        $stmt->execute([$contact['id']]);
        $contact['phone_numbers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['success' => true, 'contacts' => $contacts, 'count' => count($contacts)]);
}

/**
 * Get single contact
 */
function handleContact($pdo, $method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Contact ID required']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
    $stmt->execute([$id]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contact) {
        http_response_code(404);
        echo json_encode(['error' => 'Contact not found']);
        return;
    }

    // Get phone numbers
    $stmt = $pdo->prepare("SELECT * FROM contact_phone_numbers WHERE contact_id = ?");
    $stmt->execute([$id]);
    $contact['phone_numbers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent calls
    $stmt = $pdo->prepare("SELECT * FROM call_history WHERE contact_id = ? ORDER BY call_date DESC LIMIT 10");
    $stmt->execute([$id]);
    $contact['recent_calls'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'contact' => $contact]);
}

/**
 * Create new contact
 */
function createContact($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input']);
        return;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO contacts (
                contact_type, first_name, last_name, company, title, department,
                primary_phone, mobile_phone, work_phone, home_phone, fax_phone, other_phone,
                email, website, address_line1, address_line2, city, state, zip, country,
                notes, tags, photo_url, speed_dial, ring_tone, owner_extension, shared, favorite
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $input['contact_type'] ?? 'personal',
            $input['first_name'] ?? '',
            $input['last_name'] ?? '',
            $input['company'] ?? null,
            $input['title'] ?? null,
            $input['department'] ?? null,
            $input['primary_phone'] ?? null,
            $input['mobile_phone'] ?? null,
            $input['work_phone'] ?? null,
            $input['home_phone'] ?? null,
            $input['fax_phone'] ?? null,
            $input['other_phone'] ?? null,
            $input['email'] ?? null,
            $input['website'] ?? null,
            $input['address_line1'] ?? null,
            $input['address_line2'] ?? null,
            $input['city'] ?? null,
            $input['state'] ?? null,
            $input['zip'] ?? null,
            $input['country'] ?? 'USA',
            $input['notes'] ?? null,
            $input['tags'] ?? null,
            $input['photo_url'] ?? null,
            $input['speed_dial'] ?? null,
            $input['ring_tone'] ?? null,
            $input['owner_extension'] ?? null,
            $input['shared'] ?? 0,
            $input['favorite'] ?? 0
        ]);

        $contactId = $pdo->lastInsertId();

        // Insert phone numbers into normalized table
        $phoneFields = ['primary_phone', 'mobile_phone', 'work_phone', 'home_phone', 'fax_phone', 'other_phone'];
        foreach ($phoneFields as $field) {
            if (!empty($input[$field])) {
                $phoneType = str_replace('_phone', '', $field);
                $stmt = $pdo->prepare("
                    INSERT INTO contact_phone_numbers (contact_id, phone_number, phone_type, is_primary)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $contactId,
                    normalizePhone($input[$field]),
                    $phoneType,
                    $phoneType === 'primary' ? 1 : 0
                ]);
            }
        }

        $pdo->commit();

        echo json_encode(['success' => true, 'contact_id' => $contactId, 'message' => 'Contact created successfully']);

    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create contact', 'details' => $e->getMessage()]);
    }
}

/**
 * Update contact
 */
function updateContact($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Contact ID required']);
        return;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE contacts SET
                contact_type = ?, first_name = ?, last_name = ?, company = ?, title = ?, department = ?,
                primary_phone = ?, mobile_phone = ?, work_phone = ?, home_phone = ?, fax_phone = ?, other_phone = ?,
                email = ?, website = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, zip = ?, country = ?,
                notes = ?, tags = ?, photo_url = ?, speed_dial = ?, ring_tone = ?, shared = ?, favorite = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([
            $input['contact_type'] ?? 'personal',
            $input['first_name'] ?? '',
            $input['last_name'] ?? '',
            $input['company'] ?? null,
            $input['title'] ?? null,
            $input['department'] ?? null,
            $input['primary_phone'] ?? null,
            $input['mobile_phone'] ?? null,
            $input['work_phone'] ?? null,
            $input['home_phone'] ?? null,
            $input['fax_phone'] ?? null,
            $input['other_phone'] ?? null,
            $input['email'] ?? null,
            $input['website'] ?? null,
            $input['address_line1'] ?? null,
            $input['address_line2'] ?? null,
            $input['city'] ?? null,
            $input['state'] ?? null,
            $input['zip'] ?? null,
            $input['country'] ?? 'USA',
            $input['notes'] ?? null,
            $input['tags'] ?? null,
            $input['photo_url'] ?? null,
            $input['speed_dial'] ?? null,
            $input['ring_tone'] ?? null,
            $input['shared'] ?? 0,
            $input['favorite'] ?? 0,
            $input['id']
        ]);

        // Update phone numbers
        $pdo->prepare("DELETE FROM contact_phone_numbers WHERE contact_id = ?")->execute([$input['id']]);

        $phoneFields = ['primary_phone', 'mobile_phone', 'work_phone', 'home_phone', 'fax_phone', 'other_phone'];
        foreach ($phoneFields as $field) {
            if (!empty($input[$field])) {
                $phoneType = str_replace('_phone', '', $field);
                $stmt = $pdo->prepare("
                    INSERT INTO contact_phone_numbers (contact_id, phone_number, phone_type, is_primary)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $input['id'],
                    normalizePhone($input[$field]),
                    $phoneType,
                    $phoneType === 'primary' ? 1 : 0
                ]);
            }
        }

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Contact updated successfully']);

    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update contact', 'details' => $e->getMessage()]);
    }
}

/**
 * Delete contact
 */
function deleteContact($pdo) {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Contact ID required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Contact deleted successfully']);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete contact', 'details' => $e->getMessage()]);
    }
}

/**
 * Search contacts
 */
function searchContacts($pdo) {
    $query = $_GET['q'] ?? '';
    $extension = $_GET['extension'] ?? null;

    if (strlen($query) < 2) {
        echo json_encode(['success' => true, 'contacts' => []]);
        return;
    }

    $sql = "SELECT c.*,
            (SELECT COUNT(*) FROM call_history WHERE contact_id = c.id) as total_calls
            FROM contacts c
            WHERE (c.first_name LIKE ? OR c.last_name LIKE ? OR c.company LIKE ?
                   OR c.primary_phone LIKE ? OR c.mobile_phone LIKE ? OR c.email LIKE ?)";

    $params = array_fill(0, 6, "%$query%");

    if ($extension) {
        $sql .= " AND (c.owner_extension = ? OR c.shared = 1)";
        $params[] = $extension;
    }

    $sql .= " ORDER BY c.last_name, c.first_name LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'contacts' => $contacts, 'count' => count($contacts)]);
}

/**
 * Caller ID lookup by phone number
 */
function lookupCallerID($pdo) {
    $phoneNumber = $_GET['number'] ?? '';

    if (empty($phoneNumber)) {
        http_response_code(400);
        echo json_encode(['error' => 'Phone number required']);
        return;
    }

    $normalized = normalizePhone($phoneNumber);

    // Check cache first
    $stmt = $pdo->prepare("SELECT * FROM callerid_cache WHERE phone_number = ? AND (expires_at IS NULL OR expires_at > NOW())");
    $stmt->execute([$normalized]);
    $cached = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cached) {
        // Update lookup stats
        $pdo->prepare("UPDATE callerid_cache SET lookup_count = lookup_count + 1 WHERE id = ?")->execute([$cached['id']]);

        echo json_encode([
            'success' => true,
            'caller_name' => $cached['caller_name'],
            'phone_number' => $phoneNumber,
            'source' => $cached['source'],
            'cached' => true
        ]);
        return;
    }

    // Look up in contacts
    $stmt = $pdo->prepare("
        SELECT c.*, cpn.phone_type
        FROM contacts c
        JOIN contact_phone_numbers cpn ON c.id = cpn.contact_id
        WHERE cpn.phone_number = ? AND c.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$normalized]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($contact) {
        $callerName = trim($contact['first_name'] . ' ' . $contact['last_name']);
        if (!empty($contact['company'])) {
            $callerName .= ' (' . $contact['company'] . ')';
        }

        // Cache the result
        $stmt = $pdo->prepare("
            INSERT INTO callerid_cache (phone_number, caller_name, source, verified, last_verified, lookup_count)
            VALUES (?, ?, 'contact', 1, NOW(), 1)
            ON DUPLICATE KEY UPDATE caller_name = ?, lookup_count = lookup_count + 1, last_lookup = NOW()
        ");
        $stmt->execute([$normalized, $callerName, $callerName]);

        echo json_encode([
            'success' => true,
            'caller_name' => $callerName,
            'phone_number' => $phoneNumber,
            'contact_id' => $contact['id'],
            'contact' => $contact,
            'source' => 'contact',
            'cached' => false
        ]);
        return;
    }

    // No match found
    echo json_encode([
        'success' => true,
        'caller_name' => null,
        'phone_number' => $phoneNumber,
        'source' => 'unknown',
        'cached' => false
    ]);
}

/**
 * Get recent call history
 */
function getRecentCalls($pdo) {
    $extension = $_GET['extension'] ?? null;
    $limit = (int)($_GET['limit'] ?? 50);
    $type = $_GET['type'] ?? null;

    $query = "SELECT ch.*, c.first_name, c.last_name, c.company
              FROM call_history ch
              LEFT JOIN contacts c ON ch.contact_id = c.id
              WHERE 1=1";

    $params = [];

    if ($extension) {
        $query .= " AND ch.destination_extension = ?";
        $params[] = $extension;
    }

    if ($type) {
        $query .= " AND ch.call_type = ?";
        $params[] = $type;
    }

    $query .= " ORDER BY ch.call_date DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $calls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'calls' => $calls, 'count' => count($calls)]);
}

/**
 * Handle blacklist
 */
function handleBlacklist($pdo, $method) {
    if ($method === 'GET') {
        $extension = $_GET['extension'] ?? null;

        $query = "SELECT * FROM call_screening WHERE screening_type = 'blacklist'";
        $params = [];

        if ($extension) {
            $query .= " AND (apply_to_extension = ? OR apply_globally = 1)";
            $params[] = $extension;
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $blacklist = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'blacklist' => $blacklist]);

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        $stmt = $pdo->prepare("
            INSERT INTO call_screening (phone_number, screening_type, reason, action, apply_to_extension, apply_globally)
            VALUES (?, 'blacklist', ?, ?, ?, ?)
        ");
        $stmt->execute([
            normalizePhone($input['phone_number']),
            $input['reason'] ?? 'Blocked',
            $input['action'] ?? 'block',
            $input['apply_to_extension'] ?? null,
            $input['apply_globally'] ?? 0
        ]);

        echo json_encode(['success' => true, 'message' => 'Number added to blacklist']);

    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;

        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM call_screening WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Entry removed from blacklist']);
        }
    }
}

/**
 * Handle whitelist
 */
function handleWhitelist($pdo, $method) {
    if ($method === 'GET') {
        $extension = $_GET['extension'] ?? null;

        $query = "SELECT * FROM call_screening WHERE screening_type = 'whitelist'";
        $params = [];

        if ($extension) {
            $query .= " AND (apply_to_extension = ? OR apply_globally = 1)";
            $params[] = $extension;
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $whitelist = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'whitelist' => $whitelist]);

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        $stmt = $pdo->prepare("
            INSERT INTO call_screening (phone_number, screening_type, reason, action, apply_to_extension, apply_globally)
            VALUES (?, 'whitelist', ?, 'allow', ?, ?)
        ");
        $stmt->execute([
            normalizePhone($input['phone_number']),
            $input['reason'] ?? 'Always allow',
            $input['apply_to_extension'] ?? null,
            $input['apply_globally'] ?? 0
        ]);

        echo json_encode(['success' => true, 'message' => 'Number added to whitelist']);
    }
}

/**
 * Handle contact groups
 */
function handleGroups($pdo, $method) {
    if ($method === 'GET') {
        $extension = $_GET['extension'] ?? null;

        $query = "SELECT g.*, COUNT(cgm.contact_id) as member_count
                  FROM contact_groups g
                  LEFT JOIN contact_group_members cgm ON g.id = cgm.group_id
                  WHERE 1=1";

        $params = [];

        if ($extension) {
            $query .= " AND (g.owner_extension = ? OR g.shared = 1)";
            $params[] = $extension;
        }

        $query .= " GROUP BY g.id ORDER BY g.group_name";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'groups' => $groups]);

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        $stmt = $pdo->prepare("
            INSERT INTO contact_groups (group_name, description, owner_extension, shared)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $input['group_name'],
            $input['description'] ?? null,
            $input['owner_extension'] ?? null,
            $input['shared'] ?? 0
        ]);

        echo json_encode(['success' => true, 'group_id' => $pdo->lastInsertId()]);
    }
}

/**
 * Get contact statistics
 */
function getContactStats($pdo) {
    $extension = $_GET['extension'] ?? null;

    $query = "SELECT
              COUNT(*) as total_contacts,
              COUNT(CASE WHEN contact_type = 'business' THEN 1 END) as business_contacts,
              COUNT(CASE WHEN favorite = 1 THEN 1 END) as favorite_contacts,
              COUNT(CASE WHEN status = 'blocked' THEN 1 END) as blocked_contacts
              FROM contacts WHERE 1=1";

    $params = [];

    if ($extension) {
        $query .= " AND (owner_extension = ? OR shared = 1)";
        $params[] = $extension;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get call stats
    $callQuery = "SELECT
                  COUNT(*) as total_calls,
                  COUNT(CASE WHEN call_type = 'inbound' THEN 1 END) as inbound_calls,
                  COUNT(CASE WHEN call_type = 'outbound' THEN 1 END) as outbound_calls,
                  COUNT(CASE WHEN call_type = 'missed' THEN 1 END) as missed_calls
                  FROM call_history WHERE 1=1";

    if ($extension) {
        $callQuery .= " AND destination_extension = ?";
    }

    $stmt = $pdo->prepare($callQuery);
    $stmt->execute($extension ? [$extension] : []);
    $callStats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'contacts' => $stats,
        'calls' => $callStats
    ]);
}

/**
 * Handle speed dial
 */
function handleSpeedDial($pdo, $method) {
    if ($method === 'GET') {
        $extension = $_GET['extension'] ?? null;

        $query = "SELECT * FROM contacts WHERE speed_dial IS NOT NULL";
        $params = [];

        if ($extension) {
            $query .= " AND owner_extension = ?";
            $params[] = $extension;
        }

        $query .= " ORDER BY speed_dial";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $speedDials = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'speed_dials' => $speedDials]);
    }
}

/**
 * Normalize phone number (remove formatting)
 */
function normalizePhone($phone) {
    return preg_replace('/[^0-9]/', '', $phone);
}

/**
 * Format phone number
 */
function formatPhone($phone) {
    $phone = normalizePhone($phone);

    if (strlen($phone) === 10) {
        return sprintf("(%s) %s-%s", substr($phone, 0, 3), substr($phone, 3, 3), substr($phone, 6));
    } elseif (strlen($phone) === 11 && substr($phone, 0, 1) === '1') {
        return sprintf("+1 (%s) %s-%s", substr($phone, 1, 3), substr($phone, 4, 3), substr($phone, 7));
    }

    return $phone;
}
