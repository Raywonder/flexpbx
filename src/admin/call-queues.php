<?php require_once __DIR__ . '/admin_auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Queue Management - FlexPBX Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 14px;
        }

        .back-link {
            display: inline-block;
            color: #667eea;
            text-decoration: none;
            margin-top: 10px;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .main-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
            flex-wrap: wrap;
        }

        .tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 15px;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .tab:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .tooltip {
            position: relative;
            display: inline-block;
            margin-left: 5px;
            cursor: help;
        }

        .tooltiptext {
            visibility: hidden;
            width: 300px;
            background-color: #333;
            color: #fff;
            text-align: left;
            border-radius: 6px;
            padding: 10px;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            margin-left: -150px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 13px;
            line-height: 1.4;
            font-weight: normal;
        }

        .tooltiptext::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 20px;
        }

        .close:hover {
            color: #000;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .help-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }

        .help-section h3 {
            color: #667eea;
            margin-bottom: 15px;
        }

        .help-section h4 {
            color: #333;
            margin: 15px 0 10px 0;
        }

        .help-section ul, .help-section ol {
            margin-left: 20px;
            line-height: 1.8;
        }

        .help-section code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            color: #e83e8c;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .loading::after {
            content: "...";
            animation: dots 1.5s steps(4, end) infinite;
        }

        @keyframes dots {
            0%, 20% { content: "."; }
            40% { content: ".."; }
            60%, 100% { content: "..."; }
        }

        .wallboard {
            background: #1a1a1a;
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .wallboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
        }

        .wallboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .wallboard-stat {
            background: #2a2a2a;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .wallboard-stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #4ade80;
        }

        .wallboard-stat-label {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .agent-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }

        .agent-card {
            background: #2a2a2a;
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid #4ade80;
        }

        .agent-card.paused {
            border-left-color: #f59e0b;
        }

        .agent-card.unavailable {
            border-left-color: #ef4444;
        }

        .agent-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .agent-status {
            font-size: 12px;
            color: #999;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .tabs {
                overflow-x: auto;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📞 Call Queue Management</h1>
            <p class="subtitle">Manage call queues, agents, and monitor real-time queue performance</p>
            <a href="dashboard.html" class="back-link">← Back to Dashboard</a>
        </div>

        <div class="main-content">
            <div class="tabs">
                <button class="tab active" onclick="switchTab('overview')">Queue Overview</button>
                <button class="tab" onclick="switchTab('manage')">Manage Queues</button>
                <button class="tab" onclick="switchTab('members')">Queue Members</button>
                <button class="tab" onclick="switchTab('wallboard')">Live Wallboard</button>
                <button class="tab" onclick="switchTab('statistics')">Statistics</button>
                <button class="tab" onclick="switchTab('help')">How to Use</button>
            </div>

            <div id="alert-container"></div>

            <!-- Queue Overview Tab -->
            <div id="overview-tab" class="tab-content active">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Active Queues</h2>
                    <button class="btn" onclick="refreshQueues()">🔄 Refresh</button>
                </div>

                <div id="queues-list">
                    <div class="loading">Loading queues</div>
                </div>
            </div>

            <!-- Manage Queues Tab -->
            <div id="manage-tab" class="tab-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Queue Configuration</h2>
                    <button class="btn" onclick="showCreateQueueModal()">➕ Create New Queue</button>
                </div>

                <div id="manage-queues-list">
                    <div class="loading">Loading queues</div>
                </div>
            </div>

            <!-- Queue Members Tab -->
            <div id="members-tab" class="tab-content">
                <h2>Queue Member Management</h2>
                <p style="color: #666; margin-bottom: 20px;">Add agents to queues, set penalties, and manage agent status</p>

                <div class="form-group">
                    <label>Select Queue:</label>
                    <select id="member-queue-select" onchange="loadQueueMembers()">
                        <option value="">-- Select a Queue --</option>
                    </select>
                </div>

                <div id="queue-members-content" style="display: none;">
                    <button class="btn btn-success" style="margin-bottom: 20px;" onclick="showAddMemberModal()">➕ Add Agent to Queue</button>

                    <div id="members-list">
                        <div class="loading">Loading members</div>
                    </div>
                </div>
            </div>

            <!-- Live Wallboard Tab -->
            <div id="wallboard-tab" class="tab-content">
                <div class="wallboard">
                    <div class="wallboard-header">
                        <h2 style="margin: 0; color: white;">📊 Live Queue Wallboard</h2>
                        <div>
                            <span id="wallboard-time" style="font-size: 18px; margin-right: 20px;"></span>
                            <button class="btn btn-small" onclick="refreshWallboard()">🔄 Refresh</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="color: white;">Select Queue:</label>
                        <select id="wallboard-queue-select" onchange="loadWallboard()" style="max-width: 400px;">
                            <option value="">-- Select a Queue --</option>
                        </select>
                    </div>

                    <div id="wallboard-content" style="display: none;">
                        <div class="wallboard-stats">
                            <div class="wallboard-stat">
                                <div class="wallboard-stat-number" id="wb-waiting">0</div>
                                <div class="wallboard-stat-label">Waiting Calls</div>
                            </div>
                            <div class="wallboard-stat">
                                <div class="wallboard-stat-number" id="wb-available">0</div>
                                <div class="wallboard-stat-label">Available Agents</div>
                            </div>
                            <div class="wallboard-stat">
                                <div class="wallboard-stat-number" id="wb-busy">0</div>
                                <div class="wallboard-stat-label">Busy Agents</div>
                            </div>
                            <div class="wallboard-stat">
                                <div class="wallboard-stat-number" id="wb-paused">0</div>
                                <div class="wallboard-stat-label">Paused Agents</div>
                            </div>
                            <div class="wallboard-stat">
                                <div class="wallboard-stat-number" id="wb-avgwait">0s</div>
                                <div class="wallboard-stat-label">Avg Wait Time</div>
                            </div>
                            <div class="wallboard-stat">
                                <div class="wallboard-stat-number" id="wb-longest">0s</div>
                                <div class="wallboard-stat-label">Longest Wait</div>
                            </div>
                        </div>

                        <h3 style="color: white; margin: 20px 0 10px 0;">Agents</h3>
                        <div id="wallboard-agents" class="agent-list">
                            <div style="grid-column: 1 / -1; text-align: center; color: #666;">No agents available</div>
                        </div>

                        <div id="wallboard-calls" style="margin-top: 20px;">
                            <h3 style="color: white; margin-bottom: 10px;">Waiting Calls</h3>
                            <div id="wallboard-calls-list" style="color: #999;">
                                No calls waiting
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Tab -->
            <div id="statistics-tab" class="tab-content">
                <h2>Queue Statistics</h2>
                <p style="color: #666; margin-bottom: 20px;">View historical queue performance and analytics</p>

                <div class="form-group">
                    <label>Select Queue:</label>
                    <select id="stats-queue-select" onchange="loadStatistics()">
                        <option value="">-- Select a Queue --</option>
                    </select>
                </div>

                <div id="statistics-content" style="display: none;">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number" id="stat-offered">0</div>
                            <div class="stat-label">Total Calls Offered</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="stat-answered">0</div>
                            <div class="stat-label">Calls Answered</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="stat-abandoned">0</div>
                            <div class="stat-label">Calls Abandoned</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="stat-sla">0%</div>
                            <div class="stat-label">Service Level</div>
                        </div>
                    </div>

                    <div id="statistics-table">
                        <h3 style="margin: 30px 0 15px 0;">Daily Statistics</h3>
                        <div class="loading">Loading statistics</div>
                    </div>
                </div>
            </div>

            <!-- How to Use Tab -->
            <div id="help-tab" class="tab-content">
                <h2>📚 Call Queue Management Guide</h2>

                <div class="help-section">
                    <h3>What are Call Queues?</h3>
                    <p>Call queues (also called ACD - Automatic Call Distribution) are a powerful feature that allows incoming calls to be distributed among a group of agents based on various strategies. When all agents are busy, callers wait in line and are connected to the next available agent.</p>
                </div>

                <div class="help-section">
                    <h3>Common Use Cases</h3>
                    <ul>
                        <li><strong>Customer Support:</strong> Route customer calls to available support agents</li>
                        <li><strong>Sales Teams:</strong> Distribute incoming sales inquiries fairly among sales reps</li>
                        <li><strong>Help Desk:</strong> Queue technical support requests with priority handling</li>
                        <li><strong>Reception:</strong> Handle overflow calls when main receptionist is busy</li>
                        <li><strong>Departments:</strong> Route calls to accounting, billing, or other departments</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Queue Strategies Explained</h3>
                    <h4>Ring All</h4>
                    <p>All available agents ring simultaneously. First to answer gets the call. Best for small teams where you want fastest answer time.</p>

                    <h4>Least Recent</h4>
                    <p>Calls go to the agent who hasn't taken a call in the longest time. Ensures fair distribution of call volume.</p>

                    <h4>Fewest Calls</h4>
                    <p>Routes to the agent who has answered the fewest calls. Perfect for balancing workload.</p>

                    <h4>Random</h4>
                    <p>Calls distributed randomly among available agents. Simple and unpredictable distribution.</p>

                    <h4>Round Robin (rrmemory)</h4>
                    <p>Cycles through agents in order, remembering where it left off. Classic fair distribution method.</p>

                    <h4>Linear</h4>
                    <p>Always starts from the first agent and works down the list. Good for priority-based agent ordering.</p>

                    <h4>Weighted Random (wrandom)</h4>
                    <p>Random distribution but respects agent penalty scores. Lower penalty = more calls.</p>
                </div>

                <div class="help-section">
                    <h3>How to Create a Queue</h3>
                    <ol>
                        <li>Go to the <strong>"Manage Queues"</strong> tab</li>
                        <li>Click <strong>"Create New Queue"</strong></li>
                        <li>Enter a queue number (e.g., 5000 for sales, 5001 for support)</li>
                        <li>Give it a descriptive name (e.g., "Sales Department")</li>
                        <li>Choose a ring strategy (start with "ringall" or "leastrecent")</li>
                        <li>Set timeout (how long to ring each agent - 15 seconds is typical)</li>
                        <li>Configure other settings as needed</li>
                        <li>Click <strong>"Create Queue"</strong></li>
                        <li>Click <strong>"Apply Configuration"</strong> to activate</li>
                    </ol>
                </div>

                <div class="help-section">
                    <h3>How to Add Agents to a Queue</h3>
                    <ol>
                        <li>Go to the <strong>"Queue Members"</strong> tab</li>
                        <li>Select the queue from the dropdown</li>
                        <li>Click <strong>"Add Agent to Queue"</strong></li>
                        <li>Enter the extension number (e.g., 2000, 2001)</li>
                        <li>Optionally set a name for easy identification</li>
                        <li>Set penalty (0 = highest priority, higher numbers = lower priority)</li>
                        <li>Click <strong>"Add Member"</strong></li>
                        <li>Click <strong>"Apply Configuration"</strong> to activate</li>
                    </ol>
                </div>

                <div class="help-section">
                    <h3>Understanding Agent Penalties</h3>
                    <p>Penalties control agent priority. Lower penalty = higher priority.</p>
                    <ul>
                        <li><strong>Penalty 0:</strong> Senior agents, answered first</li>
                        <li><strong>Penalty 1-3:</strong> Regular agents</li>
                        <li><strong>Penalty 4-5:</strong> Backup/overflow agents, only when others busy</li>
                    </ul>
                    <p>Example: Set your best agent to penalty 0, regular team to penalty 1, and trainees to penalty 3.</p>
                </div>

                <div class="help-section">
                    <h3>Agent Pause/Unpause</h3>
                    <p>Agents can pause themselves to temporarily stop receiving queue calls (for breaks, meetings, etc.)</p>

                    <h4>To Pause an Agent:</h4>
                    <ol>
                        <li>Go to <strong>"Queue Members"</strong> tab</li>
                        <li>Select the queue</li>
                        <li>Click <strong>"Pause"</strong> next to the agent</li>
                        <li>Enter a reason (optional but recommended)</li>
                    </ol>

                    <h4>Agent Pause Codes (Dial from Extension):</h4>
                    <ul>
                        <li><code>*75</code> - Log in to queue (unpause)</li>
                        <li><code>*76</code> - Log out from queue (pause)</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Using the Live Wallboard</h3>
                    <p>The wallboard provides real-time queue monitoring. Perfect for supervisors to display on a screen.</p>

                    <p><strong>What you'll see:</strong></p>
                    <ul>
                        <li>Number of calls waiting</li>
                        <li>Available, busy, and paused agents</li>
                        <li>Average and longest wait times</li>
                        <li>Individual agent status</li>
                        <li>List of waiting calls with wait time</li>
                    </ul>

                    <p><strong>Tip:</strong> Auto-refresh is every 10 seconds. Open wallboard on a dedicated monitor for queue oversight.</p>
                </div>

                <div class="help-section">
                    <h3>Understanding Queue Settings</h3>

                    <h4>Timeout</h4>
                    <p>How long to ring each agent before trying the next. 15 seconds is standard.</p>

                    <h4>Retry</h4>
                    <p>Seconds to wait before retrying agents after everyone was tried. 5 seconds is typical.</p>

                    <h4>Max Wait Time</h4>
                    <p>Maximum time a caller can wait in queue. 0 = unlimited. Set to 300 (5 min) to prevent excessive waits.</p>

                    <h4>Max Callers</h4>
                    <p>Maximum callers allowed in queue. 0 = unlimited. Prevents queue overload.</p>

                    <h4>Announce Frequency</h4>
                    <p>How often (in seconds) to play periodic announcements to waiting callers. 90 seconds is common.</p>

                    <h4>Announce Position</h4>
                    <p>Tell callers their position in line ("You are number 3 in line"). Choose "yes" for transparency.</p>

                    <h4>Announce Hold Time</h4>
                    <p>Tell callers estimated wait time. "yes" = announce every time, "once" = only when entering queue.</p>

                    <h4>Wrap-up Time</h4>
                    <p>Seconds after finishing a call before agent gets next call. Allows time for notes. 30-60 seconds typical.</p>

                    <h4>Service Level</h4>
                    <p>Target time (in seconds) to answer calls for SLA reporting. 60 seconds = "calls answered within 1 minute".</p>
                </div>

                <div class="help-section">
                    <h3>Routing Calls to a Queue</h3>
                    <p>To route incoming calls to a queue, you have several options:</p>

                    <h4>Option 1: Direct DID Routing</h4>
                    <p>In Inbound Routing, set the destination to the queue number (e.g., 5000)</p>

                    <h4>Option 2: IVR Menu Option</h4>
                    <p>Create an IVR menu with options like "Press 1 for Sales" routing to queue 5000</p>

                    <h4>Option 3: Transfer from Extension</h4>
                    <p>Any extension can transfer a call to a queue by dialing the queue number</p>

                    <h4>Option 4: Time-Based Routing</h4>
                    <p>Use Time Conditions to route to queue during business hours, voicemail after hours</p>
                </div>

                <div class="help-section">
                    <h3>Best Practices</h3>
                    <ul>
                        <li><strong>Start Simple:</strong> Begin with "ringall" or "leastrecent" strategy</li>
                        <li><strong>Set Realistic Timeouts:</strong> 15 seconds gives agents time to finish typing/switch tasks</li>
                        <li><strong>Use Penalties Wisely:</strong> Don't over-complicate. 0-2 levels is usually enough</li>
                        <li><strong>Monitor Regularly:</strong> Check statistics weekly to identify bottlenecks</li>
                        <li><strong>Set Max Wait Time:</strong> Don't let callers wait indefinitely. Offer callback or voicemail</li>
                        <li><strong>Use Wrap-up Time:</strong> Give agents 30-60 seconds between calls for notes</li>
                        <li><strong>Announce Position:</strong> Transparency reduces hangups ("You are next in line")</li>
                        <li><strong>Test Thoroughly:</strong> Call the queue from external number to experience caller journey</li>
                        <li><strong>Train Agents:</strong> Ensure agents know how to use *75/*76 pause codes</li>
                        <li><strong>Review Statistics:</strong> Use SLA metrics to optimize staffing levels</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Troubleshooting</h3>

                    <h4>Queue not ringing agents</h4>
                    <ul>
                        <li>Verify queue is enabled</li>
                        <li>Check that agents are added as members</li>
                        <li>Ensure agents are not paused</li>
                        <li>Click "Apply Configuration" after any changes</li>
                        <li>Verify extensions are registered (check admin dashboard)</li>
                    </ul>

                    <h4>Calls going straight to voicemail</h4>
                    <ul>
                        <li>Check DID routing points to correct queue number</li>
                        <li>Verify queue has at least one available agent</li>
                        <li>Check if max_callers limit is reached</li>
                    </ul>

                    <h4>No music on hold for waiting callers</h4>
                    <ul>
                        <li>Check music_class setting in queue configuration</li>
                        <li>Verify MOH files exist in /var/lib/asterisk/moh/</li>
                        <li>Set to "default" for system default MOH</li>
                    </ul>

                    <h4>Statistics not updating</h4>
                    <ul>
                        <li>Statistics are calculated from Asterisk CDR (Call Detail Records)</li>
                        <li>Ensure CDR logging is enabled in Asterisk</li>
                        <li>Check database connectivity</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Feature Codes Quick Reference</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Function</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>*75</code></td>
                                <td>Queue Login</td>
                                <td>Agent dials to unpause and start receiving queue calls</td>
                            </tr>
                            <tr>
                                <td><code>*76</code></td>
                                <td>Queue Logout</td>
                                <td>Agent dials to pause and stop receiving queue calls</td>
                            </tr>
                            <tr>
                                <td><code>5000-5999</code></td>
                                <td>Queue Numbers</td>
                                <td>Typical range for queue extensions (configurable)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-info" style="margin-top: 30px;">
                    <strong>💡 Pro Tip:</strong> For high-volume call centers, consider setting up multiple queues by department or expertise level. Use penalties to create skill-based routing where specialized agents get calls first, with overflow to general agents.
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Queue Modal -->
    <div id="queue-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeQueueModal()">&times;</span>
            <h2 id="queue-modal-title">Create New Queue</h2>
            <form id="queue-form" onsubmit="saveQueue(event)">
                <input type="hidden" id="queue-id" name="id">

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            Queue Number *
                            <span class="tooltip">❔
                                <span class="tooltiptext">The extension number to dial to reach this queue. Typically 5000-5999. Must be unique.</span>
                            </span>
                        </label>
                        <input type="text" id="queue-number" name="queue_number" required pattern="[0-9]+" placeholder="e.g., 5000">
                    </div>

                    <div class="form-group">
                        <label>
                            Queue Name *
                            <span class="tooltip">❔
                                <span class="tooltiptext">Descriptive name for this queue. E.g., "Sales Department", "Customer Support", "Technical Help Desk"</span>
                            </span>
                        </label>
                        <input type="text" id="queue-name" name="queue_name" required placeholder="e.g., Sales Department">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            Ring Strategy *
                            <span class="tooltip">❔
                                <span class="tooltiptext">
                                    <strong>ringall:</strong> All agents ring at once<br>
                                    <strong>leastrecent:</strong> Agent who hasn't taken call longest<br>
                                    <strong>fewestcalls:</strong> Agent with fewest answered calls<br>
                                    <strong>random:</strong> Random agent selection<br>
                                    <strong>rrmemory:</strong> Round-robin with memory<br>
                                    <strong>linear:</strong> In order, top to bottom
                                </span>
                            </span>
                        </label>
                        <select id="queue-strategy" name="strategy" required>
                            <option value="ringall">Ring All (simultaneous)</option>
                            <option value="leastrecent">Least Recent</option>
                            <option value="fewestcalls">Fewest Calls</option>
                            <option value="random">Random</option>
                            <option value="rrmemory">Round Robin (memory)</option>
                            <option value="linear">Linear (in order)</option>
                            <option value="wrandom">Weighted Random</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            Music on Hold Class
                            <span class="tooltip">❔
                                <span class="tooltiptext">Music played to callers while waiting. "default" uses system default MOH. You can create custom MOH classes in Music on Hold Manager.</span>
                            </span>
                        </label>
                        <input type="text" id="queue-music" name="music_class" value="default" placeholder="default">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            Agent Timeout (seconds) *
                            <span class="tooltip">❔
                                <span class="tooltiptext">How long to ring each agent before trying the next. 15 seconds is standard. Lower = faster rotation, higher = agents have more time to answer.</span>
                            </span>
                        </label>
                        <input type="number" id="queue-timeout" name="timeout" required value="15" min="5" max="60">
                    </div>

                    <div class="form-group">
                        <label>
                            Retry Delay (seconds)
                            <span class="tooltip">❔
                                <span class="tooltiptext">How long to wait before retrying all agents after everyone has been tried. 5 seconds prevents excessive ringing.</span>
                            </span>
                        </label>
                        <input type="number" id="queue-retry" name="retry" value="5" min="1" max="30">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            Max Wait Time (seconds)
                            <span class="tooltip">❔
                                <span class="tooltiptext">Maximum time a caller can wait in queue. 0 = unlimited. Set to 300 (5 min) to prevent excessive waits. After timeout, call follows no-answer destination.</span>
                            </span>
                        </label>
                        <input type="number" id="queue-maxwait" name="max_wait_time" value="0" min="0">
                    </div>

                    <div class="form-group">
                        <label>
                            Max Callers
                            <span class="tooltip">❔
                                <span class="tooltiptext">Maximum number of callers allowed in queue. 0 = unlimited. Set a limit to prevent queue overload and provide alternative routing.</span>
                            </span>
                        </label>
                        <input type="number" id="queue-maxcallers" name="max_callers" value="0" min="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            Announcement Frequency (seconds)
                            <span class="tooltip">❔
                                <span class="tooltiptext">How often to play periodic announcements to waiting callers. 90 seconds is common. 0 = no periodic announcements.</span>
                            </span>
                        </label>
                        <input type="number" id="queue-announce-freq" name="announce_frequency" value="90" min="0">
                    </div>

                    <div class="form-group">
                        <label>
                            Wrap-up Time (seconds)
                            <span class="tooltip">❔
                                <span class="tooltiptext">Time given to agent after finishing a call before receiving the next call. Allows time for notes. 30-60 seconds is typical. 0 = immediate next call.</span>
                            </span>
                        </label>
                        <input type="number" id="queue-wrapup" name="wrap_up_time" value="0" min="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            Announce Position
                            <span class="tooltip">❔
                                <span class="tooltiptext">
                                    <strong>yes:</strong> Tell caller their position ("You are number 3")<br>
                                    <strong>no:</strong> Don't announce position<br>
                                    <strong>limit:</strong> Only when more than 'announce_frequency'<br>
                                    <strong>more:</strong> Announce if more callers than agents
                                </span>
                            </span>
                        </label>
                        <select id="queue-announce-pos" name="announce_position">
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                            <option value="limit">Limit</option>
                            <option value="more">More</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            Announce Hold Time
                            <span class="tooltip">❔
                                <span class="tooltiptext">
                                    <strong>yes:</strong> Announce estimated wait time periodically<br>
                                    <strong>no:</strong> Never announce wait time<br>
                                    <strong>once:</strong> Only announce when entering queue
                                </span>
                            </span>
                        </label>
                        <select id="queue-announce-hold" name="announce_holdtime">
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                            <option value="once">Once</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            Service Level (seconds)
                            <span class="tooltip">❔
                                <span class="tooltiptext">Target time to answer calls for SLA reporting. 60 = "percentage of calls answered within 1 minute". Used for statistics only, doesn't affect call behavior.</span>
                            </span>
                        </label>
                        <input type="number" id="queue-service-level" name="service_level" value="60" min="1">
                    </div>

                    <div class="form-group">
                        <label>
                            Auto Pause
                            <span class="tooltip">❔
                                <span class="tooltiptext">
                                    <strong>no:</strong> Never auto-pause agents<br>
                                    <strong>yes:</strong> Pause agent if they don't answer<br>
                                    <strong>all:</strong> Pause on all devices if one fails
                                </span>
                            </span>
                        </label>
                        <select id="queue-autopause" name="auto_pause">
                            <option value="no">No</option>
                            <option value="yes">Yes</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        Join Announcement
                        <span class="tooltip">❔
                            <span class="tooltiptext">Audio file to play when caller enters queue. Leave blank for no announcement. E.g., "Thank you for calling. Please hold for the next available agent."</span>
                        </span>
                    </label>
                    <input type="text" id="queue-join-announce" name="join_announcement" placeholder="Optional: custom/welcome-to-queue">
                </div>

                <div class="form-group">
                    <label>
                        Periodic Announcements
                        <span class="tooltip">❔
                            <span class="tooltiptext">Comma-separated list of audio files to play periodically to waiting callers. E.g., "custom/still-waiting,custom/your-call-important". Rotates through list.</span>
                        </span>
                    </label>
                    <input type="text" id="queue-periodic" name="periodic_announce" placeholder="Optional: custom/announcement1,custom/announcement2">
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="queue-enabled" name="enabled" checked style="width: auto;">
                        <span>
                            Queue Enabled
                            <span class="tooltip">❔
                                <span class="tooltiptext">Enable or disable this queue. Disabled queues won't accept calls but configuration is preserved.</span>
                            </span>
                        </span>
                    </label>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="submit" class="btn btn-success">💾 Save Queue</button>
                    <button type="button" class="btn btn-secondary" onclick="closeQueueModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Member Modal -->
    <div id="member-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeMemberModal()">&times;</span>
            <h2>Add Agent to Queue</h2>
            <form id="member-form" onsubmit="saveMember(event)">
                <input type="hidden" id="member-queue-id" name="queue_id">

                <div class="form-group">
                    <label>
                        Extension Number *
                        <span class="tooltip">❔
                            <span class="tooltiptext">The extension number of the agent. E.g., 2000, 2001, 2002. Extension must exist in the system.</span>
                        </span>
                    </label>
                    <input type="text" id="member-extension" name="member_extension" required pattern="[0-9]+" placeholder="e.g., 2000">
                </div>

                <div class="form-group">
                    <label>
                        Display Name
                        <span class="tooltip">❔
                            <span class="tooltiptext">Optional friendly name for this agent. E.g., "John Smith", "Support Team A". Makes monitoring easier.</span>
                        </span>
                    </label>
                    <input type="text" id="member-name" name="member_name" placeholder="Optional: e.g., John Smith">
                </div>

                <div class="form-group">
                    <label>
                        Penalty
                        <span class="tooltip">❔
                            <span class="tooltiptext">
                                Agent priority level. Lower = higher priority.<br>
                                <strong>0:</strong> Highest priority (senior agents)<br>
                                <strong>1-2:</strong> Normal priority<br>
                                <strong>3-5:</strong> Backup/overflow only<br>
                                Calls go to lowest penalty agents first.
                            </span>
                        </span>
                    </label>
                    <input type="number" id="member-penalty" name="penalty" value="0" min="0" max="10">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="submit" class="btn btn-success">➕ Add Agent</button>
                    <button type="button" class="btn btn-secondary" onclick="closeMemberModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentQueues = [];
        let wallboardInterval = null;

        // Tab Switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');

            // Stop wallboard refresh if leaving that tab
            if (tabName !== 'wallboard' && wallboardInterval) {
                clearInterval(wallboardInterval);
                wallboardInterval = null;
            }

            // Load data for specific tabs
            if (tabName === 'overview') {
                refreshQueues();
            } else if (tabName === 'manage') {
                loadManageQueues();
            } else if (tabName === 'wallboard') {
                loadWallboardQueues();
                startWallboardRefresh();
            } else if (tabName === 'statistics') {
                loadStatisticsQueues();
            } else if (tabName === 'members') {
                loadMemberQueues();
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            refreshQueues();
            updateWallboardTime();
            setInterval(updateWallboardTime, 1000);
        });

        function updateWallboardTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString();
            const timeEl = document.getElementById('wallboard-time');
            if (timeEl) {
                timeEl.textContent = timeStr;
            }
        }

        // Alert Functions
        function showAlert(message, type = 'success') {
            const container = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            container.appendChild(alert);

            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        // Queue Overview Functions
        async function refreshQueues() {
            const container = document.getElementById('queues-list');
            container.innerHTML = '<div class="loading">Loading queues</div>';

            try {
                const response = await fetch('/api/call-queues.php?path=list');
                const result = await response.json();

                if (result.success) {
                    currentQueues = result.data;
                    displayQueues(result.data);
                } else {
                    container.innerHTML = `<div class="alert alert-error">${result.message}</div>`;
                }
            } catch (error) {
                container.innerHTML = `<div class="alert alert-error">Error loading queues: ${error.message}</div>`;
            }
        }

        function displayQueues(queues) {
            const container = document.getElementById('queues-list');

            if (queues.length === 0) {
                container.innerHTML = `
                    <div class="alert alert-info">
                        <strong>No queues configured yet.</strong><br>
                        Click "Manage Queues" tab and create your first queue to get started.
                    </div>
                `;
                return;
            }

            let html = '<table><thead><tr>';
            html += '<th>Queue Number</th>';
            html += '<th>Name</th>';
            html += '<th>Strategy</th>';
            html += '<th>Members</th>';
            html += '<th>Status</th>';
            html += '<th>Timeout</th>';
            html += '</tr></thead><tbody>';

            queues.forEach(queue => {
                const statusBadge = queue.enabled == 1
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-danger">Disabled</span>';

                html += `<tr>
                    <td><strong>${queue.queue_number}</strong></td>
                    <td>${queue.queue_name}</td>
                    <td>${queue.strategy}</td>
                    <td>${queue.member_count || 0} agents</td>
                    <td>${statusBadge}</td>
                    <td>${queue.timeout}s</td>
                </tr>`;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Manage Queues Functions
        async function loadManageQueues() {
            const container = document.getElementById('manage-queues-list');
            container.innerHTML = '<div class="loading">Loading queues</div>';

            try {
                const response = await fetch('/api/call-queues.php?path=list');
                const result = await response.json();

                if (result.success) {
                    displayManageQueues(result.data);
                } else {
                    container.innerHTML = `<div class="alert alert-error">${result.message}</div>`;
                }
            } catch (error) {
                container.innerHTML = `<div class="alert alert-error">Error: ${error.message}</div>`;
            }
        }

        function displayManageQueues(queues) {
            const container = document.getElementById('manage-queues-list');

            if (queues.length === 0) {
                container.innerHTML = `
                    <div class="alert alert-info">
                        No queues configured. Click "Create New Queue" to add your first queue.
                    </div>
                `;
                return;
            }

            let html = '<table><thead><tr>';
            html += '<th>Queue</th>';
            html += '<th>Strategy</th>';
            html += '<th>Settings</th>';
            html += '<th>Status</th>';
            html += '<th>Actions</th>';
            html += '</tr></thead><tbody>';

            queues.forEach(queue => {
                const statusBadge = queue.enabled == 1
                    ? '<span class="badge badge-success">Enabled</span>'
                    : '<span class="badge badge-danger">Disabled</span>';

                html += `<tr>
                    <td>
                        <strong>${queue.queue_number}</strong><br>
                        <small>${queue.queue_name}</small>
                    </td>
                    <td>${queue.strategy}</td>
                    <td>
                        <small>
                            Timeout: ${queue.timeout}s<br>
                            MOH: ${queue.music_class}<br>
                            Members: ${queue.member_count || 0}
                        </small>
                    </td>
                    <td>${statusBadge}</td>
                    <td>
                        <button class="btn btn-small btn-secondary" onclick='editQueue(${JSON.stringify(queue)})'>✏️ Edit</button>
                        <button class="btn btn-small btn-danger" onclick="deleteQueue(${queue.id}, '${queue.queue_name}')">🗑️ Delete</button>
                    </td>
                </tr>`;
            });

            html += '</tbody></table>';
            html += '<div style="margin-top: 20px;"><button class="btn btn-success" onclick="applyConfiguration()">✅ Apply Configuration to Asterisk</button></div>';
            container.innerHTML = html;
        }

        function showCreateQueueModal() {
            document.getElementById('queue-modal-title').textContent = 'Create New Queue';
            document.getElementById('queue-form').reset();
            document.getElementById('queue-id').value = '';
            document.getElementById('queue-enabled').checked = true;
            document.getElementById('queue-modal').style.display = 'block';
        }

        function editQueue(queue) {
            document.getElementById('queue-modal-title').textContent = 'Edit Queue';
            document.getElementById('queue-id').value = queue.id;
            document.getElementById('queue-number').value = queue.queue_number;
            document.getElementById('queue-name').value = queue.queue_name;
            document.getElementById('queue-strategy').value = queue.strategy;
            document.getElementById('queue-timeout').value = queue.timeout;
            document.getElementById('queue-retry').value = queue.retry;
            document.getElementById('queue-maxwait').value = queue.max_wait_time;
            document.getElementById('queue-maxcallers').value = queue.max_callers;
            document.getElementById('queue-announce-freq').value = queue.announce_frequency;
            document.getElementById('queue-announce-pos').value = queue.announce_position;
            document.getElementById('queue-announce-hold').value = queue.announce_holdtime;
            document.getElementById('queue-music').value = queue.music_class;
            document.getElementById('queue-wrapup').value = queue.wrap_up_time;
            document.getElementById('queue-service-level').value = queue.service_level;
            document.getElementById('queue-autopause').value = queue.auto_pause;
            document.getElementById('queue-join-announce').value = queue.join_announcement || '';
            document.getElementById('queue-periodic').value = queue.periodic_announce || '';
            document.getElementById('queue-enabled').checked = queue.enabled == 1;
            document.getElementById('queue-modal').style.display = 'block';
        }

        function closeQueueModal() {
            document.getElementById('queue-modal').style.display = 'none';
        }

        async function saveQueue(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const data = {};

            formData.forEach((value, key) => {
                if (key === 'enabled') {
                    data[key] = document.getElementById('queue-enabled').checked ? 1 : 0;
                } else {
                    data[key] = value;
                }
            });

            const queueId = document.getElementById('queue-id').value;
            const isEdit = queueId !== '';

            try {
                const url = isEdit
                    ? `/api/call-queues.php?path=update&id=${queueId}`
                    : '/api/call-queues.php?path=create';

                const response = await fetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(isEdit ? 'Queue updated successfully!' : 'Queue created successfully!', 'success');
                    closeQueueModal();
                    loadManageQueues();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('Error saving queue: ' + error.message, 'error');
            }
        }

        async function deleteQueue(id, name) {
            if (!confirm(`Are you sure you want to delete queue "${name}"? This will also remove all agents from this queue.`)) {
                return;
            }

            try {
                const response = await fetch(`/api/call-queues.php?path=delete&id=${id}`, {
                    method: 'DELETE'
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Queue deleted successfully!', 'success');
                    loadManageQueues();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('Error deleting queue: ' + error.message, 'error');
            }
        }

        async function applyConfiguration() {
            if (!confirm('Apply queue configuration to Asterisk? This will reload all queues.')) {
                return;
            }

            try {
                const response = await fetch('/api/call-queues.php?path=apply-config', {
                    method: 'POST'
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Configuration applied successfully! Queues reloaded.', 'success');
                } else {
                    showAlert('Error applying configuration: ' + result.message, 'error');
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'error');
            }
        }

        // Queue Members Functions
        async function loadMemberQueues() {
            const select = document.getElementById('member-queue-select');
            select.innerHTML = '<option value="">-- Select a Queue --</option>';

            try {
                const response = await fetch('/api/call-queues.php?path=list');
                const result = await response.json();

                if (result.success) {
                    result.data.forEach(queue => {
                        const option = document.createElement('option');
                        option.value = queue.id;
                        option.textContent = `${queue.queue_number} - ${queue.queue_name}`;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                showAlert('Error loading queues: ' + error.message, 'error');
            }
        }

        async function loadQueueMembers() {
            const queueId = document.getElementById('member-queue-select').value;
            const content = document.getElementById('queue-members-content');
            const membersList = document.getElementById('members-list');

            if (!queueId) {
                content.style.display = 'none';
                return;
            }

            content.style.display = 'block';
            membersList.innerHTML = '<div class="loading">Loading members</div>';

            try {
                const response = await fetch(`/api/call-queues.php?path=members&queue_id=${queueId}`);
                const result = await response.json();

                if (result.success) {
                    displayMembers(result.data, queueId);
                } else {
                    membersList.innerHTML = `<div class="alert alert-error">${result.message}</div>`;
                }
            } catch (error) {
                membersList.innerHTML = `<div class="alert alert-error">Error: ${error.message}</div>`;
            }
        }

        function displayMembers(members, queueId) {
            const container = document.getElementById('members-list');

            if (members.length === 0) {
                container.innerHTML = `
                    <div class="alert alert-info">
                        No agents assigned to this queue yet. Click "Add Agent to Queue" to add your first agent.
                    </div>
                `;
                return;
            }

            let html = '<table><thead><tr>';
            html += '<th>Extension</th>';
            html += '<th>Name</th>';
            html += '<th>Penalty</th>';
            html += '<th>Status</th>';
            html += '<th>Actions</th>';
            html += '</tr></thead><tbody>';

            members.forEach(member => {
                const statusBadge = member.paused == 1
                    ? '<span class="badge badge-warning">Paused</span>'
                    : '<span class="badge badge-success">Active</span>';

                const pauseReason = member.paused == 1 && member.paused_reason
                    ? `<br><small>${member.paused_reason}</small>`
                    : '';

                html += `<tr>
                    <td><strong>${member.member_extension}</strong></td>
                    <td>${member.member_name || '-'}</td>
                    <td>${member.penalty}</td>
                    <td>${statusBadge}${pauseReason}</td>
                    <td>
                        ${member.paused == 1
                            ? `<button class="btn btn-small btn-success" onclick="unpauseMember(${member.id}, ${queueId})">▶️ Unpause</button>`
                            : `<button class="btn btn-small btn-warning" onclick="pauseMember(${member.id}, ${queueId})">⏸️ Pause</button>`
                        }
                        <button class="btn btn-small btn-danger" onclick="removeMember(${member.id}, ${queueId}, '${member.member_extension}')">🗑️ Remove</button>
                    </td>
                </tr>`;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        function showAddMemberModal() {
            const queueId = document.getElementById('member-queue-select').value;
            if (!queueId) {
                showAlert('Please select a queue first', 'warning');
                return;
            }

            document.getElementById('member-queue-id').value = queueId;
            document.getElementById('member-form').reset();
            document.getElementById('member-queue-id').value = queueId; // Reset clears it, so set again
            document.getElementById('member-modal').style.display = 'block';
        }

        function closeMemberModal() {
            document.getElementById('member-modal').style.display = 'none';
        }

        async function saveMember(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const data = {};

            formData.forEach((value, key) => {
                data[key] = value;
            });

            try {
                const response = await fetch('/api/call-queues.php?path=add-member', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Agent added to queue successfully!', 'success');
                    closeMemberModal();
                    loadQueueMembers();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('Error adding member: ' + error.message, 'error');
            }
        }

        async function pauseMember(memberId, queueId) {
            const reason = prompt('Reason for pausing (optional):');
            if (reason === null) return; // Cancelled

            try {
                const response = await fetch('/api/call-queues.php?path=pause-member', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        member_id: memberId,
                        reason: reason || 'Manual pause'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Agent paused successfully!', 'success');
                    loadQueueMembers();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('Error pausing member: ' + error.message, 'error');
            }
        }

        async function unpauseMember(memberId, queueId) {
            try {
                const response = await fetch('/api/call-queues.php?path=unpause-member', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ member_id: memberId })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Agent unpaused successfully!', 'success');
                    loadQueueMembers();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('Error unpausing member: ' + error.message, 'error');
            }
        }

        async function removeMember(memberId, queueId, extension) {
            if (!confirm(`Remove extension ${extension} from this queue?`)) {
                return;
            }

            try {
                const response = await fetch(`/api/call-queues.php?path=remove-member&id=${memberId}`, {
                    method: 'DELETE'
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Agent removed from queue successfully!', 'success');
                    loadQueueMembers();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('Error removing member: ' + error.message, 'error');
            }
        }

        // Wallboard Functions
        async function loadWallboardQueues() {
            const select = document.getElementById('wallboard-queue-select');
            select.innerHTML = '<option value="">-- Select a Queue --</option>';

            try {
                const response = await fetch('/api/call-queues.php?path=list');
                const result = await response.json();

                if (result.success) {
                    result.data.forEach(queue => {
                        const option = document.createElement('option');
                        option.value = queue.queue_number;
                        option.textContent = `${queue.queue_number} - ${queue.queue_name}`;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                showAlert('Error loading queues: ' + error.message, 'error');
            }
        }

        function startWallboardRefresh() {
            if (wallboardInterval) {
                clearInterval(wallboardInterval);
            }
            wallboardInterval = setInterval(() => {
                const queueNumber = document.getElementById('wallboard-queue-select').value;
                if (queueNumber) {
                    loadWallboard();
                }
            }, 10000); // Refresh every 10 seconds
        }

        async function loadWallboard() {
            const queueNumber = document.getElementById('wallboard-queue-select').value;
            const content = document.getElementById('wallboard-content');

            if (!queueNumber) {
                content.style.display = 'none';
                return;
            }

            content.style.display = 'block';

            try {
                const response = await fetch(`/api/call-queues.php?path=live-status&queue=${queueNumber}`);
                const result = await response.json();

                if (result.success && result.data) {
                    displayWallboard(result.data);
                } else {
                    showAlert('Error loading wallboard data', 'error');
                }
            } catch (error) {
                console.error('Wallboard error:', error);
            }
        }

        function displayWallboard(data) {
            // Update statistics
            document.getElementById('wb-waiting').textContent = data.calls_waiting || 0;
            document.getElementById('wb-available').textContent = data.agents_available || 0;
            document.getElementById('wb-busy').textContent = data.agents_busy || 0;
            document.getElementById('wb-paused').textContent = data.agents_paused || 0;
            document.getElementById('wb-avgwait').textContent = (data.avg_wait_time || 0) + 's';
            document.getElementById('wb-longest').textContent = (data.longest_wait || 0) + 's';

            // Display agents
            const agentsContainer = document.getElementById('wallboard-agents');
            if (data.members && data.members.length > 0) {
                let html = '';
                data.members.forEach(member => {
                    let statusClass = 'unavailable';
                    let statusText = 'Unavailable';

                    if (member.paused) {
                        statusClass = 'paused';
                        statusText = member.paused_reason || 'Paused';
                    } else if (member.in_call) {
                        statusClass = 'unavailable';
                        statusText = 'In Call';
                    } else {
                        statusClass = '';
                        statusText = 'Available';
                    }

                    // Add SIP registration info
                    let sipBadge = '';
                    let clientInfo = '';

                    if (member.sip_registered !== undefined) {
                        if (member.sip_registered) {
                            sipBadge = '<span style="color: #22c55e; font-size: 0.75rem;">🟢 Registered</span>';
                        } else {
                            sipBadge = '<span style="color: #ef4444; font-size: 0.75rem;">🔴 Offline</span>';
                        }
                    }

                    if (member.client && member.client !== 'Unknown') {
                        let osIcon = '';
                        switch(member.os) {
                            case 'iOS': osIcon = '📱'; break;
                            case 'Android': osIcon = '🤖'; break;
                            case 'Windows': osIcon = '🪟'; break;
                            case 'macOS': osIcon = '🍎'; break;
                            case 'Linux': osIcon = '🐧'; break;
                            default: osIcon = '💻';
                        }
                        clientInfo = `<div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">${member.client} ${osIcon} ${member.os || ''}</div>`;
                    }

                    html += `
                        <div class="agent-card ${statusClass}">
                            <div class="agent-name">${member.name || 'Ext ' + member.extension}</div>
                            <div class="agent-status">${statusText}</div>
                            ${sipBadge}
                            ${clientInfo}
                        </div>
                    `;
                });
                agentsContainer.innerHTML = html;
            } else {
                agentsContainer.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; color: #666;">No agents available</div>';
            }

            // Display waiting calls
            const callsContainer = document.getElementById('wallboard-calls-list');
            if (data.waiting_calls && data.waiting_calls.length > 0) {
                let html = '<table style="width: 100%; color: white;"><thead><tr>';
                html += '<th>Caller ID</th><th>Wait Time</th><th>Position</th>';
                html += '</tr></thead><tbody>';

                data.waiting_calls.forEach((call, index) => {
                    html += `<tr>
                        <td>${call.callerid || 'Unknown'}</td>
                        <td>${call.wait_time || 0}s</td>
                        <td>${index + 1}</td>
                    </tr>`;
                });

                html += '</tbody></table>';
                callsContainer.innerHTML = html;
            } else {
                callsContainer.innerHTML = '<div style="color: #999;">No calls waiting</div>';
            }
        }

        function refreshWallboard() {
            loadWallboard();
        }

        // Statistics Functions
        async function loadStatisticsQueues() {
            const select = document.getElementById('stats-queue-select');
            select.innerHTML = '<option value="">-- Select a Queue --</option>';

            try {
                const response = await fetch('/api/call-queues.php?path=list');
                const result = await response.json();

                if (result.success) {
                    result.data.forEach(queue => {
                        const option = document.createElement('option');
                        option.value = queue.id;
                        option.textContent = `${queue.queue_number} - ${queue.queue_name}`;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                showAlert('Error loading queues: ' + error.message, 'error');
            }
        }

        async function loadStatistics() {
            const queueId = document.getElementById('stats-queue-select').value;
            const content = document.getElementById('statistics-content');

            if (!queueId) {
                content.style.display = 'none';
                return;
            }

            content.style.display = 'block';

            try {
                const response = await fetch(`/api/call-queues.php?path=statistics&queue_id=${queueId}`);
                const result = await response.json();

                if (result.success) {
                    displayStatistics(result.data);
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('Error loading statistics: ' + error.message, 'error');
            }
        }

        function displayStatistics(stats) {
            // Update summary cards
            let totalOffered = 0;
            let totalAnswered = 0;
            let totalAbandoned = 0;
            let avgSLA = 0;

            if (stats.length > 0) {
                stats.forEach(stat => {
                    totalOffered += parseInt(stat.calls_offered) || 0;
                    totalAnswered += parseInt(stat.calls_answered) || 0;
                    totalAbandoned += parseInt(stat.calls_abandoned) || 0;
                    avgSLA += parseFloat(stat.service_level_percentage) || 0;
                });
                avgSLA = (avgSLA / stats.length).toFixed(1);
            }

            document.getElementById('stat-offered').textContent = totalOffered;
            document.getElementById('stat-answered').textContent = totalAnswered;
            document.getElementById('stat-abandoned').textContent = totalAbandoned;
            document.getElementById('stat-sla').textContent = avgSLA + '%';

            // Display daily statistics table
            const tableContainer = document.getElementById('statistics-table');
            if (stats.length === 0) {
                tableContainer.innerHTML = `
                    <h3 style="margin: 30px 0 15px 0;">Daily Statistics</h3>
                    <div class="alert alert-info">No statistics data available yet. Statistics are generated from call activity.</div>
                `;
                return;
            }

            let html = '<h3 style="margin: 30px 0 15px 0;">Daily Statistics</h3>';
            html += '<table><thead><tr>';
            html += '<th>Date</th><th>Offered</th><th>Answered</th><th>Abandoned</th>';
            html += '<th>Avg Wait</th><th>Max Wait</th><th>SLA %</th>';
            html += '</tr></thead><tbody>';

            stats.forEach(stat => {
                html += `<tr>
                    <td>${stat.date}</td>
                    <td>${stat.calls_offered}</td>
                    <td>${stat.calls_answered}</td>
                    <td>${stat.calls_abandoned}</td>
                    <td>${stat.avg_wait_time}s</td>
                    <td>${stat.max_wait_time}s</td>
                    <td>${stat.service_level_percentage}%</td>
                </tr>`;
            });

            html += '</tbody></table>';
            tableContainer.innerHTML = html;
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
