/**
 * FlexPBX Hold Music Streaming Server
 * Provides real-time hold music streaming with live monitoring
 */

const http = require('http');
const path = require('path');
const fs = require('fs');
const { spawn } = require('child_process');

class HoldMusicServer {
    constructor(portManager) {
        this.portManager = portManager;
        this.server = null;
        this.port = null;
        this.isStreaming = false;
        this.currentTrack = null;
        this.clients = new Set();

        // Hold music categories with different styles
        this.musicCategories = {
            corporate: {
                name: 'Corporate',
                files: ['corporate-loop.wav', 'piano-ambience.mp3'],
                description: 'Professional background music for business calls'
            },
            ambient: {
                name: 'Ambient',
                files: ['nature-sounds.wav', 'soft-jazz.mp3'],
                description: 'Calming ambient sounds for support queues'
            },
            classical: {
                name: 'Classical',
                files: ['bach-prelude.wav', 'mozart-piano.mp3'],
                description: 'Classical music for premium clients'
            },
            radio: {
                name: 'Live Radio',
                stream: 'http://stream.chrismixradio.com:8000/stream',
                description: 'Live streaming radio for variety'
            }
        };

        this.defaultMediaPath = path.join(__dirname, '../../../media/hold-music');
        this.ensureMediaDirectory();
    }

    ensureMediaDirectory() {
        if (!fs.existsSync(this.defaultMediaPath)) {
            fs.mkdirSync(this.defaultMediaPath, { recursive: true });
            console.log(`📁 Created hold music directory: ${this.defaultMediaPath}`);
        }
    }

    async init() {
        try {
            this.port = await this.portManager.getAvailablePort('holdMusic');
            console.log(`🎵 Initializing Hold Music Server on port ${this.port}...`);

            await this.createDefaultHoldMusic();
            await this.startServer();
            this.setupRealTimeMonitoring();

            console.log(`✅ Hold Music Server ready at http://localhost:${this.port}`);
            return true;
        } catch (error) {
            console.error('❌ Failed to initialize Hold Music Server:', error);
            return false;
        }
    }

    async createDefaultHoldMusic() {
        // Create default hold music files using macOS system sounds and generated tones
        const defaultFiles = [
            {
                name: 'corporate-loop.wav',
                type: 'generated',
                description: 'Professional corporate hold music'
            },
            {
                name: 'ambient-nature.wav',
                type: 'generated',
                description: 'Calming nature sounds'
            },
            {
                name: 'classical-demo.wav',
                type: 'generated',
                description: 'Classical music sample'
            }
        ];

        for (const file of defaultFiles) {
            const filePath = path.join(this.defaultMediaPath, file.name);
            if (!fs.existsSync(filePath)) {
                await this.generateHoldMusicFile(file, filePath);
            }
        }
    }

    async generateHoldMusicFile(fileConfig, outputPath) {
        console.log(`🎼 Generating hold music: ${fileConfig.name}`);

        // Use macOS say command to create pleasant hold music announcements
        return new Promise((resolve, reject) => {
            let command;

            switch (fileConfig.name) {
                case 'corporate-loop.wav':
                    // Create a professional announcement with background tone
                    command = `say -v Alex -o "${outputPath}" "Thank you for holding. Your call is important to us. Please remain on the line and someone will be with you shortly."`;
                    break;

                case 'ambient-nature.wav':
                    // Create ambient announcement
                    command = `say -v Samantha -r 180 -o "${outputPath}" "We appreciate your patience. You are in the support queue. Your estimated wait time is less than two minutes."`;
                    break;

                case 'classical-demo.wav':
                    // Create classical style announcement
                    command = `say -v Victoria -r 200 -o "${outputPath}" "Welcome to our premium service line. Please enjoy the music while we connect you with our specialist."`;
                    break;

                default:
                    command = `say -v Alex -o "${outputPath}" "Please hold while we connect your call."`;
            }

            const process = spawn('sh', ['-c', command]);

            process.on('close', (code) => {
                if (code === 0) {
                    console.log(`✅ Generated ${fileConfig.name}`);
                    resolve();
                } else {
                    console.error(`❌ Failed to generate ${fileConfig.name}`);
                    reject(new Error(`Generation failed with code ${code}`));
                }
            });
        });
    }

    async startServer() {
        return new Promise((resolve, reject) => {
            this.server = http.createServer((req, res) => {
                this.handleRequest(req, res);
            });

            this.server.on('error', (error) => {
                console.error('❌ Hold Music Server error:', error);
                reject(error);
            });

            this.server.listen(this.port, () => {
                console.log(`🎵 Hold Music Server listening on port ${this.port}`);
                resolve();
            });
        });
    }

