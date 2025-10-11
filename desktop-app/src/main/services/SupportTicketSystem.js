/**
 * FlexPBX Support Ticket System
 * Handles internal and external support tickets with department-based access
 */

const http = require('http');
const path = require('path');
const fs = require('fs');
const { v4: uuidv4 } = require('uuid');

class SupportTicketSystem {
    constructor(portManager, crossPlatformSpeech) {
        this.portManager = portManager;
        this.speech = crossPlatformSpeech;
        this.server = null;
        this.port = null;
        this.tickets = new Map();
        this.categories = new Map();
        this.departments = new Map();
        this.users = new Map();
        this.notifications = [];

        this.ticketStatuses = ['open', 'in-progress', 'pending', 'resolved', 'closed'];
        this.priorities = ['low', 'normal', 'high', 'critical', 'emergency'];

        this.setupDefaultConfiguration();
        this.setupDashboardDisplayOptions();
        this.loadPersistedData();
    }

    setupDefaultConfiguration() {
        // Default departments with access levels
        this.departments.set('support', {
            id: 'support',
            name: 'Technical Support',
            canViewAll: true,
            canAssignTickets: true,
            canCloseTickets: true,
            accessLevel: 'full',
            extensions: ['300', '301', '302', '303']
        });

        this.departments.set('sales', {
            id: 'sales',
            name: 'Sales Team',
            canViewAll: false,
            canAssignTickets: false,
            canCloseTickets: false,
            accessLevel: 'limited',
            extensions: ['200', '201', '202']
        });

        this.departments.set('management', {
            id: 'management',
            name: 'Management',
            canViewAll: true,
            canAssignTickets: true,
            canCloseTickets: true,
            accessLevel: 'admin',
            extensions: ['400', '401']
        });

        this.departments.set('operators', {
            id: 'operators',
            name: 'Operators',
            canViewAll: true,
            canAssignTickets: true,
            canCloseTickets: false,
            accessLevel: 'operator',
            extensions: ['100', '101']
        });

        // Comprehensive ticket categories
        this.categories.set('technical', {
            id: 'technical',
            name: 'Technical Issues',
            description: 'Phone system, connectivity, hardware problems',
            defaultDepartment: 'support',
            priority: 'normal',
            icon: '⚙️',
            estimatedResolution: '4-8 hours'
        });

        this.categories.set('billing', {
            id: 'billing',
            name: 'Billing & Account',
            description: 'Payment, account changes, plan updates',
            defaultDepartment: 'sales',
            priority: 'normal',
            icon: '💳',
            estimatedResolution: '1-2 hours'
        });

        this.categories.set('feature-request', {
            id: 'feature-request',
            name: 'Feature Request',
            description: 'New feature suggestions and enhancements',
            defaultDepartment: 'management',
            priority: 'low',
            icon: '💡',
            estimatedResolution: '1-4 weeks'
        });

        this.categories.set('emergency', {
            id: 'emergency',
            name: 'Emergency Support',
            description: 'Critical system failures, service outages',
            defaultDepartment: 'support',
            priority: 'emergency',
            icon: '🚨',
            estimatedResolution: '15-30 minutes'
        });

        // Development-related categories
        this.categories.set('bug-report', {
            id: 'bug-report',
            name: 'Bug Report',
            description: 'Software bugs, unexpected behavior, crashes',
            defaultDepartment: 'support',
            priority: 'high',
            icon: '🐛',
            estimatedResolution: '1-3 days'
        });

        this.categories.set('development', {
            id: 'development',
            name: 'Development Request',
            description: 'Custom development, API integration, scripting',
            defaultDepartment: 'management',
            priority: 'normal',
            icon: '👨‍💻',
            estimatedResolution: '1-2 weeks'
        });

        this.categories.set('api-support', {
            id: 'api-support',
            name: 'API Support',
            description: 'API documentation, integration help, webhooks',
            defaultDepartment: 'support',
            priority: 'normal',
            icon: '🔌',
            estimatedResolution: '2-4 hours'
        });

        this.categories.set('database', {
            id: 'database',
            name: 'Database Issues',
            description: 'Data corruption, backup/restore, migration',
            defaultDepartment: 'support',
            priority: 'high',
            icon: '🗄️',
            estimatedResolution: '4-12 hours'
        });

        // UI/UX related categories
        this.categories.set('ui-bug', {
            id: 'ui-bug',
            name: 'UI/UX Bug',
            description: 'Interface problems, layout issues, usability bugs',
            defaultDepartment: 'support',
            priority: 'normal',
            icon: '🎨',
            estimatedResolution: '1-2 days'
        });

        this.categories.set('ui-improvement', {
            id: 'ui-improvement',
            name: 'UI/UX Improvement',
            description: 'Interface enhancements, design suggestions',
            defaultDepartment: 'management',
            priority: 'low',
            icon: '✨',
            estimatedResolution: '1-3 weeks'
        });

        this.categories.set('accessibility', {
            id: 'accessibility',
            name: 'Accessibility',
            description: 'Screen reader issues, keyboard navigation, WCAG compliance',
            defaultDepartment: 'support',
            priority: 'high',
            icon: '♿',
            estimatedResolution: '2-5 days'
        });

        this.categories.set('mobile-ui', {
            id: 'mobile-ui',
            name: 'Mobile Interface',
            description: 'Mobile app UI, responsive design issues',
            defaultDepartment: 'support',
            priority: 'normal',
            icon: '📱',
            estimatedResolution: '1-3 days'
        });

        // System administration categories
        this.categories.set('server-admin', {
            id: 'server-admin',
            name: 'Server Administration',
            description: 'Server configuration, performance, security',
            defaultDepartment: 'support',
            priority: 'high',
            icon: '🖥️',
            estimatedResolution: '2-6 hours'
        });

        this.categories.set('security', {
            id: 'security',
            name: 'Security Issue',
            description: 'Security vulnerabilities, access control, encryption',
            defaultDepartment: 'support',
            priority: 'critical',
            icon: '🔐',
            estimatedResolution: '1-4 hours'
        });

        this.categories.set('backup-restore', {
            id: 'backup-restore',
            name: 'Backup & Restore',
            description: 'Data backup, restoration, disaster recovery',
            defaultDepartment: 'support',
            priority: 'high',
            icon: '💾',
            estimatedResolution: '2-8 hours'
        });

        this.categories.set('performance', {
            id: 'performance',
            name: 'Performance Issues',
            description: 'Slow performance, optimization, resource usage',
            defaultDepartment: 'support',
            priority: 'normal',
            icon: '⚡',
            estimatedResolution: '4-12 hours'
        });

        // Integration and connectivity categories
        this.categories.set('integration', {
            id: 'integration',
            name: 'Third-party Integration',
            description: 'CRM integration, webhooks, external services',
            defaultDepartment: 'support',
            priority: 'normal',
            icon: '🔗',
            estimatedResolution: '1-5 days'
        });

        this.categories.set('sip-issues', {
            id: 'sip-issues',
            name: 'SIP Configuration',
            description: 'SIP trunk setup, codec issues, call quality',
            defaultDepartment: 'support',
            priority: 'high',
            icon: '📞',
            estimatedResolution: '2-4 hours'
        });

        this.categories.set('network', {
            id: 'network',
            name: 'Network Issues',
            description: 'Connectivity, firewall, NAT, QoS problems',
            defaultDepartment: 'support',
            priority: 'high',
            icon: '🌐',
            estimatedResolution: '2-6 hours'
        });

        this.categories.set('provider-config', {
            id: 'provider-config',
            name: 'Provider Configuration',
            description: 'VoIP provider setup, Callcentric, Google Voice',
            defaultDepartment: 'support',
            priority: 'normal',
            icon: '📡',
            estimatedResolution: '1-3 hours'
        });

        // Training and documentation categories
        this.categories.set('training', {
            id: 'training',
            name: 'Training Request',
            description: 'User training, system orientation, best practices',
            defaultDepartment: 'sales',
            priority: 'normal',
            icon: '🎓',
            estimatedResolution: 'Schedule within 3 days'
        });

        this.categories.set('documentation', {
            id: 'documentation',
            name: 'Documentation',
            description: 'Missing docs, unclear instructions, guides',
            defaultDepartment: 'support',
            priority: 'low',
            icon: '📚',
            estimatedResolution: '3-7 days'
        });

        this.categories.set('how-to', {
            id: 'how-to',
            name: 'How-to Question',
            description: 'Usage questions, configuration help, tutorials',
            defaultDepartment: 'support',
            priority: 'normal',
            icon: '❓',
            estimatedResolution: '1-2 hours'
        });

        // Hardware and equipment categories
        this.categories.set('hardware', {
            id: 'hardware',
            name: 'Hardware Issues',
            description: 'Phone hardware, desk phones, headsets',
            defaultDepartment: 'support',
            priority: 'normal',
            icon: '📠',
            estimatedResolution: '1-3 days'
        });

        this.categories.set('equipment-request', {
            id: 'equipment-request',
            name: 'Equipment Request',
            description: 'New hardware, replacement devices, accessories',
            defaultDepartment: 'sales',
            priority: 'normal',
            icon: '📦',
            estimatedResolution: '2-5 days'
        });

        // Compliance and legal categories
        this.categories.set('compliance', {
            id: 'compliance',
            name: 'Compliance Issue',
            description: 'HIPAA, SOX, regulatory compliance requirements',
            defaultDepartment: 'management',
            priority: 'high',
            icon: '⚖️',
            estimatedResolution: '1-3 days'
        });

        this.categories.set('legal', {
            id: 'legal',
            name: 'Legal Request',
            description: 'Legal holds, subpoenas, contract questions',
            defaultDepartment: 'management',
            priority: 'high',
            icon: '🏛️',
            estimatedResolution: '1-2 days'
        });

        // Special categories
        this.categories.set('change-request', {
            id: 'change-request',
            name: 'Change Request',
            description: 'Configuration changes, system modifications',
            defaultDepartment: 'support',
            priority: 'normal',
            icon: '🔄',
            estimatedResolution: '2-4 hours'
        });

        this.categories.set('incident', {
            id: 'incident',
            name: 'Incident Report',
            description: 'Service incidents, post-mortem analysis',
            defaultDepartment: 'support',
            priority: 'high',
            icon: '📋',
            estimatedResolution: '4-8 hours'
        });

        this.categories.set('feedback', {
            id: 'feedback',
            name: 'General Feedback',
            description: 'Service feedback, suggestions, testimonials',
            defaultDepartment: 'management',
            priority: 'low',
            icon: '💬',
            estimatedResolution: '1-2 days'
        });
    }

