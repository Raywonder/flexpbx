<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX Module Manager
 * Admin interface for browsing, installing, and managing FlexPBX modules
 */

session_start();

// Get API base URL
$api_base = 'https://flexpbx.devinecreations.net/api/modules.php';
$api_key = $_SESSION['api_key'] ?? null; // From session if authenticated

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $module_key = $_POST['module_key'] ?? '';

    // Build API request
    $headers = ['Content-Type: application/json'];
    if ($api_key) {
        $headers[] = 'X-API-KEY: ' . $api_key;
    }

    switch ($action) {
        case 'install':
            $url = $api_base . '?path=install';
            $data = json_encode([
                'module_key' => $module_key,
                'config' => json_decode($_POST['config'] ?? '{}', true)
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);
            if ($http_code === 200 && isset($result['success']) && $result['success']) {
                $success_message = 'Module installed successfully!';
            } else {
                $error_message = $result['error'] ?? 'Failed to install module';
            }
            break;

        case 'enable':
        case 'disable':
            $url = $api_base . '?path=' . $action;
            $data = json_encode(['module_key' => $module_key]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);
            if ($http_code === 200 && isset($result['success']) && $result['success']) {
                $success_message = 'Module ' . ($action === 'enable' ? 'enabled' : 'disabled') . ' successfully!';
            } else {
                $error_message = $result['error'] ?? 'Failed to ' . $action . ' module';
            }
            break;
    }

    // Redirect to clear POST data
    header('Location: modules.php?success=' . urlencode($success_message) . '&error=' . urlencode($error_message));
    exit;
}

// Get success/error from redirect
if (isset($_GET['success']) && $_GET['success']) {
    $success_message = $_GET['success'];
}
if (isset($_GET['error']) && $_GET['error']) {
    $error_message = $_GET['error'];
}

// Fetch available modules
$modules_url = $api_base . '?path=available';
$ch = curl_init($modules_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
if ($api_key) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-KEY: ' . $api_key]);
}
$response = curl_exec($ch);
curl_close($ch);

$modules_data = json_decode($response, true);
$modules = $modules_data['modules'] ?? [];

// Group modules by category
$grouped_modules = [
    'core' => [],
    'feature' => [],
    'integration' => [],
    'addon' => []
];

foreach ($modules as $module) {
    $category = $module['category'] ?? 'addon';
    $grouped_modules[$category][] = $module;
}

