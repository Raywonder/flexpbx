const { EventEmitter } = require('events');
const path = require('path');
const fs = require('fs-extra');
const crypto = require('crypto');
const WebSocket = require('ws');

class RichMessagingService extends EventEmitter {
    constructor() {
        super();
        this.messagesDir = path.join(require('os').homedir(), '.flexpbx', 'messages');
        this.conversations = new Map();
        this.clients = new Map();
        this.messageHistory = new Map();
        this.webSocketServer = null;
        this.messageQueue = [];
        this.isOnline = false;

        // Rich message types supported
        this.messageTypes = {
            text: 'text',
            html: 'html',
            markdown: 'markdown',
            code: 'code',
            file: 'file',
            image: 'image',
            audio: 'audio',
            video: 'video',
            location: 'location',
            contact: 'contact',
            system: 'system',
            notification: 'notification',
            accessibility: 'accessibility',
            voiceNote: 'voice-note',
            screenReader: 'screen-reader',
            remoteControl: 'remote-control'
        };

        // Message encryption settings
        this.encryption = {
            enabled: true,
            algorithm: 'aes-256-gcm',
            keyLength: 32
        };

        // User presence tracking
        this.userPresence = new Map();

        // Message formatting and rendering
        this.messageRenderer = {
            html: this.renderHTMLMessage.bind(this),
            markdown: this.renderMarkdownMessage.bind(this),
            code: this.renderCodeMessage.bind(this),
            accessibility: this.renderAccessibilityMessage.bind(this)
        };

        this.initializeMessaging();
    }

    async initializeMessaging() {
        console.log('💬 Initializing Rich Messaging Service...');

        try {
            // Ensure messages directory exists
            await fs.ensureDir(this.messagesDir);

            // Initialize WebSocket server for real-time messaging
            this.setupWebSocketServer();

            // Load message history
            await this.loadMessageHistory();

            // Setup encryption keys
            await this.setupEncryption();

            console.log('✅ Rich Messaging Service initialized');
            this.emit('messaging-ready');
        } catch (error) {
            console.error('❌ Failed to initialize messaging:', error);
            this.emit('messaging-error', error);
        }
    }

    setupWebSocketServer() {
        this.webSocketServer = new WebSocket.Server({
            port: 41238,
            verifyClient: (info) => {
                // Add authentication logic here
                return true;
            }
        });

        this.webSocketServer.on('connection', (ws, request) => {
            const clientId = this.generateClientId();
            console.log(`💬 Client connected: ${clientId}`);

            // Store client connection
            this.clients.set(clientId, {
                id: clientId,
                socket: ws,
                connectedAt: new Date().toISOString(),
                ip: request.socket.remoteAddress,
                userAgent: request.headers['user-agent']
            });

            // Handle incoming messages
            ws.on('message', async (data) => {
                try {
                    const message = JSON.parse(data.toString());
                    await this.handleIncomingMessage(clientId, message);
                } catch (error) {
                    console.error('Error handling message:', error);
                    this.sendErrorToClient(clientId, 'Invalid message format');
                }
            });

            // Handle client disconnect
            ws.on('close', () => {
                console.log(`💬 Client disconnected: ${clientId}`);
                this.clients.delete(clientId);
                this.updateUserPresence(clientId, 'offline');
            });

            // Send welcome message
            this.sendToClient(clientId, {
                type: 'welcome',
                message: 'Connected to FlexPBX Rich Messaging',
                serverInfo: {
                    version: '2.0.0',
                    features: Object.keys(this.messageTypes),
                    encryption: this.encryption.enabled
                }
            });
        });

        console.log('📡 Rich Messaging WebSocket server listening on port 41238');
    }

