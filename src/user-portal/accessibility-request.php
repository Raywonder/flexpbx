<?php
/**
 * FlexPBX User Accessibility Request Form
 * Allows users to request accessibility accommodations
 */

session_start();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? null;
    $extension = $_POST['extension'] ?? '';
    $email = $_POST['email'] ?? '';
    $categories = $_POST['accessibility'] ?? [];
    $special_requirements = $_POST['special_requirements'] ?? '';
    $urgency = $_POST['urgency'] ?? 'medium';

    // Validate input
    if (empty($email) || empty($categories)) {
        $error_message = "Please provide your email and select at least one accessibility category";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email address";
    } else {
        // Prepare request data
        $request_data = [
            'user_id' => $user_id,
            'extension_number' => $extension,
            'email' => $email,
            'requested_categories' => $categories,
            'special_requirements' => $special_requirements,
            'urgency' => $urgency
        ];

        // Call API
        $api_url = 'https://flexpbx.devinecreations.net/api/accessibility-categories.php?path=submit-request';

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development only

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                $success_message = "Accessibility request submitted successfully! Request ID: " . $result['request_id'] .
                                   ". We will review your request and contact you at " . htmlspecialchars($email);
                // Clear form
                $extension = '';
                $email = '';
                $special_requirements = '';
                $categories = [];
            } else {
                $error_message = "Failed to submit request: " . ($result['error'] ?? 'Unknown error');
            }
        } else {
            $error_message = "Server error. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accessibility Request - FlexPBX</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            color: white;
            margin-bottom: 2rem;
        }
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .request-box {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-group small {
            display: block;
            margin-top: 0.3rem;
            color: #666;
            font-size: 0.9rem;
        }
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }
        .checkbox-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .checkbox-item:hover {
            border-color: #667eea;
            background: #f0f1ff;
        }
        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin-right: 0.5rem;
        }
        .checkbox-item label {
            display: flex;
            align-items: flex-start;
            cursor: pointer;
            margin: 0;
        }
        .checkbox-item strong {
            display: block;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        .checkbox-item p {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.25rem;
        }
        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
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
            text-align: center;
            margin-top: 1rem;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            color: #004085;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .info-box h3 {
            margin-bottom: 0.5rem;
        }
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8rem;
            }
            .checkbox-group {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>♿ Accessibility Request</h1>
            <p>Request accessibility accommodations for your FlexPBX account</p>
        </div>

        <div class="request-box">
            <h2 style="margin-bottom: 1rem; color: #2c3e50;">Request Accessibility Features</h2>

            <div class="info-box">
                <h3>About Accessibility Support</h3>
                <p>FlexPBX is committed to providing accessible services for all users. Select the accessibility categories that apply to your needs, and our team will configure your account accordingly.</p>
            </div>

            <?php if ($success_message): ?>
            <div class="alert alert-success">
                ✓ <?= $success_message ?>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-error">
                ⚠️ <?= $error_message ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required aria-required="true">
                    <small>We'll contact you at this address regarding your request</small>
                </div>

                <div class="form-group">
                    <label for="extension">Extension Number (if applicable)</label>
                    <input type="text" id="extension" name="extension" value="<?= htmlspecialchars($extension ?? '') ?>" placeholder="e.g., 2001">
                    <small>Your current extension number, if you have one</small>
                </div>

                <div class="form-group">
                    <label>Accessibility Categories Needed *</label>
                    <small style="margin-bottom: 1rem;">Select all that apply to your needs</small>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <label>
                                <input type="checkbox" name="accessibility[]" value="visual_impairment" <?= in_array('visual_impairment', $categories ?? []) ? 'checked' : '' ?>>
                                <div>
                                    <strong>Visual Impairment Support</strong>
                                    <p>Screen reader compatible, high contrast mode, keyboard navigation, audio feedback</p>
                                </div>
                            </label>
                        </div>

                        <div class="checkbox-item">
                            <label>
                                <input type="checkbox" name="accessibility[]" value="hearing_impairment" <?= in_array('hearing_impairment', $categories ?? []) ? 'checked' : '' ?>>
                                <div>
                                    <strong>Hearing Impairment Support</strong>
                                    <p>TTY/TDD support, visual alerts, closed captions, text-based communication</p>
                                </div>
                            </label>
                        </div>

                        <div class="checkbox-item">
                            <label>
                                <input type="checkbox" name="accessibility[]" value="mobility_support" <?= in_array('mobility_support', $categories ?? []) ? 'checked' : '' ?>>
                                <div>
                                    <strong>Mobility Support</strong>
                                    <p>Voice control, simplified interface, single-switch access, adaptive input</p>
                                </div>
                            </label>
                        </div>

                        <div class="checkbox-item">
                            <label>
                                <input type="checkbox" name="accessibility[]" value="cognitive_support" <?= in_array('cognitive_support', $categories ?? []) ? 'checked' : '' ?>>
                                <div>
                                    <strong>Cognitive Support</strong>
                                    <p>Simplified menus, clear instructions, consistent navigation, focus mode</p>
                                </div>
                            </label>
                        </div>

                        <div class="checkbox-item">
                            <label>
                                <input type="checkbox" name="accessibility[]" value="language_support" <?= in_array('language_support', $categories ?? []) ? 'checked' : '' ?>>
                                <div>
                                    <strong>Language Support</strong>
                                    <p>Multilingual interface, translation services, RTL language support</p>
                                </div>
                            </label>
                        </div>

                        <div class="checkbox-item">
                            <label>
                                <input type="checkbox" name="accessibility[]" value="assistive_tech" <?= in_array('assistive_tech', $categories ?? []) ? 'checked' : '' ?>>
                                <div>
                                    <strong>Assistive Technology</strong>
                                    <p>Compatible with braille displays, switch devices, eye-tracking systems</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="special-requirements">Special Accessibility Requirements</label>
                    <textarea id="special-requirements" name="special_requirements" placeholder="Please describe any specific accessibility needs or devices you use..."><?= htmlspecialchars($special_requirements ?? '') ?></textarea>
                    <small>Help us understand your specific needs for better support</small>
                </div>

                <div class="form-group">
                    <label for="urgency">Request Urgency *</label>
                    <select id="urgency" name="urgency" required>
                        <option value="low" <?= ($urgency ?? 'medium') == 'low' ? 'selected' : '' ?>>Low - Within a week</option>
                        <option value="medium" <?= ($urgency ?? 'medium') == 'medium' ? 'selected' : '' ?>>Medium - Within 2-3 days</option>
                        <option value="high" <?= ($urgency ?? 'medium') == 'high' ? 'selected' : '' ?>>High - Within 24 hours</option>
                        <option value="critical" <?= ($urgency ?? 'medium') == 'critical' ? 'selected' : '' ?>>Critical - Immediate assistance needed</option>
                    </select>
                    <small>How urgently do you need these accessibility features?</small>
                </div>

                <button type="submit" class="btn">Submit Accessibility Request</button>
            </form>

            <div class="back-link">
                <a href="../admin/dashboard.html">← Back to Dashboard</a> |
                <a href="signup.php">User Sign-Up</a>
            </div>
        </div>
    </div>
</body>
</html>