// Statistics
$total_modules = count($modules);
$installed_modules = count(array_filter($modules, fn($m) => $m['is_installed'] ?? false));
$enabled_modules = count(array_filter($modules, fn($m) => ($m['is_installed'] ?? false) && ($m['is_enabled'] ?? false)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module Manager - FlexPBX</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .stat-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-box .number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
        }
        .stat-box .label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .card h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .module-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        .module-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .module-card.required {
            border-left: 4px solid #dc3545;
            background: #fff5f5;
        }
        .module-card.installed {
            border-left: 4px solid #28a745;
        }
        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        .module-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        .module-version {
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            color: #666;
        }
        .module-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        .module-meta {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-core { background: #dc3545; color: white; }
        .badge-feature { background: #007bff; color: white; }
        .badge-integration { background: #28a745; color: white; }
        .badge-addon { background: #6c757d; color: white; }
        .badge-required { background: #ffc107; color: #333; }
        .badge-installed { background: #28a745; color: white; }
        .badge-enabled { background: #4ade80; color: white; }
        .badge-disabled { background: #6c757d; color: white; }
        .dependencies {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }
        .dependencies strong {
            color: #333;
        }
        .module-actions {
            display: flex;
            gap: 8px;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-danger { background: #dc3545; }
        .btn-secondary { background: #6c757d; }
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .back-link {
            color: #667eea;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 10px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .category-section {
            margin-bottom: 30px;
        }
        .category-title {
            font-size: 24px;
            color: white;
            font-weight: 600;
            margin-bottom: 15px;
            padding-left: 5px;
        }
        .category-icon {
            margin-right: 8px;
        }
        .search-box {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.html" class="back-link">← Back to Dashboard</a>

        <div class="header">
            <h1>📦 Module Manager</h1>
            <p style="color: #666; margin-top: 5px;">Browse, install, and manage FlexPBX modules</p>

            <div class="stats-grid">
                <div class="stat-box">
                    <div class="number"><?= $total_modules ?></div>
                    <div class="label">Available Modules</div>
                </div>
                <div class="stat-box">
                    <div class="number" style="color: #28a745;"><?= $installed_modules ?></div>
                    <div class="label">Installed</div>
                </div>
                <div class="stat-box">
                    <div class="number" style="color: #4ade80;"><?= $enabled_modules ?></div>
                    <div class="label">Enabled</div>
                </div>
                <div class="stat-box">
                    <div class="number" style="color: #dc3545;"><?= count($grouped_modules['core']) ?></div>
                    <div class="label">Core Modules</div>
                </div>
            </div>
        </div>

        <?php if ($success_message): ?>
        <div class="alert alert-success">
            ✓ <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-error">
            ⚠️ <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <?php if (!$api_key): ?>
        <div class="alert alert-error">
            ⚠️ No API key found in session. Some features may be limited. <a href="#" style="color: #721c24; text-decoration: underline;">Register your installation</a>
        </div>
        <?php endif; ?>

        <div class="card">
            <input type="text" class="search-box" id="searchBox" placeholder="🔍 Search modules by name, description, or category..." onkeyup="filterModules()">
        </div>

        <!-- Core Modules -->
        <?php if (!empty($grouped_modules['core'])): ?>
        <div class="category-section">
            <h2 class="category-title"><span class="category-icon">🔧</span>Core Modules (Required)</h2>
            <div class="module-grid" id="core-modules">
                <?php foreach ($grouped_modules['core'] as $module): ?>
                <div class="module-card required" data-module-name="<?= strtolower($module['module_name']) ?>" data-module-key="<?= $module['module_key'] ?>">
                    <div class="module-header">
                        <div class="module-title"><?= htmlspecialchars($module['module_name']) ?></div>
                        <div class="module-version">v<?= $module['version'] ?></div>
                    </div>
                    <div class="module-description"><?= htmlspecialchars($module['module_description']) ?></div>
                    <div class="module-meta">
                        <span class="badge badge-core">Core</span>
                        <span class="badge badge-required">Required</span>
                        <?php if ($module['is_installed']): ?>
                        <span class="badge badge-installed">Installed</span>
                        <?php if ($module['is_enabled']): ?>
                        <span class="badge badge-enabled">Enabled</span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($module['dependencies'])): ?>
                    <div class="dependencies">
                        <strong>Dependencies:</strong> <?= implode(', ', $module['dependencies']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="module-actions">
                        <?php if (!$module['is_installed']): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="install">
                            <input type="hidden" name="module_key" value="<?= $module['module_key'] ?>">
                            <button type="submit" class="btn btn-success btn-small" <?= !$api_key ? 'disabled title="API key required"' : '' ?>>
                                Install
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="btn btn-small" disabled>Cannot Remove</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Feature Modules -->
        <?php if (!empty($grouped_modules['feature'])): ?>
        <div class="category-section">
            <h2 class="category-title"><span class="category-icon">⭐</span>Feature Modules</h2>
            <div class="module-grid" id="feature-modules">
                <?php foreach ($grouped_modules['feature'] as $module): ?>
                <div class="module-card <?= $module['is_installed'] ? 'installed' : '' ?>" data-module-name="<?= strtolower($module['module_name']) ?>" data-module-key="<?= $module['module_key'] ?>">
                    <div class="module-header">
                        <div class="module-title"><?= htmlspecialchars($module['module_name']) ?></div>
                        <div class="module-version">v<?= $module['version'] ?></div>
                    </div>
                    <div class="module-description"><?= htmlspecialchars($module['module_description']) ?></div>
                    <div class="module-meta">
                        <span class="badge badge-feature">Feature</span>
                        <?php if ($module['is_installed']): ?>
                        <span class="badge badge-installed">Installed</span>
                        <?php if ($module['is_enabled']): ?>
                        <span class="badge badge-enabled">Enabled</span>
                        <?php else: ?>
                        <span class="badge badge-disabled">Disabled</span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($module['dependencies'])): ?>
                    <div class="dependencies">
                        <strong>Dependencies:</strong> <?= implode(', ', $module['dependencies']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="module-actions">
                        <?php if (!$module['is_installed']): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="install">
                            <input type="hidden" name="module_key" value="<?= $module['module_key'] ?>">
                            <button type="submit" class="btn btn-success btn-small" <?= !$api_key ? 'disabled title="API key required"' : '' ?>>
                                Install
                            </button>
                        </form>
                        <?php else: ?>
                        <?php if ($module['is_enabled']): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="disable">
                            <input type="hidden" name="module_key" value="<?= $module['module_key'] ?>">
                            <button type="submit" class="btn btn-warning btn-small" <?= !$api_key ? 'disabled title="API key required"' : '' ?>>
                                Disable
                            </button>
                        </form>
                        <?php else: ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="enable">
                            <input type="hidden" name="module_key" value="<?= $module['module_key'] ?>">
                            <button type="submit" class="btn btn-success btn-small" <?= !$api_key ? 'disabled title="API key required"' : '' ?>>
                                Enable
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Integration Modules -->
        <?php if (!empty($grouped_modules['integration'])): ?>
        <div class="category-section">
            <h2 class="category-title"><span class="category-icon">🔌</span>Integration Modules</h2>
            <div class="module-grid" id="integration-modules">
                <?php foreach ($grouped_modules['integration'] as $module): ?>
                <div class="module-card <?= $module['is_installed'] ? 'installed' : '' ?>" data-module-name="<?= strtolower($module['module_name']) ?>" data-module-key="<?= $module['module_key'] ?>">
                    <div class="module-header">
                        <div class="module-title"><?= htmlspecialchars($module['module_name']) ?></div>
                        <div class="module-version">v<?= $module['version'] ?></div>
                    </div>
                    <div class="module-description"><?= htmlspecialchars($module['module_description']) ?></div>
                    <div class="module-meta">
                        <span class="badge badge-integration">Integration</span>
                        <?php if ($module['is_installed']): ?>
                        <span class="badge badge-installed">Installed</span>
                        <?php if ($module['is_enabled']): ?>
                        <span class="badge badge-enabled">Enabled</span>
                        <?php else: ?>
                        <span class="badge badge-disabled">Disabled</span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($module['dependencies'])): ?>
                    <div class="dependencies">
                        <strong>Dependencies:</strong> <?= implode(', ', $module['dependencies']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="module-actions">
                        <?php if (!$module['is_installed']): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="install">
                            <input type="hidden" name="module_key" value="<?= $module['module_key'] ?>">
                            <button type="submit" class="btn btn-success btn-small" <?= !$api_key ? 'disabled title="API key required"' : '' ?>>
                                Install
                            </button>
                        </form>
                        <?php else: ?>
                        <?php if ($module['is_enabled']): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="disable">
                            <input type="hidden" name="module_key" value="<?= $module['module_key'] ?>">
                            <button type="submit" class="btn btn-warning btn-small" <?= !$api_key ? 'disabled title="API key required"' : '' ?>>
                                Disable
                            </button>
                        </form>
                        <?php else: ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="enable">
                            <input type="hidden" name="module_key" value="<?= $module['module_key'] ?>">
                            <button type="submit" class="btn btn-success btn-small" <?= !$api_key ? 'disabled title="API key required"' : '' ?>>
                                Enable
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Addon Modules -->
        <?php if (!empty($grouped_modules['addon'])): ?>
        <div class="category-section">
            <h2 class="category-title"><span class="category-icon">🎁</span>Addon Modules</h2>
            <div class="module-grid" id="addon-modules">
                <?php foreach ($grouped_modules['addon'] as $module): ?>
                <div class="module-card <?= $module['is_installed'] ? 'installed' : '' ?>" data-module-name="<?= strtolower($module['module_name']) ?>" data-module-key="<?= $module['module_key'] ?>">
                    <div class="module-header">
                        <div class="module-title"><?= htmlspecialchars($module['module_name']) ?></div>
                        <div class="module-version">v<?= $module['version'] ?></div>
                    </div>
                    <div class="module-description"><?= htmlspecialchars($module['module_description']) ?></div>
                    <div class="module-meta">
                        <span class="badge badge-addon">Addon</span>
                        <?php if ($module['is_installed']): ?>
                        <span class="badge badge-installed">Installed</span>
                        <?php if ($module['is_enabled']): ?>
                        <span class="badge badge-enabled">Enabled</span>
                        <?php else: ?>
                        <span class="badge badge-disabled">Disabled</span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($module['dependencies'])): ?>
                    <div class="dependencies">
                        <strong>Dependencies:</strong> <?= implode(', ', $module['dependencies']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="module-actions">
                        <?php if (!$module['is_installed']): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="install">
                            <input type="hidden" name="module_key" value="<?= $module['module_key'] ?>">
                            <button type="submit" class="btn btn-success btn-small" <?= !$api_key ? 'disabled title="API key required"' : '' ?>>
                                Install
                            </button>
                        </form>
                        <?php else: ?>
                        <?php if ($module['is_enabled']): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="disable">
                            <input type="hidden" name="module_key" value="<?= $module['module_key'] ?>">
                            <button type="submit" class="btn btn-warning btn-small" <?= !$api_key ? 'disabled title="API key required"' : '' ?>>
                                Disable
                            </button>
                        </form>
                        <?php else: ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="enable">
                            <input type="hidden" name="module_key" value="<?= $module['module_key'] ?>">
                            <button type="submit" class="btn btn-success btn-small" <?= !$api_key ? 'disabled title="API key required"' : '' ?>>
                                Enable
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($modules)): ?>
        <div class="card">
            <div class="empty-state">
                <div class="empty-state-icon">📦</div>
                <h3>No modules available</h3>
                <p style="margin-top: 10px;">Unable to fetch modules from the API. Please check your connection.</p>
            </div>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 40px; padding: 20px;">
            <p style="color: white; opacity: 0.8;">FlexPBX Module Manager v1.0 | <a href="dashboard.html" style="color: white;">Back to Dashboard</a></p>
        </div>
    </div>

    <script>
        function filterModules() {
            const searchTerm = document.getElementById('searchBox').value.toLowerCase();
            const moduleCards = document.querySelectorAll('.module-card');

            moduleCards.forEach(card => {
                const moduleName = card.dataset.moduleName || '';
                const moduleKey = card.dataset.moduleKey || '';
                const description = card.querySelector('.module-description')?.textContent.toLowerCase() || '';

                if (moduleName.includes(searchTerm) || moduleKey.includes(searchTerm) || description.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            // Hide empty category sections
            document.querySelectorAll('.category-section').forEach(section => {
                const visibleCards = section.querySelectorAll('.module-card[style="display: block;"], .module-card:not([style])');
                section.style.display = visibleCards.length > 0 ? 'block' : 'none';
            });
        }

        // Auto-clear alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
