<?php
require_once __DIR__ . '/admin_auth_check.php';

// Set page title for header
$page_title = 'Announcements Manager - FlexPBX Admin';

// Include the admin header
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
    .announcements-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 20px 20px;
    }

    .announcements-header {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .announcements-header h1 {
        margin: 0;
        color: #333;
        font-size: 28px;
    }

    .header-actions {
        display: flex;
        gap: 10px;
    }

    .btn-primary, .btn-secondary, .btn-success, .btn-danger, .btn-warning {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-success {
        background: #4ade80;
        color: white;
    }

    .btn-danger {
        background: #ef4444;
        color: white;
    }

    .btn-warning {
        background: #f59e0b;
        color: white;
    }

    .filters-section {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .filter-group label {
        font-weight: 600;
        font-size: 14px;
        color: #333;
    }

    .filter-group select, .filter-group input {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
    }

    .announcements-list {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .announcement-card {
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 15px;
        transition: all 0.3s;
    }

    .announcement-card:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    .announcement-header-card {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .announcement-title {
        font-size: 20px;
        font-weight: 700;
        color: #333;
        margin: 0 0 10px 0;
    }

    .announcement-meta {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }

    .badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .badge-urgent {
        background: #ef4444;
        color: white;
    }

    .badge-high {
        background: #f59e0b;
        color: white;
    }

    .badge-normal {
        background: #3b82f6;
        color: white;
    }

    .badge-low {
        background: #6c757d;
        color: white;
    }

    .badge-system {
        background: #8b5cf6;
        color: white;
    }

    .badge-maintenance {
        background: #f59e0b;
        color: white;
    }

    .badge-feature {
        background: #10b981;
        color: white;
    }

    .badge-alert {
        background: #ef4444;
        color: white;
    }

    .badge-news {
        background: #3b82f6;
        color: white;
    }

    .badge-active {
        background: #4ade80;
        color: white;
    }

    .badge-inactive {
        background: #6c757d;
        color: white;
    }

    .announcement-content {
        color: #666;
        margin-bottom: 15px;
        line-height: 1.6;
    }

    .announcement-stats {
        display: flex;
        gap: 20px;
        padding: 10px 0;
        border-top: 1px solid #e0e0e0;
        margin-top: 10px;
        font-size: 14px;
        color: #666;
    }

    .announcement-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 10000;
        overflow-y: auto;
    }

    .modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        max-width: 900px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    }

    .modal-header {
        padding: 25px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h2 {
        margin: 0;
        color: #333;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: #999;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: all 0.3s;
    }

    .modal-close:hover {
        background: #f0f0f0;
        color: #333;
    }

    .modal-body {
        padding: 25px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #333;
        font-size: 14px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
    }

    .form-group textarea {
        min-height: 100px;
        resize: vertical;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .form-check {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
    }

    .form-check input[type="checkbox"] {
        width: auto;
    }

    .modal-footer {
        padding: 20px 25px;
        border-top: 1px solid #e0e0e0;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }

    .empty-state-icon {
        font-size: 64px;
        margin-bottom: 20px;
    }

    .loading {
        text-align: center;
        padding: 40px;
        color: #999;
    }

    .spinner {
        border: 3px solid #f3f3f3;
        border-top: 3px solid #667eea;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto 20px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .template-selector {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .template-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }

    .template-card {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.3s;
        text-align: center;
    }

    .template-card:hover {
        border-color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    }

    .template-card.selected {
        border-color: #667eea;
        background: #f0f4ff;
    }

    .template-name {
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }

    .template-desc {
        font-size: 12px;
        color: #666;
    }

    @media (max-width: 768px) {
        .announcements-header {
            flex-direction: column;
            align-items: stretch;
        }

        .header-actions {
            flex-direction: column;
        }

        .filters-grid {
            grid-template-columns: 1fr;
        }

        .announcement-header-card {
            flex-direction: column;
        }

        .announcement-actions {
            flex-direction: column;
        }
    }
</style>

<div class="announcements-container">
    <!-- Header -->
    <div class="announcements-header">
        <div>
            <h1>📢 Announcements Manager</h1>
            <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">Create and manage system-wide announcements</p>
        </div>
        <div class="header-actions">
            <button class="btn-primary" onclick="showCreateModal()">
                ➕ New Announcement
            </button>
            <button class="btn-secondary" onclick="loadAnnouncements()">
                🔄 Refresh
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <h3 style="margin: 0 0 15px 0; color: #333;">Filters</h3>
        <div class="filters-grid">
            <div class="filter-group">
                <label>Type</label>
                <select id="filterType" onchange="applyFilters()">
                    <option value="">All Types</option>
                    <option value="system">System</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="feature">Feature</option>
                    <option value="alert">Alert</option>
                    <option value="news">News</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Priority</label>
                <select id="filterPriority" onchange="applyFilters()">
                    <option value="">All Priorities</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="normal">Normal</option>
                    <option value="low">Low</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select id="filterStatus" onchange="applyFilters()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="expired">Expired</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Search</label>
                <input type="text" id="filterSearch" placeholder="Search title or content..." oninput="applyFilters()">
            </div>
        </div>
    </div>

    <!-- Announcements List -->
    <div class="announcements-list" id="announcementsList">
        <div class="loading">
            <div class="spinner"></div>
            <p>Loading announcements...</p>
        </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal" id="announcementModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">New Announcement</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="announcementForm">
                <input type="hidden" id="announcementId">

                <!-- Template Selector -->
                <div class="template-selector" id="templateSelector">
                    <h4 style="margin: 0 0 10px 0;">Quick Start with Template</h4>
                    <div class="template-grid" id="templateGrid">
                        <!-- Templates loaded dynamically -->
                    </div>
                </div>

                <!-- Basic Info -->
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" id="title" required placeholder="Enter announcement title...">
                </div>

                <div class="form-group">
                    <label>Content *</label>
                    <textarea id="content" required></textarea>
                </div>

                <!-- Settings Row -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Type *</label>
                        <select id="announcement_type" required>
                            <option value="news">News</option>
                            <option value="feature">Feature</option>
                            <option value="system">System</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="alert">Alert</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Priority *</label>
                        <select id="priority" required>
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <!-- Date Range -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date (optional)</label>
                        <input type="datetime-local" id="start_date">
                    </div>

                    <div class="form-group">
                        <label>End Date (optional)</label>
                        <input type="datetime-local" id="end_date">
                    </div>
                </div>

                <!-- Target Roles -->
                <div class="form-group">
                    <label>Target Roles (leave all unchecked for everyone)</label>
                    <div class="form-check">
                        <input type="checkbox" id="role_admin" value="admin">
                        <label for="role_admin">Admins</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" id="role_user" value="user">
                        <label for="role_user">Users</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" id="role_supervisor" value="supervisor">
                        <label for="role_supervisor">Supervisors</label>
                    </div>
                </div>

                <!-- Display Options -->
                <div class="form-group">
                    <label>Display Options</label>
                    <div class="form-check">
                        <input type="checkbox" id="is_active" checked>
                        <label for="is_active">Active (show to users)</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" id="is_dismissible" checked>
                        <label for="is_dismissible">Dismissible (users can close it)</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" id="show_banner">
                        <label for="show_banner">Show as top banner</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" id="show_popup">
                        <label for="show_popup">Show as popup on login</label>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal()">Cancel</button>
            <button class="btn-primary" onclick="saveAnnouncement()">Save Announcement</button>
        </div>
    </div>
</div>

<!-- TinyMCE CDN -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<script>
let allAnnouncements = [];
let allTemplates = [];
let currentEditId = null;

// Initialize TinyMCE
tinymce.init({
    selector: '#content',
    height: 400,
    menubar: false,
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'help', 'wordcount'
    ],
    toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | ' +
        'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | ' +
        'removeformat | link image | code preview | help',
    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; font-size: 14px; }',
    branding: false
});

// Load announcements on page load
document.addEventListener('DOMContentLoaded', function() {
    loadAnnouncements();
    loadTemplates();
});

// Load all announcements
async function loadAnnouncements() {
    try {
        const response = await fetch('/api/announcements.php?action=all');
        const data = await response.json();

        if (data.success) {
            allAnnouncements = data.announcements;
            applyFilters();
        } else {
            showError('Failed to load announcements: ' + data.error);
        }
    } catch (error) {
        showError('Error loading announcements: ' + error.message);
    }
}

// Load templates
async function loadTemplates() {
    try {
        const response = await fetch('/api/announcements.php?action=templates');
        const data = await response.json();

        if (data.success) {
            allTemplates = data.templates;
            renderTemplates();
        }
    } catch (error) {
        console.error('Error loading templates:', error);
    }
}

// Render template selector
function renderTemplates() {
    const grid = document.getElementById('templateGrid');
    grid.innerHTML = allTemplates.map(template => `
        <div class="template-card" onclick="selectTemplate(${template.id})">
            <div class="template-name">${escapeHtml(template.name)}</div>
            <div class="template-desc">${escapeHtml(template.description || '')}</div>
        </div>
    `).join('');
}

// Select template
function selectTemplate(templateId) {
    const template = allTemplates.find(t => t.id === templateId);
    if (!template) return;

    document.getElementById('title').value = template.title_template;
    tinymce.get('content').setContent(template.content_template);
    document.getElementById('announcement_type').value = template.default_type;
    document.getElementById('priority').value = template.default_priority;

    // Highlight selected template
    document.querySelectorAll('.template-card').forEach(card => card.classList.remove('selected'));
    event.target.closest('.template-card').classList.add('selected');
}

// Apply filters
function applyFilters() {
    const typeFilter = document.getElementById('filterType').value;
    const priorityFilter = document.getElementById('filterPriority').value;
    const statusFilter = document.getElementById('filterStatus').value;
    const searchFilter = document.getElementById('filterSearch').value.toLowerCase();

    let filtered = allAnnouncements.filter(ann => {
        // Type filter
        if (typeFilter && ann.announcement_type !== typeFilter) return false;

        // Priority filter
        if (priorityFilter && ann.priority !== priorityFilter) return false;

        // Status filter
        if (statusFilter) {
            const now = new Date();
            const startDate = ann.start_date ? new Date(ann.start_date) : null;
            const endDate = ann.end_date ? new Date(ann.end_date) : null;

            if (statusFilter === 'active' && !ann.is_active) return false;
            if (statusFilter === 'inactive' && ann.is_active) return false;
            if (statusFilter === 'scheduled' && (!startDate || startDate <= now)) return false;
            if (statusFilter === 'expired' && (!endDate || endDate >= now)) return false;
        }

        // Search filter
        if (searchFilter) {
            const searchText = (ann.title + ' ' + ann.content).toLowerCase();
            if (!searchText.includes(searchFilter)) return false;
        }

        return true;
    });

    renderAnnouncements(filtered);
}

// Render announcements
function renderAnnouncements(announcements) {
    const container = document.getElementById('announcementsList');

    if (announcements.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>No announcements found</h3>
                <p>Create your first announcement to get started</p>
            </div>
        `;
        return;
    }

    container.innerHTML = announcements.map(ann => {
        const now = new Date();
        const startDate = ann.start_date ? new Date(ann.start_date) : null;
        const endDate = ann.end_date ? new Date(ann.end_date) : null;

        let statusText = 'Active';
        if (!ann.is_active) statusText = 'Inactive';
        else if (startDate && startDate > now) statusText = 'Scheduled';
        else if (endDate && endDate < now) statusText = 'Expired';

        return `
            <div class="announcement-card">
                <div class="announcement-header-card">
                    <div style="flex: 1;">
                        <h3 class="announcement-title">${escapeHtml(ann.title)}</h3>
                        <div class="announcement-meta">
                            <span class="badge badge-${ann.priority}">${ann.priority}</span>
                            <span class="badge badge-${ann.announcement_type}">${ann.announcement_type}</span>
                            <span class="badge badge-${ann.is_active ? 'active' : 'inactive'}">${statusText}</span>
                            ${ann.is_dismissible ? '<span class="badge" style="background: #10b981; color: white;">Dismissible</span>' : ''}
                            ${ann.show_banner ? '<span class="badge" style="background: #f59e0b; color: white;">Banner</span>' : ''}
                            ${ann.show_popup ? '<span class="badge" style="background: #8b5cf6; color: white;">Popup</span>' : ''}
                        </div>
                    </div>
                </div>

                <div class="announcement-content">
                    ${stripHtml(ann.content).substring(0, 200)}${ann.content.length > 200 ? '...' : ''}
                </div>

                <div class="announcement-stats">
                    <span>👁️ ${ann.view_count || 0} views</span>
                    <span>❌ ${ann.dismiss_count || 0} dismissed</span>
                    <span>📅 Created: ${formatDate(ann.created_at)}</span>
                    ${ann.start_date ? `<span>⏰ Start: ${formatDate(ann.start_date)}</span>` : ''}
                    ${ann.end_date ? `<span>⏰ End: ${formatDate(ann.end_date)}</span>` : ''}
                </div>

                <div class="announcement-actions">
                    <button class="btn-primary" onclick="editAnnouncement(${ann.id})">✏️ Edit</button>
                    <button class="btn-success" onclick="viewAnalytics(${ann.id})">📊 Analytics</button>
                    <button class="btn-warning" onclick="toggleStatus(${ann.id}, ${ann.is_active})">
                        ${ann.is_active ? '⏸️ Deactivate' : '▶️ Activate'}
                    </button>
                    <button class="btn-danger" onclick="deleteAnnouncement(${ann.id})">🗑️ Delete</button>
                </div>
            </div>
        `;
    }).join('');
}

// Show create modal
function showCreateModal() {
    currentEditId = null;
    document.getElementById('modalTitle').textContent = 'New Announcement';
    document.getElementById('announcementForm').reset();
    document.getElementById('announcementId').value = '';
    document.getElementById('is_active').checked = true;
    document.getElementById('is_dismissible').checked = true;
    document.getElementById('show_banner').checked = false;
    document.getElementById('show_popup').checked = false;
    tinymce.get('content').setContent('');
    document.getElementById('templateSelector').style.display = 'block';
    document.querySelectorAll('.template-card').forEach(card => card.classList.remove('selected'));
    document.getElementById('announcementModal').classList.add('active');
}

// Edit announcement
async function editAnnouncement(id) {
    currentEditId = id;
    const ann = allAnnouncements.find(a => a.id === id);
    if (!ann) return;

    document.getElementById('modalTitle').textContent = 'Edit Announcement';
    document.getElementById('announcementId').value = ann.id;
    document.getElementById('title').value = ann.title;
    tinymce.get('content').setContent(ann.content);
    document.getElementById('announcement_type').value = ann.announcement_type;
    document.getElementById('priority').value = ann.priority;
    document.getElementById('start_date').value = ann.start_date ? formatDateTimeLocal(ann.start_date) : '';
    document.getElementById('end_date').value = ann.end_date ? formatDateTimeLocal(ann.end_date) : '';
    document.getElementById('is_active').checked = ann.is_active == 1;
    document.getElementById('is_dismissible').checked = ann.is_dismissible == 1;
    document.getElementById('show_banner').checked = ann.show_banner == 1;
    document.getElementById('show_popup').checked = ann.show_popup == 1;

    // Set target roles
    const roles = ann.target_roles ? JSON.parse(ann.target_roles) : [];
    document.getElementById('role_admin').checked = roles.includes('admin');
    document.getElementById('role_user').checked = roles.includes('user');
    document.getElementById('role_supervisor').checked = roles.includes('supervisor');

    document.getElementById('templateSelector').style.display = 'none';
    document.getElementById('announcementModal').classList.add('active');
}

// Save announcement
async function saveAnnouncement() {
    const id = document.getElementById('announcementId').value;
    const title = document.getElementById('title').value.trim();
    const content = tinymce.get('content').getContent();
    const announcement_type = document.getElementById('announcement_type').value;
    const priority = document.getElementById('priority').value;
    const start_date = document.getElementById('start_date').value || null;
    const end_date = document.getElementById('end_date').value || null;
    const is_active = document.getElementById('is_active').checked ? 1 : 0;
    const is_dismissible = document.getElementById('is_dismissible').checked ? 1 : 0;
    const show_banner = document.getElementById('show_banner').checked ? 1 : 0;
    const show_popup = document.getElementById('show_popup').checked ? 1 : 0;

    // Get selected roles
    const roles = [];
    if (document.getElementById('role_admin').checked) roles.push('admin');
    if (document.getElementById('role_user').checked) roles.push('user');
    if (document.getElementById('role_supervisor').checked) roles.push('supervisor');

    if (!title || !content) {
        alert('Please fill in all required fields');
        return;
    }

    const data = {
        id: id || undefined,
        title,
        content,
        announcement_type,
        priority,
        start_date,
        end_date,
        is_active,
        is_dismissible,
        show_banner,
        show_popup,
        target_roles: roles.length > 0 ? roles : null
    };

    try {
        const url = id ? '/api/announcements.php' : '/api/announcements.php?action=create';
        const method = id ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showSuccess(id ? 'Announcement updated successfully' : 'Announcement created successfully');
            closeModal();
            loadAnnouncements();
        } else {
            showError('Failed to save announcement: ' + result.error);
        }
    } catch (error) {
        showError('Error saving announcement: ' + error.message);
    }
}

// Toggle announcement status
async function toggleStatus(id, currentStatus) {
    const ann = allAnnouncements.find(a => a.id === id);
    if (!ann) return;

    ann.is_active = currentStatus ? 0 : 1;

    try {
        const response = await fetch('/api/announcements.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(ann)
        });

        const result = await response.json();

        if (result.success) {
            showSuccess('Status updated successfully');
            loadAnnouncements();
        } else {
            showError('Failed to update status: ' + result.error);
        }
    } catch (error) {
        showError('Error updating status: ' + error.message);
    }
}

// Delete announcement
async function deleteAnnouncement(id) {
    if (!confirm('Are you sure you want to delete this announcement? This action cannot be undone.')) {
        return;
    }

    try {
        const response = await fetch('/api/announcements.php?id=' + id, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (result.success) {
            showSuccess('Announcement deleted successfully');
            loadAnnouncements();
        } else {
            showError('Failed to delete announcement: ' + result.error);
        }
    } catch (error) {
        showError('Error deleting announcement: ' + error.message);
    }
}

// View analytics
async function viewAnalytics(id) {
    try {
        const response = await fetch('/api/announcements.php?action=analytics&id=' + id);
        const data = await response.json();

        if (data.success) {
            const stats = data.statistics;
            const timeline = data.timeline;

            let message = `Announcement Analytics:\n\n`;
            message += `Unique Views: ${stats.unique_views || 0}\n`;
            message += `Dismissals: ${stats.dismissals || 0}\n`;
            message += `First View: ${stats.first_view ? formatDate(stats.first_view) : 'N/A'}\n`;
            message += `Last View: ${stats.last_view ? formatDate(stats.last_view) : 'N/A'}\n\n`;
            message += `View Timeline:\n`;
            timeline.forEach(day => {
                message += `${day.date}: ${day.views} views\n`;
            });

            alert(message);
        } else {
            showError('Failed to load analytics: ' + data.error);
        }
    } catch (error) {
        showError('Error loading analytics: ' + error.message);
    }
}

// Close modal
function closeModal() {
    document.getElementById('announcementModal').classList.remove('active');
}

// Utility functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function stripHtml(html) {
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.textContent || div.innerText || '';
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

function formatDateTimeLocal(dateStr) {
    const date = new Date(dateStr);
    const offset = date.getTimezoneOffset();
    const localDate = new Date(date.getTime() - (offset * 60 * 1000));
    return localDate.toISOString().slice(0, 16);
}

function showSuccess(message) {
    alert('✓ ' + message);
}

function showError(message) {
    alert('✗ ' + message);
}

// Close modal when clicking outside
document.getElementById('announcementModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php
// Include footer if you have one
// require_once __DIR__ . '/includes/admin_footer.php';
?>

</body>
</html>
