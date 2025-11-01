<?php
/**
 * Accessibility Tab Content
 * Embeds the accessibility categories interface
 */

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo '<div style="padding: 2rem; text-align: center; color: #ef4444;">Authentication required</div>';
    exit;
}
?>

<style>
    .accessibility-embed {
        width: 100%;
        height: calc(100vh - 250px);
        min-height: 800px;
        border: none;
    }
</style>

<iframe
    src="../accessibility-categories.php"
    class="accessibility-embed"
    title="Accessibility Settings">
</iframe>
