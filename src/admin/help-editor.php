<?php
/**
 * FlexPBX Help Editor (Admin)
 * Manage help articles and tooltips
 */

session_start();

// Admin authentication check
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    header('Location: /admin/');
    exit;
}

$pageTitle = 'Help Editor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - FlexPBX Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: white;
            color: #667eea;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .tabs {
            background: white;
            border-radius: 10px 10px 0 0;
            padding: 0;
            display: flex;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .tab {
            flex: 1;
            padding: 15px 20px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 15px;
            transition: all 0.3s;
        }

        .tab:hover {
            background: #f8f9fa;
        }

        .tab.active {
            border-bottom-color: #667eea;
            color: #667eea;
            font-weight: 600;
        }

        .tab-content {
            display: none;
            background: white;
            border-radius: 0 0 10px 10px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .tab-content.active {
            display: block;
        }

        .articles-list {
            margin-top: 20px;
        }

        .article-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }

        .article-item:hover {
            background: #e9ecef;
        }

        .article-info h3 {
            font-size: 16px;
            margin-bottom: 5px;
        }

        .article-meta {
            font-size: 13px;
            color: #666;
        }

        .article-actions {
            display: flex;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            border-color: #667eea;
        }

        select.form-control {
            cursor: pointer;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .editor-container {
            border: 1px solid #ddd;
            border-radius: 6px;
            overflow: hidden;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .modal.visible {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 20px;
            background: #667eea;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 20px;
        }

        .modal-close {
            background: transparent;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
        }

        .modal-body {
            padding: 30px;
        }

        .modal-footer {
            padding: 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .article-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .tabs {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-container">
            <h1><i class="fas fa-life-ring"></i> Help Editor</h1>
            <div class="header-actions">
                <a href="/help/" target="_blank" class="btn btn-primary">
                    <i class="fas fa-external-link-alt"></i> View Help Center
                </a>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="tabs">
            <button class="tab active" onclick="switchTab('articles')">
                <i class="fas fa-book"></i> Help Articles
            </button>
            <button class="tab" onclick="switchTab('tooltips')">
                <i class="fas fa-info-circle"></i> Tooltips
            </button>
        </div>

        <!-- Articles Tab -->
        <div id="articles-tab" class="tab-content active">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Manage Help Articles</h2>
                <button class="btn btn-success" onclick="openArticleModal()">
                    <i class="fas fa-plus"></i> New Article
                </button>
            </div>

            <div id="articles-list" class="articles-list">
                <p style="text-align: center; color: #999; padding: 40px;">Loading articles...</p>
            </div>
        </div>

        <!-- Tooltips Tab -->
        <div id="tooltips-tab" class="tab-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Manage Tooltips</h2>
                <button class="btn btn-success" onclick="openTooltipModal()">
                    <i class="fas fa-plus"></i> New Tooltip
                </button>
            </div>

            <div id="tooltips-list" class="articles-list">
                <p style="text-align: center; color: #999; padding: 40px;">Loading tooltips...</p>
            </div>
        </div>
    </div>

    <!-- Article Modal -->
    <div id="article-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="article-modal-title">New Help Article</h2>
                <button class="modal-close" onclick="closeArticleModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="article-alert"></div>
                <form id="article-form">
                    <input type="hidden" id="article-id" name="id">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Article Key *</label>
                            <input type="text" class="form-control" id="article-key" name="article_key" required>
                            <small style="color: #666;">Unique identifier (e.g., getting-started-extensions)</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Category *</label>
                            <select class="form-control" id="article-category" name="category" required>
                                <option value="getting-started">Getting Started</option>
                                <option value="extensions">Extensions</option>
                                <option value="calling">Calling</option>
                                <option value="voicemail">Voicemail</option>
                                <option value="sms">SMS Messaging</option>
                                <option value="call-recording">Call Recording</option>
                                <option value="ivr">IVR Configuration</option>
                                <option value="reports">Reports & Analytics</option>
                                <option value="user-management">User Management</option>
                                <option value="settings">System Settings</option>
                                <option value="troubleshooting">Troubleshooting</option>
                                <option value="security">Security</option>
                                <option value="integrations">Integrations</option>
                                <option value="mobile">Mobile Apps</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Title *</label>
                        <input type="text" class="form-control" id="article-title" name="title" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Page Context</label>
                            <input type="text" class="form-control" id="article-context" name="page_context" placeholder="e.g., dashboard, extensions">
                            <small style="color: #666;">Shows article on specific pages</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Keywords</label>
                            <input type="text" class="form-control" id="article-keywords" name="keywords" placeholder="comma, separated, keywords">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Sort Order</label>
                            <input type="number" class="form-control" id="article-sort" name="sort_order" value="0">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-control" id="article-status" name="is_published">
                                <option value="1">Published</option>
                                <option value="0">Draft</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Content *</label>
                        <div class="editor-container">
                            <textarea id="article-content" name="content"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeArticleModal()">Cancel</button>
                <button class="btn btn-success" onclick="saveArticle()">
                    <i class="fas fa-save"></i> Save Article
                </button>
            </div>
        </div>
    </div>

    <!-- Tooltip Modal -->
    <div id="tooltip-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="tooltip-modal-title">New Tooltip</h2>
                <button class="modal-close" onclick="closeTooltipModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="tooltip-alert"></div>
                <form id="tooltip-form">
                    <input type="hidden" id="tooltip-id" name="id">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Element ID *</label>
                            <input type="text" class="form-control" id="tooltip-element" name="element_id" required>
                            <small style="color: #666;">Unique identifier for tooltip</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Page *</label>
                            <input type="text" class="form-control" id="tooltip-page" name="page" required placeholder="e.g., dashboard">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" id="tooltip-title" name="title">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Content *</label>
                        <textarea class="form-control" id="tooltip-content" name="content" rows="4" required></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Position</label>
                            <select class="form-control" id="tooltip-position" name="position">
                                <option value="top">Top</option>
                                <option value="bottom">Bottom</option>
                                <option value="left">Left</option>
                                <option value="right">Right</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-control" id="tooltip-status" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeTooltipModal()">Cancel</button>
                <button class="btn btn-success" onclick="saveTooltip()">
                    <i class="fas fa-save"></i> Save Tooltip
                </button>
            </div>
        </div>
    </div>

    <script>
        // Initialize TinyMCE
        tinymce.init({
            selector: '#article-content',
            height: 400,
            menubar: false,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount'
            ],
            toolbar: 'undo redo | blocks | bold italic forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | code | help',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif; font-size: 14px; }'
        });

        // Tab switching
        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            event.target.classList.add('active');
            document.getElementById(tab + '-tab').classList.add('active');

            if (tab === 'articles') {
                loadArticles();
            } else if (tab === 'tooltips') {
                loadTooltips();
            }
        }

        // Load articles
        async function loadArticles() {
            try {
                const response = await fetch('/api/help.php?action=get_all');
                const data = await response.json();

                if (data.success) {
                    renderArticles(data.articles);
                }
            } catch (error) {
                console.error('Error loading articles:', error);
            }
        }

        function renderArticles(articles) {
            const container = document.getElementById('articles-list');

            if (articles.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">No articles yet. Create your first one!</p>';
                return;
            }

            let html = '';
            articles.forEach(article => {
                html += `
                    <div class="article-item">
                        <div class="article-info">
                            <h3>${article.title}</h3>
                            <div class="article-meta">
                                <span class="badge ${article.is_published ? 'badge-success' : 'badge-warning'}">
                                    ${article.is_published ? 'Published' : 'Draft'}
                                </span>
                                <span style="margin-left: 10px;">${article.category}</span>
                                <span style="margin-left: 10px;">Updated: ${new Date(article.updated_at).toLocaleDateString()}</span>
                            </div>
                        </div>
                        <div class="article-actions">
                            <button class="btn btn-primary" onclick='editArticle(${JSON.stringify(article)})'>
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-danger" onclick="deleteArticle(${article.id})">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Article modal functions
        function openArticleModal() {
            document.getElementById('article-modal-title').textContent = 'New Help Article';
            document.getElementById('article-form').reset();
            document.getElementById('article-id').value = '';
            tinymce.get('article-content').setContent('');
            document.getElementById('article-modal').classList.add('visible');
        }

        function closeArticleModal() {
            document.getElementById('article-modal').classList.remove('visible');
        }

        function editArticle(article) {
            document.getElementById('article-modal-title').textContent = 'Edit Help Article';
            document.getElementById('article-id').value = article.id;
            document.getElementById('article-key').value = article.article_key;
            document.getElementById('article-title').value = article.title;
            document.getElementById('article-category').value = article.category;
            document.getElementById('article-context').value = article.page_context || '';
            document.getElementById('article-keywords').value = article.keywords || '';
            document.getElementById('article-sort').value = article.sort_order;
            document.getElementById('article-status').value = article.is_published;
            tinymce.get('article-content').setContent(article.content);
            document.getElementById('article-modal').classList.add('visible');
        }

        async function saveArticle() {
            const form = document.getElementById('article-form');
            const data = {
                id: document.getElementById('article-id').value,
                article_key: document.getElementById('article-key').value,
                title: document.getElementById('article-title').value,
                category: document.getElementById('article-category').value,
                page_context: document.getElementById('article-context').value,
                keywords: document.getElementById('article-keywords').value,
                sort_order: document.getElementById('article-sort').value,
                is_published: document.getElementById('article-status').value,
                content: tinymce.get('article-content').getContent()
            };

            try {
                const response = await fetch('/api/help.php?action=save_article', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    closeArticleModal();
                    loadArticles();
                } else {
                    document.getElementById('article-alert').innerHTML =
                        `<div class="alert alert-danger">${result.error}</div>`;
                }
            } catch (error) {
                document.getElementById('article-alert').innerHTML =
                    `<div class="alert alert-danger">Error saving article</div>`;
            }
        }

        async function deleteArticle(id) {
            if (!confirm('Are you sure you want to delete this article?')) return;

            try {
                const formData = new FormData();
                formData.append('id', id);

                const response = await fetch('/api/help.php?action=delete_article', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    loadArticles();
                }
            } catch (error) {
                console.error('Error deleting article:', error);
            }
        }

        // Load tooltips
        async function loadTooltips() {
            try {
                const response = await fetch('/api/help.php?action=get_all');
                const data = await response.json();

                if (data.success) {
                    // Note: API needs to be updated to also return all tooltips
                    renderTooltips([]);
                }
            } catch (error) {
                console.error('Error loading tooltips:', error);
            }
        }

        function renderTooltips(tooltips) {
            const container = document.getElementById('tooltips-list');

            if (tooltips.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">No tooltips yet. Create your first one!</p>';
                return;
            }

            let html = '';
            tooltips.forEach(tooltip => {
                html += `
                    <div class="article-item">
                        <div class="article-info">
                            <h3>${tooltip.element_id}</h3>
                            <div class="article-meta">
                                <span class="badge ${tooltip.is_active ? 'badge-success' : 'badge-warning'}">
                                    ${tooltip.is_active ? 'Active' : 'Inactive'}
                                </span>
                                <span style="margin-left: 10px;">${tooltip.page}</span>
                                <span style="margin-left: 10px;">${tooltip.title}</span>
                            </div>
                        </div>
                        <div class="article-actions">
                            <button class="btn btn-primary" onclick='editTooltip(${JSON.stringify(tooltip)})'>
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-danger" onclick="deleteTooltip(${tooltip.id})">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Tooltip modal functions
        function openTooltipModal() {
            document.getElementById('tooltip-modal-title').textContent = 'New Tooltip';
            document.getElementById('tooltip-form').reset();
            document.getElementById('tooltip-id').value = '';
            document.getElementById('tooltip-modal').classList.add('visible');
        }

        function closeTooltipModal() {
            document.getElementById('tooltip-modal').classList.remove('visible');
        }

        function editTooltip(tooltip) {
            document.getElementById('tooltip-modal-title').textContent = 'Edit Tooltip';
            document.getElementById('tooltip-id').value = tooltip.id;
            document.getElementById('tooltip-element').value = tooltip.element_id;
            document.getElementById('tooltip-page').value = tooltip.page;
            document.getElementById('tooltip-title').value = tooltip.title;
            document.getElementById('tooltip-content').value = tooltip.content;
            document.getElementById('tooltip-position').value = tooltip.position;
            document.getElementById('tooltip-status').value = tooltip.is_active;
            document.getElementById('tooltip-modal').classList.add('visible');
        }

        async function saveTooltip() {
            const data = {
                id: document.getElementById('tooltip-id').value,
                element_id: document.getElementById('tooltip-element').value,
                page: document.getElementById('tooltip-page').value,
                title: document.getElementById('tooltip-title').value,
                content: document.getElementById('tooltip-content').value,
                position: document.getElementById('tooltip-position').value,
                is_active: document.getElementById('tooltip-status').value
            };

            try {
                const response = await fetch('/api/help.php?action=save_tooltip', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    closeTooltipModal();
                    loadTooltips();
                } else {
                    document.getElementById('tooltip-alert').innerHTML =
                        `<div class="alert alert-danger">${result.error}</div>`;
                }
            } catch (error) {
                document.getElementById('tooltip-alert').innerHTML =
                    `<div class="alert alert-danger">Error saving tooltip</div>`;
            }
        }

        async function deleteTooltip(id) {
            if (!confirm('Are you sure you want to delete this tooltip?')) return;

            try {
                const formData = new FormData();
                formData.append('id', id);

                const response = await fetch('/api/help.php?action=delete_tooltip', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    loadTooltips();
                }
            } catch (error) {
                console.error('Error deleting tooltip:', error);
            }
        }

        // Load initial data
        loadArticles();
    </script>
</body>
</html>