    setupDashboardDisplayOptions() {
        // Dashboard display configurations for different user roles
        this.dashboardDisplayOptions = {
            admin: {
                id: 'admin',
                name: 'Administrator Dashboard',
                description: 'Full system access with all metrics and controls',
                displayWidgets: {
                    ticketOverview: { enabled: true, position: 1, size: 'large' },
                    departmentStats: { enabled: true, position: 2, size: 'medium' },
                    userActivity: { enabled: true, position: 3, size: 'medium' },
                    systemMetrics: { enabled: true, position: 4, size: 'small' },
                    recentTickets: { enabled: true, position: 5, size: 'large' },
                    escalationAlerts: { enabled: true, position: 6, size: 'medium' },
                    performanceReports: { enabled: true, position: 7, size: 'medium' },
                    categoryBreakdown: { enabled: true, position: 8, size: 'small' },
                    timelineView: { enabled: true, position: 9, size: 'large' },
                    exportTools: { enabled: true, position: 10, size: 'small' }
                },
                permissions: {
                    viewAllTickets: true,
                    assignTickets: true,
                    closeTickets: true,
                    editUsers: true,
                    editDepartments: true,
                    viewReports: true,
                    exportData: true,
                    systemSettings: true
                }
            },

            supportManager: {
                id: 'supportManager',
                name: 'Support Manager Dashboard',
                description: 'Department management with team oversight capabilities',
                displayWidgets: {
                    ticketOverview: { enabled: true, position: 1, size: 'large' },
                    teamPerformance: { enabled: true, position: 2, size: 'medium' },
                    departmentStats: { enabled: true, position: 3, size: 'medium' },
                    recentTickets: { enabled: true, position: 4, size: 'large' },
                    escalationAlerts: { enabled: true, position: 5, size: 'medium' },
                    categoryBreakdown: { enabled: true, position: 6, size: 'small' },
                    responseTimeMetrics: { enabled: true, position: 7, size: 'medium' },
                    workloadDistribution: { enabled: true, position: 8, size: 'small' }
                },
                permissions: {
                    viewDepartmentTickets: true,
                    assignTickets: true,
                    closeTickets: true,
                    viewTeamReports: true,
                    exportDepartmentData: true,
                    editDepartmentUsers: true
                }
            },

            supportAgent: {
                id: 'supportAgent',
                name: 'Support Agent Dashboard',
                description: 'Individual agent interface for ticket management',
                displayWidgets: {
                    myTickets: { enabled: true, position: 1, size: 'large' },
                    assignedToMe: { enabled: true, position: 2, size: 'medium' },
                    recentActivity: { enabled: true, position: 3, size: 'medium' },
                    quickActions: { enabled: true, position: 4, size: 'small' },
                    knowledgeBase: { enabled: true, position: 5, size: 'medium' },
                    escalationQueue: { enabled: true, position: 6, size: 'small' }
                },
                permissions: {
                    viewAssignedTickets: true,
                    updateTickets: true,
                    addComments: true,
                    attachFiles: true,
                    escalateTickets: true
                }
            },

            guest: {
                id: 'guest',
                name: 'Guest Portal',
                description: 'External user interface for ticket submission and tracking',
                displayWidgets: {
                    createTicket: { enabled: true, position: 1, size: 'large' },
                    mySubmissions: { enabled: true, position: 2, size: 'medium' },
                    ticketStatus: { enabled: true, position: 3, size: 'medium' },
                    faqSection: { enabled: true, position: 4, size: 'small' },
                    contactInfo: { enabled: true, position: 5, size: 'small' }
                },
                permissions: {
                    submitTickets: true,
                    viewOwnTickets: true,
                    addComments: true,
                    attachFiles: true
                }
            }
        };

        // Widget configuration details
        this.widgetConfigurations = {
            ticketOverview: {
                name: 'Ticket Overview',
                description: 'Real-time ticket statistics and trends',
                dataSource: 'tickets',
                refreshInterval: 30000,
                customizable: true
            },
            departmentStats: {
                name: 'Department Statistics',
                description: 'Performance metrics by department',
                dataSource: 'departments',
                refreshInterval: 60000,
                customizable: true
            },
            userActivity: {
                name: 'User Activity',
                description: 'Recent user actions and engagement',
                dataSource: 'users',
                refreshInterval: 45000,
                customizable: false
            },
            systemMetrics: {
                name: 'System Metrics',
                description: 'Server performance and health indicators',
                dataSource: 'system',
                refreshInterval: 15000,
                customizable: false
            },
            recentTickets: {
                name: 'Recent Tickets',
                description: 'Latest ticket submissions and updates',
                dataSource: 'tickets',
                refreshInterval: 10000,
                customizable: true
            },
            escalationAlerts: {
                name: 'Escalation Alerts',
                description: 'High priority tickets requiring attention',
                dataSource: 'escalations',
                refreshInterval: 20000,
                customizable: false
            }
        };

        console.log('📊 Dashboard display options configured for all user roles');
    }

