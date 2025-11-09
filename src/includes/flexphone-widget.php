<?php
/**
 * FlexPhone Popup Widget
 * Embeddable WebRTC phone interface for admin and user portals
 *
 * Usage:
 * - Include this file in admin/user pages
 * - Call: renderFlexPhoneWidget($extension, $options);
 */

function renderFlexPhoneWidget($extension = null, $options = []) {
    // Default options
    $defaults = [
        'position' => 'bottom-right',  // bottom-right, bottom-left, top-right, top-left
        'width' => '350px',
        'height' => '500px',
        'minimized' => true,
        'theme' => 'dark',
        'show_settings' => true,
        'allow_new_tab' => true,
        'allow_new_window' => true,
        'auto_register' => true
    ];

    $opts = array_merge($defaults, $options);

    // Get SIP credentials if extension provided
    $sip_config = null;
    if ($extension) {
        $sip_config = getSIPCredentials($extension);
    }

    ?>
    <style>
        #flexphone-widget-container {
            position: fixed;
            <?php echo getPositionCSS($opts['position']); ?>
            z-index: 9999;
            width: <?php echo $opts['width']; ?>;
            height: <?php echo $opts['height']; ?>;
            transition: all 0.3s ease-in-out;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            border-radius: 12px;
            overflow: hidden;
        }

        #flexphone-widget-container.minimized {
            height: 60px;
            width: 60px;
            border-radius: 50%;
        }

        #flexphone-toggle-btn {
            position: absolute;
            top: 0;
            right: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            z-index: 10000;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
        }

        #flexphone-toggle-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        #flexphone-iframe-wrapper {
            width: 100%;
            height: 100%;
            background: <?php echo $opts['theme'] === 'dark' ? '#1a1a2e' : '#ffffff'; ?>;
            display: none;
        }

        #flexphone-iframe-wrapper.active {
            display: block;
        }

        #flexphone-widget-controls {
            position: absolute;
            top: 10px;
            right: 10px;
            display: none;
            gap: 5px;
            z-index: 10001;
        }

        #flexphone-widget-controls.active {
            display: flex;
        }

        .flexphone-control-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }

        .flexphone-control-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        #flexphone-status-indicator {
            position: absolute;
            top: 5px;
            left: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #ff4444;
            display: none;
            z-index: 10002;
        }

        #flexphone-status-indicator.registered {
            background: #44ff44;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>

    <div id="flexphone-widget-container" class="minimized">
        <button id="flexphone-toggle-btn" title="FlexPhone - Click to Open">
            <i class="fas fa-phone"></i>
        </button>

        <div id="flexphone-status-indicator"></div>

        <div id="flexphone-widget-controls">
            <?php if ($opts['allow_new_tab']): ?>
                <button class="flexphone-control-btn" onclick="openFlexPhoneNewTab()" title="Open in New Tab">
                    <i class="fas fa-external-link-alt"></i>
                </button>
            <?php endif; ?>

            <?php if ($opts['allow_new_window']): ?>
                <button class="flexphone-control-btn" onclick="openFlexPhoneNewWindow()" title="Open in New Window">
                    <i class="fas fa-window-maximize"></i>
                </button>
            <?php endif; ?>

            <?php if ($opts['show_settings']): ?>
                <button class="flexphone-control-btn" onclick="openFlexPhoneSettings()" title="Settings">
                    <i class="fas fa-cog"></i>
                </button>
            <?php endif; ?>

            <button class="flexphone-control-btn" onclick="minimizeFlexPhone()" title="Minimize">
                <i class="fas fa-minus"></i>
            </button>
        </div>

        <div id="flexphone-iframe-wrapper">
            <iframe
                id="flexphone-iframe"
                src="/flexphone/?embedded=true<?php echo $extension ? '&ext=' . urlencode($extension) : ''; ?>"
                frameborder="0"
                width="100%"
                height="100%"
                allow="microphone; camera"
            ></iframe>
        </div>
    </div>

    <script>
        // FlexPhone Widget Controller
        const FlexPhoneWidget = {
            isMinimized: true,
            isRegistered: false,
            sipConfig: <?php echo json_encode($sip_config); ?>,

            init() {
                this.attachEventListeners();
                this.checkRegistrationStatus();

                <?php if ($opts['auto_register'] && $sip_config): ?>
                // Auto-register when opened
                this.autoRegister();
                <?php endif; ?>
            },

            attachEventListeners() {
                document.getElementById('flexphone-toggle-btn').addEventListener('click', () => {
                    this.toggle();
                });

                // Listen for messages from FlexPhone iframe
                window.addEventListener('message', (event) => {
                    if (event.data.type === 'flexphone-status') {
                        this.updateStatus(event.data.status);
                    }
                });
            },

            toggle() {
                const container = document.getElementById('flexphone-widget-container');
                const wrapper = document.getElementById('flexphone-iframe-wrapper');
                const controls = document.getElementById('flexphone-widget-controls');
                const btn = document.getElementById('flexphone-toggle-btn');

                this.isMinimized = !this.isMinimized;

                if (this.isMinimized) {
                    container.classList.add('minimized');
                    wrapper.classList.remove('active');
                    controls.classList.remove('active');
                    btn.innerHTML = '<i class="fas fa-phone"></i>';
                } else {
                    container.classList.remove('minimized');
                    wrapper.classList.add('active');
                    controls.classList.add('active');
                    btn.innerHTML = '<i class="fas fa-times"></i>';
                }
            },

            updateStatus(status) {
                const indicator = document.getElementById('flexphone-status-indicator');
                this.isRegistered = (status === 'registered');

                if (this.isRegistered) {
                    indicator.classList.add('registered');
                    indicator.style.display = 'block';
                } else {
                    indicator.classList.remove('registered');
                    indicator.style.display = this.isMinimized ? 'none' : 'block';
                }
            },

            checkRegistrationStatus() {
                // Poll registration status every 5 seconds
                setInterval(() => {
                    const iframe = document.getElementById('flexphone-iframe');
                    if (iframe.contentWindow) {
                        iframe.contentWindow.postMessage({
                            type: 'get-status'
                        }, '*');
                    }
                }, 5000);
            },

            autoRegister() {
                // Wait for iframe to load, then send SIP credentials
                setTimeout(() => {
                    const iframe = document.getElementById('flexphone-iframe');
                    if (iframe.contentWindow && this.sipConfig) {
                        iframe.contentWindow.postMessage({
                            type: 'auto-register',
                            config: this.sipConfig
                        }, '*');
                    }
                }, 2000);
            }
        };

        // Global functions for control buttons
        function minimizeFlexPhone() {
            FlexPhoneWidget.toggle();
        }

        function openFlexPhoneNewTab() {
            window.open('/flexphone/', '_blank');
        }

        function openFlexPhoneNewWindow() {
            window.open(
                '/flexphone/',
                'FlexPhone',
                'width=400,height=600,menubar=no,toolbar=no,location=no,status=no'
            );
        }

        function openFlexPhoneSettings() {
            const iframe = document.getElementById('flexphone-iframe');
            if (iframe.contentWindow) {
                iframe.contentWindow.postMessage({
                    type: 'open-settings'
                }, '*');
            }
        }

        // Initialize widget when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            FlexPhoneWidget.init();
        });
    </script>
    <?php
}

/**
 * Get SIP credentials for extension
 */
function getSIPCredentials($extension) {
    try {
        $pdo = require __DIR__ . '/../config/database.php';

        $stmt = $pdo->prepare("
            SELECT extension, secret, context, display_name
            FROM extensions
            WHERE extension = ?
        ");
        $stmt->execute([$extension]);
        $ext = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ext) {
            return [
                'username' => $ext['extension'],
                'password' => $ext['secret'],
                'server' => $_SERVER['HTTP_HOST'],
                'display_name' => $ext['display_name'],
                'context' => $ext['context'] ?? 'from-internal'
            ];
        }
    } catch (Exception $e) {
        error_log("FlexPhone Widget Error: " . $e->getMessage());
    }

    return null;
}

/**
 * Get position CSS based on option
 */
function getPositionCSS($position) {
    switch ($position) {
        case 'bottom-right':
            return 'bottom: 20px; right: 20px;';
        case 'bottom-left':
            return 'bottom: 20px; left: 20px;';
        case 'top-right':
            return 'top: 20px; right: 20px;';
        case 'top-left':
            return 'top: 20px; left: 20px;';
        default:
            return 'bottom: 20px; right: 20px;';
    }
}
?>
