<?php
/**
 * FlexPBX User Portal - SMS Messaging
 * Send and receive SMS messages
 */

// Require authentication
require_once __DIR__ . '/user_auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Messaging - FlexPBX</title>
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
            padding: 20px 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .phone-info {
            background: #f0f0f0;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            color: #666;
        }

        .phone-info strong {
            color: #333;
        }

        .messaging-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 20px;
            height: calc(100vh - 200px);
        }

        .conversations-panel {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
        }

        .panel-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .new-message-btn {
            background: white;
            color: #667eea;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }

        .new-message-btn:hover {
            background: #f0f0f0;
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
        }

        .conversation-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
        }

        .conversation-item:hover {
            background: #f8f8f8;
        }

        .conversation-item.active {
            background: #e8e8ff;
            border-left: 3px solid #667eea;
        }

        .conversation-contact {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .conversation-preview {
            font-size: 13px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-time {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .chat-panel {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
        }

        .chat-contact-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .chat-contact-number {
            font-size: 14px;
            opacity: 0.9;
        }

        .messages-container {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f8f8;
        }

        .message {
            margin-bottom: 15px;
            display: flex;
        }

        .message.outbound {
            justify-content: flex-end;
        }

        .message.inbound {
            justify-content: flex-start;
        }

        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
        }

        .message.outbound .message-bubble {
            background: #667eea;
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message.inbound .message-bubble {
            background: white;
            color: #333;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .message-time {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 5px;
        }

        .message-status {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 5px;
        }

        .compose-area {
            padding: 20px;
            border-top: 1px solid #eee;
            background: white;
            border-radius: 0 0 12px 12px;
        }

        .compose-row {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .message-input {
            flex: 1;
            border: 2px solid #e0e0e0;
            border-radius: 24px;
            padding: 12px 20px;
            font-size: 14px;
            resize: none;
            font-family: inherit;
            max-height: 100px;
        }

        .message-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .send-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 24px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            white-space: nowrap;
        }

        .send-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .char-counter {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
            text-align: right;
        }

        .char-counter.warning {
            color: #f59e0b;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #999;
            padding: 40px;
            text-align: center;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .templates-btn {
            background: #f0f0f0;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            color: #666;
        }

        .templates-btn:hover {
            background: #e0e0e0;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #666;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 2000;
            display: none;
        }

        .notification.show {
            display: block;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .notification.success {
            border-left: 4px solid #4ade80;
        }

        .notification.error {
            border-left: 4px solid #ef4444;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💬 SMS Messaging</h1>
            <div class="phone-info">
                <strong>Your Number:</strong> <span id="myPhoneNumber">Loading...</span>
            </div>
        </div>

        <div class="messaging-container">
            <!-- Conversations List -->
            <div class="conversations-panel">
                <div class="panel-header">
                    Conversations
                    <button class="new-message-btn" onclick="showNewMessageModal()">+ New</button>
                </div>
                <div class="conversations-list" id="conversationsList">
                    <div class="empty-state">
                        <div class="empty-state-icon">💬</div>
                        <p>No conversations yet</p>
                        <p style="font-size: 14px; margin-top: 10px;">Start a new message to begin</p>
                    </div>
                </div>
            </div>

            <!-- Chat Panel -->
            <div class="chat-panel">
                <div id="chatEmpty" class="empty-state" style="height: 100%;">
                    <div class="empty-state-icon">📱</div>
                    <p>Select a conversation to view messages</p>
                    <p style="font-size: 14px; margin-top: 10px;">or start a new conversation</p>
                </div>

                <div id="chatActive" style="display: none; height: 100%; flex-direction: column;">
                    <div class="chat-header">
                        <div class="chat-contact-name" id="chatContactName"></div>
                        <div class="chat-contact-number" id="chatContactNumber"></div>
                    </div>

                    <div class="messages-container" id="messagesContainer">
                        <!-- Messages will be loaded here -->
                    </div>

                    <div class="compose-area">
                        <button class="templates-btn" onclick="showTemplatesModal()">📝 Templates</button>
                        <div class="compose-row" style="margin-top: 10px;">
                            <textarea
                                id="messageInput"
                                class="message-input"
                                placeholder="Type your message..."
                                rows="1"
                                maxlength="160"
                                oninput="updateCharCounter()"
                                onkeypress="handleMessageKeyPress(event)"
                            ></textarea>
                            <button class="send-btn" onclick="sendMessage()" id="sendBtn">Send</button>
                        </div>
                        <div class="char-counter" id="charCounter">0 / 160</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Message Modal -->
    <div class="modal" id="newMessageModal">
        <div class="modal-content">
            <div class="modal-header">New Message</div>
            <div class="form-group">
                <label>To (Phone Number)</label>
                <input type="tel" id="newMessageTo" placeholder="e.g., 555-123-4567">
            </div>
            <div class="form-group">
                <label>Carrier</label>
                <select id="newMessageCarrier">
                    <option value="">Select carrier...</option>
                </select>
            </div>
            <div class="form-group">
                <label>Message</label>
                <textarea id="newMessageText" rows="4" maxlength="160" placeholder="Type your message..."></textarea>
                <div class="char-counter" id="newMessageCharCounter">0 / 160</div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeNewMessageModal()">Cancel</button>
                <button class="btn btn-primary" onclick="sendNewMessage()">Send</button>
            </div>
        </div>
    </div>

    <!-- Templates Modal -->
    <div class="modal" id="templatesModal">
        <div class="modal-content">
            <div class="modal-header">Message Templates</div>
            <div id="templatesList"></div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeTemplatesModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Notification -->
    <div class="notification" id="notification"></div>

    <script>
        let currentExtension = null;
        let currentPhoneNumber = null;
        let currentConversation = null;
        let conversations = {};
        let refreshInterval = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            // Get extension from session or URL parameter
            currentExtension = new URLSearchParams(window.location.search).get('extension') || '2006';

            loadPhoneNumber();
            loadCarriers();
            loadConversations();

            // Refresh messages every 10 seconds
            refreshInterval = setInterval(loadConversations, 10000);
        });

        async function loadPhoneNumber() {
            try {
                const response = await fetch(`/api/sms.php?path=phone-numbers&extension=${currentExtension}`);
                const data = await response.json();

                if (data.success && data.data.length > 0) {
                    currentPhoneNumber = data.data[0].phone_number;
                    document.getElementById('myPhoneNumber').textContent = formatPhoneNumber(currentPhoneNumber);
                } else {
                    document.getElementById('myPhoneNumber').textContent = 'Not configured';
                }
            } catch (error) {
                console.error('Error loading phone number:', error);
            }
        }

        async function loadCarriers() {
            try {
                const response = await fetch('/api/sms.php?path=carriers');
                const data = await response.json();

                if (data.success) {
                    const select = document.getElementById('newMessageCarrier');
                    Object.keys(data.carriers).forEach(carrier => {
                        const option = document.createElement('option');
                        option.value = data.carriers[carrier];
                        option.textContent = carrier;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading carriers:', error);
            }
        }

        async function loadConversations() {
            try {
                const response = await fetch(`/api/sms.php?path=messages&extension=${currentExtension}&limit=100`);
                const data = await response.json();

                if (data.success) {
                    // Group messages by phone number
                    conversations = {};
                    data.data.forEach(msg => {
                        const phoneNumber = msg.direction === 'inbound' ? msg.from_number : msg.to_number;
                        if (!conversations[phoneNumber]) {
                            conversations[phoneNumber] = [];
                        }
                        conversations[phoneNumber].push(msg);
                    });

                    displayConversations();

                    // Refresh current conversation if open
                    if (currentConversation) {
                        loadConversation(currentConversation);
                    }
                }
            } catch (error) {
                console.error('Error loading conversations:', error);
            }
        }

        function displayConversations() {
            const container = document.getElementById('conversationsList');

            if (Object.keys(conversations).length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">💬</div>
                        <p>No conversations yet</p>
                        <p style="font-size: 14px; margin-top: 10px;">Start a new message to begin</p>
                    </div>
                `;
                return;
            }

            let html = '';
            Object.keys(conversations).sort((a, b) => {
                const lastA = conversations[a][0].created_at;
                const lastB = conversations[b][0].created_at;
                return lastB.localeCompare(lastA);
            }).forEach(phoneNumber => {
                const messages = conversations[phoneNumber];
                const lastMessage = messages[0];
                const isActive = currentConversation === phoneNumber;

                html += `
                    <div class="conversation-item ${isActive ? 'active' : ''}" onclick="selectConversation('${phoneNumber}')">
                        <div class="conversation-contact">${formatPhoneNumber(phoneNumber)}</div>
                        <div class="conversation-preview">${escapeHtml(lastMessage.message_body)}</div>
                        <div class="conversation-time">${formatTime(lastMessage.created_at)}</div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        async function selectConversation(phoneNumber) {
            currentConversation = phoneNumber;
            displayConversations();
            loadConversation(phoneNumber);
        }

        async function loadConversation(phoneNumber) {
            try {
                const response = await fetch(`/api/sms.php?path=conversation&extension=${currentExtension}&phone=${phoneNumber}`);
                const data = await response.json();

                if (data.success) {
                    document.getElementById('chatEmpty').style.display = 'none';
                    document.getElementById('chatActive').style.display = 'flex';

                    document.getElementById('chatContactName').textContent = formatPhoneNumber(phoneNumber);
                    document.getElementById('chatContactNumber').textContent = phoneNumber;

                    displayMessages(data.data);
                }
            } catch (error) {
                console.error('Error loading conversation:', error);
            }
        }

        function displayMessages(messages) {
            const container = document.getElementById('messagesContainer');

            let html = '';
            messages.forEach(msg => {
                html += `
                    <div class="message ${msg.direction}">
                        <div class="message-bubble">
                            <div>${escapeHtml(msg.message_body)}</div>
                            <div class="message-time">${formatTime(msg.created_at)}</div>
                            ${msg.direction === 'outbound' ? `<div class="message-status">${msg.status}</div>` : ''}
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            container.scrollTop = container.scrollHeight;
        }

        async function sendMessage() {
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();

            if (!message || !currentConversation) return;

            const sendBtn = document.getElementById('sendBtn');
            sendBtn.disabled = true;
            sendBtn.textContent = 'Sending...';

            try {
                // Determine carrier gateway - for now, use Verizon as default
                // In production, you'd want to store this with each conversation
                const carrierGateway = 'vtext.com';

                const response = await fetch('/api/sms.php?path=send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        extension: currentExtension,
                        to_number: currentConversation,
                        message: message,
                        carrier_gateway: carrierGateway
                    })
                });

                const data = await response.json();

                if (data.success) {
                    messageInput.value = '';
                    updateCharCounter();
                    showNotification('Message sent!', 'success');

                    // Reload conversation
                    setTimeout(() => {
                        loadConversations();
                    }, 1000);
                } else {
                    showNotification('Error: ' + data.error, 'error');
                }
            } catch (error) {
                showNotification('Error sending message', 'error');
                console.error('Error:', error);
            } finally {
                sendBtn.disabled = false;
                sendBtn.textContent = 'Send';
            }
        }

        function showNewMessageModal() {
            document.getElementById('newMessageModal').classList.add('active');
        }

        function closeNewMessageModal() {
            document.getElementById('newMessageModal').classList.remove('active');
            document.getElementById('newMessageTo').value = '';
            document.getElementById('newMessageCarrier').value = '';
            document.getElementById('newMessageText').value = '';
        }

        async function sendNewMessage() {
            const to = document.getElementById('newMessageTo').value.replace(/[^0-9]/g, '');
            const carrier = document.getElementById('newMessageCarrier').value;
            const message = document.getElementById('newMessageText').value.trim();

            if (!to || !carrier || !message) {
                showNotification('Please fill in all fields', 'error');
                return;
            }

            try {
                const response = await fetch('/api/sms.php?path=send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        extension: currentExtension,
                        to_number: to,
                        message: message,
                        carrier_gateway: carrier
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Message sent!', 'success');
                    closeNewMessageModal();
                    loadConversations();
                    selectConversation(to);
                } else {
                    showNotification('Error: ' + data.error, 'error');
                }
            } catch (error) {
                showNotification('Error sending message', 'error');
                console.error('Error:', error);
            }
        }

        function showTemplatesModal() {
            // Load templates
            document.getElementById('templatesModal').classList.add('active');
        }

        function closeTemplatesModal() {
            document.getElementById('templatesModal').classList.remove('active');
        }

        function updateCharCounter() {
            const input = document.getElementById('messageInput');
            const counter = document.getElementById('charCounter');
            const length = input.value.length;

            counter.textContent = `${length} / 160`;
            if (length > 140) {
                counter.classList.add('warning');
            } else {
                counter.classList.remove('warning');
            }
        }

        function handleMessageKeyPress(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        }

        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification show ${type}`;

            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        function formatPhoneNumber(number) {
            if (!number) return '';
            const cleaned = ('' + number).replace(/\D/g, '');
            const match = cleaned.match(/^1?(\d{3})(\d{3})(\d{4})$/);
            if (match) {
                return '(' + match[1] + ') ' + match[2] + '-' + match[3];
            }
            return number;
        }

        function formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;

            if (diff < 60000) return 'Just now';
            if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
            if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';

            return date.toLocaleDateString();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>
</body>
</html>
