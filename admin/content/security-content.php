<?php
/**
 * Security Tab Content
 */

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo '<div style="padding: 2rem; text-align: center; color: #ef4444;">Authentication required</div>';
    exit;
}
?>

<style>
    .content-wrapper {
        padding: 2rem;
        max-width: 1400px;
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
        text-align: center;
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

<div class="content-wrapper">
    <h1 class="section-title">Security Settings</h1>

    <div class="info-card">
        <h3>Security & Access Control</h3>
        <p>This section will display security settings, firewall rules, fail2ban status, and IP management.</p>
        <p style="margin-top: 1rem;">Coming soon: Security dashboard, IP whitelist/blacklist management, and fail2ban monitoring.</p>
    </div>
</div>
