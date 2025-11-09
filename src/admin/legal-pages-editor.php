<?php
require_once __DIR__ . '/admin_auth_check.php';

// Set page title for header
$page_title = 'Legal Pages Editor';

// Include the admin header
require_once __DIR__ . '/includes/admin_header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-gavel"></i> Legal Pages Editor</h2>
            <p class="text-muted">Create and edit privacy policy, terms of service, and other legal documents</p>
        </div>
    </div>

    <!-- Page Selection -->
    <div class="row mb-4">
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-list"></i> Legal Pages
                </div>
                <div class="list-group list-group-flush" id="pagesList">
                    <a href="#" class="list-group-item list-group-item-action" data-page="privacy-policy">
                        <i class="fas fa-shield-alt"></i> Privacy Policy
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-page="terms-of-service">
                        <i class="fas fa-file-contract"></i> Terms of Service
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-page="acceptable-use">
                        <i class="fas fa-check-circle"></i> Acceptable Use Policy
                    </a>
                </div>
                <div class="card-footer">
                    <button class="btn btn-success btn-sm btn-block" onclick="createNewPage()">
                        <i class="fas fa-plus"></i> New Page
                    </button>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mt-3">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-bolt"></i> Quick Actions
                </div>
                <div class="card-body">
                    <button class="btn btn-primary btn-sm btn-block mb-2" onclick="loadDefaultContent()">
                        <i class="fas fa-download"></i> Load Defaults
                    </button>
                    <button class="btn btn-warning btn-sm btn-block mb-2" onclick="previewPage()">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                    <button class="btn btn-secondary btn-sm btn-block" onclick="viewPublicPage()">
                        <i class="fas fa-external-link-alt"></i> View Public
                    </button>
                </div>
            </div>

            <!-- Page Status -->
            <div class="card mt-3">
                <div class="card-header bg-secondary text-white">
                    <i class="fas fa-info-circle"></i> Page Status
                </div>
                <div class="card-body">
                    <div id="pageStatus">
                        <p class="text-muted mb-0">Select a page to edit</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-edit"></i> <span id="editorTitle">Editor</span></span>
                    <div>
                        <button class="btn btn-light btn-sm" onclick="togglePublishStatus()">
                            <i class="fas fa-toggle-on"></i> <span id="publishBtnText">Publish</span>
                        </button>
                        <button class="btn btn-success btn-sm" onclick="savePage()">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Page Title -->
                    <div class="form-group">
                        <label for="pageTitle">Page Title</label>
                        <input type="text" class="form-control" id="pageTitle" placeholder="Enter page title">
                    </div>

                    <!-- TinyMCE Editor -->
                    <div class="form-group">
                        <label>Page Content</label>
                        <textarea id="pageContent" rows="20"></textarea>
                    </div>

                    <!-- Save Button -->
                    <div class="form-group">
                        <button class="btn btn-success btn-lg" onclick="savePage()">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button class="btn btn-secondary btn-lg" onclick="resetEditor()">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>

                    <!-- Publishing Info -->
                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle"></i> Publishing Information</h5>
                        <p>
                            These legal pages are accessible at:<br>
                            <code>https://flexpbx.devinecreations.net/legal/[page-key].php</code>
                        </p>
                        <p class="mb-0">
                            <strong>Important:</strong> All users accessing your FlexPBX system are responsible for ensuring
                            these documents comply with applicable laws and regulations in their jurisdiction.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Preview: <span id="previewTitle"></span></h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="previewContent" style="max-height: 600px; overflow-y: auto;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = null;
let currentPageData = null;
let editor = null;

// Initialize TinyMCE
tinymce.init({
    selector: '#pageContent',
    height: 600,
    menubar: true,
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'help', 'wordcount'
    ],
    toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | code preview | help',
    content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
    setup: function(ed) {
        editor = ed;
    }
});

// Page selection
document.querySelectorAll('#pagesList .list-group-item').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        const pageKey = this.getAttribute('data-page');
        loadPage(pageKey);

        // Update active state
        document.querySelectorAll('#pagesList .list-group-item').forEach(i => i.classList.remove('active'));
        this.classList.add('active');
    });
});

function loadPage(pageKey) {
    currentPage = pageKey;

    fetch(`../api/legal-pages.php?action=get&page=${pageKey}`)
        .then(r => r.json())
        .then(result => {
            if (result.success && result.data) {
                currentPageData = result.data;
                document.getElementById('pageTitle').value = result.data.page_title || '';
                document.getElementById('editorTitle').textContent = result.data.page_title || 'Editor';

                if (editor) {
                    editor.setContent(result.data.page_content || '');
                } else {
                    document.getElementById('pageContent').value = result.data.page_content || '';
                }

                updatePageStatus(result.data);
            } else {
                // Page doesn't exist, load default
                loadDefaultContent();
            }
        })
        .catch(err => {
            console.error('Error loading page:', err);
            alert('Error loading page');
        });
}