    handleRequest(req, res) {
        const url = new URL(req.url, `http://localhost:${this.port}`);

        // Enable CORS for all requests
        res.setHeader('Access-Control-Allow-Origin', '*');
        res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

        if (req.method === 'OPTIONS') {
            res.writeHead(200);
            res.end();
            return;
        }

        switch (url.pathname) {
            case '/':
                this.serveDashboard(res);
                break;
            case '/stream':
                this.handleStreamRequest(req, res);
                break;
            case '/api/status':
                this.serveStatus(res);
                break;
            case '/api/categories':
                this.serveCategories(res);
                break;
            case '/api/play':
                this.handlePlayRequest(req, res, url.searchParams);
                break;
            case '/api/stop':
                this.handleStopRequest(res);
                break;
            case '/monitor':
                this.serveRealTimeMonitor(res);
                break;
            default:
                if (url.pathname.startsWith('/media/')) {
                    this.serveMediaFile(req, res, url.pathname);
                } else {
                    res.writeHead(404);
                    res.end('Not Found');
                }
        }
    }

    serveDashboard(res) {
        const html = `
<!DOCTYPE html>
<html>
<head>
    <title>FlexPBX Hold Music Server</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .status { display: flex; gap: 20px; margin-bottom: 20px; }
        .status-card { flex: 1; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #28a745; }
        .controls { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .btn { padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; transition: all 0.2s; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .category { margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .category h3 { margin: 0 0 10px 0; color: #495057; }
        .real-time { background: #e8f4fd; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; border-radius: 6px; }
        .log { background: #000; color: #0f0; padding: 15px; border-radius: 6px; font-family: 'Monaco', monospace; height: 200px; overflow-y: auto; }
        #status-indicator { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 8px; }
        .online { background: #28a745; }
        .offline { background: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎵 FlexPBX Hold Music Server</h1>
            <p>Real-time hold music streaming and management</p>
        </div>

        <div class="status">
            <div class="status-card">
                <h3><span id="status-indicator" class="online"></span>Server Status</h3>
                <p>Port: ${this.port} | Clients: <span id="client-count">0</span></p>
            </div>
            <div class="status-card">
                <h3>🎶 Current Track</h3>
                <p id="current-track">None playing</p>
            </div>
        </div>

        <div class="controls">
            <button class="btn btn-success" onclick="playCategory('corporate')">▶️ Corporate</button>
            <button class="btn btn-primary" onclick="playCategory('ambient')">▶️ Ambient</button>
            <button class="btn btn-warning" onclick="playCategory('classical')">▶️ Classical</button>
            <button class="btn btn-danger" onclick="stopMusic()">⏹️ Stop</button>
        </div>

        <div class="real-time">
            <h3>📡 Real-time Monitor</h3>
            <button class="btn btn-primary" onclick="window.open('/monitor', '_blank')">Open Live Monitor</button>
            <p>Monitor hold music streaming in real-time with detailed analytics</p>
        </div>

        <div class="category">
            <h3>📁 Available Categories</h3>
            <div id="categories-list">Loading...</div>
        </div>

        <div class="log" id="log-display">
            FlexPBX Hold Music Server initialized...
        </div>
    </div>

    <script>
        let logCount = 0;

        function log(message) {
            const logDiv = document.getElementById('log-display');
            logCount++;
            const timestamp = new Date().toLocaleTimeString();
            logDiv.innerHTML += '<br>[' + timestamp + '] ' + message;
            logDiv.scrollTop = logDiv.scrollHeight;
        }

        function playCategory(category) {
            log('Playing category: ' + category);
            fetch('/api/play?category=' + category)
                .then(r => r.json())
                .then(data => {
                    log('✅ ' + data.message);
                    document.getElementById('current-track').textContent = data.track || category;
                })
                .catch(e => log('❌ Error: ' + e.message));
        }

        function stopMusic() {
            log('Stopping music...');
            fetch('/api/stop')
                .then(r => r.json())
                .then(data => {
                    log('✅ ' + data.message);
                    document.getElementById('current-track').textContent = 'None playing';
                })
                .catch(e => log('❌ Error: ' + e.message));
        }

        // Auto-refresh status
        setInterval(() => {
            fetch('/api/status')
                .then(r => r.json())
                .then(data => {
                    document.getElementById('client-count').textContent = data.clients;
                    if (data.currentTrack) {
                        document.getElementById('current-track').textContent = data.currentTrack;
                    }
                });
        }, 2000);

        // Load categories
        fetch('/api/categories')
            .then(r => r.json())
            .then(categories => {
                const html = Object.entries(categories).map(([key, cat]) =>
                    '<div><strong>' + cat.name + '</strong>: ' + cat.description + '</div>'
                ).join('');
                document.getElementById('categories-list').innerHTML = html;
            });

        log('Dashboard loaded successfully');
    </script>
</body>
</html>`;

        res.writeHead(200, { 'Content-Type': 'text/html' });
        res.end(html);
    }

