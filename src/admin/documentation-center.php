<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX Documentation Center
 * Centralized access to all documentation and guides
 */

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$admin_username = $_SESSION['admin_username'] ?? 'Unknown';
$admin_role = $_SESSION['admin_role'] ?? 'admin';

// Documentation files
$docsPath = '/home/flexpbxuser/apps/flexpbx';
$docs = [
    'system' => [
        'title' => 'System Status & Implementation',
        'files' => [
            ['file' => 'COMPLETE_SYSTEM_STATUS_NOV9_2025.md', 'title' => 'Complete System Status', 'desc' => 'Current system overview - 16 extensions, 6 MOH classes, 8 IVR templates'],
            ['file' => 'SESSION_COMPLETE_NOV9_2025.md', 'title' => 'Session Implementation Log', 'desc' => 'Feature codes, IVR templates, admin pages created'],
            ['file' => 'FINAL_SESSION_SUMMARY_NOV9_2025.md', 'title' => 'Final Session Summary', 'desc' => 'AzuraCast integration, testing checklists'],
        ]
    ],
    'setup' => [
        'title' => 'Setup & Configuration Guides',
        'files' => [
            ['file' => 'INSTALLER_DIALPLAN_INTEGRATION.md', 'title' => 'Dialplan Integration', 'desc' => 'Auto-configuration scripts, feature code setup'],
            ['file' => 'FREEPBX_COMPATIBILITY_GUIDE.md', 'title' => 'FreePBX Compatibility', 'desc' => '38 verified prompts, IVR templates, audio formats'],
            ['file' => 'AZURACAST_INTEGRATION.md', 'title' => 'AzuraCast Integration', 'desc' => 'TappedIn Radio, Raywonder Radio, conference audio'],
            ['file' => 'FLEXPBX_XMPP_INTEGRATION.md', 'title' => 'XMPP Integration', 'desc' => 'Prosody installation, web client, database schema'],
        ]
    ],
    'user_management' => [
        'title' => 'User Management',
        'files' => [
            ['file' => 'USER_INVITATION_QUICK_START.md', 'title' => 'User Invitation Quick Start', 'desc' => 'Invite users, create departments, auto-assign extensions'],
            ['file' => 'USER_MIGRATION_COMPLETE_GUIDE.md', 'title' => 'User Migration Complete Guide', 'desc' => 'Extension changes, department transfers, bulk operations'],
            ['file' => 'USER_MIGRATION_SUMMARY_NOV9_2025.md', 'title' => 'Migration Implementation Summary', 'desc' => 'What gets auto-updated, database schema, testing'],
        ]
    ],
    'reference' => [
        'title' => 'Quick References',
        'files' => [
            ['file' => 'FLEXPBX_QUICK_REFERENCE_CARD.md', 'title' => 'Quick Reference Card', 'desc' => 'All 13 feature codes, troubleshooting tips'],
            ['file' => 'DOCUMENTATION_INDEX.md', 'title' => 'Documentation Index', 'desc' => 'Complete index of all guides and documentation'],
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation Center - FlexPBX Admin</title>
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
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
        }

        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            opacity: 0.9;
        }

        .nav-links {
            background: #f8f9fa;
            padding: 1rem;
            border-bottom: 2px solid #e0e6ed;
        }

        .nav-links a {
            color: #667eea;
            text-decoration: none;
            margin-right: 1rem;
            font-weight: 600;
        }

        .nav-links a:hover {
            text-decoration: underline;
        }

        .search-box {
            padding: 2rem;
            background: #f8f9fa;
            border-bottom: 2px solid #e0e6ed;
        }

        .search-box input {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            transition: border-color 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .content {
            padding: 2rem;
        }

        .doc-category {
            margin-bottom: 3rem;
        }

        .category-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid #667eea;
        }

        .doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .doc-card {
            background: white;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            padding: 1.5rem;
            transition: all 0.3s;
            cursor: pointer;
        }

        .doc-card:hover {
            border-color: #667eea;
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.2);
        }

        .doc-card h3 {
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .doc-card p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .doc-card .doc-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e0e6ed;
        }

        .doc-badge {
            background: #667eea;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .doc-link {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
        }

        .doc-link:hover {
            text-decoration: underline;
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Documentation Center</h1>
            <p>Complete FlexPBX documentation, guides, and references</p>
        </div>

        <div class="nav-links">
            <a href="/admin/dashboard.php">← Back to Dashboard</a>
            <a href="/admin/help-center.php">Help Center</a>
            <a href="../faq.php">FAQ</a>
        </div>

        <div class="search-box">
            <input type="text" id="docSearch" placeholder="Search documentation... (e.g., 'extension migration', 'IVR templates', 'feature codes')" />
        </div>

        <div class="content">
            <div class="quick-stats">
                <div class="stat-card">
                    <div class="stat-value">15+</div>
                    <div class="stat-label">Documentation Files</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">3000+</div>
                    <div class="stat-label">Total Pages</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">100%</div>
                    <div class="stat-label">Coverage</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">Current</div>
                    <div class="stat-label">Last Updated: Nov 9</div>
                </div>
            </div>

            <?php foreach ($docs as $categoryKey => $category): ?>
                <div class="doc-category">
                    <h2 class="category-title"><?php echo htmlspecialchars($category['title']); ?></h2>
                    <div class="doc-grid">
                        <?php foreach ($category['files'] as $doc): ?>
                            <?php
                            $filePath = "$docsPath/{$doc['file']}";
                            $fileExists = file_exists($filePath);
                            $fileSize = $fileExists ? filesize($filePath) : 0;
                            $lines = $fileExists ? count(file($filePath)) : 0;
                            ?>
                            <div class="doc-card" onclick="window.location.href='view-documentation.php?file=<?php echo urlencode($doc['file']); ?>'">
                                <h3><?php echo htmlspecialchars($doc['title']); ?></h3>
                                <p><?php echo htmlspecialchars($doc['desc']); ?></p>
                                <div class="doc-meta">
                                    <span class="doc-badge"><?php echo $lines; ?> lines</span>
                                    <a href="view-documentation.php?file=<?php echo urlencode($doc['file']); ?>" class="doc-link" onclick="event.stopPropagation()">View →</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    // Search functionality
    document.getElementById('docSearch').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const cards = document.querySelectorAll('.doc-card');
        
        cards.forEach(card => {
            const title = card.querySelector('h3').textContent.toLowerCase();
            const desc = card.querySelector('p').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || desc.includes(searchTerm)) {
                card.style.display = '';
            } else {
                card.style.display = searchTerm === '' ? '' : 'none';
            }
        });
    });
    </script>
</body>
</html>