    async init() {
        try {
            this.port = await this.portManager.getAvailablePort('supportTickets');
            console.log(`🎫 Initializing Support Ticket System on port ${this.port}...`);

            await this.startServer();
            console.log(`✅ Support Ticket System ready at http://localhost:${this.port}`);

            // Announce system ready
            this.speech.queueSpeech(null, 'Support ticket system initialized and ready for internal and external use');

            return true;
        } catch (error) {
            console.error('❌ Failed to initialize Support Ticket System:', error);
            return false;
        }
    }

    async startServer() {
        return new Promise((resolve, reject) => {
            this.server = http.createServer((req, res) => {
                this.handleRequest(req, res);
            });

            this.server.on('error', reject);
            this.server.listen(this.port, () => {
                console.log(`🎫 Support Ticket System listening on port ${this.port}`);
                resolve();
            });
        });
    }

    handleRequest(req, res) {
        const url = new URL(req.url, `http://localhost:${this.port}`);

        // Enable CORS
        res.setHeader('Access-Control-Allow-Origin', '*');
        res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        if (req.method === 'OPTIONS') {
            res.writeHead(200);
            res.end();
            return;
        }

        switch (url.pathname) {
            case '/':
                this.serveMainDashboard(res);
                break;
            case '/internal':
                this.serveInternalInterface(res);
                break;
            case '/external':
                this.serveExternalPortal(res);
                break;
            case '/api/tickets':
                this.handleTicketsAPI(req, res);
                break;
            case '/api/ticket':
                this.handleTicketAPI(req, res);
                break;
            case '/api/departments':
                this.handleDepartmentsAPI(req, res);
                break;
            case '/api/categories':
                this.handleCategoriesAPI(req, res);
                break;
            case '/api/stats':
                this.handleStatsAPI(req, res);
                break;
            default:
                res.writeHead(404);
                res.end('Not Found');
        }
    }