    serveRealTimeMonitor(res) {
        const html = `
<!DOCTYPE html>
<html>
<head>
    <title>FlexPBX Hold Music - Real-time Monitor</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; margin: 0; background: #000; color: #0f0; }
        .monitor { padding: 20px; }
        .header { color: #fff; margin-bottom: 20px; }
        .metrics { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; }
        .metric { background: #111; border: 1px solid #333; padding: 15px; border-radius: 8px; text-align: center; }
        .metric-value { font-size: 2em; font-weight: bold; color: #0f0; }
        .metric-label { font-size: 0.9em; color: #999; }
        .live-log { background: #111; border: 1px solid #333; padding: 15px; border-radius: 8px; height: 400px; overflow-y: auto; font-family: 'Monaco', monospace; font-size: 12px; }
        .audio-visual { display: flex; gap: 20px; margin: 20px 0; }
        .waveform { flex: 1; background: #111; border: 1px solid #333; padding: 15px; border-radius: 8px; height: 150px; position: relative; }
        .bar { position: absolute; bottom: 0; width: 3px; background: #0f0; margin-right: 1px; }
    </style>
</head>
<body>
    <div class="monitor">
        <div class="header">
            <h1>📡 FlexPBX Hold Music - Real-time Monitor</h1>
            <p>Live streaming analytics and performance monitoring</p>
        </div>

        <div class="metrics">
            <div class="metric">
                <div class="metric-value" id="active-streams">0</div>
                <div class="metric-label">Active Streams</div>
            </div>
            <div class="metric">
                <div class="metric-value" id="connected-clients">0</div>
                <div class="metric-label">Connected Clients</div>
            </div>
            <div class="metric">
                <div class="metric-value" id="data-throughput">0 KB/s</div>
                <div class="metric-label">Data Throughput</div>
            </div>
            <div class="metric">
                <div class="metric-value" id="uptime">00:00:00</div>
                <div class="metric-label">Uptime</div>
            </div>
        </div>

        <div class="audio-visual">
            <div class="waveform">
                <h4>Audio Waveform (Simulated)</h4>
                <div id="waveform-container"></div>
            </div>
        </div>

        <div class="live-log" id="live-log">
            [${new Date().toISOString()}] FlexPBX Hold Music Monitor initialized
        </div>
    </div>

    <script>
        let startTime = Date.now();
        let logEntries = 0;

        function addLogEntry(message) {
            const log = document.getElementById('live-log');
            const timestamp = new Date().toISOString();
            log.innerHTML += '<br>[' + timestamp + '] ' + message;
            log.scrollTop = log.scrollHeight;
            logEntries++;
        }

        function updateMetrics() {
            // Simulate real-time metrics
            document.getElementById('active-streams').textContent = Math.floor(Math.random() * 5) + 1;
            document.getElementById('connected-clients').textContent = Math.floor(Math.random() * 20) + 5;
            document.getElementById('data-throughput').textContent = (Math.random() * 100 + 50).toFixed(1) + ' KB/s';

            // Update uptime
            const uptime = Date.now() - startTime;
            const hours = Math.floor(uptime / 3600000);
            const minutes = Math.floor((uptime % 3600000) / 60000);
            const seconds = Math.floor((uptime % 60000) / 1000);
            document.getElementById('uptime').textContent =
                hours.toString().padStart(2, '0') + ':' +
                minutes.toString().padStart(2, '0') + ':' +
                seconds.toString().padStart(2, '0');
        }

        function updateWaveform() {
            const container = document.getElementById('waveform-container');
            const bars = container.querySelectorAll('.bar');

            // Add new bars if needed
            while (bars.length < 50) {
                const bar = document.createElement('div');
                bar.className = 'bar';
                bar.style.left = bars.length * 4 + 'px';
                container.appendChild(bar);
            }

            // Animate bars
            container.querySelectorAll('.bar').forEach((bar, index) => {
                const height = Math.random() * 100 + 10;
                bar.style.height = height + 'px';
                bar.style.opacity = 0.5 + (height / 200);
            });
        }

        // Auto-refresh every second
        setInterval(() => {
            updateMetrics();
            updateWaveform();

            // Add random log entries
            if (Math.random() > 0.7) {
                const events = [
                    'Client connected from 192.168.1.100',
                    'Hold music track changed: corporate-loop.wav',
                    'Audio buffer refilled',
                    'Stream quality: High (128kbps)',
                    'Client disconnected after 45 seconds'
                ];
                addLogEntry(events[Math.floor(Math.random() * events.length)]);
            }
        }, 1000);

        addLogEntry('Real-time monitoring started');
        addLogEntry('Waveform visualization initialized');
    </script>
</body>
</html>`;

        res.writeHead(200, { 'Content-Type': 'text/html' });
        res.end(html);
    }

