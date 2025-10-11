/**
 * Local TTS Server for Auto-generating Missing Voice Files
 * Provides a web server for TTS generation and automatically creates missing audio files
 */

const http = require('http');
const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');
const os = require('os');

class LocalTTSServer {
    constructor(portManager, crossPlatformSpeech) {
        this.portManager = portManager;
        this.crossPlatformSpeech = crossPlatformSpeech;
        this.server = null;
        this.port = 8085; // Default TTS server port
        this.isRunning = false;

        // Audio file directory
        this.audioDir = path.join(process.cwd(), 'media', 'sounds');
        this.ensureAudioDirectories();

        // Pre-defined messages for auto-generation
        this.predefinedMessages = {
            'system/door-closing-fx.wav': 'Door closing',
            'notifications/message-send.wav': 'Message sent successfully',
            'notifications/message-receive.wav': 'New message received',
            'system/startup.wav': 'FlexPBX system starting up',
            'system/shutdown.wav': 'FlexPBX system shutting down',
            'notifications/call-incoming.wav': 'Incoming call',
            'notifications/call-ended.wav': 'Call ended',
            'system/service-ready.wav': 'Service ready',
            'system/error.wav': 'System error detected',
            'notifications/extension-registered.wav': 'Extension registered successfully'
        };
    }

    async init() {
        try {
            console.log('🔊 Initializing Local TTS Server...');

            // Get available port
            this.port = await this.portManager.getAvailablePort('localTTS');

            // Auto-generate missing voice files
            await this.generateMissingVoiceFiles();

            // Start TTS web server
            await this.startServer();

            console.log(`✅ Local TTS Server running at http://localhost:${this.port}`);
            return true;
        } catch (error) {
            console.error('❌ Failed to initialize Local TTS Server:', error);
            return false;
        }
    }

    ensureAudioDirectories() {
        const dirs = [
            path.join(this.audioDir, 'system'),
            path.join(this.audioDir, 'notifications'),
            path.join(this.audioDir, 'voice-prompts'),
            path.join(this.audioDir, 'generated')
        ];

        dirs.forEach(dir => {
            if (!fs.existsSync(dir)) {
                fs.mkdirSync(dir, { recursive: true });
                console.log(`📁 Created audio directory: ${dir}`);
            }
        });
    }

    async generateMissingVoiceFiles() {
        console.log('🎵 Checking for missing voice files...');
        let generatedCount = 0;

        for (const [filePath, message] of Object.entries(this.predefinedMessages)) {
            const fullPath = path.join(this.audioDir, filePath);

            if (!fs.existsSync(fullPath)) {
                console.log(`🔊 Generating missing voice file: ${filePath}`);
                try {
                    await this.generateVoiceFile(message, fullPath);
                    generatedCount++;
                } catch (error) {
                    console.error(`❌ Failed to generate ${filePath}:`, error.message);
                }
            }
        }

        if (generatedCount > 0) {
            console.log(`✅ Generated ${generatedCount} missing voice files`);
        } else {
            console.log('✅ All voice files present');
        }
    }

    async generateVoiceFile(text, outputPath) {
        return new Promise((resolve, reject) => {
            const dir = path.dirname(outputPath);
            if (!fs.existsSync(dir)) {
                fs.mkdirSync(dir, { recursive: true });
            }

            let command, args;

            switch (os.platform()) {
                case 'darwin': // macOS
                    command = 'say';
                    args = [
                        '-v', 'Alex', // Use professional voice
                        '-r', '180',  // Moderate speed
                        '--data-format=LEI16@22050', // Good quality
                        '-o', outputPath,
                        text
                    ];
                    break;

                case 'win32': // Windows
                    // Use PowerShell with SAPI for Windows
                    const script = `
                        Add-Type -AssemblyName System.Speech
                        $synth = New-Object System.Speech.Synthesis.SpeechSynthesizer
                        $synth.SetOutputToWaveFile("${outputPath}")
                        $synth.Speak("${text}")
                        $synth.Dispose()
                    `;
                    command = 'powershell';
                    args = ['-Command', script];
                    break;

                case 'linux': // Linux
                    command = 'espeak';
                    args = [
                        '-v', 'en+f3',
                        '-s', '160',
                        '-w', outputPath,
                        text
                    ];
                    break;

                default:
                    return reject(new Error(`Unsupported platform: ${os.platform()}`));
            }

            const process = spawn(command, args);

            process.on('close', (code) => {
                if (code === 0 && fs.existsSync(outputPath)) {
                    console.log(`✅ Generated: ${path.basename(outputPath)}`);
                    resolve();
                } else {
                    reject(new Error(`Voice generation failed with code ${code}`));
                }
            });

            process.on('error', (error) => {
                reject(error);
            });
        });
    }

