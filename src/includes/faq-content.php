<?php
/**
 * FlexPBX FAQ Content
 * Organized by category with public and private sections
 */

$faq_data = [
    'getting-started' => [
        [
            'question' => 'What is FlexPBX?',
            'answer' => 'FlexPBX is a modern, web-based PBX (Private Branch Exchange) system built on Asterisk. It provides voice calling, SMS messaging, conferencing, voicemail, and more through an intuitive web interface. FlexPBX is designed to be easy to install, configure, and use without requiring deep technical knowledge.',
            'access' => 'public'
        ],
        [
            'question' => 'What are the system requirements?',
            'answer' => '<strong>Minimum Requirements:</strong>
<ul>
    <li><strong>OS:</strong> Ubuntu 20.04+, Debian 10+, CentOS 8+, or AlmaLinux 8+</li>
    <li><strong>CPU:</strong> 2 cores (4+ recommended)</li>
    <li><strong>RAM:</strong> 2 GB (4 GB+ recommended)</li>
    <li><strong>Storage:</strong> 20 GB (SSD recommended)</li>
    <li><strong>Network:</strong> Public IP address with ports 80, 443, 5060, 10000-20000 accessible</li>
    <li><strong>Software:</strong> PHP 7.4+, MariaDB 10.5+, Asterisk 18+</li>
</ul>',
            'access' => 'public'
        ],
        [
            'question' => 'How do I get started with FlexPBX?',
            'answer' => '<strong>Quick Start Guide:</strong>
<ol>
    <li>Download the installer from <a href="/downloads/">https://flexpbx.devinecreations.net/downloads/</a></li>
    <li>Extract and run: <code>./install-flexpbx.sh</code></li>
    <li>Follow the setup wizard at <code>https://your-server/admin/setup-wizard.php</code></li>
    <li>Create your first extension and start making calls!</li>
</ol>
<p>See the <a href="?category=installation">Installation</a> category for detailed instructions.</p>',
            'access' => 'public'
        ],
        [
            'question' => 'What clients can I use to make calls?',
            'answer' => '<strong>FlexPBX supports multiple clients:</strong>
<ul>
    <li><strong>FlexPhone (Recommended):</strong> Built-in web client accessible from your browser</li>
    <li><strong>Groundwire:</strong> Premium mobile app for iOS and Android</li>
    <li><strong>Linphone:</strong> Free and open-source SIP client</li>
    <li><strong>Zoiper:</strong> Popular cross-platform softphone</li>
    <li><strong>Hardware Phones:</strong> Any SIP-compatible phone (Yealink, Polycom, Cisco, etc.)</li>
</ul>',
            'access' => 'public'
        ],
    ],

    'installation' => [
        [
            'question' => 'How do I install FlexPBX on a fresh server?',
            'answer' => '<strong>Installation Steps:</strong>
<pre><code># 1. Download the installer
wget https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.2.tar.gz

# 2. Extract the archive
tar -xzf FlexPBX-Master-Server-v1.2.tar.gz
cd FlexPBX-Master-Server-v1.2

# 3. Run the installer (as root)
sudo ./install-flexpbx.sh

# 4. Follow the prompts and wait for installation to complete

# 5. Access the setup wizard
# Visit: https://your-server-ip/admin/setup-wizard.php
</code></pre>
<p>The installer will automatically install all dependencies including Apache, PHP, MariaDB, and Asterisk.</p>',
            'access' => 'public'
        ],
        [
            'question' => 'Can I install FlexPBX without SSH access?',
            'answer' => 'Unfortunately, the initial installation requires SSH access to install system dependencies. However, <strong>once installed</strong>, you can manage everything through the web interface including:
<ul>
    <li>Installing modules (no SSH required)</li>
    <li>Updating the system</li>
    <li>Configuring extensions, trunks, and routes</li>
    <li>Managing users and permissions</li>
</ul>
<p>If you\'re using a shared hosting provider, consider using a VPS or dedicated server for FlexPBX.</p>',
            'access' => 'public'
        ],
        [
            'question' => 'How do I install on cPanel/WHM?',
            'answer' => 'FlexPBX can be installed on a cPanel/WHM server:
<ol>
    <li>SSH into your server as root</li>
    <li>Download and run the installer as usual</li>
    <li>The installer will detect cPanel and adjust configurations accordingly</li>
    <li>Access the web interface via your cPanel domain or IP</li>
</ol>
<p><strong>Note:</strong> FlexPBX requires its own cPanel user account. The installer will create a "flexpbxuser" account automatically.</p>',
            'access' => 'public'
        ],
        [
            'question' => 'What ports need to be open in my firewall?',
            'answer' => '<strong>Required Ports:</strong>
<table class="table table-sm">
    <thead>
        <tr>
            <th>Port</th>
            <th>Protocol</th>
            <th>Purpose</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>80</td><td>TCP</td><td>HTTP (redirects to HTTPS)</td></tr>
        <tr><td>443</td><td>TCP</td><td>HTTPS (web interface)</td></tr>
        <tr><td>5060</td><td>UDP</td><td>SIP signaling</td></tr>
        <tr><td>5061</td><td>TCP</td><td>SIP over TLS (secure)</td></tr>
        <tr><td>10000-20000</td><td>UDP</td><td>RTP media streams (voice/video)</td></tr>
    </tbody>
</table>
<pre><code># Example firewall rules (iptables)
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 5060/udp
sudo ufw allow 5061/tcp
sudo ufw allow 10000:20000/udp
</code></pre>',
            'access' => 'public'
        ],
    ],

    'modules' => [
        [
            'question' => 'What are FlexPBX modules?',
            'answer' => 'Modules are add-ons that extend FlexPBX functionality. They can add new features, integrations, or enhancements without modifying core code.
<br><br>
<strong>Module Types:</strong>
<ul>
    <li><strong>Required:</strong> Core modules needed for basic operation (e.g., Universal Checklist System)</li>
    <li><strong>Optional:</strong> Extra features you can install as needed (e.g., FlexBot AI, Mastodon Auth)</li>
</ul>',
            'access' => 'public'
        ],
        [
            'question' => 'How do I install modules?',
            'answer' => '<strong>Web-Based Installation (Recommended):</strong>
<ol>
    <li>Log into your admin panel</li>
    <li>Navigate to <strong>Admin → Module Manager</strong></li>
    <li>Browse available modules from the master server</li>
    <li>Click <strong>Install</strong> on the module you want</li>
    <li>Wait for automatic download, extraction, and installation</li>
</ol>
<p><strong>No FTP, SFTP, or SSH required!</strong> The module manager handles everything through your web browser.</p>
<br>
<strong>Manual Installation:</strong>
<pre><code># 1. Download module
wget https://flexpbx.devinecreations.net/downloads/modules/optional/flexbot-1.0.0.tar.gz

# 2. Extract
tar -xzf flexbot-1.0.0.tar.gz
cd flexbot-1.0.0

# 3. Run installer
sudo ./install.sh
</code></pre>',
            'access' => 'public'
        ],
        [
            'question' => 'What modules are available?',
            'answer' => '<strong>Current Modules:</strong>
<br><br>
<strong>Required Modules:</strong>
<ul>
    <li><strong>Universal Checklist System v2.0:</strong> Setup wizard, maintenance checklists, progress tracking</li>
</ul>
<br>
<strong>Optional Modules:</strong>
<ul>
    <li><strong>FlexBot AI Assistant v1.0:</strong> AI-powered help, natural language processing, task automation</li>
    <li><strong>Mastodon Authentication v1.0:</strong> Single sign-on with Mastodon (federated social network)</li>
    <li><strong>Remote Deployment v1.0:</strong> Deploy FlexPBX to remote servers from master server</li>
</ul>
<p>Visit the <a href="/admin/module-manager.php">Module Manager</a> to see the full list and install modules.</p>',
            'access' => 'public'
        ],
        [
            'question' => 'How do I update modules?',
            'answer' => 'Module updates are displayed in the Module Manager:
<ol>
    <li>Go to <strong>Admin → Module Manager</strong></li>
    <li>Modules with updates available will show an <span class="badge bg-warning text-dark">UPDATE AVAILABLE</span> badge</li>
    <li>Click <strong>Update</strong> to install the new version</li>
    <li>Or click <strong>Update All</strong> to update all modules at once</li>
</ol>
<p>The system automatically checks for updates when you visit the Module Manager page.</p>',
            'access' => 'public'
        ],
        [
            'question' => 'Can I create my own modules?',
            'answer' => 'Yes! FlexPBX has a modular architecture designed for extensibility. See the <a href="?category=advanced">Advanced</a> section for module development documentation.',
            'access' => 'public'
        ],
    ],

    'configuration' => [
        [
            'question' => 'How do I create an extension?',
            'answer' => '<strong>Creating Extensions:</strong>
<ol>
    <li>Log into admin panel</li>
    <li>Go to <strong>Extensions → Add New</strong></li>
    <li>Fill in:
        <ul>
            <li><strong>Extension Number:</strong> e.g., 2000, 2001</li>
            <li><strong>Display Name:</strong> User\'s name</li>
            <li><strong>Secret/Password:</strong> SIP authentication password</li>
            <li><strong>Email:</strong> For voicemail notifications</li>
        </ul>
    </li>
    <li>Click <strong>Save</strong></li>
    <li>Configure your phone with the extension details</li>
</ol>',
            'access' => 'public'
        ],
        [
            'question' => 'How do I add a SIP trunk?',
            'answer' => '<strong>Adding SIP Trunks:</strong>
<ol>
    <li>Go to <strong>Trunks → Add New</strong></li>
    <li>Select trunk type: <strong>SIP</strong></li>
    <li>Enter trunk details from your provider:
        <ul>
            <li><strong>Trunk Name:</strong> Descriptive name</li>
            <li><strong>Host:</strong> Provider\'s SIP server</li>
            <li><strong>Username:</strong> Your account username</li>
            <li><strong>Password:</strong> Your account password</li>
            <li><strong>DID/Phone Number:</strong> Inbound number(s)</li>
        </ul>
    </li>
    <li>Save and test the trunk</li>
</ol>
<p>Popular providers: Twilio, Flowroute, Telnyx, Bandwidth, TextNow</p>',
            'access' => 'public'
        ],
        [
            'question' => 'How do I set up voicemail?',
            'answer' => 'Voicemail is enabled by default for all extensions. To configure:
<ol>
    <li>Go to <strong>Extensions → [Your Extension] → Voicemail</strong></li>
    <li>Set:
        <ul>
            <li><strong>Voicemail PIN:</strong> Security PIN for accessing voicemail</li>
            <li><strong>Email:</strong> Receive voicemail notifications and recordings</li>
            <li><strong>Greeting:</strong> Record custom greeting or use default</li>
        </ul>
    </li>
    <li>Save settings</li>
</ol>
<p><strong>To check voicemail:</strong> Dial *98 from your extension or call your DID and press * during greeting.</p>',
            'access' => 'public'
        ],
        [
            'question' => 'How do I create a conference room?',
            'answer' => '<strong>Conference Room Setup:</strong>
<ol>
    <li>Go to <strong>Applications → Conferences</strong></li>
    <li>Click <strong>Add New Conference</strong></li>
    <li>Configure:
        <ul>
            <li><strong>Room Number:</strong> e.g., 3000</li>
            <li><strong>Room PIN:</strong> Optional security PIN</li>
            <li><strong>Admin PIN:</strong> PIN for moderator controls</li>
            <li><strong>Max Participants:</strong> Capacity limit</li>
            <li><strong>Record Conference:</strong> Enable/disable recording</li>
        </ul>
    </li>
    <li>Save and share the room number with participants</li>
</ol>
<p>Participants can dial the room number to join the conference.</p>',
            'access' => 'public'
        ],
    ],

    'troubleshooting' => [
        [
            'question' => 'My extension won\'t register. What should I check?',
            'answer' => '<strong>Extension Registration Troubleshooting:</strong>
<ol>
    <li><strong>Verify Credentials:</strong>
        <ul>
            <li>Extension number is correct</li>
            <li>Secret/password matches exactly</li>
            <li>Server address is correct (IP or domain)</li>
        </ul>
    </li>
    <li><strong>Check Network:</strong>
        <ul>
            <li>Port 5060 (UDP) is open in firewall</li>
            <li>No NAT issues (enable STUN if behind NAT)</li>
            <li>Server is reachable from client network</li>
        </ul>
    </li>
    <li><strong>Review Logs:</strong>
        <ul>
            <li>Admin → Logs → Asterisk Log</li>
            <li>Look for "Registration from" messages</li>
            <li>Check for "authentication failed" errors</li>
        </ul>
    </li>
    <li><strong>Test from CLI:</strong>
        <pre><code>asterisk -rx "sip show peers"
asterisk -rx "sip show registry"</code></pre>
    </li>
</ol>',
            'access' => 'public'
        ],
        [
            'question' => 'I can call but have no audio (one-way or no audio)',
            'answer' => '<strong>Audio Issues - Most Common Cause: RTP/Firewall</strong>
<br><br>
<strong>Quick Fixes:</strong>
<ol>
    <li><strong>Check RTP Ports:</strong>
        <pre><code>sudo ufw allow 10000:20000/udp</code></pre>
    </li>
    <li><strong>Configure NAT Settings:</strong>
        <ul>
            <li>Admin → Settings → SIP Settings</li>
            <li>Set <strong>External IP</strong> to your public IP</li>
            <li>Set <strong>Local Network</strong> to your private subnet</li>
            <li>Enable <strong>NAT</strong> if behind router</li>
        </ul>
    </li>
    <li><strong>Enable STUN:</strong>
        <ul>
            <li>In your SIP client, configure STUN server</li>
            <li>Use: stun.l.google.com:19302</li>
        </ul>
    </li>
    <li><strong>Check Codec Support:</strong>
        <ul>
            <li>Ensure both endpoints support same codec (ulaw, alaw, gsm)</li>
            <li>Admin → Settings → Codecs</li>
        </ul>
    </li>
</ol>',
            'access' => 'public'
        ],
        [
            'question' => 'How do I view system logs?',
            'answer' => '<strong>Log Locations:</strong>
<ul>
    <li><strong>Web Interface:</strong> Admin → System → Logs</li>
    <li><strong>Asterisk Full Log:</strong> <code>/var/log/asterisk/full</code></li>
    <li><strong>Apache Error Log:</strong> <code>/var/log/apache2/error.log</code></li>
    <li><strong>PHP Error Log:</strong> <code>/home/flexpbxuser/public_html/api/error_log</code></li>
    <li><strong>Module Installer Log:</strong> <code>/home/flexpbxuser/logs/module-installer.log</code></li>
</ul>
<br>
<strong>Real-Time Monitoring:</strong>
<pre><code># Watch Asterisk activity
asterisk -rvvv

# Tail full log
tail -f /var/log/asterisk/full

# SIP debug
asterisk -rx "sip set debug on"
</code></pre>',
            'access' => 'public'
        ],
        [
            'question' => 'The web interface is showing errors or blank page',
            'answer' => '<strong>Web Interface Troubleshooting:</strong>
<ol>
    <li><strong>Check PHP Errors:</strong>
        <pre><code>tail -100 /home/flexpbxuser/public_html/api/error_log</code></pre>
    </li>
    <li><strong>Verify File Permissions:</strong>
        <pre><code>cd /home/flexpbxuser/public_html
find . -type f -exec chmod 644 {} \\;
find . -type d -exec chmod 755 {} \\;</code></pre>
    </li>
    <li><strong>Check Database Connection:</strong>
        <pre><code>php -r "require \'/home/flexpbxuser/public_html/config/database.php\';"</code></pre>
    </li>
    <li><strong>Clear Browser Cache:</strong> Hard refresh (Ctrl+Shift+R)</li>
    <li><strong>Check Apache:</strong>
        <pre><code>systemctl status apache2</code></pre>
    </li>
</ol>',
            'access' => 'public'
        ],
    ],

    'security' => [
        [
            'question' => 'How do I secure my FlexPBX installation?',
            'answer' => '<strong>Security Best Practices:</strong>
<ol>
    <li><strong>Strong Passwords:</strong>
        <ul>
            <li>Use complex passwords for admin accounts</li>
            <li>Use random secrets for SIP extensions (20+ characters)</li>
            <li>Change default passwords immediately</li>
        </ul>
    </li>
    <li><strong>Firewall Rules:</strong>
        <ul>
            <li>Only allow SIP traffic from trusted IPs if possible</li>
            <li>Use fail2ban to block brute-force attempts</li>
            <li>Close unnecessary ports</li>
        </ul>
    </li>
    <li><strong>SSL/TLS:</strong>
        <ul>
            <li>Always use HTTPS for web interface</li>
            <li>Enable SIP over TLS (port 5061)</li>
            <li>Use valid SSL certificates (Let\'s Encrypt)</li>
        </ul>
    </li>
    <li><strong>Regular Updates:</strong>
        <ul>
            <li>Keep FlexPBX updated to latest version</li>
            <li>Update modules regularly</li>
            <li>Apply system security patches</li>
        </ul>
    </li>
</ol>',
            'access' => 'public'
        ],
        [
            'question' => 'How do I enable fail2ban for SIP protection?',
            'answer' => '<strong>Fail2ban Setup for Asterisk:</strong>
<pre><code># Install fail2ban
sudo apt-get install fail2ban

# Create Asterisk jail
sudo nano /etc/fail2ban/jail.local

# Add this configuration:
[asterisk]
enabled = true
port = 5060,5061
protocol = udp
filter = asterisk
logpath = /var/log/asterisk/full
maxretry = 5
bantime = 3600
findtime = 600

# Restart fail2ban
sudo systemctl restart fail2ban

# Check status
sudo fail2ban-client status asterisk
</code></pre>',
            'access' => 'public'
        ],
    ],
];

// Add private/admin sections if logged in
if ($is_logged_in) {
    $faq_data['advanced'] = [
        [
            'question' => 'How do I access the Asterisk CLI?',
            'answer' => '<strong>Asterisk Command Line Interface:</strong>
<pre><code># Connect to Asterisk CLI
asterisk -rvvv

# Common commands:
core show channels         # Show active calls
sip show peers            # Show registered extensions
sip show registry         # Show trunk registrations
dialplan reload           # Reload dialplan
core reload               # Reload all modules
database show             # Show AstDB entries

# Exit CLI:
exit
</code></pre>',
            'access' => 'private'
        ],
        [
            'question' => 'How do I create custom dialplan rules?',
            'answer' => '<strong>Custom Dialplan Example:</strong>
<pre><code>; Edit: /etc/asterisk/extensions_custom.conf

[custom-context]
exten => 999,1,Answer()
 same => n,Playback(hello-world)
 same => n,Hangup()

; Or use Admin → Dialplan → Custom Context
</code></pre>
<p><strong>Note:</strong> Changes in extensions_custom.conf survive system updates.</p>',
            'access' => 'private'
        ],
        [
            'question' => 'How do I backup and restore FlexPBX?',
            'answer' => '<strong>Backup Procedure:</strong>
<pre><code># 1. Backup database
mysqldump -u flexpbxuser_flexpbxserver -p flexpbxuser_flexpbxserver > flexpbx_backup.sql

# 2. Backup configuration files
tar -czf flexpbx_config_backup.tar.gz /etc/asterisk /home/flexpbxuser/public_html/config

# 3. Backup voicemail and recordings (optional)
tar -czf flexpbx_data_backup.tar.gz /var/spool/asterisk
</code></pre>
<br>
<strong>Restore Procedure:</strong>
<pre><code># 1. Restore database
mysql -u flexpbxuser_flexpbxserver -p flexpbxuser_flexpbxserver < flexpbx_backup.sql

# 2. Restore configuration
tar -xzf flexpbx_config_backup.tar.gz -C /

# 3. Restart services
systemctl restart asterisk
systemctl restart apache2
</code></pre>',
            'access' => 'private'
        ],
    ];
}

if ($is_admin) {
    $faq_data['admin'] = [
        [
            'question' => 'How do I develop custom modules?',
            'answer' => '<strong>Module Development Guide:</strong>
<br><br>
<strong>Module Structure:</strong>
<pre><code>my-module-1.0.0/
├── module.json          # Module metadata
├── install.sql          # Database schema
├── install.sh           # Installation script
├── files/
│   ├── api/            # API endpoints
│   ├── admin/          # Admin pages
│   ├── includes/       # PHP classes
│   └── cron/           # Cron scripts
└── README.md           # Documentation
</code></pre>
<br>
<strong>module.json Example:</strong>
<pre><code>{
  "module_info": {
    "key": "my-module",
    "name": "My Custom Module",
    "version": "1.0.0",
    "author": "Your Name",
    "category": "optional",
    "required": false,
    "description": "Module description"
  },
  "files": [
    {
      "source": "files/api/my-api.php",
      "destination": "/public_html/api/my-api.php",
      "permissions": "0644"
    }
  ],
  "dependencies": {
    "php": ">=7.4",
    "modules": ["checklist-system"]
  }
}
</code></pre>
<br>
<strong>Package and Distribute:</strong>
<pre><code># Create tarball
tar -czf my-module-1.0.0.tar.gz my-module-1.0.0/

# Upload to master server
scp my-module-1.0.0.tar.gz user@flexpbx.devinecreations.net:/home/flexpbxuser/public_html/downloads/modules/optional/
</code></pre>',
            'access' => 'admin'
        ],
        [
            'question' => 'How do I configure CopyParty for module distribution?',
            'answer' => '<strong>CopyParty Module Distribution Setup:</strong>
<br><br>
FlexPBX uses CopyParty as a secondary distribution method alongside HTTPS. This provides:
<ul>
    <li>Reliable file downloads</li>
    <li>Bandwidth efficiency</li>
    <li>Cross-platform compatibility</li>
    <li>Resume support for large files</li>
</ul>
<br>
<strong>Configuration:</strong>
<pre><code># Module installer checks protocols in order:
# 1. CopyParty (preferred)
# 2. HTTPS
# 3. FTP
# 4. SFTP
# 5. SCP

# CopyParty credentials in module-installer.php:
COPYPARTY_SERVER: https://files.devinecreations.net
COPYPARTY_BASE_PATH: /flexpbx-modules
COPYPARTY_USERNAME: flexpbx-public
COPYPARTY_PASSWORD: flexpbx2025
</code></pre>
<br>
<strong>Adding Modules to CopyParty:</strong>
<pre><code>cp module.tar.gz /home/flexpbxuser/copyparty-modules/optional/
chown flexpbxuser:flexpbxuser /home/flexpbxuser/copyparty-modules/optional/module.tar.gz
chmod 644 /home/flexpbxuser/copyparty-modules/optional/module.tar.gz
</code></pre>',
            'access' => 'admin'
        ],
        [
            'question' => 'How do I set up multi-protocol module downloads?',
            'answer' => '<strong>Multi-Protocol Download System:</strong>
<br><br>
FlexPBX module installer supports multiple transport protocols with automatic fallback:
<br><br>
<strong>Protocol Priority (configured in module-installer.php):</strong>
<ol>
    <li><strong>CopyParty:</strong> Primary method, fastest and most reliable</li>
    <li><strong>HTTPS:</strong> Direct download from master server</li>
    <li><strong>FTP:</strong> Traditional file transfer (if available)</li>
    <li><strong>SFTP:</strong> Secure FTP over SSH (if PHP ssh2 extension installed)</li>
    <li><strong>SCP:</strong> Secure copy (fallback using system command)</li>
</ol>
<br>
<strong>How It Works:</strong>
<pre><code>// Module installer tries each protocol until one succeeds
foreach (TRANSPORT_PROTOCOLS as $protocol) {
    if (downloadVia($protocol, $url, $destination)) {
        return true; // Success!
    }
}
// If all fail, show error
</code></pre>
<br>
<strong>Benefits:</strong>
<ul>
    <li>Works even if one method is unavailable</li>
    <li>Adapts to server configuration (no SSH2 ext? Uses HTTP)</li>
    <li>Logs each attempt for troubleshooting</li>
    <li>Seamless experience for end users</li>
</ul>',
            'access' => 'admin'
        ],
    ];
}

// Filter by category and search
$current_category = $category === 'all' ? array_keys($faq_data) : [$category];
$filtered_faqs = [];

foreach ($current_category as $cat) {
    if (!isset($faq_data[$cat])) continue;

    foreach ($faq_data[$cat] as $faq) {
        // Check access level
        if ($faq['access'] === 'private' && !$is_logged_in) continue;
        if ($faq['access'] === 'admin' && !$is_admin) continue;

        // Filter by search term
        if (!empty($search)) {
            $searchLower = strtolower($search);
            $questionLower = strtolower($faq['question']);
            $answerLower = strtolower(strip_tags($faq['answer']));

            if (strpos($questionLower, $searchLower) === false &&
                strpos($answerLower, $searchLower) === false) {
                continue;
            }
        }

        $filtered_faqs[] = [
            'category' => $cat,
            'question' => $faq['question'],
            'answer' => $faq['answer'],
            'access' => $faq['access']
        ];
    }
}

// Display FAQs
if (empty($filtered_faqs)) {
    echo '<div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            No FAQ items found matching your search.
          </div>';
} else {
    foreach ($filtered_faqs as $index => $faq) {
        $access_badge = '';
        if ($faq['access'] === 'private') {
            $access_badge = '<span class="badge private-badge ms-2">Private</span>';
        } elseif ($faq['access'] === 'admin') {
            $access_badge = '<span class="badge admin-badge ms-2">Admin Only</span>';
        }

        echo '<div class="card faq-item">
                <div class="faq-question">
                    <i class="fas fa-chevron-down me-2 text-primary"></i>
                    ' . htmlspecialchars($faq['question']) . '
                    ' . $access_badge . '
                </div>
                <div class="faq-answer">
                    ' . $faq['answer'] . '
                </div>
              </div>';
    }
}
?>
