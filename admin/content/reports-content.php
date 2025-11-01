<?php
/**
 * Reports Tab Content
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
</style>

<div class="content-wrapper">
    <h1 class="section-title">Reports & Analytics</h1>

    <div class="info-card">
        <h3>Analytics Dashboard</h3>
        <p>This section will display call analytics, usage reports, and system performance metrics.</p>
        <p style="margin-top: 1rem;">Coming soon: Call volume reports, extension usage statistics, and trend analysis.</p>
    </div>
</div>