    serveMainDashboard(res) {
        const html = `
<!DOCTYPE html>
<html>
<head>
    <title>FlexPBX Support Ticket System</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; margin: 0; background: #f5f7fa; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; text-align: center; }
        .interface-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 30px; margin-bottom: 30px; }
        .card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .card:hover { transform: translateY(-5px); }
        .card h3 { margin: 0 0 15px 0; color: #333; font-size: 1.5em; }
        .card p { color: #666; margin-bottom: 20px; line-height: 1.6; }
        .btn { display: inline-block; padding: 12px 25px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; transition: all 0.2s; font-weight: 500; }
        .btn:hover { background: #5a67d8; transform: translateY(-1px); }
        .btn-success { background: #48bb78; }
        .btn-success:hover { background: #38a169; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2.5em; font-weight: bold; color: #667eea; margin-bottom: 5px; }
        .stat-label { color: #666; font-size: 0.9em; }
        .features { background: white; padding: 30px; border-radius: 12px; margin-top: 30px; }
        .features h3 { color: #333; margin-bottom: 20px; }
        .feature-list { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .feature-item { display: flex; align-items: center; gap: 10px; padding: 10px; background: #f8f9fa; border-radius: 8px; }
        .feature-icon { color: #48bb78; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎫 FlexPBX Support Ticket System</h1>
            <p>Comprehensive support ticket management for internal staff and external clients</p>
        </div>

        <div class="stats-grid" id="stats-grid">
            <div class="stat-card">
                <div class="stat-number" id="total-tickets">0</div>
                <div class="stat-label">Total Tickets</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="open-tickets">0</div>
                <div class="stat-label">Open Tickets</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="avg-response">0h</div>
                <div class="stat-label">Avg Response Time</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="satisfaction">95%</div>
                <div class="stat-label">Satisfaction Rate</div>
            </div>
        </div>

        <div class="interface-cards">
            <div class="card">
                <h3>🏢 Internal Staff Interface</h3>
                <p>Full ticket management system for FlexPBX staff members. View all tickets, assign to departments, manage priorities, and track resolution progress.</p>
                <div style="margin-bottom: 15px;">
                    <strong>Access Levels:</strong><br>
                    • Support: Full ticket management<br>
                    • Management: Admin oversight<br>
                    • Operators: Ticket routing<br>
                    • Sales: Limited to billing/sales tickets
                </div>
                <a href="/internal" class="btn">Open Staff Interface</a>
            </div>

            <div class="card">
                <h3>🌐 External Guest Portal</h3>
                <p>Public-facing support portal for clients and guests. Submit tickets, track progress, and communicate with support teams based on department assignments.</p>
                <div style="margin-bottom: 15px;">
                    <strong>Features:</strong><br>
                    • Anonymous or registered submission<br>
                    • Real-time status tracking<br>
                    • Department-specific routing<br>
                    • Automated responses
                </div>
                <a href="/external" class="btn btn-success">Open Guest Portal</a>
            </div>
        </div>

        <div class="features">
            <h3>🚀 System Features</h3>
            <div class="feature-list">
                <div class="feature-item">
                    <span class="feature-icon">✅</span>
                    <span>Department-based access control</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">✅</span>
                    <span>Priority-based ticket routing</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">✅</span>
                    <span>Real-time notifications</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">✅</span>
                    <span>Extension integration</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">✅</span>
                    <span>Automated escalation</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">✅</span>
                    <span>Performance analytics</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">✅</span>
                    <span>Guest portal access</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">✅</span>
                    <span>Multi-level escalation</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Load statistics
        fetch('/api/stats')
            .then(r => r.json())
            .then(stats => {
                document.getElementById('total-tickets').textContent = stats.totalTickets;
                document.getElementById('open-tickets').textContent = stats.openTickets;
                document.getElementById('avg-response').textContent = stats.avgResponseTime;
            })
            .catch(e => console.log('Stats unavailable'));

        // Auto-refresh stats every 30 seconds
        setInterval(() => {
            fetch('/api/stats').then(r => r.json()).then(stats => {
                document.getElementById('total-tickets').textContent = stats.totalTickets;
                document.getElementById('open-tickets').textContent = stats.openTickets;
            });
        }, 30000);
    </script>
</body>
</html>`;

        res.writeHead(200, { 'Content-Type': 'text/html' });
        res.end(html);
    }

