<?php
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user = getUserById($_SESSION['user_id']);
$extension = $user['extension'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexPBX Help & Documentation</title>
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
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .nav-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .nav-btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
        }

        .nav-btn:hover {
            background: #5568d3;
        }

        .content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .section {
            margin-bottom: 40px;
        }

        .section h2 {
            color: #667eea;
            font-size: 24px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }

        .section h3 {
            color: #333;
            font-size: 18px;
            margin: 20px 0 10px 0;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .feature-card {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .feature-card h4 {
            color: #333;
            margin-bottom: 10px;
        }

        .feature-card p {
            color: #666;
            font-size: 14px;
        }

        .code {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            margin: 10px 0;
            overflow-x: auto;
        }

        .support-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
        }

        .support-box .phone {
            font-size: 24px;
            font-weight: bold;
            color: #1976d2;
            margin: 10px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        table th {
            background: #f5f5f5;
            font-weight: 600;
        }

        .tag {
            display: inline-block;
            padding: 3px 10px;
            background: #4caf50;
            color: white;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .tag.new {
            background: #ff9800;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 FlexPBX Help & Documentation</h1>
            <p class="subtitle">A system you can help build to be the best it can be. Accessible by default.</p>
            <p>Welcome, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?> (Extension: <?php echo htmlspecialchars($extension); ?>)</p>

            <div class="nav-links">
                <a href="index.php" class="nav-btn">← Back to Portal</a>
                <a href="#quick-start" class="nav-btn">Quick Start</a>
                <a href="#connection-settings" class="nav-btn">Connection Settings</a>
                <a href="#feature-codes" class="nav-btn">Feature Codes</a>
                <a href="#conference" class="nav-btn">Conferences</a>
                <a href="#support" class="nav-btn">Support</a>
            </div>

            <div class="support-box">
                <div style="font-weight: 600; color: #1976d2;">📞 Need Help?</div>
                <div class="phone">
                    <a href="tel:+13023139555" style="color: #1976d2; text-decoration: none;">(302) 313-9555</a>
                </div>
                <div style="font-size: 14px; color: #666;">Call our support team for immediate assistance</div>
            </div>
        </div>

        <div class="content">
            <!-- Quick Start -->
            <div class="section" id="quick-start">
                <h2>🚀 Quick Start Guide</h2>

                <h3>Calling the Main Number</h3>
                <p>When you call <strong>(302) 313-9555</strong>, you'll hear our new professional IVR menu:</p>
                <ul style="margin: 15px 0 15px 30px; line-height: 1.8;">
                    <li><strong>Press 1</strong> - Connect to support queue</li>
                    <li><strong>Press 2</strong> - Dial an extension (2000-2099) or conference room (8000-8099)</li>
                    <li><strong>Press 3</strong> - Access conference rooms</li>
                    <li><strong>Press 4</strong> - Company directory (spell name)</li>
                </ul>

                <h3>From the Support Queue</h3>
                <p>While waiting in the support queue, you can:</p>
                <ul style="margin: 15px 0 15px 30px; line-height: 1.8;">
                    <li><strong>Press *0</strong> - Go to conference menu</li>
                    <li><strong>Press *# or #*</strong> - Access company directory</li>
                </ul>
            </div>

            <!-- Conference Rooms -->
            <div class="section" id="conference">
                <h2>🎤 Conference Rooms</h2>

                <h3>Main Conference Room</h3>
                <p>Dial <strong>*80</strong> from any extension to join the main conference room.</p>

                <h3>Public Conference Rooms</h3>
                <p>We have <strong>100 public conference rooms</strong> (8000-8099). Simply dial the room number.</p>

                <h3>Conference Controls (DTMF)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Function</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>*1</code></td>
                            <td>Toggle mute/unmute</td>
                        </tr>
                        <tr>
                            <td><code>*2</code></td>
                            <td>Decrease listening volume</td>
                        </tr>
                        <tr>
                            <td><code>*3</code></td>
                            <td>Increase listening volume</td>
                        </tr>
                        <tr>
                            <td><code>*4</code></td>
                            <td>Decrease talking volume</td>
                        </tr>
                        <tr>
                            <td><code>*5</code></td>
                            <td>Increase talking volume</td>
                        </tr>
                        <tr>
                            <td><code>*8</code></td>
                            <td>Leave conference</td>
                        </tr>
                        <tr>
                            <td><code>*9</code></td>
                            <td>Hear participant count</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Music in Conference Rooms <span class="tag new">NEW</span></h3>
                <p>Conference rooms now support background music! Admins can control music playback including:</p>
                <ul style="margin: 15px 0 15px 30px; line-height: 1.8;">
                    <li>Live internet radio (SoulFood Radio, ChrisMix Radio)</li>
                    <li>Local music files</li>
                    <li>Auto-play when you're alone in a room</li>
                </ul>
            </div>

            <!-- Feature Codes -->
            <div class="section" id="feature-codes">
                <h2>📞 Feature Codes</h2>

                <h3>FreePBX Compatible Codes</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Feature</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>*70</code></td>
                            <td>Call Parking</td>
                            <td>Park a call for later retrieval</td>
                        </tr>
                        <tr>
                            <td><code>*72</code></td>
                            <td>Call Forward All</td>
                            <td>Forward all calls to another number</td>
                        </tr>
                        <tr>
                            <td><code>*73</code></td>
                            <td>Cancel Call Forward</td>
                            <td>Stop forwarding calls</td>
                        </tr>
                        <tr>
                            <td><code>*76</code></td>
                            <td>DND On</td>
                            <td>Enable Do Not Disturb</td>
                        </tr>
                        <tr>
                            <td><code>*79</code></td>
                            <td>DND Off</td>
                            <td>Disable Do Not Disturb</td>
                        </tr>
                        <tr>
                            <td><code>*80</code></td>
                            <td>Main Conference</td>
                            <td>Join main conference room</td>
                        </tr>
                        <tr>
                            <td><code>*411</code></td>
                            <td>Company Directory</td>
                            <td>Dial by name</td>
                        </tr>
                    </tbody>
                </table>

                <h3>During a Call</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Function</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>##</code></td>
                            <td>Transfer call (attended)</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- New Features -->
            <div class="section">
                <h2>✨ New Features</h2>

                <div class="feature-grid">
                    <div class="feature-card">
                        <h4>🎵 Conference Music Control</h4>
                        <p>Admins can now control music playback in conference rooms with live radio streaming</p>
                    </div>

                    <div class="feature-card">
                        <h4>🟢 Presence Status</h4>
                        <p>Track your online/offline status across multiple devices with custom announcements</p>
                    </div>

                    <div class="feature-card">
                        <h4>☎️ Professional IVR Menu</h4>
                        <p>New auto-attendant on main DID with 4 menu options and direct extension dialing</p>
                    </div>

                    <div class="feature-card">
                        <h4>🎬 Jellyfin MOH</h4>
                        <p>Stream music from Jellyfin media server or internet radio for hold music</p>
                    </div>
                </div>
            </div>

            <!-- Extensions -->
            <div class="section">
                <h2>👥 Extension Directory</h2>

                <h3>Key Extensions</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Extension</th>
                            <th>Name</th>
                            <th>Department</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>2000</code></td>
                            <td>Admin (Ray)</td>
                            <td>Administration</td>
                        </tr>
                        <tr>
                            <td><code>2002</code></td>
                            <td>User</td>
                            <td>General</td>
                        </tr>
                        <tr>
                            <td><code>2003</code></td>
                            <td>Support</td>
                            <td>Technical Support</td>
                        </tr>
                        <tr>
                            <td><code>2004</code></td>
                            <td>May</td>
                            <td>General</td>
                        </tr>
                        <tr>
                            <td><code>2006</code></td>
                            <td>Walter Harper</td>
                            <td>General</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Extension Ranges</h3>
                <ul style="margin: 15px 0 15px 30px; line-height: 1.8;">
                    <li><strong>2000-2099</strong> - User extensions</li>
                    <li><strong>8000-8099</strong> - Public conference rooms</li>
                </ul>
            </div>

            <!-- Support -->
            <div class="section" id="support">
                <h2>🆘 Getting Support</h2>

                <h3>Support Queue</h3>
                <p><strong>Number:</strong> (302) 313-9555</p>
                <p><strong>Available Agents:</strong> 5 (Extensions 2000, 2002, 2003, 2004, 2006)</p>
                <p><strong>Features:</strong></p>
                <ul style="margin: 15px 0 15px 30px; line-height: 1.8;">
                    <li>Music on hold</li>
                    <li>Position announcements every 30 seconds</li>
                    <li>Press *0 for conference menu</li>
                    <li>Press *# or #* for directory</li>
                    <li>Voicemail after 5 minutes</li>
                </ul>

                <h3>Email Support</h3>
                <p><strong>Email:</strong> <a href="mailto:support@devine-creations.com" style="color: #667eea;">support@devine-creations.com</a></p>

                <h3>Documentation</h3>
                <p>Complete documentation is available at:</p>
                <div class="code">/home/flexpbxuser/documentation/</div>
                <p>Key documents:</p>
                <ul style="margin: 15px 0 15px 30px; line-height: 1.8;">
                    <li><code>MAIN_IVR_MENU.md</code> - Main IVR menu guide</li>
                    <li><code>CONFERENCE_IVR_MENU.md</code> - Conference rooms guide</li>
                    <li><code>QUEUE_ESCAPE_CODES.md</code> - Queue escape codes</li>
                    <li><code>FLEXPBX_FEATURE_CODES.md</code> - All feature codes</li>
                </ul>
            </div>

            <!-- Connection Settings -->
            <div class="section" id="connection-settings">
                <h2>⚙️ SIP Connection Settings</h2>

                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 6px; margin: 20px 0;">
                    <h3 style="color: #856404; margin-top: 0;">🔐 Account Credentials</h3>
                    <p style="color: #856404;">Your extension number and SIP password are available in your <a href="index.php" style="color: #667eea;">User Portal Dashboard</a>.</p>
                </div>

                <h3>Server Configuration</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Setting</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>SIP Server</strong></td>
                            <td><code>flexpbx.devinecreations.net</code></td>
                        </tr>
                        <tr>
                            <td><strong>Port (UDP)</strong></td>
                            <td><code>5060</code></td>
                        </tr>
                        <tr>
                            <td><strong>Port (TCP)</strong></td>
                            <td><code>5060</code></td>
                        </tr>
                        <tr>
                            <td><strong>STUN Server</strong></td>
                            <td><code>stun.devinecreations.net:3478</code></td>
                        </tr>
                        <tr>
                            <td><strong>Transport</strong></td>
                            <td>UDP (recommended) or TCP</td>
                        </tr>
                        <tr>
                            <td><strong>Username</strong></td>
                            <td>Your extension number (e.g., 2000)</td>
                        </tr>
                        <tr>
                            <td><strong>Password</strong></td>
                            <td>Available in User Portal</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Recommended SIP Clients</h3>
                <div class="feature-grid">
                    <div class="feature-card">
                        <h4>🖥️ Desktop</h4>
                        <p><strong>Zoiper:</strong> Windows, macOS, Linux</p>
                        <p><strong>Telephone:</strong> macOS</p>
                        <p><strong>Linphone:</strong> All platforms</p>
                    </div>
                    <div class="feature-card">
                        <h4>📱 Mobile</h4>
                        <p><strong>Groundwire:</strong> iOS (Premium)</p>
                        <p><strong>Zoiper:</strong> iOS & Android</p>
                        <p><strong>Linphone:</strong> iOS & Android</p>
                    </div>
                    <div class="feature-card">
                        <h4>🚀 Coming Soon</h4>
                        <p><strong>FlexPBX Desktop:</strong> Native app for Windows, macOS, and Linux with advanced features</p>
                    </div>
                </div>

                <h3>Configuration Examples</h3>
                <div class="code">
# Zoiper Configuration
Domain: flexpbx.devinecreations.net
Port: 5060
Transport: UDP
Username: 2000 (your extension)
Authentication User: 2000
Password: [from User Portal]

# STUN Settings
STUN Server: stun.devinecreations.net
STUN Port: 3478
                </div>
            </div>

            <!-- Tips & Tricks -->
            <div class="section">
                <h2>💡 Tips & Tricks</h2>

                <h3>Speed Dial</h3>
                <p>Save time by creating speed dials on your phone for frequently called extensions.</p>

                <h3>Conference Room Etiquette</h3>
                <ul style="margin: 15px 0 15px 30px; line-height: 1.8;">
                    <li>Mute yourself when not speaking (*1)</li>
                    <li>Check participant count (*9) before speaking</li>
                    <li>Use clear audio equipment for best quality</li>
                </ul>

                <h3>Voicemail</h3>
                <p>Check your voicemail regularly. You can access it from:</p>
                <ul style="margin: 15px 0 15px 30px; line-height: 1.8;">
                    <li>The user portal (this site)</li>
                    <li>Email notifications</li>
                    <li>Direct voicemail dial</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div style="text-align: center; margin-top: 40px; padding: 30px 20px; background: rgba(102, 126, 234, 0.1); border-top: 2px solid rgba(102, 126, 234, 0.2);">
        <p style="color: #333; margin-bottom: 15px; font-size: 1.1em;">
            <a href="/admin/bug-tracker.php" style="color: #667eea; text-decoration: underline; margin: 0 15px; font-weight: 500;">🐛 Report a Bug</a> |
            <a href="mailto:support@devine-creations.com" style="color: #667eea; text-decoration: underline; margin: 0 15px; font-weight: 500;">📧 Support</a> |
            <a href="/user-portal/" style="color: #667eea; text-decoration: underline; margin: 0 15px; font-weight: 500;">🏠 Back to Portal</a>
        </p>
        <p style="color: #666; font-size: 0.95em; margin-top: 10px;">
            Powered by <a href="https://devine-creations.com" target="_blank" style="color: #667eea; text-decoration: underline;">Devine Creations</a> |
            <a href="https://devinecreations.net" target="_blank" style="color: #667eea; text-decoration: underline;">devinecreations.net</a>
        </p>
    </div>

    <!-- Accessibility Tools -->
    <script src="/js/accessibility.js"></script>
</body>
</html>
