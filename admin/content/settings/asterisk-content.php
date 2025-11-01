<?php
/**
 * Asterisk Configuration Sub-tab Content
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

    .info-card ul {
        color: #94a3b8;
        margin: 1rem 0 1rem 2rem;
        line-height: 1.8;
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

    .btn-secondary {
        background: #334155;
    }
</style>

<div class="settings-wrapper">
    <h1 class="section-title">Asterisk Configuration</h1>

    <div class="info-card">
        <h3>Asterisk PBX Settings</h3>
        <p>Advanced Asterisk configuration including SIP/PJSIP settings, dialplan, and trunk management.</p>

        <ul>
            <li>SIP/PJSIP endpoint configuration</li>
            <li>Trunk management (Google Voice, CallCentric, etc.)</li>
            <li>Dialplan routing rules</li>
            <li>Codec preferences</li>
            <li>NAT and network settings</li>
            <li>STUN server configuration</li>
        </ul>

        <p style="margin-top: 1rem;">
            <a href="../feature-codes-manager.php" class="btn">Feature Codes</a>
            <a href="../feature-codes.php" class="btn btn-secondary">View Feature Codes</a>
        </p>
    </div>
</div>
