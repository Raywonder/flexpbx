<?php
/**
 * FlexPBX Audio Upload Manager
 * Upload and manage IVR audio files
 */

session_start();

// Simple authentication
$valid_credentials = [
    'admin' => 'FlexPBX2024!'
];

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: audio-upload.php');
    exit;
}

// Handle login
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (isset($valid_credentials[$username]) && $valid_credentials[$username] === $password) {
        $_SESSION['audio_admin_logged_in'] = true;
        $_SESSION['audio_admin_user'] = $username;
    } else {
        $login_error = 'Invalid username or password';
    }
}

// Check if logged in
$is_logged_in = isset($_SESSION['audio_admin_logged_in']) && $_SESSION['audio_admin_logged_in'] === true;

// Define upload directories
$upload_dirs = [
    'unsorted' => '📥 Unsorted / Needs Review',
    'greetings' => 'Greetings & Welcome Messages',
    'queue' => 'Queue Management',
    'voicemail' => 'Voicemail Prompts',
    'numbers' => 'Numbers & Digits',
    'time' => 'Time & Date',
    'status' => 'Status Messages',
    'transfers' => 'Transfer Messages',
    'errors' => 'Error Messages',
    'custom' => 'Custom Recordings',
    'on-hold' => 'On-Hold Messages'
];

$upload_base = '/home/flexpbxuser/public_html/media/sounds';
$asterisk_base = '/var/lib/asterisk/sounds/en/custom';

// Scan for orphaned audio files and move to unsorted
function scanAndMoveOrphanedFiles() {
    global $upload_base;
    $scan_paths = [
        '/home/flexpbxuser/public_html/',
        '/home/flexpbxuser/public_html/uploads/',
        '/home/flexpbxuser/public_html/media/',
    ];

    $moved_count = 0;
    $unsorted_dir = "$upload_base/unsorted";

    // Ensure unsorted directory exists
    if (!is_dir($unsorted_dir)) {
        mkdir($unsorted_dir, 0755, true);
    }

    foreach ($scan_paths as $scan_path) {
        if (!is_dir($scan_path)) continue;

        // Find audio files (not in media/sounds subdirectories)
        $files = glob($scan_path . '*.{wav,mp3,gsm,m4a}', GLOB_BRACE);

        foreach ($files as $file) {
            // Skip if already in organized location
            if (strpos($file, '/media/sounds/') !== false) continue;

            $filename = basename($file);
            $dest = "$unsorted_dir/$filename";

            // Move file to unsorted if not already there
            if (!file_exists($dest)) {
                if (rename($file, $dest)) {
                    chmod($dest, 0644);
                    $moved_count++;
                }
            }
        }
    }

    return $moved_count;
}

// Auto-scan for orphaned files when admin logs in
if ($is_logged_in && !isset($_SESSION['files_scanned'])) {
    $moved = scanAndMoveOrphanedFiles();
    $_SESSION['files_scanned'] = true;
    if ($moved > 0) {
        $upload_message = "Found and moved $moved audio file(s) to Unsorted category for review.";
    }
}

// Handle file upload
$upload_message = '';
$upload_error = '';

if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['audio_file'])) {
    $category = $_POST['category'] ?? 'custom';
    $file = $_FILES['audio_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $filename = basename($file['name']);
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Allowed formats
        if (in_array($file_ext, ['wav', 'mp3', 'gsm'])) {
            $upload_path = "$upload_base/$category/$filename";

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                chmod($upload_path, 0644);

                // If it's MP3, convert to WAV
                if ($file_ext === 'mp3') {
                    $wav_filename = pathinfo($filename, PATHINFO_FILENAME) . '.wav';
                    $wav_path = "$upload_base/$category/$wav_filename";

                    // Convert with sox
                    $cmd = "sox \"$upload_path\" -r 8000 -c 1 -b 16 \"$wav_path\" norm -3 2>&1";
                    exec($cmd, $output, $return_var);

                    if ($return_var === 0) {
                        $filename = $wav_filename;
                        $upload_message = "MP3 converted to WAV successfully!";

                        // Delete original if checkbox was checked
                        if (isset($_POST['delete_original']) && $_POST['delete_original'] === '1') {
                            unlink($upload_path);
                            $upload_message .= " Original MP3 deleted.";
                        }
                    } else {
                        $upload_error = "MP3 uploaded but conversion failed. Install sox: yum install sox";
                    }
                }

                // Copy to Asterisk directory
                $asterisk_path = "$asterisk_base/$category/" . pathinfo($filename, PATHINFO_FILENAME) . '.wav';
                if (file_exists("$upload_base/$category/" . pathinfo($filename, PATHINFO_FILENAME) . '.wav')) {
                    copy("$upload_base/$category/" . pathinfo($filename, PATHINFO_FILENAME) . '.wav', $asterisk_path);
                    chown($asterisk_path, 'asterisk');
                    chmod($asterisk_path, 0644);

                    // Also create ulaw version for Asterisk native format
                    $ulaw_path = "$asterisk_base/$category/" . pathinfo($filename, PATHINFO_FILENAME) . '.ulaw';
                    $cmd_ulaw = "sox \"$asterisk_path\" -r 8000 -c 1 -t ul \"$ulaw_path\" 2>&1";
                    exec($cmd_ulaw, $output_ulaw, $return_ulaw);
                    if ($return_ulaw === 0) {
                        chown($ulaw_path, 'asterisk');
                        chmod($ulaw_path, 0644);
                    }

                    if (empty($upload_message)) {
                        $upload_message = "File uploaded and deployed to Asterisk successfully!";
                    } else {
                        $upload_message .= " Deployed to Asterisk!";
                    }
                } else {
                    if (empty($upload_error)) {
                        $upload_error = "File uploaded but couldn't copy to Asterisk directory.";
                    }
                }

            } else {
                $upload_error = "Failed to upload file.";
            }
        } else {
            $upload_error = "Invalid file type. Only WAV, MP3, and GSM files allowed.";
        }
    } else {
        $upload_error = "Upload error: " . $file['error'];
    }
}