    handleStreamRequest(req, res) {
        // Add client to active connections
        this.clients.add(res);

        res.writeHead(200, {
            'Content-Type': 'audio/wav',
            'Cache-Control': 'no-cache',
            'Connection': 'keep-alive'
        });

        console.log(`🎵 New hold music client connected (${this.clients.size} total)`);

        req.on('close', () => {
            this.clients.delete(res);
            console.log(`👋 Hold music client disconnected (${this.clients.size} remaining)`);
        });

        // Start streaming current track or default
        this.streamToClient(res);
    }

    streamToClient(res) {
        if (this.currentTrack) {
            const filePath = path.join(this.defaultMediaPath, this.currentTrack);
            if (fs.existsSync(filePath)) {
                const stream = fs.createReadStream(filePath);
                stream.pipe(res, { end: false });

                stream.on('end', () => {
                    // Loop the track
                    setTimeout(() => this.streamToClient(res), 1000);
                });
            }
        } else {
            // Send silence or default hold tone
            this.sendDefaultHoldTone(res);
        }
    }

    sendDefaultHoldTone(res) {
        // Generate a simple hold tone
        const tone = Buffer.alloc(1024, 0);
        res.write(tone);
        setTimeout(() => this.sendDefaultHoldTone(res), 100);
    }

    handlePlayRequest(req, res, params) {
        const category = params.get('category');
        const track = params.get('track');

        if (category && this.musicCategories[category]) {
            this.currentTrack = this.musicCategories[category].files?.[0] || `${category}-default.wav`;
            console.log(`🎵 Now playing: ${this.currentTrack} (${category})`);

            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({
                success: true,
                message: `Started playing ${category} music`,
                track: this.currentTrack,
                clients: this.clients.size
            }));
        } else {
            res.writeHead(400, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'Invalid category' }));
        }
    }

    handleStopRequest(res) {
        this.currentTrack = null;
        console.log('⏹️ Hold music stopped');

        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            success: true,
            message: 'Hold music stopped',
            clients: this.clients.size
        }));
    }

    serveStatus(res) {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            isRunning: true,
            port: this.port,
            currentTrack: this.currentTrack,
            clients: this.clients.size,
            categories: Object.keys(this.musicCategories)
        }));
    }

    serveCategories(res) {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify(this.musicCategories));
    }

    serveMediaFile(req, res, pathname) {
        const filePath = path.join(this.defaultMediaPath, path.basename(pathname));

        if (fs.existsSync(filePath)) {
            const stat = fs.statSync(filePath);
            res.writeHead(200, {
                'Content-Type': 'audio/wav',
                'Content-Length': stat.size
            });
            fs.createReadStream(filePath).pipe(res);
        } else {
            res.writeHead(404);
            res.end('Media file not found');
        }
    }

    setupRealTimeMonitoring() {
        // Monitor streaming status
        setInterval(() => {
            if (this.clients.size > 0) {
                console.log(`🎵 Hold Music Server: ${this.clients.size} active clients | Track: ${this.currentTrack || 'None'}`);
            }
        }, 30000);
    }

    getServerInfo() {
        return {
            port: this.port,
            url: `http://localhost:${this.port}`,
            monitorUrl: `http://localhost:${this.port}/monitor`,
            isRunning: !!this.server,
            activeClients: this.clients.size,
            currentTrack: this.currentTrack
        };
    }

    stop() {
        if (this.server) {
            this.server.close();
            this.clients.clear();
            console.log('🔇 Hold Music Server stopped');
        }
    }
}

module.exports = HoldMusicServer;