<?php
/**
 * FlexPBX Legal Pages API
 * Manages privacy policy, terms of service, and other legal documents
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/config.php';

// Check authentication for admin actions
$action = $_GET['action'] ?? $_POST['action'] ?? 'get';
$requiresAuth = in_array($action, ['update', 'create', 'delete', 'publish']);

if ($requiresAuth) {
    session_start();
    $is_admin = ($_SESSION['admin_logged_in'] ?? false);
    $api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? '';

    if (!$is_admin && $api_key !== $config['api_key']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Admin authentication required']);
        exit;
    }
}

try {
    switch ($action) {
        case 'get':
            getLegalPage();
            break;
        case 'get_all':
            getAllPages();
            break;
        case 'update':
            updateLegalPage();
            break;
        case 'create':
            createLegalPage();
            break;
        case 'publish':
            publishPage();
            break;
        case 'get_history':
            getPageHistory();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getLegalPage() {
    global $pdo;

    $page_key = $_GET['page'] ?? '';

    if (empty($page_key)) {
        throw new Exception('Page key required');
    }

    $stmt = $pdo->prepare("SELECT * FROM legal_pages WHERE page_key = ? AND is_published = 1");
    $stmt->execute([$page_key]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$page) {
        // Return default content if page doesn't exist
        $page = getDefaultContent($page_key);
    }

    echo json_encode([
        'success' => true,
        'data' => $page
    ]);
}

function getAllPages() {
    global $pdo;

    $stmt = $pdo->query("SELECT id, page_key, page_title, last_updated, updated_by, version, is_published FROM legal_pages ORDER BY page_key");
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $pages
    ]);
}

function updateLegalPage() {
    global $pdo;
    session_start();

    $page_key = $_POST['page_key'] ?? '';
    $page_title = $_POST['page_title'] ?? '';
    $page_content = $_POST['page_content'] ?? '';
    $is_published = filter_var($_POST['is_published'] ?? true, FILTER_VALIDATE_BOOLEAN);

    if (empty($page_key) || empty($page_title) || empty($page_content)) {
        throw new Exception('Page key, title, and content required');
    }

    // Check if page exists
    $stmt = $pdo->prepare("SELECT id, version FROM legal_pages WHERE page_key = ?");
    $stmt->execute([$page_key]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update existing page
        $new_version = $existing['version'] + 1;
        $stmt = $pdo->prepare("
            UPDATE legal_pages
            SET page_title = ?, page_content = ?, updated_by = ?, version = ?, is_published = ?, last_updated = NOW()
            WHERE page_key = ?
        ");
        $stmt->execute([
            $page_title,
            $page_content,
            $_SESSION['admin_username'] ?? 'admin',
            $new_version,
            $is_published,
            $page_key
        ]);

        $message = 'Legal page updated successfully';
    } else {
        // Create new page
        $stmt = $pdo->prepare("
            INSERT INTO legal_pages (page_key, page_title, page_content, updated_by, version, is_published)
            VALUES (?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([
            $page_key,
            $page_title,
            $page_content,
            $_SESSION['admin_username'] ?? 'admin',
            $is_published
        ]);

        $message = 'Legal page created successfully';
    }

    // Log the change
    logPageChange($page_key, $existing ? 'update' : 'create', $page_content);

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
}

function createLegalPage() {
    global $pdo;
    session_start();

    $page_key = $_POST['page_key'] ?? '';
    $page_title = $_POST['page_title'] ?? '';
    $page_content = $_POST['page_content'] ?? '';

    if (empty($page_key) || empty($page_title)) {
        throw new Exception('Page key and title required');
    }

    $stmt = $pdo->prepare("
        INSERT INTO legal_pages (page_key, page_title, page_content, updated_by, version, is_published)
        VALUES (?, ?, ?, ?, 1, 0)
    ");
    $stmt->execute([
        $page_key,
        $page_title,
        $page_content,
        $_SESSION['admin_username'] ?? 'admin'
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Legal page created successfully',
        'id' => $pdo->lastInsertId()
    ]);
}

function publishPage() {
    global $pdo;

    $page_key = $_POST['page_key'] ?? '';
    $is_published = filter_var($_POST['is_published'] ?? true, FILTER_VALIDATE_BOOLEAN);

    if (empty($page_key)) {
        throw new Exception('Page key required');
    }

    $stmt = $pdo->prepare("UPDATE legal_pages SET is_published = ? WHERE page_key = ?");
    $stmt->execute([$is_published, $page_key]);

    echo json_encode([
        'success' => true,
        'message' => $is_published ? 'Page published' : 'Page unpublished'
    ]);
}

function getPageHistory() {
    global $pdo;

    $page_key = $_GET['page'] ?? '';

    if (empty($page_key)) {
        throw new Exception('Page key required');
    }

    // This would require a separate history table for full versioning
    // For now, return current version info
    $stmt = $pdo->prepare("SELECT * FROM legal_pages WHERE page_key = ?");
    $stmt->execute([$page_key]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [$page]
    ]);
}

function logPageChange($page_key, $action, $content) {
    // Log to file for audit trail
    $log_dir = '/home/flexpbxuser/logs/legal-pages';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $log_entry = [
        'timestamp' => date('c'),
        'page_key' => $page_key,
        'action' => $action,
        'user' => $_SESSION['admin_username'] ?? 'system',
        'content_length' => strlen($content)
    ];

    file_put_contents(
        $log_dir . '/changes.log',
        json_encode($log_entry) . "\n",
        FILE_APPEND
    );
}

function getDefaultContent($page_key) {
    $defaults = [
        'privacy-policy' => [
            'page_key' => 'privacy-policy',
            'page_title' => 'Privacy Policy',
            'page_content' => getDefaultPrivacyPolicy(),
            'last_updated' => date('Y-m-d H:i:s'),
            'updated_by' => 'system',
            'version' => 0,
            'is_published' => false
        ],
        'terms-of-service' => [
            'page_key' => 'terms-of-service',
            'page_title' => 'Terms of Service',
            'page_content' => getDefaultTermsOfService(),
            'last_updated' => date('Y-m-d H:i:s'),
            'updated_by' => 'system',
            'version' => 0,
            'is_published' => false
        ],
        'acceptable-use' => [
            'page_key' => 'acceptable-use',
            'page_title' => 'Acceptable Use Policy',
            'page_content' => getDefaultAcceptableUse(),
            'last_updated' => date('Y-m-d H:i:s'),
            'updated_by' => 'system',
            'version' => 0,
            'is_published' => false
        ]
    ];

    return $defaults[$page_key] ?? null;
}

function getDefaultPrivacyPolicy() {
    return <<<HTML
<h1>Privacy Policy</h1>
<p><em>Last Updated: [DATE]</em></p>

<h2>1. Introduction</h2>
<p>This Privacy Policy describes how [YOUR ORGANIZATION] ("we," "us," or "our") collects, uses, and protects your information when you use our FlexPBX communication services.</p>

<h2>2. Information We Collect</h2>
<h3>2.1 Account Information</h3>
<ul>
    <li>Name and contact information</li>
    <li>Email address</li>
    <li>Phone numbers</li>
    <li>Billing information</li>
</ul>

<h3>2.2 Usage Information</h3>
<ul>
    <li>Call detail records (CDR)</li>
    <li>System logs</li>
    <li>IP addresses</li>
    <li>Device information</li>
</ul>

<h3>2.3 Communication Content</h3>
<ul>
    <li>Voicemail messages</li>
    <li>Call recordings (if enabled)</li>
    <li>SMS/text messages</li>
</ul>

<h2>3. How We Use Your Information</h2>
<p>We use your information to:</p>
<ul>
    <li>Provide and maintain our communication services</li>
    <li>Process billing and payments</li>
    <li>Improve service quality and user experience</li>
    <li>Comply with legal obligations</li>
    <li>Detect and prevent fraud or abuse</li>
</ul>

<h2>4. Data Storage and Security</h2>
<p>Your data is stored on secure servers with encryption at rest and in transit. We implement industry-standard security measures including:</p>
<ul>
    <li>SSL/TLS encryption for data transmission</li>
    <li>Encrypted database storage</li>
    <li>Regular security audits</li>
    <li>Access controls and authentication</li>
    <li>Backup and disaster recovery systems</li>
</ul>

<h2>5. Data Retention</h2>
<p>We retain your information for as long as necessary to provide services and comply with legal obligations:</p>
<ul>
    <li>Call detail records: 7 years (legal requirement)</li>
    <li>Voicemail: Until deleted by user</li>
    <li>Account information: Duration of service plus 1 year</li>
    <li>System logs: 30-90 days (configurable)</li>
</ul>

<h2>6. Third-Party Sharing</h2>
<p>We do not sell your personal information. We may share information with:</p>
<ul>
    <li>Telecommunications carriers (for call completion)</li>
    <li>Payment processors (for billing)</li>
    <li>Law enforcement (when legally required)</li>
    <li>Service providers (under confidentiality agreements)</li>
</ul>

<h2>7. Your Rights</h2>
<p>You have the right to:</p>
<ul>
    <li>Access your personal information</li>
    <li>Request correction of inaccurate data</li>
    <li>Request deletion of your data</li>
    <li>Export your data</li>
    <li>Opt-out of non-essential communications</li>
    <li>Disable call recording features</li>
</ul>

<h2>8. GDPR Compliance</h2>
<p>For users in the European Economic Area (EEA), we comply with GDPR requirements including:</p>
<ul>
    <li>Lawful basis for processing</li>
    <li>Data minimization</li>
    <li>Right to be forgotten</li>
    <li>Data portability</li>
    <li>Breach notification within 72 hours</li>
</ul>

<h2>9. CCPA Compliance</h2>
<p>For California residents, we comply with CCPA including:</p>
<ul>
    <li>Right to know what data is collected</li>
    <li>Right to delete personal information</li>
    <li>Right to opt-out of data sales (we don't sell data)</li>
    <li>Non-discrimination for exercising rights</li>
</ul>

<h2>10. Children's Privacy</h2>
<p>Our services are not intended for users under 13 years of age. We do not knowingly collect information from children.</p>

<h2>11. Changes to This Policy</h2>
<p>We may update this Privacy Policy periodically. We will notify users of material changes via email or system notification.</p>

<h2>12. Contact Us</h2>
<p>For privacy-related questions or requests:</p>
<ul>
    <li>Email: privacy@yourdomain.com</li>
    <li>Phone: [YOUR PHONE]</li>
    <li>Address: [YOUR ADDRESS]</li>
</ul>
HTML;
}

function getDefaultTermsOfService() {
    return <<<HTML
<h1>Terms of Service</h1>
<p><em>Last Updated: [DATE]</em></p>

<h2>1. Acceptance of Terms</h2>
<p>By accessing and using FlexPBX services, you agree to be bound by these Terms of Service and all applicable laws and regulations.</p>

<h2>2. Service Description</h2>
<p>FlexPBX provides cloud-based and self-hosted private branch exchange (PBX) communication services including:</p>
<ul>
    <li>Voice calling (VoIP)</li>
    <li>SMS/text messaging</li>
    <li>Video conferencing</li>
    <li>Call recording</li>
    <li>Interactive voice response (IVR)</li>
    <li>Conference bridges</li>
    <li>Voicemail services</li>
</ul>

<h2>3. Account Registration</h2>
<h3>3.1 Account Requirements</h3>
<ul>
    <li>You must be at least 18 years old</li>
    <li>Provide accurate and complete information</li>
    <li>Maintain the security of your account</li>
    <li>Notify us of any unauthorized access</li>
</ul>

<h3>3.2 Account Responsibility</h3>
<p>You are responsible for all activities that occur under your account and for maintaining the confidentiality of your credentials.</p>

<h2>4. Acceptable Use</h2>
<p>You agree NOT to use FlexPBX services for:</p>
<ul>
    <li>Illegal activities or purposes</li>
    <li>Harassment, threats, or abuse</li>
    <li>Spam or unsolicited marketing</li>
    <li>Fraud or impersonation</li>
    <li>Distributing malware or viruses</li>
    <li>Interfering with service operation</li>
    <li>Violating intellectual property rights</li>
    <li>Auto-dialing or robocalling without proper consent</li>
</ul>

<h2>5. Service Availability</h2>
<h3>5.1 Uptime</h3>
<p>We strive for 99.9% uptime but do not guarantee uninterrupted service. Maintenance windows will be scheduled during off-peak hours when possible.</p>

<h3>5.2 Service Limitations</h3>
<ul>
    <li>Bandwidth limitations may apply</li>
    <li>Storage quotas based on your plan</li>
    <li>Concurrent call limits based on your plan</li>
    <li>Geographic restrictions may apply</li>
</ul>

<h2>6. Billing and Payments</h2>
<h3>6.1 Fees</h3>
<ul>
    <li>Monthly/annual subscription fees</li>
    <li>Per-minute charges for certain destinations</li>
    <li>SMS messaging charges</li>
    <li>Additional feature charges</li>
</ul>

<h3>6.2 Payment Terms</h3>
<ul>
    <li>Payments are due in advance</li>
    <li>Auto-renewal unless cancelled</li>
    <li>Late fees may apply</li>
    <li>Service suspension for non-payment</li>
</ul>

<h3>6.3 Refund Policy</h3>
<p>Refunds available within 30 days for new customers. Pro-rated refunds may be available at our discretion.</p>

<h2>7. Emergency Services (E911)</h2>
<p><strong>IMPORTANT:</strong> FlexPBX VoIP services differ from traditional phone services:</p>
<ul>
    <li>E911 requires manual address configuration</li>
    <li>Service depends on internet connectivity and power</li>
    <li>Location information may not be automatically transmitted</li>
    <li>You must maintain accurate address information</li>
    <li>Consider maintaining backup emergency communication methods</li>
</ul>

<h2>8. Call Recording</h2>
<p>If using call recording features:</p>
<ul>
    <li>You are responsible for compliance with applicable laws</li>
    <li>You must obtain proper consent from call participants</li>
    <li>You must provide required notifications</li>
    <li>You are responsible for secure storage of recordings</li>
</ul>

<h2>9. Number Portability</h2>
<p>You may port your existing phone numbers to FlexPBX. Porting may take 7-14 business days. You must maintain service with your current provider until porting is complete.</p>

<h2>10. Intellectual Property</h2>
<p>FlexPBX and all related software, trademarks, and content are owned by [YOUR ORGANIZATION]. You retain ownership of your data and communications.</p>

<h2>11. Data Backup</h2>
<p>While we maintain regular backups, you are responsible for maintaining your own backups of critical data including:</p>
<ul>
    <li>Call recordings</li>
    <li>Voicemail messages</li>
    <li>Configuration settings</li>
    <li>Custom IVR prompts</li>
</ul>

<h2>12. Termination</h2>
<h3>12.1 By You</h3>
<p>You may cancel your account at any time with 30 days notice. Data will be retained for 30 days after cancellation.</p>

<h3>12.2 By Us</h3>
<p>We may suspend or terminate your account for:</p>
<ul>
    <li>Violation of these Terms</li>
    <li>Non-payment</li>
    <li>Fraudulent activity</li>
    <li>Abuse of service</li>
    <li>Legal requirements</li>
</ul>

<h2>13. Limitation of Liability</h2>
<p>FlexPBX services are provided "as is" without warranties. We are not liable for:</p>
<ul>
    <li>Service interruptions</li>
    <li>Data loss</li>
    <li>Indirect or consequential damages</li>
    <li>Third-party actions</li>
    <li>Force majeure events</li>
</ul>

<h2>14. Indemnification</h2>
<p>You agree to indemnify and hold harmless [YOUR ORGANIZATION] from claims arising from your use of services or violation of these Terms.</p>

<h2>15. Governing Law</h2>
<p>These Terms are governed by the laws of [YOUR JURISDICTION]. Disputes will be resolved through binding arbitration.</p>

<h2>16. Changes to Terms</h2>
<p>We may modify these Terms with 30 days notice. Continued use constitutes acceptance of modified Terms.</p>

<h2>17. Contact Information</h2>
<p>For questions about these Terms:</p>
<ul>
    <li>Email: legal@yourdomain.com</li>
    <li>Phone: [YOUR PHONE]</li>
    <li>Address: [YOUR ADDRESS]</li>
</ul>
HTML;
}

function getDefaultAcceptableUse() {
    return <<<HTML
<h1>Acceptable Use Policy</h1>
<p><em>Last Updated: [DATE]</em></p>

<h2>1. Purpose</h2>
<p>This Acceptable Use Policy (AUP) outlines prohibited uses of FlexPBX communication services to ensure service quality, security, and legal compliance for all users.</p>

<h2>2. Prohibited Activities</h2>

<h3>2.1 Illegal Activities</h3>
<ul>
    <li>Any activity that violates local, state, federal, or international law</li>
    <li>Fraud, phishing, or impersonation</li>
    <li>Money laundering or financial crimes</li>
    <li>Drug trafficking or illegal substances</li>
    <li>Human trafficking or exploitation</li>
</ul>

<h3>2.2 Abusive Communications</h3>
<ul>
    <li>Harassment, threats, or intimidation</li>
    <li>Hate speech or discriminatory content</li>
    <li>Stalking or unwanted contact</li>
    <li>Revenge or non-consensual intimate content</li>
</ul>

<h3>2.3 Spam and Unsolicited Communications</h3>
<ul>
    <li>Mass unsolicited voice calls (robocalls)</li>
    <li>Unsolicited SMS/text message campaigns</li>
    <li>Auto-dialing without proper consent</li>
    <li>Violation of TCPA, CAN-SPAM, or similar regulations</li>
    <li>Spoofing caller ID information deceptively</li>
</ul>

<h3>2.4 Service Abuse</h3>
<ul>
    <li>Excessive use that degrades service for others</li>
    <li>Circumventing usage limits or quotas</li>
    <li>Reselling service without authorization</li>
    <li>Using service for cryptocurrency mining</li>
    <li>Network scanning or penetration testing</li>
</ul>

<h3>2.5 Security Violations</h3>
<ul>
    <li>Hacking or unauthorized access attempts</li>
    <li>Distributing malware, viruses, or exploits</li>
    <li>Denial of service attacks</li>
    <li>Password cracking or brute force attacks</li>
    <li>Exploiting system vulnerabilities</li>
</ul>

<h3>2.6 Intellectual Property Violations</h3>
<ul>
    <li>Copyright infringement</li>
    <li>Trademark infringement</li>
    <li>Piracy or distribution of unauthorized content</li>
    <li>Trade secret theft</li>
</ul>

<h2>3. Compliance Requirements</h2>

<h3>3.1 Telemarketing</h3>
<p>If using FlexPBX for telemarketing, you must:</p>
<ul>
    <li>Comply with TCPA regulations</li>
    <li>Maintain proper consent records</li>
    <li>Honor Do Not Call (DNC) lists</li>
    <li>Provide opt-out mechanisms</li>
    <li>Identify yourself and purpose of call</li>
    <li>Call only during permitted hours</li>
</ul>

<h3>3.2 Call Recording</h3>
<p>When recording calls, you must:</p>
<ul>
    <li>Comply with one-party or two-party consent laws</li>
    <li>Provide required notifications</li>
    <li>Secure recorded content appropriately</li>
    <li>Honor opt-out requests</li>
</ul>

<h3>3.3 Healthcare (HIPAA)</h3>
<p>If transmitting Protected Health Information (PHI):</p>
<ul>
    <li>Execute a Business Associate Agreement (BAA)</li>
    <li>Enable required security features</li>
    <li>Maintain proper access controls</li>
    <li>Follow breach notification requirements</li>
</ul>

<h3>3.4 Financial Services</h3>
<p>If operating in financial services:</p>
<ul>
    <li>Comply with GLBA regulations</li>
    <li>Maintain proper security controls</li>
    <li>Protect customer financial information</li>
</ul>

<h2>4. Resource Usage Guidelines</h2>

<h3>4.1 Fair Use</h3>
<ul>
    <li>Use service in good faith for legitimate purposes</li>
    <li>Do not consume excessive bandwidth</li>
    <li>Respect storage quotas</li>
    <li>Maintain reasonable call volumes</li>
</ul>

<h3>4.2 High-Volume Usage</h3>
<p>For high-volume calling (e.g., call centers):</p>
<ul>
    <li>Contact sales for appropriate plan</li>
    <li>Ensure proper capacity provisioning</li>
    <li>Implement rate limiting</li>
    <li>Monitor and manage usage patterns</li>
</ul>

<h2>5. Monitoring and Enforcement</h2>

<h3>5.1 Monitoring</h3>
<p>We may monitor usage to:</p>
<ul>
    <li>Ensure compliance with this AUP</li>
    <li>Detect fraud or abuse</li>
    <li>Maintain service quality</li>
    <li>Comply with legal obligations</li>
</ul>

<h3>5.2 Violations</h3>
<p>Violations may result in:</p>
<ul>
    <li>Warning notification</li>
    <li>Temporary service suspension</li>
    <li>Service rate limiting</li>
    <li>Account termination</li>
    <li>Legal action</li>
    <li>Reporting to authorities</li>
</ul>

<h2>6. Reporting Abuse</h2>
<p>To report abuse or violations:</p>
<ul>
    <li>Email: abuse@yourdomain.com</li>
    <li>Phone: [YOUR PHONE]</li>
    <li>Include: date, time, phone numbers, description</li>
</ul>

<h2>7. Changes to This Policy</h2>
<p>We may update this AUP at any time. Continued use constitutes acceptance of changes.</p>

<h2>8. Questions</h2>
<p>Contact us at legal@yourdomain.com for questions about this policy.</p>
HTML;
}