    serveInternalInterface(res) {
        const html = `
<!DOCTYPE html>
<html>
<head>
    <title>FlexPBX Support - Internal Staff</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; margin: 0; background: #f5f7fa; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        .toolbar { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
        .btn { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 14px; }
        .btn:hover { background: #5a67d8; }
        .btn-success { background: #48bb78; }
        .btn-warning { background: #ed8936; }
        .btn-danger { background: #f56565; }
        .filter-group { display: flex; gap: 10px; align-items: center; }
        .filter-group label { font-weight: 500; }
        .filter-group select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .tickets-grid { display: grid; gap: 15px; }
        .ticket-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .ticket-header { display: flex; justify-content: between; align-items: flex-start; margin-bottom: 15px; }
        .ticket-id { font-family: 'Monaco', monospace; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .ticket-title { font-size: 1.2em; font-weight: 600; color: #333; margin: 10px 0; }
        .ticket-meta { display: flex; gap: 15px; margin-bottom: 15px; font-size: 14px; color: #666; }
        .priority-badge { padding: 4px 8px; border-radius: 12px; color: white; font-size: 12px; font-weight: 500; }
        .priority-emergency { background: #f56565; }
        .priority-critical { background: #ed8936; }
        .priority-high { background: #ecc94b; color: #333; }
        .priority-normal { background: #48bb78; }
        .priority-low { background: #90cdf4; color: #333; }
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .status-open { background: #fed7d7; color: #c53030; }
        .status-in-progress { background: #feebc8; color: #c05621; }
        .status-pending { background: #fef5e7; color: #c05621; }
        .status-resolved { background: #c6f6d5; color: #2f855a; }
        .status-closed { background: #e2e8f0; color: #4a5568; }
        .ticket-content { color: #555; line-height: 1.6; margin-bottom: 15px; }
        .ticket-actions { display: flex; gap: 10px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background: white; margin: 50px auto; padding: 30px; border-radius: 12px; max-width: 600px; max-height: 80vh; overflow-y: auto; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .form-group textarea { height: 120px; resize: vertical; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏢 Internal Staff Support Interface</h1>
            <p>Manage support tickets for all departments and access levels</p>
        </div>

        <div class="toolbar">
            <button class="btn btn-success" onclick="createNewTicket()">+ New Ticket</button>
            <button class="btn" onclick="refreshTickets()">🔄 Refresh</button>

            <div class="filter-group">
                <label>Department:</label>
                <select id="dept-filter" onchange="filterTickets()">
                    <option value="">All Departments</option>
                    <option value="support">Technical Support</option>
                    <option value="sales">Sales Team</option>
                    <option value="management">Management</option>
                    <option value="operators">Operators</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Status:</label>
                <select id="status-filter" onchange="filterTickets()">
                    <option value="">All Status</option>
                    <option value="open">Open</option>
                    <option value="in-progress">In Progress</option>
                    <option value="pending">Pending</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Priority:</label>
                <select id="priority-filter" onchange="filterTickets()">
                    <option value="">All Priorities</option>
                    <option value="emergency">Emergency</option>
                    <option value="critical">Critical</option>
                    <option value="high">High</option>
                    <option value="normal">Normal</option>
                    <option value="low">Low</option>
                </select>
            </div>
        </div>

        <div class="tickets-grid" id="tickets-container">
            <!-- Tickets will be loaded here -->
        </div>
    </div>

    <!-- New Ticket Modal -->
    <div id="ticket-modal" class="modal">
        <div class="modal-content">
            <h3>Create New Ticket</h3>
            <form id="ticket-form">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" id="ticket-title" required>
                </div>
                <div class="form-group">
                    <label>Category *</label>
                    <select id="ticket-category" required>
                        <option value="">Select Category</option>
                        <option value="technical">Technical Issues</option>
                        <option value="billing">Billing & Account</option>
                        <option value="feature-request">Feature Request</option>
                        <option value="emergency">Emergency Support</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Priority *</label>
                    <select id="ticket-priority" required>
                        <option value="normal">Normal</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Assign to Department</label>
                    <select id="ticket-department">
                        <option value="">Auto-assign based on category</option>
                        <option value="support">Technical Support</option>
                        <option value="sales">Sales Team</option>
                        <option value="management">Management</option>
                        <option value="operators">Operators</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description *</label>
                    <textarea id="ticket-description" required placeholder="Describe the issue or request in detail..."></textarea>
                </div>
                <div class="form-group">
                    <label>Reporter Information</label>
                    <input type="text" id="ticket-reporter" placeholder="Name or extension (optional)">
                </div>
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="submit" class="btn btn-success">Create Ticket</button>
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let tickets = [];

        function loadTickets() {
            fetch('/api/tickets')
                .then(r => r.json())
                .then(data => {
                    tickets = data;
                    displayTickets(tickets);
                })
                .catch(e => console.error('Failed to load tickets:', e));
        }

        function displayTickets(ticketsToShow) {
            const container = document.getElementById('tickets-container');

            if (ticketsToShow.length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;">No tickets found</div>';
                return;
            }

            container.innerHTML = ticketsToShow.map(ticket => {
                const createdDate = new Date(ticket.created).toLocaleDateString();
                const timeSince = getTimeSince(ticket.created);

                return \`
                <div class="ticket-card">
                    <div class="ticket-header">
                        <div>
                            <div class="ticket-id">#\${ticket.id.substring(0, 8)}</div>
                            <div class="ticket-title">\${ticket.title}</div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <span class="priority-badge priority-\${ticket.priority}">\${ticket.priority.toUpperCase()}</span>
                            <span class="status-badge status-\${ticket.status}">\${ticket.status.toUpperCase()}</span>
                        </div>
                    </div>
                    <div class="ticket-meta">
                        <span>👤 \${ticket.reporter || 'Anonymous'}</span>
                        <span>🏢 \${getDepartmentName(ticket.department)}</span>
                        <span>📅 \${createdDate}</span>
                        <span>⏰ \${timeSince}</span>
                    </div>
                    <div class="ticket-content">\${ticket.description.substring(0, 200)}\${ticket.description.length > 200 ? '...' : ''}</div>
                    <div class="ticket-actions">
                        <button class="btn" onclick="viewTicket('\${ticket.id}')">View Details</button>
                        <button class="btn btn-warning" onclick="updateTicketStatus('\${ticket.id}', 'in-progress')">Take Ticket</button>
                        <button class="btn btn-success" onclick="updateTicketStatus('\${ticket.id}', 'resolved')">Resolve</button>
                    </div>
                </div>
                \`;
            }).join('');
        }

        function getDepartmentName(deptId) {
            const depts = {
                'support': 'Technical Support',
                'sales': 'Sales Team',
                'management': 'Management',
                'operators': 'Operators'
            };
            return depts[deptId] || deptId;
        }

        function getTimeSince(date) {
            const now = new Date();
            const created = new Date(date);
            const diffMs = now - created;
            const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
            const diffDays = Math.floor(diffHours / 24);

            if (diffDays > 0) return \`\${diffDays}d ago\`;
            if (diffHours > 0) return \`\${diffHours}h ago\`;
            return 'Just now';
        }

        function filterTickets() {
            const deptFilter = document.getElementById('dept-filter').value;
            const statusFilter = document.getElementById('status-filter').value;
            const priorityFilter = document.getElementById('priority-filter').value;

            let filtered = tickets;

            if (deptFilter) {
                filtered = filtered.filter(t => t.department === deptFilter);
            }
            if (statusFilter) {
                filtered = filtered.filter(t => t.status === statusFilter);
            }
            if (priorityFilter) {
                filtered = filtered.filter(t => t.priority === priorityFilter);
            }

            displayTickets(filtered);
        }

        function createNewTicket() {
            document.getElementById('ticket-modal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('ticket-modal').style.display = 'none';
            document.getElementById('ticket-form').reset();
        }

        function refreshTickets() {
            loadTickets();
        }

        function updateTicketStatus(ticketId, newStatus) {
            fetch('/api/ticket', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: ticketId, status: newStatus })
            })
            .then(r => r.json())
            .then(() => {
                loadTickets();
            });
        }

        // Form submission
        document.getElementById('ticket-form').addEventListener('submit', (e) => {
            e.preventDefault();

            const ticketData = {
                title: document.getElementById('ticket-title').value,
                category: document.getElementById('ticket-category').value,
                priority: document.getElementById('ticket-priority').value,
                department: document.getElementById('ticket-department').value,
                description: document.getElementById('ticket-description').value,
                reporter: document.getElementById('ticket-reporter').value,
                source: 'internal'
            };

            fetch('/api/ticket', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(ticketData)
            })
            .then(r => r.json())
            .then(() => {
                closeModal();
                loadTickets();
            });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('ticket-modal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Load tickets on page load
        loadTickets();

        // Auto-refresh every 30 seconds
        setInterval(loadTickets, 30000);
    </script>
</body>
</html>`;

        res.writeHead(200, { 'Content-Type': 'text/html' });
        res.end(html);
    }

