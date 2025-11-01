<?php
/**
 * Simple Markdown to HTML Converter
 * Converts .md files to .html with proper styling
 */

function convertMarkdownToHtml($markdown) {
    $html = $markdown;

    // Escape HTML entities first
    $html = htmlspecialchars($html, ENT_QUOTES, 'UTF-8');

    // Convert code blocks (```)
    $html = preg_replace_callback('/```(\w+)?\n(.*?)\n```/s', function($matches) {
        $lang = $matches[1] ?? 'text';
        $code = $matches[2];
        return '<pre><code class="language-' . htmlspecialchars($lang) . '">' . $code . '</code></pre>';
    }, $html);

    // Convert inline code (`)
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

    // Convert headers (# ## ### etc)
    $html = preg_replace('/^######\s+(.+)$/m', '<h6>$1</h6>', $html);
    $html = preg_replace('/^#####\s+(.+)$/m', '<h5>$1</h5>', $html);
    $html = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $html);
    $html = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $html);

    // Convert bold (**text** or __text__)
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $html);

    // Convert italic (*text* or _text_)
    $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
    $html = preg_replace('/_(.+?)_/', '<em>$1</em>', $html);

    // Convert links [text](url)
    $html = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $html);

    // Convert horizontal rules (---)
    $html = preg_replace('/^---$/m', '<hr>', $html);

    // Convert unordered lists
    $html = preg_replace_callback('/^(\s*)[-*+]\s+(.+)$/m', function($matches) {
        $indent = strlen($matches[1]);
        return str_repeat('  ', $indent) . '<li>' . $matches[2] . '</li>';
    }, $html);

    // Wrap lists in <ul> tags
    $html = preg_replace('/(<li>.*<\/li>\n?)+/s', '<ul>$0</ul>', $html);

    // Convert ordered lists
    $html = preg_replace_callback('/^(\s*)\d+\.\s+(.+)$/m', function($matches) {
        $indent = strlen($matches[1]);
        return str_repeat('  ', $indent) . '<li>' . $matches[2] . '</li>';
    }, $html);

    // Convert blockquotes
    $html = preg_replace('/^>\s+(.+)$/m', '<blockquote>$1</blockquote>', $html);

    // Convert checkbox lists
    $html = preg_replace('/- \[ \]/', '<input type="checkbox" disabled>', $html);
    $html = preg_replace('/- \[x\]/', '<input type="checkbox" checked disabled>', $html);
    $html = preg_replace('/- \[X\]/', '<input type="checkbox" checked disabled>', $html);

    // Convert tables
    $html = preg_replace_callback('/^\|(.+)\|$/m', function($matches) {
        $cells = explode('|', trim($matches[1], '|'));
        $row = '<tr>';
        foreach ($cells as $cell) {
            $row .= '<td>' . trim($cell) . '</td>';
        }
        $row .= '</tr>';
        return $row;
    }, $html);

    // Wrap table rows in <table>
    $html = preg_replace('/(<tr>.*<\/tr>\n?)+/s', '<table>$0</table>', $html);

    // Convert paragraphs (double newline)
    $html = preg_replace('/\n\n/', '</p><p>', $html);
    $html = '<p>' . $html . '</p>';

    // Clean up empty paragraphs
    $html = preg_replace('/<p><\/p>/', '', $html);
    $html = preg_replace('/<p>(<h[1-6]>)/', '$1', $html);
    $html = preg_replace('/(<\/h[1-6]>)<\/p>/', '$1', $html);
    $html = preg_replace('/<p>(<pre>)/', '$1', $html);
    $html = preg_replace('/(<\/pre>)<\/p>/', '$1', $html);
    $html = preg_replace('/<p>(<hr>)<\/p>/', '$1', $html);
    $html = preg_replace('/<p>(<ul>)/', '$1', $html);
    $html = preg_replace('/(<\/ul>)<\/p>/', '$1', $html);
    $html = preg_replace('/<p>(<table>)/', '$1', $html);
    $html = preg_replace('/(<\/table>)<\/p>/', '$1', $html);
    $html = preg_replace('/<p>(<blockquote>)/', '$1', $html);
    $html = preg_replace('/(<\/blockquote>)<\/p>/', '$1', $html);

    // Convert single line breaks to <br>
    $html = preg_replace('/\n/', '<br>', $html);

    return $html;
}

