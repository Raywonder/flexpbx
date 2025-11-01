<?php
/**
 * Auto-Provisioning Settings Content
 * Embedded version of provisioning-settings.php for tab interface
 */

session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo '<div style="padding: 2rem; text-align: center; color: #ef4444;">Authentication required</div>';
    exit;
}

// Load the provisioning settings page content
// We'll use an iframe to embed the full provisioning interface
?>

<style>
    .provisioning-embed {
        width: 100%;
        height: calc(100vh - 250px);
        min-height: 800px;
        border: none;
        background: white;
    }

    .provisioning-header {
        background: #1e293b;
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #334155;
    }

    .provisioning-header h2 {
        color: #fff;
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }

    .provisioning-header p {
        color: #94a3b8;
        font-size: 0.875rem;
    }

    .quick-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
    }

    .action-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: transform 0.2s;
    }

    .action-btn:hover {
        transform: scale(1.05);
    }

    .action-btn.secondary {
        background: #334155;
    }
</style>

<div>
    <div class="provisioning-header">
        <h2>Auto-Provisioning Settings</h2>
        <p>Configure automatic user provisioning, default settings, and extension management</p>
        <div class="quick-actions">
            <a href="../provisioning-settings.php" target="_blank" class="action-btn">
                Open Full Interface
            </a>
            <a href="../api/provisioning.php?path=test" target="_blank" class="action-btn secondary">
                Test API
            </a>
        </div>
    </div>

    <iframe
        src="../provisioning-settings.php"
        class="provisioning-embed"
        title="Auto-Provisioning Settings"
        id="provisioning-iframe">
    </iframe>
</div>

<script>
    // Add message listener for iframe communication
    window.addEventListener('message', function(event) {
        // Handle any messages from the provisioning interface
        if (event.data.type === 'provisioning-updated') {
            console.log('Provisioning settings updated:', event.data);
            // Optionally refresh parent data or show notification
        }
    });

    // Auto-resize iframe based on content
    const iframe = document.getElementById('provisioning-iframe');
    iframe.onload = function() {
        try {
            // Adjust height based on content if same-origin
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            if (iframeDoc) {
                iframe.style.height = iframeDoc.body.scrollHeight + 'px';
            }
        } catch (e) {
            // Cross-origin - keep default height
            console.log('Cross-origin iframe - using default height');
        }
    };
</script>