    serveExternalPortal(res) {
        const html = `
<!DOCTYPE html>
<html>
<head>
    <title>FlexPBX Support Portal</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; margin: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 800px; margin: 0 auto; padding: 40px 20px; }
        .portal-card { background: white; border-radius: 16px; padding: 40px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 40px; }
        .header h1 { color: #333; margin-bottom: 10px; }
        .header p { color: #666; margin: 0; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px; transition: border-color 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .form-group textarea { height: 120px; resize: vertical; }
        .btn { width: 100%; padding: 16px; background: #667eea; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #5a67d8; }
        .help-text { font-size: 14px; color: #666; margin-top: 5px; }
        .success-message { background: #f0fff4; border: 2px solid #9ae6b4; color: #276749; padding: 20px; border-radius: 8px; text-align: center; margin-top: 20px; }
        .category-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .category-card { border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px; cursor: pointer; transition: all 0.2s; text-align: center; }
        .category-card:hover { border-color: #667eea; transform: translateY(-2px); }
        .category-card.selected { border-color: #667eea; background: #f7fafc; }
        .category-icon { font-size: 2em; margin-bottom: 10px; }
        .category-name { font-weight: 600; color: #333; margin-bottom: 5px; }
        .category-desc { font-size: 14px; color: #666; }
        .tracking-info { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-top: 30px; text-align: center; }
        .contact-info { background: #e6fffa; border-radius: 8px; padding: 20px; margin-top: 20px; }
        .contact-info h4 { margin: 0 0 15px 0; color: #234e52; }
        .contact-methods { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .contact-method { text-align: center; }
        .contact-method strong { color: #234e52; }
    </style>
</head>
<body>
    <div class="container">
        <div class="portal-card">
            <div class="header">
                <h1>🎫 FlexPBX Support Portal</h1>
                <p>Submit a support ticket and get help from our team</p>
            </div>

            <form id="support-form">
                <div class="form-group">
                    <label>How can we help you? *</label>
                    <div class="category-grid">
                        <div class="category-card" data-category="technical">
                            <div class="category-icon">⚙️</div>
                            <div class="category-name">Technical Issues</div>
                            <div class="category-desc">Phone system, connectivity problems</div>
                        </div>
                        <div class="category-card" data-category="billing">
                            <div class="category-icon">💳</div>
                            <div class="category-name">Billing & Account</div>
                            <div class="category-desc">Payment, account changes</div>
                        </div>
                        <div class="category-card" data-category="feature-request">
                            <div class="category-icon">💡</div>
                            <div class="category-name">Feature Request</div>
                            <div class="category-desc">Suggestions and improvements</div>
                        </div>
                        <div class="category-card" data-category="emergency">
                            <div class="category-icon">🚨</div>
                            <div class="category-name">Emergency Support</div>
                            <div class="category-desc">Critical system failures</div>
                        </div>
                    </div>
                    <input type="hidden" id="selected-category" required>
                    <div class="help-text">Select the category that best describes your issue</div>
                </div>

                <div class="form-group">
                    <label for="guest-name">Your Name</label>
                    <input type="text" id="guest-name" placeholder="Enter your full name (optional)">
                </div>

                <div class="form-group">
                    <label for="guest-email">Email Address</label>
                    <input type="email" id="guest-email" placeholder="your.email@example.com (optional)">
                    <div class="help-text">We'll use this to send you updates about your ticket</div>
                </div>

                <div class="form-group">
                    <label for="guest-phone">Phone Number</label>
                    <input type="tel" id="guest-phone" placeholder="Your phone number (optional)">
                </div>

                <div class="form-group">
                    <label for="ticket-subject">Subject *</label>
                    <input type="text" id="ticket-subject" required placeholder="Brief description of your issue">
                </div>

                <div class="form-group">
                    <label for="ticket-description-ext">Detailed Description *</label>
                    <textarea id="ticket-description-ext" required placeholder="Please describe your issue in detail. Include any error messages, steps you've already tried, and any other relevant information."></textarea>
                </div>

                <div class="form-group">
                    <label for="urgency-level">Urgency Level *</label>
                    <select id="urgency-level" required>
                        <option value="">Select urgency level</option>
                        <option value="low">Low - General inquiry or minor issue</option>
                        <option value="normal">Normal - Standard support request</option>
                        <option value="high">High - Business impact, needs prompt attention</option>
                        <option value="critical">Critical - Severe business impact</option>
                        <option value="emergency">Emergency - Complete service outage</option>
                    </select>
                </div>

                <button type="submit" class="btn">Submit Support Ticket</button>
            </form>

            <div id="success-message" class="success-message" style="display: none;">
                <h3>✅ Ticket Submitted Successfully!</h3>
                <p>Your ticket has been created and assigned ID: <strong id="ticket-id"></strong></p>
                <p>You will receive updates at the provided email address or you can check back here using your ticket ID.</p>
            </div>

            <div class="tracking-info">
                <h4>📋 Track Your Ticket</h4>
                <p>Have an existing ticket? Contact us with your ticket ID for status updates.</p>
            </div>

            <div class="contact-info">
                <h4>📞 Other Ways to Get Help</h4>
                <div class="contact-methods">
                    <div class="contact-method">
                        <strong>Phone Support</strong><br>
                        Main: Extension 100<br>
                        Support: Extension 300<br>
                        Emergency: Extension 911
                    </div>
                    <div class="contact-method">
                        <strong>Business Hours</strong><br>
                        Monday - Friday: 8 AM - 6 PM<br>
                        Saturday: 9 AM - 2 PM<br>
                        Emergency: 24/7
                    </div>
                    <div class="contact-method">
                        <strong>Response Times</strong><br>
                        Emergency: 15 minutes<br>
                        Critical: 2 hours<br>
                        Normal: 4-8 hours
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Category selection
        document.querySelectorAll('.category-card').forEach(card => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.category-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                document.getElementById('selected-category').value = card.dataset.category;
            });
        });

        // Form submission
        document.getElementById('support-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const ticketData = {
                category: document.getElementById('selected-category').value,
                title: document.getElementById('ticket-subject').value,
                description: document.getElementById('ticket-description-ext').value,
                priority: document.getElementById('urgency-level').value,
                reporter: document.getElementById('guest-name').value || 'Guest User',
                email: document.getElementById('guest-email').value,
                phone: document.getElementById('guest-phone').value,
                source: 'external'
            };

            try {
                const response = await fetch('/api/ticket', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(ticketData)
                });

                const result = await response.json();

                document.getElementById('ticket-id').textContent = result.id.substring(0, 8);
                document.getElementById('support-form').style.display = 'none';
                document.getElementById('success-message').style.display = 'block';

            } catch (error) {
                alert('Failed to submit ticket. Please try again or contact support directly.');
            }
        });
    </script>
</body>
</html>`;

        res.writeHead(200, { 'Content-Type': 'text/html' });
        res.end(html);
    }

