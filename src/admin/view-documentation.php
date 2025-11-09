<?php
require_once __DIR__ . '/admin_auth_check.php';
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$file = $_GET['file'] ?? '';
$docsPath = '/home/flexpbxuser/apps/flexpbx';
$filePath = "$docsPath/" . basename($file); // Prevent directory traversal

if (!file_exists($filePath)) {
    die('Documentation file not found');
}

$content = file_get_contents($filePath);

// Simple markdown to HTML conversion
function markdownToHtml($markdown) {
    // Headers
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $markdown);
    $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
    
    // Bold
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
    
    // Links
    $html = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $html);
    
    // Code blocks
    $html = preg_replace('/```([a-z]*)\n(.+?)```/s', '<pre><code class="language-$1">$2</code></pre>', $html);
    
    // Inline code
    $html = preg_replace('/`(.+?)`/', '<code>$1</code>', $html);
    
    // Lists
    $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/(<li>.+<\/li>\n)+/s', '<ul>$0</ul>', $html);
    
    // Line breaks
    $html = nl2br($html);
    
    return $html;
}

$htmlContent = markdownToHtml($content);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(basename($file)); ?> - FlexPBX Documentation</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 3rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .nav {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e0e6ed;
        }
        
        .nav a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-right: 1rem;
        }
        
        .nav a:hover {
            text-decoration: underline;
        }
        
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #667eea;
            padding-bottom: 0.5rem;
        }
        
        h2 {
            color: #34495e;
            margin-top: 2rem;
            border-bottom: 2px solid #e0e6ed;
            padding-bottom: 0.3rem;
        }
        
        h3 {
            color: #3498db;
            margin-top: 1.5rem;
        }
        
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 1rem;
            border-radius: 6px;
            overflow-x: auto;
        }
        
        pre code {
            background: transparent;
            color: #ecf0f1;
        }
        
        ul {
            margin-left: 1.5rem;
        }
        
        li {
            margin: 0.5rem 0;
        }
        
        a {
            color: #3498db;
        }
        
        a:hover {
            text-decoration: underline;
        }
        
        .print-btn {
            background: #667eea;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-right: 1rem;
        }
        
        .print-btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="/admin/documentation-center.php">← Back to Documentation Center</a>
            <a href="/admin/dashboard.php">Dashboard</a>
            <button class="print-btn" onclick="window.print()">🖨️ Print</button>
            <button class="print-btn" onclick="downloadMarkdown()">💾 Download</button>
        </div>
        
        <div class="content">
            <?php echo $htmlContent; ?>
        </div>
    </div>
    
    <script>
    function downloadMarkdown() {
        const filename = '<?php echo basename($file); ?>';
        const content = <?php echo json_encode($content); ?>;
        const blob = new Blob([content], { type: 'text/markdown' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    </script>
</body>
</html>