    async handleIncomingMessage(clientId, message) {
        const { type, content, target, messageType, metadata } = message;

        switch (type) {
            case 'send-message':
                await this.sendMessage(clientId, target, content, messageType, metadata);
                break;

            case 'get-conversations':
                await this.sendConversations(clientId);
                break;

            case 'get-message-history':
                await this.sendMessageHistory(clientId, message.conversationId);
                break;

            case 'update-presence':
                this.updateUserPresence(clientId, message.status);
                break;

            case 'start-typing':
                this.broadcastTypingStatus(clientId, target, true);
                break;

            case 'stop-typing':
                this.broadcastTypingStatus(clientId, target, false);
                break;

            case 'mark-as-read':
                await this.markMessagesAsRead(clientId, message.messageIds);
                break;

            case 'request-file-upload':
                await this.handleFileUploadRequest(clientId, message);
                break;

            default:
                console.log(`❓ Unknown message type: ${type}`);
        }
    }

    async sendMessage(senderId, targetId, content, messageType = 'text', metadata = {}) {
        const messageId = this.generateMessageId();
        const timestamp = new Date().toISOString();

        // Create rich message object
        const message = {
            id: messageId,
            senderId,
            targetId,
            content,
            type: messageType,
            timestamp,
            metadata: {
                ...metadata,
                platform: process.platform,
                clientVersion: '2.0.0'
            },
            status: 'sent',
            encrypted: false
        };

        // Encrypt message if enabled
        if (this.encryption.enabled) {
            message.content = await this.encryptMessage(content);
            message.encrypted = true;
        }

        // Store message
        await this.storeMessage(message);

        // Add to conversation
        const conversationId = this.getConversationId(senderId, targetId);
        await this.addToConversation(conversationId, message);

        // Send to target client if online
        const targetClient = this.clients.get(targetId);
        if (targetClient) {
            this.sendToClient(targetId, {
                type: 'new-message',
                message: await this.formatMessageForClient(message),
                conversationId
            });

            // Mark as delivered
            message.status = 'delivered';
            await this.updateMessageStatus(messageId, 'delivered');
        } else {
            // Add to message queue for offline delivery
            this.messageQueue.push(message);
        }

        // Send confirmation to sender
        this.sendToClient(senderId, {
            type: 'message-status',
            messageId,
            status: message.status,
            timestamp
        });

        // Emit event
        this.emit('message-sent', { message, conversationId });

        return { success: true, messageId, status: message.status };
    }

    async formatMessageForClient(message, clientId = null) {
        // Decrypt if needed
        let content = message.content;
        if (message.encrypted) {
            content = await this.decryptMessage(message.content);
        }

        // Render content based on type
        const renderedContent = await this.renderMessage(content, message.type);

        return {
            id: message.id,
            senderId: message.senderId,
            content: renderedContent.html,
            plainText: renderedContent.plainText,
            type: message.type,
            timestamp: message.timestamp,
            metadata: message.metadata,
            status: message.status,
            accessibility: renderedContent.accessibility
        };
    }

    async renderMessage(content, messageType) {
        const renderer = this.messageRenderer[messageType];

        if (renderer) {
            return await renderer(content);
        }

        // Default text rendering
        return {
            html: this.escapeHtml(content),
            plainText: content,
            accessibility: {
                description: `Text message: ${content}`,
                ariaLabel: `Message from ${new Date().toLocaleTimeString()}`
            }
        };
    }

    async renderHTMLMessage(content) {
        // Sanitize HTML content for security
        const sanitized = this.sanitizeHTML(content);

        return {
            html: sanitized,
            plainText: this.htmlToPlainText(sanitized),
            accessibility: {
                description: `HTML message: ${this.htmlToPlainText(sanitized)}`,
                ariaLabel: 'Rich HTML message'
            }
        };
    }

