<?php
/**
 * FlexPBX Module Repository API v1.0
 * Public endpoint for distributing modules to fresh installations
 *
 * Endpoints:
 * - GET ?action=list - Lists all available modules
 * - GET ?action=download&module=MODULE_NAME - Downloads module package
 * - GET ?action=info&module=MODULE_NAME - Gets detailed module information
 * - GET ?action=categories - Lists all module categories
 * - GET ?action=search&q=QUERY - Searches modules
 */

// Repository configuration
define('REPOSITORY_PATH', '/home/flexpbxuser/module-repository');
define('MANIFEST_FILE', REPOSITORY_PATH . '/manifests/registry.json');
define('PACKAGES_PATH', REPOSITORY_PATH . '/packages');

// Helper function to load registry
function loadRegistry() {
    if (!file_exists(MANIFEST_FILE)) {
        return [
            'success' => false,
            'error' => 'Registry file not found',
            'modules' => []
        ];
    }

    $content = file_get_contents(MANIFEST_FILE);
    $registry = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'Invalid registry format',
            'modules' => []
        ];
    }

    return $registry;
}

// Helper function to find module by key
function findModule($module_key) {
    $registry = loadRegistry();

    if (!isset($registry['modules'])) {
        return null;
    }

    foreach ($registry['modules'] as $module) {
        if ($module['module_key'] === $module_key) {
            return $module;
        }
    }

    return null;
}

// Helper function to validate module exists
function validateModule($module_key) {
    $module = findModule($module_key);

    if (!$module) {
        return [
            'valid' => false,
            'error' => 'Module not found in registry'
        ];
    }

    $package_path = REPOSITORY_PATH . '/' . $module['package_file'];

    if (!file_exists($package_path)) {
        return [
            'valid' => false,
            'error' => 'Module package file not found',
            'expected_path' => $package_path
        ];
    }

    return [
        'valid' => true,
        'module' => $module,
        'package_path' => $package_path
    ];
}

// Get action from query parameter
$action = $_GET['action'] ?? 'list';

// Handle different actions
switch ($action) {

    case 'list':
        // List all available modules
        header('Content-Type: application/json');

        $registry = loadRegistry();

        if (!isset($registry['modules'])) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to load module registry'
            ]);
            exit;
        }

        // Optional filtering by category
        $category = $_GET['category'] ?? null;
        $modules = $registry['modules'];

        if ($category) {
            $modules = array_filter($modules, function($mod) use ($category) {
                return $mod['category'] === $category;
            });
            $modules = array_values($modules); // Re-index array
        }

        // Return module list
        echo json_encode([
            'success' => true,
            'repository_version' => $registry['repository_version'] ?? '1.0.0',
            'last_updated' => $registry['last_updated'] ?? date('c'),
            'count' => count($modules),
            'modules' => $modules
        ], JSON_PRETTY_PRINT);
        break;

    case 'info':
        // Get detailed information about a specific module
        header('Content-Type: application/json');

        $module_key = $_GET['module'] ?? null;

        if (!$module_key) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Module parameter required'
            ]);
            exit;
        }

        $module = findModule($module_key);

        if (!$module) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Module not found',
                'module_key' => $module_key
            ]);
            exit;
        }

        // Add additional metadata
        $package_path = REPOSITORY_PATH . '/' . $module['package_file'];
        $module['package_exists'] = file_exists($package_path);

        if ($module['package_exists']) {
            $module['actual_size'] = filesize($package_path);
            $module['last_modified'] = date('c', filemtime($package_path));
        }

        echo json_encode([
            'success' => true,
            'module' => $module
        ], JSON_PRETTY_PRINT);
        break;

    case 'download':
        // Download a module package
        $module_key = $_GET['module'] ?? null;

        if (!$module_key) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Module parameter required'
            ]);
            exit;
        }

        $validation = validateModule($module_key);

        if (!$validation['valid']) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $validation['error'],
                'module_key' => $module_key
            ]);
            exit;
        }

        $module = $validation['module'];
        $package_path = $validation['package_path'];

        // Serve the file
        $filename = basename($package_path);
        $filesize = filesize($package_path);

        // Set headers for file download
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
        header('Expires: 0');

        // Output file
        readfile($package_path);
        exit;

    case 'categories':
        // List all available categories
        header('Content-Type: application/json');

        $registry = loadRegistry();

        if (!isset($registry['modules'])) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to load module registry'
            ]);
            exit;
        }

        $categories = [];
        foreach ($registry['modules'] as $module) {
            $cat = $module['category'];
            if (!isset($categories[$cat])) {
                $categories[$cat] = [
                    'name' => $cat,
                    'count' => 0,
                    'modules' => []
                ];
            }
            $categories[$cat]['count']++;
            $categories[$cat]['modules'][] = $module['module_key'];
        }

        echo json_encode([
            'success' => true,
            'categories' => array_values($categories)
        ], JSON_PRETTY_PRINT);
        break;

    case 'search':
        // Search modules
        header('Content-Type: application/json');

        $query = $_GET['q'] ?? '';

        if (empty($query)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Search query required (q parameter)'
            ]);
            exit;
        }

        $registry = loadRegistry();

        if (!isset($registry['modules'])) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to load module registry'
            ]);
            exit;
        }

        $query_lower = strtolower($query);
        $results = [];

        foreach ($registry['modules'] as $module) {
            // Search in name, description, and features
            $searchable = strtolower(
                $module['module_name'] . ' ' .
                $module['description'] . ' ' .
                implode(' ', $module['features'] ?? [])
            );

            if (strpos($searchable, $query_lower) !== false) {
                $results[] = $module;
            }
        }

        echo json_encode([
            'success' => true,
            'query' => $query,
            'count' => count($results),
            'modules' => $results
        ], JSON_PRETTY_PRINT);
        break;

    case 'verify':
        // Verify module package integrity
        header('Content-Type: application/json');

        $module_key = $_GET['module'] ?? null;

        if (!$module_key) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Module parameter required'
            ]);
            exit;
        }

        $validation = validateModule($module_key);

        if (!$validation['valid']) {
            echo json_encode([
                'success' => false,
                'valid' => false,
                'error' => $validation['error']
            ]);
            exit;
        }

        $package_path = $validation['package_path'];

        echo json_encode([
            'success' => true,
            'valid' => true,
            'module_key' => $module_key,
            'package_exists' => true,
            'package_size' => filesize($package_path),
            'package_md5' => md5_file($package_path),
            'package_sha256' => hash_file('sha256', $package_path)
        ], JSON_PRETTY_PRINT);
        break;

    default:
        // Invalid action
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action',
            'available_actions' => [
                'list' => 'List all available modules',
                'info' => 'Get detailed module information (requires module parameter)',
                'download' => 'Download module package (requires module parameter)',
                'categories' => 'List all module categories',
                'search' => 'Search modules (requires q parameter)',
                'verify' => 'Verify module package integrity (requires module parameter)'
            ],
            'examples' => [
                'List all modules' => '/api/module-repository.php?action=list',
                'List by category' => '/api/module-repository.php?action=list&category=core',
                'Get module info' => '/api/module-repository.php?action=info&module=bug-tracker',
                'Download module' => '/api/module-repository.php?action=download&module=bug-tracker',
                'Search modules' => '/api/module-repository.php?action=search&q=analytics',
                'List categories' => '/api/module-repository.php?action=categories',
                'Verify package' => '/api/module-repository.php?action=verify&module=bug-tracker'
            ]
        ], JSON_PRETTY_PRINT);
        break;
}