function createHtmlFromMarkdown($mdFile, $outputDir) {
    if (!file_exists($mdFile)) {
        echo "Error: File not found: $mdFile\n";
        return false;
    }

    $markdown = file_get_contents($mdFile);
    $basename = basename($mdFile, '.md');
    $htmlFile = $outputDir . '/' . $basename . '.html';

    // Get title from first # heading
    preg_match('/^#\s+(.+)$/m', $markdown, $matches);
    $title = $matches[1] ?? $basename;

    $htmlContent = convertMarkdownToHtml($markdown);

    $fullHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title - FlexPBX Documentation</title>
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
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header {
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        h1 {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        h2 {
            color: #34495e;
            font-size: 1.8rem;
            margin-top: 30px;
            margin-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 10px;
        }

        h3 {
            color: #555;
            font-size: 1.4rem;
            margin-top: 25px;
            margin-bottom: 12px;
        }

        h4 {
            color: #666;
            font-size: 1.2rem;
            margin-top: 20px;
            margin-bottom: 10px;
        }

        h5, h6 {
            color: #777;
            margin-top: 15px;
            margin-bottom: 8px;
        }

        p {
            margin-bottom: 15px;
            line-height: 1.8;
        }

        ul, ol {
            margin-bottom: 15px;
            margin-left: 30px;
        }

        li {
            margin-bottom: 8px;
            line-height: 1.6;
        }

        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            color: #e74c3c;
        }

        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 6px;
            overflow-x: auto;
            margin-bottom: 20px;
        }

        pre code {
            background: none;
            color: inherit;
            padding: 0;
            font-size: 0.95em;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            overflow-x: auto;
            display: block;
        }

        table thead {
            background: #667eea;
            color: white;
        }

        table th,
        table td {
            padding: 12px 15px;
            text-align: left;
            border: 1px solid #ddd;
        }

        table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }

        table tbody tr:hover {
            background: #e9ecef;
        }

        blockquote {
            border-left: 4px solid #667eea;
            padding-left: 20px;
            margin: 20px 0;
            color: #555;
            font-style: italic;
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 4px;
        }

        hr {
            border: none;
            border-top: 2px solid #ecf0f1;
            margin: 30px 0;
        }

        a {
            color: #667eea;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        strong {
            font-weight: 600;
            color: #2c3e50;
        }

        em {
            font-style: italic;
            color: #555;
        }

        input[type="checkbox"] {
            margin-right: 8px;
        }

        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #ecf0f1;
            text-align: center;
            color: #999;
            font-size: 0.9em;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            h1 {
                font-size: 2rem;
            }

            h2 {
                font-size: 1.5rem;
            }

            table {
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.html" class="back-link">← Back to Documentation Index</a>

        <div class="header">
            <h1>$title</h1>
        </div>

        <div class="content">
            $htmlContent
        </div>

        <div class="footer">
            <p>FlexPBX Documentation | Generated from Markdown</p>
            <p><a href="$basename.md" download>Download Markdown Version</a></p>
        </div>
    </div>
</body>
</html>
HTML;

    file_put_contents($htmlFile, $fullHtml);
    echo "Created: $htmlFile\n";
    return true;
}

// Main execution
$docsDir = '/home/flexpbxuser/public_html/docs';
$mdFiles = glob($docsDir . '/*.md');

echo "Converting " . count($mdFiles) . " markdown files to HTML...\n\n";

foreach ($mdFiles as $mdFile) {
    createHtmlFromMarkdown($mdFile, $docsDir);
}

echo "\nConversion complete!\n";