function updatePageStatus(data) {
    const statusDiv = document.getElementById('pageStatus');
    const isPublished = data.is_published === 1 || data.is_published === true;

    statusDiv.innerHTML = `
        <p><strong>Status:</strong> <span class="badge badge-${isPublished ? 'success' : 'warning'}">${isPublished ? 'Published' : 'Draft'}</span></p>
        <p><strong>Version:</strong> ${data.version || 0}</p>
        <p><strong>Last Updated:</strong><br>${data.last_updated || 'Never'}</p>
        <p class="mb-0"><strong>Updated By:</strong> ${data.updated_by || 'System'}</p>
    `;

    document.getElementById('publishBtnText').textContent = isPublished ? 'Unpublish' : 'Publish';
}

function savePage() {
    if (!currentPage) {
        alert('Please select a page to edit');
        return;
    }

    const pageTitle = document.getElementById('pageTitle').value;
    const pageContent = editor ? editor.getContent() : document.getElementById('pageContent').value;

    if (!pageTitle || !pageContent) {
        alert('Please enter both title and content');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('page_key', currentPage);
    formData.append('page_title', pageTitle);
    formData.append('page_content', pageContent);
    formData.append('is_published', currentPageData?.is_published || 1);

    fetch('../api/legal-pages.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert('Page saved successfully!');
            loadPage(currentPage); // Reload to update status
        } else {
            alert('Error saving page: ' + result.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error saving page');
    });
}

function togglePublishStatus() {
    if (!currentPage || !currentPageData) {
        alert('Please select a page first');
        return;
    }

    const newStatus = !(currentPageData.is_published === 1 || currentPageData.is_published === true);
    const formData = new FormData();
    formData.append('action', 'publish');
    formData.append('page_key', currentPage);
    formData.append('is_published', newStatus);

    fetch('../api/legal-pages.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert(result.message);
            loadPage(currentPage);
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(err => alert('Error toggling publish status'));
}

function loadDefaultContent() {
    if (!currentPage) {
        alert('Please select a page first');
        return;
    }

    if (!confirm('Load default content? This will replace current content (you can save first if needed).')) {
        return;
    }

    fetch(`../api/legal-pages.php?action=get&page=${currentPage}`)
        .then(r => r.json())
        .then(result => {
            if (result.success && result.data) {
                document.getElementById('pageTitle').value = result.data.page_title;
                if (editor) {
                    editor.setContent(result.data.page_content);
                } else {
                    document.getElementById('pageContent').value = result.data.page_content;
                }
                alert('Default content loaded. Click Save to persist changes.');
            }
        });
}

function previewPage() {
    const pageTitle = document.getElementById('pageTitle').value;
    const pageContent = editor ? editor.getContent() : document.getElementById('pageContent').value;

    document.getElementById('previewTitle').textContent = pageTitle;
    document.getElementById('previewContent').innerHTML = pageContent;

    $('#previewModal').modal('show');
}

function viewPublicPage() {
    if (!currentPage) {
        alert('Please select a page first');
        return;
    }

    window.open(`/legal/${currentPage}.php`, '_blank');
}

function resetEditor() {
    if (confirm('Reset editor? This will reload the last saved version.')) {
        if (currentPage) {
            loadPage(currentPage);
        }
    }
}

function createNewPage() {
    const pageKey = prompt('Enter page key (lowercase, dashes only, e.g., "data-processing-agreement"):');
    if (!pageKey) return;

    if (!/^[a-z0-9-]+$/.test(pageKey)) {
        alert('Invalid page key. Use only lowercase letters, numbers, and dashes.');
        return;
    }

    const pageTitle = prompt('Enter page title:');
    if (!pageTitle) return;

    currentPage = pageKey;
    document.getElementById('pageTitle').value = pageTitle;
    if (editor) {
        editor.setContent('<h1>' + pageTitle + '</h1><p>Start editing your content here...</p>');
    }

    alert('New page created. Click Save to persist.');
}

// Auto-load first page on page load
window.addEventListener('load', () => {
    const firstPage = document.querySelector('#pagesList .list-group-item');
    if (firstPage) {
        firstPage.click();
    }
});
</script>

<style>
#pagesList .list-group-item {
    cursor: pointer;
    transition: background-color 0.2s;
}

#pagesList .list-group-item:hover {
    background-color: #f8f9fa;
}

#pagesList .list-group-item.active {
    background-color: #667eea;
    color: white;
    border-color: #667eea;
}

#pageStatus p {
    margin-bottom: 0.5rem;
}
</style>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
