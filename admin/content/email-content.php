<?php
/**
 * Email Configuration Tab Content
 * Embeds the email settings interface
 */

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo '<div style="padding: 2rem; text-align: center; color: #ef4444;">Authentication required</div>';
    exit;
}
?>

<style>
    .email-embed {
        width: 100%;
        height: calc(100vh - 250px);
        min-height: 800px;
        border: none;
    }
</style>

<iframe
    src="../email-settings.php"
    class="email-embed"
    title="Email Configuration">
</iframe>
