<?php
/**
 * Network Settings Sub-tab Content
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
</style>

<div class="settings-wrapper">
    <h1 class="section-title">Network Settings</h1>

    <div class="info-card">
        <h3>Network & Firewall Configuration</h3>
        <p>Configure network settings, firewall rules, and security options.</p>

        <ul>
            <li>Firewall (CSF/iptables) configuration</li>
            <li>IP whitelisting and blacklisting</li>
            <li>Port management (SIP, RTP, STUN)</li>
            <li>Fail2ban monitoring and settings</li>
            <li>STUN/TURN server configuration</li>
            <li>NAT traversal settings</li>
        </ul>

        <p style="margin-top: 1rem; color: #fbbf24;">
            ⚠️ Network settings require SSH access. Please use the command line for advanced configuration.
        </p>
    </div>
</div>
