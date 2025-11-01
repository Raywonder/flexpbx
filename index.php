<?php
// FlexPBX - Main Landing Page
// Version: 1.0.0

session_start();

// Check if user is authenticated
$is_authenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// Redirect authenticated users to their appropriate portal
if ($is_authenticated) {
    if ($is_admin) {
        header('Location: /admin/dashboard.html');
        exit;
    } else {
        header('Location: /user-portal/');
        exit;
    }
}

// Configuration for public landing page
$config = [
    'version' => '1.0.0',
    'title' => 'FlexPBX',
    'domain' => $_SERVER['HTTP_HOST'] ?? 'flexpbx.devinecreations.net'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config['title']; ?> - Cloud PBX Solution</title>
    <meta name="description" content="FlexPBX - Modern Cloud-Based PBX System with Advanced Features">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .hero {
            text-align: center;
            color: white;
            margin-bottom: 3rem;
        }

        .hero h1 {
            font-size: 4rem;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            animation: fadeInDown 1s ease;
        }

        .hero .tagline {
            font-size: 1.5rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            animation: fadeInUp 1s ease 0.2s both;
        }

        .hero .version {
            font-size: 1rem;
            opacity: 0.7;
            animation: fadeInUp 1s ease 0.4s both;
        }

        .portal-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin: 3rem 0;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 3rem 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            animation: fadeInUp 1s ease both;
            text-align: center;
        }

        .card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .card:nth-child(3) {
            animation-delay: 0.4s;
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 50px rgba(0,0,0,0.3);
        }

        .card-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .card h2 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }

        .card p {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .btn.secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }

        .features {
            background: rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 2rem;
            margin: 3rem 0;
            backdrop-filter: blur(10px);
        }

        .features h3 {
            color: white;
            text-align: center;
            font-size: 2rem;
            margin-bottom: 2rem;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .feature-item {
            color: white;
            padding: 1.5rem;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .feature-item:hover {
            background: rgba(255,255,255,0.2);
            transform: scale(1.05);
        }

        .feature-item h4 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .feature-item p {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .footer {
            text-align: center;
            color: white;
            opacity: 0.8;
            margin-top: 3rem;
            padding: 2rem 0;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero .tagline {
                font-size: 1.2rem;
            }

            .portal-cards {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="hero">
            <h1>📞 FlexPBX</h1>
            <div class="tagline">Modern Cloud-Based PBX Solution</div>
            <div class="version">Version <?php echo $config['version']; ?> • <?php echo $config['domain']; ?></div>
        </div>

        <div class="portal-cards">
            <div class="card">
                <div class="card-icon">👤</div>
                <h2>User Portal</h2>
                <p>Access your voicemail, manage your extension settings, configure call forwarding, and view call history.</p>
                <a href="/user-portal/" class="btn">User Login</a>
            </div>

            <div class="card">
                <div class="card-icon">🔐</div>
                <h2>Admin Portal</h2>
                <p>Full system administration, extension management, trunk configuration, and advanced PBX settings.</p>
                <a href="/admin/dashboard.html" class="btn">Admin Login</a>
            </div>

            <div class="card">
                <div class="card-icon">📱</div>
                <h2>Desktop/Mobile Apps</h2>
                <p>Download SIP clients for your computer or phone to make calls using your FlexPBX extension.</p>
                <a href="#downloads" class="btn secondary">View Downloads</a>
            </div>
        </div>

        <div class="features">
            <h3>✨ Key Features</h3>
            <div class="feature-grid">
                <div class="feature-item">
                    <h4>🎯 Call Management</h4>
                    <p>Call parking, queues, ring groups, IVR menus, and advanced routing</p>
                </div>
                <div class="feature-item">
                    <h4>📬 Voicemail</h4>
                    <p>Email notifications, voicemail-to-email, visual voicemail, and custom greetings</p>
                </div>
                <div class="feature-item">
                    <h4>📊 Analytics</h4>
                    <p>Real-time dashboards, call logs, queue statistics, and detailed reporting</p>
                </div>
                <div class="feature-item">
                    <h4>🔒 Security</h4>
                    <p>Fail2ban protection, IP whitelisting, encrypted connections, and secure authentication</p>
                </div>
                <div class="feature-item">
                    <h4>🌐 Multi-Device</h4>
                    <p>Desktop phones, softphones, mobile apps, and WebRTC support</p>
                </div>
                <div class="feature-item">
                    <h4>🔄 Integrations</h4>
                    <p>Google Voice, WHMCS, WordPress, REST API, and webhooks</p>
                </div>
                <div class="feature-item">
                    <h4>☁️ Cloud-Ready</h4>
                    <p>Works on any server, auto-scaling, backup/restore, and high availability</p>
                </div>
                <div class="feature-item">
                    <h4>📞 Feature Codes</h4>
                    <p>Speed dial codes for quick access to voicemail, call parking, conferencing, and system features</p>
                </div>
            </div>
        </div>

        <div class="features" id="downloads">
            <h3>📥 Downloads & SIP Clients</h3>
            <div class="feature-grid">
                <div class="feature-item" style="background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.3);">
                    <h4>🚀 FlexPBX Desktop (Coming Soon)</h4>
                    <p><strong>Native desktop client</strong> for Windows, macOS, and Linux with full PBX integration, call management, and advanced features.</p>
                    <p style="margin-top: 1rem; font-size: 0.9em; opacity: 0.8;">Stay tuned for our official release!</p>
                </div>
                <div class="feature-item">
                    <h4>🖥️ Desktop Clients</h4>
                    <p><strong>Zoiper:</strong> Windows, macOS, Linux</p>
                    <p><strong>Telephone:</strong> macOS</p>
                    <p><strong>Linphone:</strong> All platforms</p>
                    <p><strong>MicroSIP:</strong> Windows</p>
                </div>
                <div class="feature-item">
                    <h4>📱 Mobile Clients</h4>
                    <p><strong>Groundwire:</strong> iOS (Premium)</p>
                    <p><strong>Zoiper:</strong> iOS & Android</p>
                    <p><strong>Linphone:</strong> iOS & Android</p>
                    <p><strong>Bria:</strong> iOS & Android</p>
                </div>
                <div class="feature-item">
                    <h4>⚙️ Getting Started</h4>
                    <p><strong>Easy Setup:</strong> Download a SIP client, log in to your user portal for connection details and extension credentials.</p>
                    <p style="margin-top: 1rem;"><strong>Need help?</strong> Contact support for assistance with configuration.</p>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> FlexPBX. All rights reserved.</p>
            <p>Enterprise Cloud PBX Solution</p>
            <p style="margin-top: 1.5rem;">
                <a href="/admin/bug-tracker.php" style="color: white; text-decoration: underline; margin: 0 10px;">🐛 Report a Bug</a> |
                <a href="mailto:support@devine-creations.com" style="color: white; text-decoration: underline; margin: 0 10px;">📧 Support</a>
            </p>
            <p style="margin-top: 0.5rem; font-size: 0.9em;">
                Powered by <a href="https://devine-creations.com" target="_blank" style="color: white; text-decoration: underline;">Devine Creations</a> |
                <a href="https://devinecreations.net" target="_blank" style="color: white; text-decoration: underline;">devinecreations.net</a>
            </p>
        </div>
    </div>

    <!-- Accessibility Tools -->
    <script src="/js/accessibility.js"></script>

    <script>
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
