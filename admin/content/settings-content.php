<?php
/**
 * Settings Tab Content
 * Container for settings sub-tabs
 */

session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo '<div style="padding: 2rem; text-align: center; color: #ef4444;">Authentication required</div>';
    exit;
}
?>

<style>
    .settings-container {
        background: #0f172a;
        min-height: 500px;
    }
</style>

<div class="settings-container">
    <div id="subtab-content-container">
        <!-- Subtab content will be loaded here -->
        <div style="padding: 2rem; text-align: center; color: #64748b;">
            <p>Loading settings...</p>
        </div>
    </div>
</div>

<script>
    // Auto-load default subtab (provisioning) when settings tab loads
    if (typeof currentSubTab !== 'undefined' && currentSubTab) {
        // Wait a moment for the page to be ready
        setTimeout(() => {
            if (typeof loadSubTabContent === 'function') {
                loadSubTabContent(currentSubTab || 'provisioning');
            }
        }, 100);
    }
</script>
