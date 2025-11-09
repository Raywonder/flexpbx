<?php
/**
 * Forwarded Numbers Management API
 * Allows users to manage external numbers forwarded to their extension
 */

session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['username']) || !isset($_SESSION['account_type'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$username = $_SESSION['username'];
$account_type = $_SESSION['account_type'];

// Determine which extension to manage
$extension = null;
if ($account_type === 'user') {
    // Users can only manage their own extension
    $user_file = "/home/flexpbxuser/users/user_{$username}.json";
    if (file_exists($user_file)) {
        $user_data = json_decode(file_get_contents($user_file), true);
        $extension = $user_data['extension'];
    }
} elseif ($account_type === 'admin') {
    // Admins can manage any extension (specified via parameter) or their linked extension
    if (isset($_GET['extension'])) {
        $extension = preg_replace('/[^0-9]/', '', $_GET['extension']);
    } else {
        // Check if admin has linked extension
        $admin_file = "/home/flexpbxuser/admins/admin_{$username}.json";
        if (file_exists($admin_file)) {
            $admin_data = json_decode(file_get_contents($admin_file), true);
            if (isset($admin_data['linked_extension'])) {
                $extension = $admin_data['linked_extension'];
            }
        }
    }
}

if (!$extension) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No extension available']);
    exit;
}

$user_file = "/home/flexpbxuser/users/user_{$extension}.json";

// Handle different request methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get forwarded numbers for extension
        if (file_exists($user_file)) {
            $user_data = json_decode(file_get_contents($user_file), true);
            $forwarded_numbers = $user_data['forwarded_numbers'] ?? [];
            echo json_encode([
                'success' => true,
                'extension' => $extension,
                'forwarded_numbers' => $forwarded_numbers
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Extension not found']);
        }
        break;

    case 'POST':
        // Add new forwarded number
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['number']) || empty(trim($input['number']))) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Number is required']);
            exit;
        }

        $number = preg_replace('/[^0-9]/', '', $input['number']);
        $description = htmlspecialchars(trim($input['description'] ?? ''), ENT_QUOTES, 'UTF-8');
        $ring_time = intval($input['ring_time'] ?? 30);
        $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : true;

        if (strlen($number) < 10) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid phone number']);
            exit;
        }

        if ($ring_time < 5 || $ring_time > 300) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ring time must be between 5 and 300 seconds']);
            exit;
        }

        if (file_exists($user_file)) {
            $user_data = json_decode(file_get_contents($user_file), true);

            // Initialize forwarded_numbers if not exists
            if (!isset($user_data['forwarded_numbers'])) {
                $user_data['forwarded_numbers'] = [];
            }

            // Check if number already exists
            foreach ($user_data['forwarded_numbers'] as $existing) {
                if ($existing['number'] === $number) {
                    http_response_code(409);
                    echo json_encode(['success' => false, 'error' => 'Number already exists']);
                    exit;
                }
            }

            // Add new forwarded number
            $user_data['forwarded_numbers'][] = [
                'number' => $number,
                'description' => $description,
                'ring_time' => $ring_time,
                'enabled' => $enabled,
                'added_date' => date('Y-m-d H:i:s')
            ];

            $user_data['updated_at'] = date('Y-m-d H:i:s');

            if (file_put_contents($user_file, json_encode($user_data, JSON_PRETTY_PRINT))) {
                chmod($user_file, 0640);
                echo json_encode([
                    'success' => true,
                    'message' => 'Forwarded number added successfully',
                    'forwarded_numbers' => $user_data['forwarded_numbers']
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to save changes']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Extension not found']);
        }
        break;

    case 'PUT':
        // Update existing forwarded number
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['number']) || empty(trim($input['number']))) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Number is required']);
            exit;
        }

        $number = preg_replace('/[^0-9]/', '', $input['number']);

        if (file_exists($user_file)) {
            $user_data = json_decode(file_get_contents($user_file), true);

            if (!isset($user_data['forwarded_numbers'])) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Number not found']);
                exit;
            }

            $found = false;
            foreach ($user_data['forwarded_numbers'] as $index => $existing) {
                if ($existing['number'] === $number) {
                    // Update fields if provided
                    if (isset($input['description'])) {
                        $user_data['forwarded_numbers'][$index]['description'] = htmlspecialchars(trim($input['description']), ENT_QUOTES, 'UTF-8');
                    }
                    if (isset($input['ring_time'])) {
                        $ring_time = intval($input['ring_time']);
                        if ($ring_time < 5 || $ring_time > 300) {
                            http_response_code(400);
                            echo json_encode(['success' => false, 'error' => 'Ring time must be between 5 and 300 seconds']);
                            exit;
                        }
                        $user_data['forwarded_numbers'][$index]['ring_time'] = $ring_time;
                    }
                    if (isset($input['enabled'])) {
                        $user_data['forwarded_numbers'][$index]['enabled'] = (bool)$input['enabled'];
                    }
                    $user_data['forwarded_numbers'][$index]['updated_date'] = date('Y-m-d H:i:s');
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Number not found']);
                exit;
            }

            $user_data['updated_at'] = date('Y-m-d H:i:s');

            if (file_put_contents($user_file, json_encode($user_data, JSON_PRETTY_PRINT))) {
                chmod($user_file, 0640);
                echo json_encode([
                    'success' => true,
                    'message' => 'Forwarded number updated successfully',
                    'forwarded_numbers' => $user_data['forwarded_numbers']
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to save changes']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Extension not found']);
        }
        break;

    case 'DELETE':
        // Remove forwarded number
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['number']) || empty(trim($input['number']))) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Number is required']);
            exit;
        }

        $number = preg_replace('/[^0-9]/', '', $input['number']);

        if (file_exists($user_file)) {
            $user_data = json_decode(file_get_contents($user_file), true);

            if (!isset($user_data['forwarded_numbers'])) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Number not found']);
                exit;
            }

            $original_count = count($user_data['forwarded_numbers']);
            $user_data['forwarded_numbers'] = array_values(array_filter(
                $user_data['forwarded_numbers'],
                function($item) use ($number) {
                    return $item['number'] !== $number;
                }
            ));

            if (count($user_data['forwarded_numbers']) === $original_count) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Number not found']);
                exit;
            }

            $user_data['updated_at'] = date('Y-m-d H:i:s');

            if (file_put_contents($user_file, json_encode($user_data, JSON_PRETTY_PRINT))) {
                chmod($user_file, 0640);
                echo json_encode([
                    'success' => true,
                    'message' => 'Forwarded number removed successfully',
                    'forwarded_numbers' => $user_data['forwarded_numbers']
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to save changes']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Extension not found']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        break;
}
