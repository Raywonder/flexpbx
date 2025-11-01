<?php
/**
 * FlexPBX User Portal - Unified Messaging
 * Handles both internal extension messaging AND external SMS
 * Auto-routes based on recipient type
 */

session_start();

// Check authentication
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: /user-portal/login.php');
    exit;
}

$extension = $_SESSION['user_extension'] ?? 'Unknown';
$username = $_SESSION['user_username'] ?? $extension;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - FlexPBX User Portal</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
            display: flex;
            gap: 1rem;
            height: calc(100vh - 2rem);
        }

        .sidebar {
            width: 350px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .sidebar-header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
            color: #2c3e50;
        }

        .sidebar-header .subtitle {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .sound-toggle-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 6px;
            margin-top: 0.5rem;
        }

        .sound-toggle {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .sound-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .sound-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 24px;
        }

        .sound-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked + .sound-slider {
            background-color: #4CAF50;
        }

        input:checked + .sound-slider:before {
            transform: translateX(20px);
        }

        .sound-toggle-label {
            font-size: 0.85rem;
            color: #666;
            user-select: none;
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
        }

        .conversation {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            transition: background 0.2s;
        }

        .conversation:hover {
            background: #f8f9fa;
        }

        .conversation.active {
            background: #e3f2fd;
            border-left: 3px solid #2196f3;
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.25rem;
        }

        .conversation-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .conversation-type {
            font-size: 0.75rem;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            background: #e0e0e0;
            color: #666;
        }

        .conversation-type.extension {
            background: #4CAF50;
            color: white;
        }

        .conversation-type.phone {
            background: #2196f3;
            color: white;
        }

        .conversation-preview {
            font-size: 0.9rem;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 0.25rem;
        }

        .conversation-time {
            font-size: 0.75rem;
            color: #999;
        }

        .unread-badge {
            display: inline-block;
            background: #f44336;
            color: white;
            border-radius: 12px;
            padding: 0.15rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .chat-area {
            flex: 1;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e0e0e0;
            background: #f8f9fa;
        }

        .chat-header h2 {
            margin: 0;
            font-size: 1.3rem;
            color: #2c3e50;
        }

        .chat-header .recipient-info {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.25rem;
        }

        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            background: #f8f9fa;
        }

        .message {
            margin-bottom: 1rem;
            display: flex;
        }

        .message.sent {
            justify-content: flex-end;
        }

        .message.received {
            justify-content: flex-start;
        }

        .message-bubble {
            max-width: 60%;
            padding: 0.75rem 1rem;
            border-radius: 16px;
            word-wrap: break-word;
        }

        .message.sent .message-bubble {
            background: #2196f3;
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message.received .message-bubble {
            background: white;
            color: #2c3e50;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }

        .message.sent .message-time {
            text-align: right;
        }

        .compose-area {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e0e0e0;
            background: white;
        }

        .compose-form {
            display: flex;
            gap: 0.5rem;
        }

        .compose-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 20px;
            font-size: 0.95rem;
            resize: none;
            font-family: inherit;
            outline: none;
            max-height: 100px;
        }

        .compose-input:focus {
            border-color: #2196f3;
        }

        .send-btn {
            padding: 0.75rem 1.5rem;
            background: #2196f3;
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .send-btn:hover {
            background: #1976d2;
        }

        .send-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #999;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .new-conversation-btn {
            display: block;
            width: calc(100% - 3rem);
            margin: 1rem 1.5rem;
            padding: 0.75rem;
            background: #2196f3;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .new-conversation-btn:hover {
            background: #1976d2;
        }

        .new-conversation-modal {
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

        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
        }

        .modal-content h3 {
            margin: 0 0 1rem 0;
            color: #2c3e50;
        }

        .modal-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }

        .modal-hint {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 1rem;
        }

        .modal-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #2196f3;
            color: white;
        }

        .btn-primary:hover {
            background: #1976d2;
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #666;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .back-link {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 1rem 1.5rem;
            font-weight: 600;
        }

        .back-link:hover {
            background: #5a6268;
        }

        /* Hidden audio elements for notifications */
        audio {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar with conversations -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h1>💬 Messages</h1>
                <p class="subtitle">Extension <?= htmlspecialchars($extension) ?> • <?= htmlspecialchars($username) ?></p>

                <!-- Sound Toggle -->
                <div class="sound-toggle-container">
                    <label class="sound-toggle">
                        <input type="checkbox" id="sound-enabled" onchange="toggleSound(this.checked)">
                        <span class="sound-slider"></span>
                    </label>
                    <label for="sound-enabled" class="sound-toggle-label">
                        <span id="sound-status">🔇 Sounds Off</span>
                    </label>
                </div>
            </div>

            <button class="new-conversation-btn" onclick="openNewConversationModal()">
                ➕ New Message
            </button>

            <div class="conversations-list" id="conversations-list">
                <p style="text-align: center; padding: 2rem; color: #999;">Loading conversations...</p>
            </div>

            <a href="/user-portal/" class="back-link">← Back to Dashboard</a>
        </div>

        <!-- Chat area -->
        <div class="chat-area">
            <div id="empty-state" class="empty-state">
                <div class="empty-state-icon">💬</div>
                <p><strong>Select a conversation to start messaging</strong></p>
                <p>Send messages to extensions (2000-2999) or phone numbers</p>
            </div>

            <div id="chat-view" style="display: none; height: 100%; display: flex; flex-direction: column;">
                <div class="chat-header">
                    <h2 id="chat-recipient-name">Loading...</h2>
                    <div class="recipient-info" id="chat-recipient-info"></div>
                </div>

                <div class="messages-container" id="messages-container">
                    <!-- Messages will be populated here -->
                </div>

                <div class="compose-area">
                    <form class="compose-form" onsubmit="sendMessage(event)">
                        <textarea
                            class="compose-input"
                            id="message-input"
                            placeholder="Type your message..."
                            rows="1"
                            onkeydown="handleKeyDown(event)"
                        ></textarea>
                        <button type="submit" class="send-btn" id="send-btn">Send</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- New Conversation Modal -->
    <div class="new-conversation-modal" id="new-conversation-modal">
        <div class="modal-content">
            <h3>New Message</h3>
            <input
                type="text"
                class="modal-input"
                id="new-recipient"
                placeholder="Extension (2000) or Phone Number (302-313-9555)"
                autofocus
            >
            <p class="modal-hint">
                💡 Enter an extension (4 digits) for internal messaging or a phone number for SMS
            </p>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeNewConversationModal()">Cancel</button>
                <button class="btn btn-primary" onclick="startNewConversation()">Start Chat</button>
            </div>
        </div>
    </div>

    <!-- Hidden audio elements for notification sounds -->
    <audio id="message-send-sound" preload="auto">
        <source src="/uploads/media/sounds/system/connected.wav" type="audio/wav">
    </audio>
    <audio id="message-receive-sound" preload="auto">
        <source src="/uploads/media/sounds/system/message.wav" type="audio/wav">
    </audio>

    <script>
        const extension = '<?= $extension ?>';
        let conversations = [];
        let currentRecipient = null;
        let currentThread = [];
        let lastPollTimestamp = 0;
        let pollInterval = null;
        let soundEnabled = false;

        // Load preferences and start
        document.addEventListener('DOMContentLoaded', async () => {
            await loadSoundPreference();
            await loadConversations();
            startPolling();
        });

        // Load sound preference from API
        async function loadSoundPreference() {
            try {
                const response = await fetch('/api/notification-subscribe.php?action=get_preferences');
                const data = await response.json();

                if (data.success) {
                    soundEnabled = data.preferences.message_sounds_enabled ?? false;
                    document.getElementById('sound-enabled').checked = soundEnabled;
                    updateSoundStatus();
                }
            } catch (error) {
                console.error('Failed to load sound preference:', error);
            }
        }

        // Toggle sound preference
        async function toggleSound(enabled) {
            soundEnabled = enabled;
            updateSoundStatus();

            try {
                const response = await fetch('/api/notification-subscribe.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_preferences',
                        message_sounds_enabled: enabled
                    })
                });

                const data = await response.json();

                if (!data.success) {
                    console.error('Failed to update sound preference:', data.error);
                }
            } catch (error) {
                console.error('Failed to update sound preference:', error);
            }
        }

        // Update sound status display
        function updateSoundStatus() {
            const statusEl = document.getElementById('sound-status');
            if (soundEnabled) {
                statusEl.textContent = '🔊 Sounds On';
            } else {
                statusEl.textContent = '🔇 Sounds Off';
            }
        }

        // Play notification sound
        function playSound(type) {
            if (!soundEnabled) return;

            const audio = document.getElementById(type === 'send' ? 'message-send-sound' : 'message-receive-sound');

            // Request permission if needed
            if (audio) {
                audio.play().catch(err => {
                    console.log('Auto-play prevented, user interaction needed:', err);
                });
            }
        }

        // Load conversations
        async function loadConversations() {
            try {
                const response = await fetch(`/api/messages.php?action=conversations&extension=${extension}`);
                const data = await response.json();

                if (data.success) {
                    conversations = data.conversations;
                    renderConversations();
                } else {
                    console.error('Failed to load conversations:', data.error);
                }
            } catch (error) {
                console.error('Failed to load conversations:', error);
            }
        }

        // Render conversations list
        function renderConversations() {
            const container = document.getElementById('conversations-list');

            if (conversations.length === 0) {
                container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #999;">No conversations yet<br>Click "New Message" to start</p>';
                return;
            }

            let html = '';
            conversations.forEach(conv => {
                const isActive = currentRecipient === conv.recipient;
                const typeLabel = conv.recipient_type === 'extension' ? '📱 Extension' : '📞 Phone';
                const typeClass = conv.recipient_type === 'extension' ? 'extension' : 'phone';
                const time = new Date(conv.last_message_time * 1000).toLocaleString();

                html += `
                    <div class="conversation ${isActive ? 'active' : ''}" onclick="openConversation('${conv.recipient}', '${conv.recipient_name}', '${conv.recipient_type}')">
                        <div class="conversation-header">
                            <span class="conversation-name">${conv.recipient_name}</span>
                            <span class="conversation-type ${typeClass}">${typeLabel}</span>
                        </div>
                        <div class="conversation-preview">${conv.last_message}</div>
                        <div class="conversation-time">
                            ${time}
                            ${conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : ''}
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Open conversation
        async function openConversation(recipient, name, type) {
            currentRecipient = recipient;

            // Show chat view
            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('chat-view').style.display = 'flex';

            // Update header
            document.getElementById('chat-recipient-name').textContent = name;
            const typeLabel = type === 'extension' ? '📱 Extension' : '📞 Phone Number';
            document.getElementById('chat-recipient-info').textContent = `${typeLabel} • ${recipient}`;

            // Load thread
            await loadThread(recipient);

            // Re-render conversations to update active state
            renderConversations();
        }

        // Load message thread
        async function loadThread(recipient) {
            try {
                const response = await fetch(`/api/messages.php?action=thread&extension=${extension}&recipient=${encodeURIComponent(recipient)}`);
                const data = await response.json();

                if (data.success) {
                    currentThread = data.messages;
                    renderMessages();
                    scrollToBottom();
                } else {
                    console.error('Failed to load thread:', data.error);
                }
            } catch (error) {
                console.error('Failed to load thread:', error);
            }
        }

        // Render messages
        function renderMessages() {
            const container = document.getElementById('messages-container');

            if (currentThread.length === 0) {
                container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #999;">No messages yet<br>Start the conversation!</p>';
                return;
            }

            let html = '';
            currentThread.forEach(msg => {
                const direction = msg.direction || (msg.sender === extension ? 'sent' : 'received');
                const time = new Date(msg.timestamp * 1000).toLocaleTimeString();

                html += `
                    <div class="message ${direction}">
                        <div class="message-bubble">
                            ${msg.message}
                            <div class="message-time">${time}</div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Scroll to bottom of messages
        function scrollToBottom() {
            const container = document.getElementById('messages-container');
            container.scrollTop = container.scrollHeight;
        }

        // Send message
        async function sendMessage(event) {
            event.preventDefault();

            const input = document.getElementById('message-input');
            const message = input.value.trim();

            if (!message || !currentRecipient) return;

            const sendBtn = document.getElementById('send-btn');
            sendBtn.disabled = true;

            try {
                const response = await fetch('/api/messages.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'send',
                        sender: extension,
                        recipient: currentRecipient,
                        message: message
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Clear input
                    input.value = '';

                    // Play send sound
                    playSound('send');

                    // Reload thread and conversations
                    await loadThread(currentRecipient);
                    await loadConversations();
                } else {
                    alert('Failed to send message: ' + data.error);
                }
            } catch (error) {
                console.error('Failed to send message:', error);
                alert('Failed to send message. Please try again.');
            } finally {
                sendBtn.disabled = false;
                input.focus();
            }
        }

        // Handle Enter key in textarea
        function handleKeyDown(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage(event);
            }
        }

        // Poll for new messages
        function startPolling() {
            pollInterval = setInterval(async () => {
                try {
                    const response = await fetch(`/api/messages.php?action=poll&extension=${extension}&since=${lastPollTimestamp}`);
                    const data = await response.json();

                    if (data.success && data.new_messages.length > 0) {
                        // Play receive sound
                        playSound('receive');

                        // Reload conversations to update unread counts
                        await loadConversations();

                        // If viewing the conversation, reload thread
                        if (currentRecipient) {
                            await loadThread(currentRecipient);
                        }

                        lastPollTimestamp = data.timestamp;
                    }
                } catch (error) {
                    console.error('Polling error:', error);
                }
            }, 5000); // Poll every 5 seconds
        }

        // New conversation modal
        function openNewConversationModal() {
            document.getElementById('new-conversation-modal').style.display = 'flex';
            document.getElementById('new-recipient').value = '';
            document.getElementById('new-recipient').focus();
        }

        function closeNewConversationModal() {
            document.getElementById('new-conversation-modal').style.display = 'none';
        }

        async function startNewConversation() {
            const recipient = document.getElementById('new-recipient').value.trim();

            if (!recipient) {
                alert('Please enter an extension or phone number');
                return;
            }

            // Determine if extension or phone
            const isExtension = /^[2-9]\d{3}$/.test(recipient);
            const isPhone = /^\d{10,11}$/.test(recipient.replace(/\D/g, ''));

            if (!isExtension && !isPhone) {
                alert('Please enter a valid extension (4 digits) or phone number (10-11 digits)');
                return;
            }

            closeNewConversationModal();

            // Open conversation
            const name = isExtension ? `Extension ${recipient}` : recipient;
            const type = isExtension ? 'extension' : 'phone';

            await openConversation(recipient, name, type);
        }

        // Auto-resize textarea
        document.getElementById('message-input').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });

        // Close modal when clicking outside
        document.getElementById('new-conversation-modal').addEventListener('click', (e) => {
            if (e.target.id === 'new-conversation-modal') {
                closeNewConversationModal();
            }
        });

        // Support Enter to start new conversation
        document.getElementById('new-recipient').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                startNewConversation();
            }
        });
    </script>
</body>
</html>