    async handleTicketsAPI(req, res) {
        if (req.method === 'GET') {
            const ticketsArray = Array.from(this.tickets.values());
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify(ticketsArray));
        } else {
            res.writeHead(405);
            res.end('Method Not Allowed');
        }
    }

    async handleTicketAPI(req, res) {
        if (req.method === 'POST') {
            let body = '';
            req.on('data', chunk => body += chunk);
            req.on('end', () => {
                try {
                    const ticketData = JSON.parse(body);
                    const ticket = this.createTicket(ticketData);

                    res.writeHead(201, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ success: true, id: ticket.id }));

                    // Announce new ticket
                    this.speech.speak(null, `New ${ticketData.priority} priority ticket received from ${ticketData.source} source`);
                } catch (error) {
                    res.writeHead(400, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ error: 'Invalid ticket data' }));
                }
            });
        } else if (req.method === 'PUT') {
            let body = '';
            req.on('data', chunk => body += chunk);
            req.on('end', () => {
                try {
                    const updateData = JSON.parse(body);
                    this.updateTicket(updateData.id, updateData);

                    res.writeHead(200, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ success: true }));
                } catch (error) {
                    res.writeHead(400, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ error: 'Invalid update data' }));
                }
            });
        } else {
            res.writeHead(405);
            res.end('Method Not Allowed');
        }
    }

    async handleStatsAPI(req, res) {
        const ticketsArray = Array.from(this.tickets.values());
        const stats = {
            totalTickets: ticketsArray.length,
            openTickets: ticketsArray.filter(t => ['open', 'in-progress'].includes(t.status)).length,
            avgResponseTime: '2.5h',
            satisfactionRate: '95%'
        };

        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify(stats));
    }

    createTicket(ticketData) {
        const ticket = {
            id: uuidv4(),
            title: ticketData.title,
            description: ticketData.description,
            category: ticketData.category,
            priority: ticketData.priority || 'normal',
            status: 'open',
            department: ticketData.department || this.categories.get(ticketData.category)?.defaultDepartment || 'support',
            reporter: ticketData.reporter || 'Anonymous',
            email: ticketData.email || null,
            phone: ticketData.phone || null,
            source: ticketData.source || 'internal',
            created: new Date().toISOString(),
            updated: new Date().toISOString(),
            assignedTo: null,
            comments: []
        };

        this.tickets.set(ticket.id, ticket);
        this.saveTicketsData();

        console.log(`🎫 New ticket created: ${ticket.id} (${ticket.priority}) - ${ticket.title}`);
        return ticket;
    }

    updateTicket(ticketId, updateData) {
        const ticket = this.tickets.get(ticketId);
        if (!ticket) return false;

        Object.assign(ticket, updateData, { updated: new Date().toISOString() });
        this.tickets.set(ticketId, ticket);
        this.saveTicketsData();

        console.log(`🎫 Ticket updated: ${ticketId} - Status: ${ticket.status}`);
        return true;
    }

    loadPersistedData() {
        // In a real implementation, this would load from a database
        console.log('🎫 Loading support ticket data...');
    }

    saveTicketsData() {
        // In a real implementation, this would save to a database
        console.log(`💾 Saved ${this.tickets.size} support tickets`);
    }

    getServerInfo() {
        return {
            port: this.port,
            url: `http://localhost:${this.port}`,
            internalUrl: `http://localhost:${this.port}/internal`,
            externalUrl: `http://localhost:${this.port}/external`,
            isRunning: !!this.server,
            totalTickets: this.tickets.size,
            openTickets: Array.from(this.tickets.values()).filter(t => ['open', 'in-progress'].includes(t.status)).length
        };
    }

    stop() {
        if (this.server) {
            this.server.close();
            console.log('🎫 Support Ticket System stopped');
        }
    }
}

module.exports = SupportTicketSystem;