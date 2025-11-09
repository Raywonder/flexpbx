<?php
/**
 * FlexPBX User Recordings Manager
 * Upload personal voicemail greetings and recordings
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    header('Location: ../user-portal/');
    exit;
}

$extension = $_SESSION['user_extension'] ?? '';

// User can only upload to their own voicemail directory
$upload_base = "/home/flexpbxuser/public_html/media/sounds/voicemail";
$asterisk_base = "/var/lib/asterisk/sounds/en/custom/voicemail";

// Greeting types
$greeting_types = [
    'unavailable' => 'Unavailable Greeting (when you don\'t answer)',
    'busy' => 'Busy Greeting (when you\'re on another call)',
    'name' => 'Name Recording (for directory)',
    'temp' => 'Temporary Greeting (overrides unavailable)'
];

// Handle file upload
$upload_message = '';
$upload_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['audio_file'])) {
    $greeting_type = $_POST['greeting_type'] ?? 'unavailable';
    $file = $_FILES['audio_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Allowed formats
        if (in_array($file_ext, ['wav', 'mp3', 'gsm', 'm4a'])) {
            // Generate filename based on greeting type and extension
            $filename = "{$extension}-{$greeting_type}";
            $upload_path = "$upload_base/{$filename}.{$file_ext}";

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                chmod($upload_path, 0644);

                // Convert to WAV if needed
                $wav_path = "$upload_base/{$filename}.wav";

                if ($file_ext !== 'wav') {
                    $cmd = "sox \"$upload_path\" -r 8000 -c 1 -b 16 \"$wav_path\" norm -3 2>&1";
                    exec($cmd, $output, $return_var);

                    if ($return_var === 0) {
                        $upload_message = "File converted to WAV successfully!";

                        // Delete original if checkbox was checked
                        if (isset($_POST['delete_original']) && $_POST['delete_original'] === '1') {
                            unlink($upload_path);
                            $upload_message .= " Original file deleted.";
                        }
                    } else {
                        $upload_error = "File uploaded but conversion failed. Make sure sox is installed.";
                    }
                } else {
                    $upload_message = "File uploaded successfully!";
                }

                // Copy to Asterisk directory
                if (file_exists($wav_path)) {
                    $asterisk_path = "$asterisk_base/{$filename}.wav";
                    copy($wav_path, $asterisk_path);
                    chown($asterisk_path, 'asterisk');
                    chmod($asterisk_path, 0644);

                    // Create Asterisk voicemail greeting links
                    // Asterisk voicemail looks for files like: /var/spool/asterisk/voicemail/flexpbx/{extension}/unavail.wav
                    $voicemail_dir = "/var/spool/asterisk/voicemail/flexpbx/{$extension}";
                    if (!is_dir($voicemail_dir)) {
                        mkdir($voicemail_dir, 0755, true);
                        chown($voicemail_dir, 'asterisk');
                    }

                    // Map greeting type to Asterisk filenames
                    $asterisk_greeting_names = [
                        'unavailable' => 'unavail',
                        'busy' => 'busy',
                        'name' => 'greet',
                        'temp' => 'temp'
                    ];

                    $greeting_filename = $asterisk_greeting_names[$greeting_type] ?? 'unavail';
                    $voicemail_greeting_path = "$voicemail_dir/{$greeting_filename}.wav";

                    copy($wav_path, $voicemail_greeting_path);
                    chown($voicemail_greeting_path, 'asterisk');
                    chmod($voicemail_greeting_path, 0644);

                    // Also create ulaw version for Asterisk native format
                    $voicemail_ulaw_path = "$voicemail_dir/{$greeting_filename}.ulaw";
                    $cmd_ulaw = "sox \"$voicemail_greeting_path\" -r 8000 -c 1 -t ul \"$voicemail_ulaw_path\" 2>&1";
                    exec($cmd_ulaw, $output_ulaw, $return_ulaw);
                    if ($return_ulaw === 0) {
                        chown($voicemail_ulaw_path, 'asterisk');
                        chmod($voicemail_ulaw_path, 0644);
                    }

                    if (empty($upload_message)) {
                        $upload_message = "Greeting uploaded and activated successfully!";
                    } else {
                        $upload_message .= " Greeting activated!";
                    }
                } else {
                    if (empty($upload_error)) {
                        $upload_error = "File uploaded but couldn't be processed.";
                    }
                }

            } else {
                $upload_error = "Failed to upload file.";
            }
        } else {
            $upload_error = "Invalid file type. Only WAV, MP3, GSM, and M4A files allowed.";
        }
    } else {
        $upload_error = "Upload error: " . $file['error'];
    }
}

// Get existing recordings
function getUserRecordings($extension) {
    global $upload_base;
    $files = [];

    foreach (['unavailable', 'busy', 'name', 'temp'] as $type) {
        $path = "$upload_base/{$extension}-{$type}.wav";
        if (file_exists($path)) {
            $files[$type] = [
                'exists' => true,
                'size' => filesize($path),
                'modified' => filemtime($path)
            ];
        } else {
            $files[$type] = ['exists' => false];
        }
    }

    return $files;
}

$recordings = getUserRecordings($extension);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Recordings - FlexPBX</title>
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
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .back-btn {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
        }

        .extension-badge {
            background: #667eea;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
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

        select,
        input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
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

        .recordings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .recording-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }

        .recording-item h4 {
            color: #667eea;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-none {
            background: #f8d7da;
            color: #721c24;
        }

        .file-info {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
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
            font-size: 14px;
        }

        .help-text {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div>
                    <h1>🎙️ My Recordings</h1>
                    <span class="extension-badge">Extension <?php echo htmlspecialchars($extension); ?></span>
                </div>
                <a href="../user-portal/" class="back-btn">← Back to Portal</a>
            </div>

            <div class="info-box">
                <h4>📱 How to Record Your Greeting</h4>
                <ul>
                    <li><strong>Dial *97:</strong> Access voicemail and follow prompts to record greetings</li>
                    <li><strong>Press 0:</strong> In voicemail menu for mailbox options, then follow prompts</li>
                    <li><strong>Or upload a file:</strong> Use the form below to upload a pre-recorded greeting</li>
                    <li><strong>Professional recording:</strong> Record using your phone's voice recorder or computer software, then upload</li>
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
                    <label>Greeting Type</label>
                    <select name="greeting_type" required>
                        <?php foreach ($greeting_types as $type => $description): ?>
                            <option value="<?php echo $type; ?>"><?php echo $description; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="help-text">Choose which greeting to upload</p>
                </div>

                <div class="form-group">
                    <label>Audio File</label>
                    <input type="file" name="audio_file" accept=".wav,.mp3,.gsm,.m4a" required>
                    <p class="help-text">Supported: WAV, MP3, GSM, M4A (will auto-convert)</p>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="delete_original" value="1" checked style="width: auto;">
                        <span>Delete original file after conversion</span>
                    </label>
                    <p class="help-text">Removes source file after successful conversion to WAV</p>
                </div>

                <button type="submit">📤 Upload Greeting</button>
            </form>

            <h2 style="margin-top: 40px; margin-bottom: 20px; color: #333;">📂 Your Current Greetings</h2>
            <div class="recordings-grid">
                <?php foreach ($greeting_types as $type => $description): ?>
                    <div class="recording-item">
                        <h4><?php echo ucfirst($type); ?></h4>
                        <?php if ($recordings[$type]['exists']): ?>
                            <span class="status-badge status-active">✓ Active</span>
                            <div class="file-info">
                                Size: <?php echo number_format($recordings[$type]['size'] / 1024, 1); ?> KB<br>
                                Updated: <?php echo date('M j, Y', $recordings[$type]['modified']); ?>
                            </div>
                        <?php else: ?>
                            <span class="status-badge status-none">Not set</span>
                            <div class="file-info">Using default</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="info-box" style="margin-top: 30px;">
                <h4>💡 Tips for Great Greetings</h4>
                <ul>
                    <li>Speak clearly and at moderate pace</li>
                    <li>Record in a quiet environment</li>
                    <li>Keep it professional and friendly</li>
                    <li>Include your name and extension</li>
                    <li>Tell callers what to do: "Please leave a message..."</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