    async renderMarkdownMessage(content) {
        // Convert markdown to HTML (basic implementation)
        let html = content
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/`(.*?)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>');

        return {
            html: html,
            plainText: content,
            accessibility: {
                description: `Markdown message: ${content}`,
                ariaLabel: 'Formatted markdown message'
            }
        };
    }

    async renderCodeMessage(content) {
        const { code, language = 'javascript' } = typeof content === 'object' ? content : { code: content };

        const html = `
            <div class="code-message" data-language="${language}">
                <div class="code-header">
                    <span class="language-label">${language}</span>
                    <button class="copy-button" onclick="copyCode(this)">Copy</button>
                </div>
                <pre><code class="language-${language}">${this.escapeHtml(code)}</code></pre>
            </div>
        `;

        return {
            html: html,
            plainText: `Code (${language}):\n${code}`,
            accessibility: {
                description: `Code snippet in ${language}: ${code}`,
                ariaLabel: `Code message in ${language} programming language`
            }
        };
    }

    async renderAccessibilityMessage(content) {
        const { action, target, result, screenReader } = content;

        const html = `
            <div class="accessibility-message">
                <div class="accessibility-header">
                    <span class="screen-reader-icon">♿</span>
                    <span class="screen-reader-name">${screenReader || 'Screen Reader'}</span>
                </div>
                <div class="accessibility-content">
                    <div class="action">Action: <strong>${action}</strong></div>
                    ${target ? `<div class="target">Target: <em>${target}</em></div>` : ''}
                    ${result ? `<div class="result">Result: ${result}</div>` : ''}
                </div>
            </div>
        `;

        return {
            html: html,
            plainText: `Accessibility: ${action} ${target ? 'on ' + target : ''} ${result ? '- ' + result : ''}`,
            accessibility: {
                description: `Accessibility command: ${action} executed ${result ? 'successfully' : 'unsuccessfully'}`,
                ariaLabel: `Screen reader accessibility message`
            }
        };
    }

    // File and media handling
    async handleFileMessage(senderId, targetId, fileInfo, metadata = {}) {
        const { filename, size, mimeType, data } = fileInfo;

        // Save file to messages directory
        const fileId = this.generateFileId();
        const filePath = path.join(this.messagesDir, 'files', fileId);
        await fs.ensureDir(path.dirname(filePath));
        await fs.writeFile(filePath, data);

        // Create file message
        const content = {
            fileId,
            filename,
            size,
            mimeType,
            path: filePath,
            url: `/api/files/${fileId}`, // Internal URL for file access
            thumbnail: await this.generateThumbnail(filePath, mimeType)
        };

        return await this.sendMessage(senderId, targetId, content, 'file', {
            ...metadata,
            originalFilename: filename,
            fileSize: size
        });
    }

    async generateThumbnail(filePath, mimeType) {
        // Generate thumbnails for images/videos
        if (mimeType.startsWith('image/')) {
            // Image thumbnail logic would go here
            return { type: 'image', url: `/api/thumbnails/${path.basename(filePath)}` };
        }

        if (mimeType.startsWith('video/')) {
            // Video thumbnail logic would go here
            return { type: 'video', url: `/api/thumbnails/${path.basename(filePath)}` };
        }

        return null;
    }

    // User presence and typing indicators
    updateUserPresence(clientId, status) {
        this.userPresence.set(clientId, {
            status, // online, away, busy, offline
            lastSeen: new Date().toISOString()
        });

        // Broadcast presence update
        this.broadcastToClients({
            type: 'presence-update',
            clientId,
            status,
            timestamp: new Date().toISOString()
        }, [clientId]); // Exclude the client themselves
    }

    broadcastTypingStatus(clientId, targetId, isTyping) {
        const targetClient = this.clients.get(targetId);
        if (targetClient) {
            this.sendToClient(targetId, {
                type: 'typing-status',
                clientId,
                isTyping,
                timestamp: new Date().toISOString()
            });
        }
    }

    // Message persistence
    async storeMessage(message) {
        const messageFile = path.join(this.messagesDir, 'messages', `${message.id}.json`);
        await fs.ensureDir(path.dirname(messageFile));
        await fs.writeJson(messageFile, message, { spaces: 2 });
    }

    async loadMessageHistory() {
        try {
            const messagesDir = path.join(this.messagesDir, 'messages');
            if (await fs.pathExists(messagesDir)) {
                const files = await fs.readdir(messagesDir);
                for (const file of files) {
                    if (file.endsWith('.json')) {
                        const message = await fs.readJson(path.join(messagesDir, file));
                        const conversationId = this.getConversationId(message.senderId, message.targetId);
                        if (!this.messageHistory.has(conversationId)) {
                            this.messageHistory.set(conversationId, []);
                        }
                        this.messageHistory.get(conversationId).push(message);
                    }
                }
            }
        } catch (error) {
            console.error('Error loading message history:', error);
        }
    }

    // Encryption
    async setupEncryption() {
        if (!this.encryption.enabled) return;

        const keyFile = path.join(this.messagesDir, 'encryption.key');

        if (await fs.pathExists(keyFile)) {
            this.encryptionKey = await fs.readFile(keyFile);
        } else {
            this.encryptionKey = crypto.randomBytes(this.encryption.keyLength);
            await fs.writeFile(keyFile, this.encryptionKey);
            await fs.chmod(keyFile, 0o600);
        }
    }

    async encryptMessage(content) {
        if (!this.encryption.enabled || !this.encryptionKey) return content;

        const iv = crypto.randomBytes(16);
        const cipher = crypto.createCipheriv(this.encryption.algorithm, this.encryptionKey, iv);

        let encrypted = cipher.update(JSON.stringify(content), 'utf8', 'hex');
        encrypted += cipher.final('hex');

        const authTag = cipher.getAuthTag();

        return {
            encrypted,
            iv: iv.toString('hex'),
            authTag: authTag.toString('hex'),
            algorithm: this.encryption.algorithm
        };
    }

    async decryptMessage(encryptedData) {
        if (!this.encryption.enabled || !this.encryptionKey) return encryptedData;

        if (typeof encryptedData !== 'object') return encryptedData;

        const { encrypted, iv, authTag } = encryptedData;
        const decipher = crypto.createDecipheriv(this.encryption.algorithm, this.encryptionKey, Buffer.from(iv, 'hex'));
        decipher.setAuthTag(Buffer.from(authTag, 'hex'));

        let decrypted = decipher.update(encrypted, 'hex', 'utf8');
        decrypted += decipher.final('utf8');

        return JSON.parse(decrypted);
    }

    // Utility methods
    generateClientId() {
        return crypto.randomBytes(16).toString('hex');
    }

    generateMessageId() {
        return `msg_${Date.now()}_${crypto.randomBytes(8).toString('hex')}`;
    }

    generateFileId() {
        return `file_${Date.now()}_${crypto.randomBytes(8).toString('hex')}`;
    }

    getConversationId(clientId1, clientId2) {
        return [clientId1, clientId2].sort().join('_');
    }

    sendToClient(clientId, message) {
        const client = this.clients.get(clientId);
        if (client && client.socket.readyState === WebSocket.OPEN) {
            client.socket.send(JSON.stringify(message));
            return true;
        }
        return false;
    }

    broadcastToClients(message, excludeClients = []) {
        this.clients.forEach((client, clientId) => {
            if (!excludeClients.includes(clientId)) {
                this.sendToClient(clientId, message);
            }
        });
    }

    escapeHtml(text) {
        const div = { innerHTML: text };
        return div.textContent || div.innerText || '';
    }

    sanitizeHTML(html) {
        // Basic HTML sanitization - in production, use a proper library like DOMPurify
        return html
            .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
            .replace(/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi, '')
            .replace(/on\w+="[^"]*"/gi, '');
    }

    htmlToPlainText(html) {
        return html.replace(/<[^>]*>/g, '').replace(/&[^;]+;/g, ' ');
    }

    sendErrorToClient(clientId, error) {
        this.sendToClient(clientId, {
            type: 'error',
            error: typeof error === 'string' ? error : error.message,
            timestamp: new Date().toISOString()
        });
    }

    // Status and management
    getStatus() {
        return {
            serverRunning: !!this.webSocketServer,
            connectedClients: this.clients.size,
            messageTypes: Object.keys(this.messageTypes),
            encryption: {
                enabled: this.encryption.enabled,
                algorithm: this.encryption.algorithm
            },
            conversations: this.conversations.size,
            messageQueue: this.messageQueue.length,
            features: {
                richFormatting: true,
                fileSharing: true,
                encryption: this.encryption.enabled,
                accessibility: true,
                typing: true,
                presence: true,
                offlineMessages: true,
                messageHistory: true
            }
        };
    }

    async stop() {
        if (this.webSocketServer) {
            this.webSocketServer.close();
            console.log('💬 Rich Messaging Service stopped');
        }
    }
}

module.exports = RichMessagingService;