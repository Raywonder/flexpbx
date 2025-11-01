<?php
/**
 * General Settings Sub-tab Content
 */

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo '<div style="padding: 2rem; text-align: center; color: #ef4444;">Authentication required</div>';
    exit;
}
?>

<style>
    .settings-wrapper {
        padding: 2rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 2rem;
    }

    .info-card {
        background: #1e293b;
        border: 1px solid #334155;
        border-radius: 12px;
        padding: 2rem;
    }

    .info-card h3 {
        color: #667eea;
        margin-bottom: 1rem;
    }

    .info-card p {
        color: #94a3b8;
        line-height: 1.6;
    }

    .btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: transform 0.2s;
        margin-top: 1rem;
    }

    .btn:hover {
        transform: scale(1.05);
    }
</style>

<div class="settings-wrapper">
    <h1 class="section-title">General Settings</h1>

    <div class="info-card">
        <h3>System-Wide Configuration</h3>
        <p>Configure general system settings, branding, and default preferences.</p>
        <p style="margin-top: 1rem;">
            <a href="../system-settings.php" class="btn">Open System Settings</a>
        </p>
    </div>
</div>