    async startServer() {
        return new Promise((resolve, reject) => {
            this.server = http.createServer((req, res) => {
                this.handleRequest(req, res);
            });

            this.server.listen(this.port, () => {
                this.isRunning = true;
                console.log(`🔊 TTS Server listening on port ${this.port}`);
                resolve();
            });

            this.server.on('error', (error) => {
                console.error('❌ TTS Server error:', error);
                reject(error);
            });
        });
    }

    handleRequest(req, res) {
        const url = new URL(req.url, `http://localhost:${this.port}`);
        const pathname = url.pathname;

        // Set CORS headers
        res.setHeader('Access-Control-Allow-Origin', '*');
        res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

        if (req.method === 'OPTIONS') {
            res.writeHead(200);
            res.end();
            return;
        }

        switch (pathname) {
            case '/':
                this.handleHomePage(res);
                break;
            case '/generate':
                this.handleGenerate(req, res);
                break;
            case '/list':
                this.handleListFiles(res);
                break;
            case '/download':
                this.handleDownload(req, res, url.searchParams);
                break;
            case '/health':
                this.handleHealth(res);
                break;
            default:
                this.handle404(res);
        }
    }

    handleHomePage(res) {
        const html = `
<!DOCTYPE html>
<html>
<head>
    <title>FlexPBX TTS Server</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007cba; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #005a87; }
        .file-list { background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 20px; }
        .file-item { margin: 5px 0; padding: 5px; background: white; border-radius: 3px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔊 FlexPBX TTS Server</h1>
        <p>Generate voice files for your FlexPBX system</p>

        <div class="form-group">
            <label for="text">Text to Convert:</label>
            <textarea id="text" rows="3" placeholder="Enter text to convert to speech..."></textarea>
        </div>

        <div class="form-group">
            <label for="filename">Filename (optional):</label>
            <input type="text" id="filename" placeholder="e.g., custom-message.wav">
        </div>

        <div class="form-group">
            <label for="voice">Voice:</label>
            <select id="voice">
                <option value="default">Default Voice</option>
                <option value="alex">Alex (macOS)</option>
                <option value="samantha">Samantha (macOS)</option>
                <option value="zira">Microsoft Zira (Windows)</option>
                <option value="david">Microsoft David (Windows)</option>
            </select>
        </div>

        <button onclick="generateSpeech()">Generate Voice File</button>

        <div id="status"></div>

        <h3>Available Voice Files</h3>
        <div id="fileList" class="file-list">Loading...</div>

        <h3>Predefined Messages</h3>
        <button onclick="generatePredefined()">Generate All Missing Predefined Files</button>
    </div>

    <script>
        async function generateSpeech() {
            const text = document.getElementById('text').value;
            const filename = document.getElementById('filename').value;
            const voice = document.getElementById('voice').value;

            if (!text.trim()) {
                showStatus('Please enter text to convert', 'error');
                return;
            }

            showStatus('Generating voice file...', 'info');

            try {
                const response = await fetch('/generate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ text, filename, voice })
                });

                const result = await response.json();

                if (result.success) {
                    showStatus(\`✅ Generated: \${result.filename}\`, 'success');
                    loadFileList();
                } else {
                    showStatus(\`❌ Error: \${result.error}\`, 'error');
                }
            } catch (error) {
                showStatus(\`❌ Error: \${error.message}\`, 'error');
            }
        }

        async function generatePredefined() {
            showStatus('Generating predefined voice files...', 'info');

            try {
                const response = await fetch('/generate-predefined', { method: 'POST' });
                const result = await response.json();

                if (result.success) {
                    showStatus(\`✅ Generated \${result.count} files\`, 'success');
                    loadFileList();
                } else {
                    showStatus(\`❌ Error: \${result.error}\`, 'error');
                }
            } catch (error) {
                showStatus(\`❌ Error: \${error.message}\`, 'error');
            }
        }

        async function loadFileList() {
            try {
                const response = await fetch('/list');
                const files = await response.json();

                const listElement = document.getElementById('fileList');
                if (files.length === 0) {
                    listElement.innerHTML = '<p>No voice files found</p>';
                } else {
                    listElement.innerHTML = files.map(file =>
                        \`<div class="file-item">
                            📄 \${file.name} (\${file.size} bytes)
                            <a href="/download?file=\${encodeURIComponent(file.path)}" style="margin-left: 10px;">Download</a>
                        </div>\`
                    ).join('');
                }
            } catch (error) {
                document.getElementById('fileList').innerHTML = '<p>Error loading file list</p>';
            }
        }

        function showStatus(message, type) {
            const statusDiv = document.getElementById('status');
            statusDiv.innerHTML = \`<div class="status \${type}">\${message}</div>\`;
        }

        // Load file list on page load
        loadFileList();
    </script>
</body>
</html>`;

        res.writeHead(200, { 'Content-Type': 'text/html' });
        res.end(html);
    }