// Get list of files in each category
function getFilesInCategory($category) {
    global $asterisk_base;
    $dir = "$asterisk_base/$category";
    if (is_dir($dir)) {
        $files = glob("$dir/*.{wav,gsm}", GLOB_BRACE);
        return array_map('basename', $files);
    }
    return [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexPBX Audio Upload Manager</title>
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

        .login-box, .upload-box {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-top: 50px;
        }

        .login-box {
            max-width: 400px;
            margin: 100px auto;
        }

        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        input[type="text"],
        input[type="password"],
        select,
        input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
        }

        .error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            background: #efe;
            border: 1px solid #cfc;
            color: #3c3;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .logout-btn {
            background: #dc3545;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            width: auto;
            display: inline-block;
        }

        .file-list {
            margin-top: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .category-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }

        .category-box h3 {
            color: #667eea;
            font-size: 16px;
            margin-bottom: 12px;
        }

        .category-box ul {
            list-style: none;
            font-size: 13px;
            color: #666;
        }

        .category-box li {
            padding: 6px 0;
            border-bottom: 1px solid #e1e8ed;
        }

        .category-box li:last-child {
            border-bottom: none;
        }

        .file-count {
            background: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 8px;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .info-box h4 {
            color: #1976D2;
            margin-bottom: 8px;
        }

        .info-box ul {
            margin-left: 20px;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$is_logged_in): ?>
            <!-- Login Form -->
            <div class="login-box">
                <h1>🎙️ Audio Upload Manager</h1>
                <?php if ($login_error): ?>
                    <div class="error"><?php echo htmlspecialchars($login_error); ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" name="login">Login</button>
                </form>
            </div>
        <?php else: ?>
            <!-- Upload Interface -->
            <div class="upload-box">
                <div class="header">
                    <h1>🎙️ Audio Upload Manager</h1>
                    <div style="display: flex; gap: 10px;">
                        <a href="dashboard.html" class="logout-btn" style="background: #6c757d;">← Dashboard</a>
                        <a href="?logout" class="logout-btn">Logout</a>
                    </div>
                </div>

                <div class="info-box">
                    <h4>ℹ️ Supported Formats</h4>
                    <ul>
                        <li><strong>WAV:</strong> Recommended (8kHz, 16-bit, mono) - Ready for Asterisk</li>
                        <li><strong>MP3:</strong> Will be auto-converted to WAV</li>
                        <li><strong>GSM:</strong> Compressed format (smaller files)</li>
                    </ul>
                </div>

                <?php if ($upload_message): ?>
                    <div class="success">✅ <?php echo htmlspecialchars($upload_message); ?></div>
                <?php endif; ?>

                <?php if ($upload_error): ?>
                    <div class="error">❌ <?php echo htmlspecialchars($upload_error); ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" required>
                            <?php foreach ($upload_dirs as $dir => $label): ?>
                                <option value="<?php echo $dir; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Audio File</label>
                        <input type="file" name="audio_file" accept=".wav,.mp3,.gsm" required>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="delete_original" value="1" checked style="width: auto;">
                            <span>Delete original file after conversion</span>
                        </label>
                        <small style="color: #666; font-size: 0.9rem;">Removes MP3/GSM source after successful WAV conversion</small>
                    </div>

                    <button type="submit">Upload & Deploy</button>
                </form>

                <div class="info-box" style="margin-top: 30px;">
                    <h4>📁 Upload Locations</h4>
                    <ul>
                        <li><strong>Web:</strong> /home/flexpbxuser/public_html/media/sounds/</li>
                        <li><strong>Asterisk:</strong> /var/lib/asterisk/sounds/en/custom/</li>
                    </ul>
                </div>

                <!-- File List -->
                <h2 style="margin-top: 40px; margin-bottom: 20px; color: #333;">📂 Existing Files</h2>
                <div class="file-list">
                    <?php foreach ($upload_dirs as $dir => $label): ?>
                        <?php $files = getFilesInCategory($dir); ?>
                        <div class="category-box">
                            <h3><?php echo $label; ?> <span class="file-count"><?php echo count($files); ?></span></h3>
                            <ul>
                                <?php if (empty($files)): ?>
                                    <li style="color: #999; font-style: italic;">No files yet</li>
                                <?php else: ?>
                                    <?php foreach ($files as $file): ?>
                                        <li><?php echo htmlspecialchars($file); ?></li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
