<?php
/**
 * Departments Tab Content
 * Embeds the department management interface
 */

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo '<div style="padding: 2rem; text-align: center; color: #ef4444;">Authentication required</div>';
    exit;
}
?>

<style>
    .departments-embed {
        width: 100%;
        height: calc(100vh - 250px);
        min-height: 800px;
        border: none;
    }
</style>

<iframe
    src="../department-management.php"
    class="departments-embed"
    title="Department Management">
</iframe>
