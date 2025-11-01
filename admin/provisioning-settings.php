<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX Auto-Provisioning Settings
 * Admin panel for managing automatic user provisioning configuration
 *
 * @author FlexPBX
 * @version 1.0.0
 * @created 2025-10-17
 */

session_start();

// Simple authentication check
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$is_admin) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/ProvisioningSettings.php';

// Get all settings grouped by category
$settings = ProvisioningSettings::getAllByCategory();
$extensionInfo = ProvisioningSettings::getExtensionRangeInfo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto-Provisioning Settings - FlexPBX Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
        }

        .subtitle {
            color: #666;
            font-size: 14px;
        }

        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        .tabs {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }

        .tab-buttons {
            display: flex;
            overflow-x: auto;
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab-button {
            padding: 15px 25px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            white-space: nowrap;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }

        .tab-button:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: white;
        }

        .tab-content {
            display: none;
            padding: 30px;
            animation: fadeIn 0.3s;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .setting-group {
            margin-bottom: 25px;
        }

        .setting-label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .setting-description {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
            font-style: italic;
        }

        .setting-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .setting-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 30px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }

        .checkbox-group {
            margin-bottom: 10px;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 10px;
            border-radius: 6px;
            transition: background 0.3s;
        }

        .checkbox-group label:hover {
            background: #f8f9fa;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            cursor: pointer;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
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

        .btn-secondary {
            background: #6c757d;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-group {
            position: sticky;
            top: 20px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            z-index: 100;
            margin-bottom: 20px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            animation: slideDown 0.3s;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
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

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .preview-box {
            background: #f8f9fa;
            border: 2px solid #667eea;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }

        .preview-label {
            font-weight: 600;
            color: #667eea;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .preview-value {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }

        .tooltip {
            position: relative;
            display: inline-block;
            margin-left: 5px;
            cursor: help;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 250px;
            background-color: #333;
            color: #fff;
            text-align: left;
            border-radius: 6px;
            padding: 10px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -125px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
            line-height: 1.4;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: white;
            text-decoration: none;
            opacity: 0.9;
        }

        .back-link a:hover {
            opacity: 1;
            text-decoration: underline;
        }

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .loading-spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        @media (max-width: 768px) {
            .tab-buttons {
                flex-direction: column;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="container">
        <div class="header">
            <h1>Auto-Provisioning Settings</h1>
            <p class="subtitle">Configure automatic user provisioning and default feature settings</p>
        </div>

        <div class="stats-bar">
            <div class="stat-card">
                <div class="stat-value"><?php echo $extensionInfo['next']; ?></div>
                <div class="stat-label">Next Extension</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $extensionInfo['available']; ?></div>
                <div class="stat-label">Available Extensions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $extensionInfo['used']; ?></div>
                <div class="stat-label">Extensions in Use</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $extensionInfo['percentage_used']; ?>%</div>
                <div class="stat-label">Capacity Used</div>
            </div>
        </div>

        <div class="btn-group">
            <button class="btn" onclick="saveSettings()">Save All Changes</button>
            <button class="btn btn-secondary" onclick="location.reload()">Reload</button>
            <button class="btn btn-danger" onclick="resetToDefaults()">Reset to Defaults</button>
        </div>

        <div id="alertBox" class="alert"></div>

        <div class="tabs">
            <div class="tab-buttons">
                <button class="tab-button active" onclick="switchTab('general')">General</button>
                <button class="tab-button" onclick="switchTab('extensions')">Extensions</button>
                <button class="tab-button" onclick="switchTab('features')">Default Features</button>
                <button class="tab-button" onclick="switchTab('did_management')">DID Management</button>
                <button class="tab-button" onclick="switchTab('voicemail')">Voicemail</button>
                <button class="tab-button" onclick="switchTab('notifications')">Notifications</button>
                <button class="tab-button" onclick="switchTab('advanced')">Advanced</button>
                <button class="tab-button" onclick="switchTab('security')">Security</button>
                <button class="tab-button" onclick="switchTab('email')">Email</button>
            </div>

            <!-- General Settings -->
            <div id="tab-general" class="tab-content active">
                <h2>General Settings</h2>
                <?php if (isset($settings['general'])): ?>
                    <?php foreach ($settings['general'] as $key => $setting): ?>
                        <div class="setting-group">
                            <label class="setting-label">
                                <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                <?php if ($setting['description']): ?>
                                    <span class="tooltip">ℹ️
                                        <span class="tooltiptext"><?php echo htmlspecialchars($setting['description']); ?></span>
                                    </span>
                                <?php endif; ?>
                            </label>
                            <?php if ($setting['description']): ?>
                                <span class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></span>
                            <?php endif; ?>
                            <?php if ($setting['type'] === 'boolean'): ?>
                                <label class="toggle-switch">
                                    <input type="checkbox"
                                           id="<?php echo $key; ?>"
                                           data-key="<?php echo $key; ?>"
                                           data-type="<?php echo $setting['type']; ?>"
                                           <?php echo $setting['value'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            <?php elseif ($setting['type'] === 'number'): ?>
                                <input type="number"
                                       class="setting-input"
                                       id="<?php echo $key; ?>"
                                       data-key="<?php echo $key; ?>"
                                       data-type="<?php echo $setting['type']; ?>"
                                       value="<?php echo htmlspecialchars($setting['value']); ?>">
                            <?php else: ?>
                                <input type="text"
                                       class="setting-input"
                                       id="<?php echo $key; ?>"
                                       data-key="<?php echo $key; ?>"
                                       data-type="<?php echo $setting['type']; ?>"
                                       value="<?php echo htmlspecialchars($setting['value']); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Extension Settings -->
            <div id="tab-extensions" class="tab-content">
                <h2>Extension Settings</h2>
                <?php if (isset($settings['extensions'])): ?>
                    <?php foreach ($settings['extensions'] as $key => $setting): ?>
                        <div class="setting-group">
                            <label class="setting-label">
                                <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                <?php if ($setting['description']): ?>
                                    <span class="tooltip">ℹ️
                                        <span class="tooltiptext"><?php echo htmlspecialchars($setting['description']); ?></span>
                                    </span>
                                <?php endif; ?>
                            </label>
                            <?php if ($setting['description']): ?>
                                <span class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></span>
                            <?php endif; ?>
                            <?php if ($setting['type'] === 'boolean'): ?>
                                <label class="toggle-switch">
                                    <input type="checkbox"
                                           id="<?php echo $key; ?>"
                                           data-key="<?php echo $key; ?>"
                                           data-type="<?php echo $setting['type']; ?>"
                                           <?php echo $setting['value'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            <?php else: ?>
                                <input type="number"
                                       class="setting-input"
                                       id="<?php echo $key; ?>"
                                       data-key="<?php echo $key; ?>"
                                       data-type="<?php echo $setting['type']; ?>"
                                       value="<?php echo htmlspecialchars($setting['value']); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="preview-box">
                        <div class="preview-label">Extension Range Preview</div>
                        <div class="preview-value">
                            <?php echo $extensionInfo['start']; ?> - <?php echo $extensionInfo['end']; ?>
                            (<?php echo $extensionInfo['total']; ?> total)
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Default Features -->
            <div id="tab-features" class="tab-content">
                <h2>Default Features</h2>
                <p style="color: #666; margin-bottom: 20px;">Enable or disable features for new users by default</p>
                <div class="grid-2">
                    <?php if (isset($settings['features'])): ?>
                        <?php foreach ($settings['features'] as $key => $setting): ?>
                            <div class="setting-group">
                                <label class="setting-label">
                                    <?php echo ucwords(str_replace(['auto_', '_'], ['', ' '], $key)); ?>
                                    <?php if ($setting['description']): ?>
                                        <span class="tooltip">ℹ️
                                            <span class="tooltiptext"><?php echo htmlspecialchars($setting['description']); ?></span>
                                        </span>
                                    <?php endif; ?>
                                </label>
                                <?php if ($setting['description']): ?>
                                    <span class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></span>
                                <?php endif; ?>
                                <label class="toggle-switch">
                                    <input type="checkbox"
                                           id="<?php echo $key; ?>"
                                           data-key="<?php echo $key; ?>"
                                           data-type="<?php echo $setting['type']; ?>"
                                           <?php echo $setting['value'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DID Management -->
            <div id="tab-did_management" class="tab-content">
                <h2>DID Management</h2>
                <?php if (isset($settings['did_management'])): ?>
                    <?php foreach ($settings['did_management'] as $key => $setting): ?>
                        <div class="setting-group">
                            <label class="setting-label">
                                <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                <?php if ($setting['description']): ?>
                                    <span class="tooltip">ℹ️
                                        <span class="tooltiptext"><?php echo htmlspecialchars($setting['description']); ?></span>
                                    </span>
                                <?php endif; ?>
                            </label>
                            <?php if ($setting['description']): ?>
                                <span class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></span>
                            <?php endif; ?>
                            <?php if ($setting['type'] === 'boolean'): ?>
                                <label class="toggle-switch">
                                    <input type="checkbox"
                                           id="<?php echo $key; ?>"
                                           data-key="<?php echo $key; ?>"
                                           data-type="<?php echo $setting['type']; ?>"
                                           <?php echo $setting['value'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            <?php elseif ($setting['type'] === 'number'): ?>
                                <input type="number"
                                       class="setting-input"
                                       id="<?php echo $key; ?>"
                                       data-key="<?php echo $key; ?>"
                                       data-type="<?php echo $setting['type']; ?>"
                                       value="<?php echo htmlspecialchars($setting['value']); ?>">
                            <?php else: ?>
                                <input type="text"
                                       class="setting-input"
                                       id="<?php echo $key; ?>"
                                       data-key="<?php echo $key; ?>"
                                       data-type="<?php echo $setting['type']; ?>"
                                       value="<?php echo htmlspecialchars($setting['value']); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Voicemail -->
            <div id="tab-voicemail" class="tab-content">
                <h2>Voicemail Settings</h2>
                <?php if (isset($settings['voicemail'])): ?>
                    <?php foreach ($settings['voicemail'] as $key => $setting): ?>
                        <div class="setting-group">
                            <label class="setting-label">
                                <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                <?php if ($setting['description']): ?>
                                    <span class="tooltip">ℹ️
                                        <span class="tooltiptext"><?php echo htmlspecialchars($setting['description']); ?></span>
                                    </span>
                                <?php endif; ?>
                            </label>
                            <?php if ($setting['description']): ?>
                                <span class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></span>
                            <?php endif; ?>
                            <?php if ($setting['type'] === 'boolean'): ?>
                                <label class="toggle-switch">
                                    <input type="checkbox"
                                           id="<?php echo $key; ?>"
                                           data-key="<?php echo $key; ?>"
                                           data-type="<?php echo $setting['type']; ?>"
                                           <?php echo $setting['value'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            <?php else: ?>
                                <input type="number"
                                       class="setting-input"
                                       id="<?php echo $key; ?>"
                                       data-key="<?php echo $key; ?>"
                                       data-type="<?php echo $setting['type']; ?>"
                                       value="<?php echo htmlspecialchars($setting['value']); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Notifications -->
            <div id="tab-notifications" class="tab-content">
                <h2>Notification Defaults</h2>
                <p style="color: #666; margin-bottom: 20px;">Configure default notification preferences for new users</p>
                <div class="grid-2">
                    <?php if (isset($settings['notifications'])): ?>
                        <?php foreach ($settings['notifications'] as $key => $setting): ?>
                            <div class="setting-group">
                                <label class="setting-label">
                                    <?php echo ucwords(str_replace(['_default', '_'], ['', ' '], $key)); ?>
                                    <?php if ($setting['description']): ?>
                                        <span class="tooltip">ℹ️
                                            <span class="tooltiptext"><?php echo htmlspecialchars($setting['description']); ?></span>
                                        </span>
                                    <?php endif; ?>
                                </label>
                                <?php if ($setting['description']): ?>
                                    <span class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></span>
                                <?php endif; ?>
                                <?php if ($setting['type'] === 'boolean'): ?>
                                    <label class="toggle-switch">
                                        <input type="checkbox"
                                               id="<?php echo $key; ?>"
                                               data-key="<?php echo $key; ?>"
                                               data-type="<?php echo $setting['type']; ?>"
                                               <?php echo $setting['value'] ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                <?php else: ?>
                                    <select class="setting-input"
                                            id="<?php echo $key; ?>"
                                            data-key="<?php echo $key; ?>"
                                            data-type="<?php echo $setting['type']; ?>">
                                        <option value="immediate" <?php echo $setting['value'] === 'immediate' ? 'selected' : ''; ?>>Immediate</option>
                                        <option value="hourly" <?php echo $setting['value'] === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                        <option value="daily" <?php echo $setting['value'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Advanced -->
            <div id="tab-advanced" class="tab-content">
                <h2>Advanced Settings</h2>
                <p style="color: #dc3545; margin-bottom: 20px;"><strong>Warning:</strong> Only modify these settings if you understand their impact on the system.</p>
                <?php if (isset($settings['advanced'])): ?>
                    <?php foreach ($settings['advanced'] as $key => $setting): ?>
                        <div class="setting-group">
                            <label class="setting-label">
                                <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                <?php if ($setting['description']): ?>
                                    <span class="tooltip">ℹ️
                                        <span class="tooltiptext"><?php echo htmlspecialchars($setting['description']); ?></span>
                                    </span>
                                <?php endif; ?>
                            </label>
                            <?php if ($setting['description']): ?>
                                <span class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></span>
                            <?php endif; ?>
                            <?php if ($setting['type'] === 'boolean'): ?>
                                <label class="toggle-switch">
                                    <input type="checkbox"
                                           id="<?php echo $key; ?>"
                                           data-key="<?php echo $key; ?>"
                                           data-type="<?php echo $setting['type']; ?>"
                                           <?php echo $setting['value'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            <?php else: ?>
                                <input type="number"
                                       class="setting-input"
                                       id="<?php echo $key; ?>"
                                       data-key="<?php echo $key; ?>"
                                       data-type="<?php echo $setting['type']; ?>"
                                       value="<?php echo htmlspecialchars($setting['value']); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Security -->
            <div id="tab-security" class="tab-content">
                <h2>Security Settings</h2>
                <p style="color: #666; margin-bottom: 20px;">Configure password and security policies for auto-provisioning</p>
                <?php if (isset($settings['security'])): ?>
                    <?php foreach ($settings['security'] as $key => $setting): ?>
                        <div class="setting-group">
                            <label class="setting-label">
                                <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                <?php if ($setting['description']): ?>
                                    <span class="tooltip">ℹ️
                                        <span class="tooltiptext"><?php echo htmlspecialchars($setting['description']); ?></span>
                                    </span>
                                <?php endif; ?>
                            </label>
                            <?php if ($setting['description']): ?>
                                <span class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></span>
                            <?php endif; ?>
                            <?php if ($setting['type'] === 'boolean'): ?>
                                <label class="toggle-switch">
                                    <input type="checkbox"
                                           id="<?php echo $key; ?>"
                                           data-key="<?php echo $key; ?>"
                                           data-type="<?php echo $setting['type']; ?>"
                                           <?php echo $setting['value'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            <?php else: ?>
                                <input type="number"
                                       class="setting-input"
                                       id="<?php echo $key; ?>"
                                       data-key="<?php echo $key; ?>"
                                       data-type="<?php echo $setting['type']; ?>"
                                       value="<?php echo htmlspecialchars($setting['value']); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Email -->
            <div id="tab-email" class="tab-content">
                <h2>Email Settings</h2>
                <p style="color: #666; margin-bottom: 20px;">Configure welcome email template and delivery settings</p>
                <?php if (isset($settings['email'])): ?>
                    <?php foreach ($settings['email'] as $key => $setting): ?>
                        <div class="setting-group">
                            <label class="setting-label">
                                <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                <?php if ($setting['description']): ?>
                                    <span class="tooltip">ℹ️
                                        <span class="tooltiptext"><?php echo htmlspecialchars($setting['description']); ?></span>
                                    </span>
                                <?php endif; ?>
                            </label>
                            <?php if ($setting['description']): ?>
                                <span class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></span>
                            <?php endif; ?>
                            <?php if ($setting['type'] === 'boolean'): ?>
                                <label class="toggle-switch">
                                    <input type="checkbox"
                                           id="<?php echo $key; ?>"
                                           data-key="<?php echo $key; ?>"
                                           data-type="<?php echo $setting['type']; ?>"
                                           <?php echo $setting['value'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            <?php elseif ($setting['type'] === 'number'): ?>
                                <input type="number"
                                       class="setting-input"
                                       id="<?php echo $key; ?>"
                                       data-key="<?php echo $key; ?>"
                                       data-type="<?php echo $setting['type']; ?>"
                                       value="<?php echo htmlspecialchars($setting['value']); ?>">
                            <?php else: ?>
                                <input type="text"
                                       class="setting-input"
                                       id="<?php echo $key; ?>"
                                       data-key="<?php echo $key; ?>"
                                       data-type="<?php echo $setting['type']; ?>"
                                       value="<?php echo htmlspecialchars($setting['value']); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="back-link">
            <a href="dashboard-live.php">← Back to Admin Dashboard</a>
        </div>
    </div>

    <script>
        // Tab switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active from all buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById('tab-' + tabName).classList.add('active');

            // Activate button
            event.target.classList.add('active');
        }

        // Show alert
        function showAlert(message, type = 'success') {
            const alertBox = document.getElementById('alertBox');
            alertBox.className = 'alert alert-' + type;
            alertBox.textContent = message;
            alertBox.style.display = 'block';

            setTimeout(() => {
                alertBox.style.display = 'none';
            }, 5000);
        }

        // Show loading
        function showLoading(show = true) {
            document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
        }

        // Collect all settings
        function collectSettings() {
            const settings = {};

            // Get all inputs with data-key attribute
            document.querySelectorAll('[data-key]').forEach(input => {
                const key = input.dataset.key;
                const type = input.dataset.type;
                let value;

                if (input.type === 'checkbox') {
                    value = input.checked;
                } else if (type === 'number') {
                    value = parseInt(input.value) || 0;
                } else {
                    value = input.value;
                }

                settings[key] = {
                    value: value,
                    type: type
                };
            });

            return settings;
        }

        // Save settings
        async function saveSettings() {
            showLoading(true);

            const settings = collectSettings();

            try {
                const response = await fetch('/api/provisioning-settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'update',
                        settings: settings
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(data.message || 'Settings saved successfully!', 'success');
                } else {
                    showAlert(data.message || 'Failed to save settings', 'error');
                }
            } catch (error) {
                showAlert('Error saving settings: ' + error.message, 'error');
            } finally {
                showLoading(false);
            }
        }

        // Reset to defaults
        async function resetToDefaults() {
            if (!confirm('Are you sure you want to reset all settings to defaults? This cannot be undone.')) {
                return;
            }

            showLoading(true);

            try {
                const response = await fetch('/api/provisioning-settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'reset',
                        confirm: 'yes'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('Settings reset to defaults. Reloading...', 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert(data.message || 'Failed to reset settings', 'error');
                }
            } catch (error) {
                showAlert('Error resetting settings: ' + error.message, 'error');
            } finally {
                showLoading(false);
            }
        }

        // Auto-save on change (optional)
        let saveTimeout;
        document.querySelectorAll('[data-key]').forEach(input => {
            input.addEventListener('change', () => {
                clearTimeout(saveTimeout);
                showAlert('Changes detected. Click "Save All Changes" to apply.', 'info');
            });
        });
    </script>
</body>
</html>