    handleGenerate(req, res) {
        if (req.method !== 'POST') {
            res.writeHead(405, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'Method not allowed' }));
            return;
        }

        let body = '';
        req.on('data', chunk => {
            body += chunk.toString();
        });

        req.on('end', async () => {
            try {
                const { text, filename, voice } = JSON.parse(body);

                if (!text) {
                    res.writeHead(400, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ error: 'Text is required' }));
                    return;
                }

                const outputFilename = filename || `generated-${Date.now()}.wav`;
                const outputPath = path.join(this.audioDir, 'generated', outputFilename);

                await this.generateVoiceFile(text, outputPath);

                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({
                    success: true,
                    filename: outputFilename,
                    path: outputPath
                }));
            } catch (error) {
                res.writeHead(500, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ error: error.message }));
            }
        });
    }

    handleListFiles(res) {
        try {
            const files = [];

            const scanDirectory = (dir, prefix = '') => {
                if (!fs.existsSync(dir)) return;

                const items = fs.readdirSync(dir);
                items.forEach(item => {
                    const fullPath = path.join(dir, item);
                    const stat = fs.statSync(fullPath);

                    if (stat.isDirectory()) {
                        scanDirectory(fullPath, prefix + item + '/');
                    } else if (item.endsWith('.wav')) {
                        files.push({
                            name: prefix + item,
                            path: fullPath,
                            size: stat.size,
                            created: stat.birthtime
                        });
                    }
                });
            };

            scanDirectory(this.audioDir);

            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify(files));
        } catch (error) {
            res.writeHead(500, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: error.message }));
        }
    }

    handleDownload(req, res, params) {
        const filePath = params.get('file');
        if (!filePath || !fs.existsSync(filePath)) {
            res.writeHead(404, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'File not found' }));
            return;
        }

        const filename = path.basename(filePath);
        res.writeHead(200, {
            'Content-Type': 'audio/wav',
            'Content-Disposition': `attachment; filename="${filename}"`
        });

        fs.createReadStream(filePath).pipe(res);
    }

    handleHealth(res) {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            status: 'healthy',
            port: this.port,
            platform: os.platform(),
            audioDir: this.audioDir,
            uptime: process.uptime()
        }));
    }

    handle404(res) {
        res.writeHead(404, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ error: 'Not found' }));
    }

    async stop() {
        if (this.server) {
            this.server.close();
            this.isRunning = false;
            console.log('🔊 TTS Server stopped');
        }
    }

    getStatus() {
        return {
            running: this.isRunning,
            port: this.port,
            url: `http://localhost:${this.port}`,
            audioDir: this.audioDir
        };
    }
}

module.exports = LocalTTSServer;