<?php
/**
 * FlexPBX Mattermost Widget
 * Embeddable Mattermost channel viewer and chat interface
 *
 * @author FlexPBX Development Team
 * @version 1.0.0
 * @created 2025-11-06
 */

// Load configuration
$config = require_once(__DIR__ . '/../api/config.php');

// Connect to database
try {
    $db = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    echo '<div class="mattermost-error">Failed to load chat: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}

// Get Mattermost configuration
$stmt = $db->query("SELECT * FROM mattermost_config ORDER BY id DESC LIMIT 1");
$mattermostConfig = $stmt->fetch();

// Get visible channels
$stmt = $db->query("SELECT * FROM mattermost_channels WHERE is_visible = 1 ORDER BY sort_order ASC, channel_display_name ASC");
$channels = $stmt->fetchAll();

// Get default channel
$defaultChannel = null;
foreach ($channels as $channel) {
    if ($channel['is_default']) {
        $defaultChannel = $channel;
        break;
    }
}

// If no default, use first channel
if (!$defaultChannel && !empty($channels)) {
    $defaultChannel = $channels[0];
}
?>

<div id="mattermost-widget" class="mattermost-widget">
    <!-- Channel Sidebar -->
    <div class="mattermost-sidebar">
        <div class="mattermost-sidebar-header">
            <h3>Channels</h3>
            <button class="btn-refresh" onclick="MattermostWidget.refreshChannels()" title="Refresh">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                    <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                </svg>
            </button>
        </div>

        <div class="mattermost-channel-list">
            <?php if (empty($channels)): ?>
                <div class="no-channels">
                    <p>No channels configured</p>
                    <a href="/admin/mattermost-channels.php">Configure Channels</a>
                </div>
            <?php else: ?>
                <?php foreach ($channels as $channel): ?>
                    <div class="channel-item <?php echo $defaultChannel && $channel['channel_id'] === $defaultChannel['channel_id'] ? 'active' : ''; ?>"
                         data-channel-id="<?php echo htmlspecialchars($channel['channel_id']); ?>"
                         data-channel-name="<?php echo htmlspecialchars($channel['channel_display_name']); ?>"
                         onclick="MattermostWidget.switchChannel('<?php echo htmlspecialchars($channel['channel_id']); ?>', '<?php echo htmlspecialchars($channel['channel_display_name']); ?>')">
                        <span class="channel-icon">#</span>
                        <span class="channel-name"><?php echo htmlspecialchars($channel['channel_display_name']); ?></span>
                        <span class="unread-badge" style="display: none;">0</span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="mattermost-chat">
        <!-- Chat Header -->
        <div class="mattermost-chat-header">
            <div class="channel-info">
                <h3 id="current-channel-name">
                    <?php echo $defaultChannel ? htmlspecialchars($defaultChannel['channel_display_name']) : 'Select a channel'; ?>
                </h3>
                <p id="current-channel-description"></p>
            </div>
            <div class="chat-controls">
                <button class="btn-icon" onclick="MattermostWidget.toggleSearch()" title="Search">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                    </svg>
                </button>
                <button class="btn-icon" onclick="MattermostWidget.openInMattermost()" title="Open in Mattermost">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1.002 1.002 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4.018 4.018 0 0 1-.128-1.287z"/>
                        <path d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 1 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243L6.586 4.672z"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Search Bar (Hidden by default) -->
        <div id="search-bar" class="search-bar" style="display: none;">
            <input type="text" id="search-input" placeholder="Search messages..." onkeyup="MattermostWidget.searchMessages(event)">
            <button onclick="MattermostWidget.toggleSearch()">Cancel</button>
        </div>

        <!-- Messages Area -->
        <div class="mattermost-messages" id="mattermost-messages">
            <div class="loading-indicator" style="display: none;">
                <div class="spinner"></div>
                <p>Loading messages...</p>
            </div>
            <div class="messages-container" id="messages-container">
                <?php if (!$defaultChannel): ?>
                    <div class="empty-state">
                        <p>Select a channel to start chatting</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Message Input -->
        <div class="mattermost-input">
            <form id="message-form" onsubmit="MattermostWidget.sendMessage(event)">
                <textarea id="message-input"
                          placeholder="Type a message..."
                          rows="1"
                          onkeydown="MattermostWidget.handleKeyPress(event)"></textarea>
                <button type="submit" class="btn-send" title="Send message">
                    <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M15.854.146a.5.5 0 0 1 .11.54l-5.819 14.547a.75.75 0 0 1-1.329.124l-3.178-4.995L.643 7.184a.75.75 0 0 1 .124-1.33L15.314.037a.5.5 0 0 1 .54.11ZM6.636 10.07l2.761 4.338L14.13 2.576 6.636 10.07Zm6.787-8.201L1.591 6.602l4.339 2.76 7.494-7.493Z"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</div>

<style>
.mattermost-widget {
    display: flex;
    height: 600px;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

.mattermost-sidebar {
    width: 260px;
    background: #2c2d30;
    color: white;
    display: flex;
    flex-direction: column;
    border-right: 1px solid #1a1a1a;
}

.mattermost-sidebar-header {
    padding: 20px;
    border-bottom: 1px solid #1a1a1a;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mattermost-sidebar-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.btn-refresh {
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.7);
    cursor: pointer;
    padding: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.btn-refresh:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.mattermost-channel-list {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
}

.channel-item {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    margin-bottom: 2px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    color: rgba(255, 255, 255, 0.7);
}

.channel-item:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.channel-item.active {
    background: rgba(102, 126, 234, 0.2);
    color: white;
}

.channel-icon {
    margin-right: 10px;
    font-weight: 600;
    font-size: 18px;
}

.channel-name {
    flex: 1;
    font-size: 14px;
}

.unread-badge {
    background: #dc3545;
    color: white;
    border-radius: 10px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 600;
}

.no-channels {
    text-align: center;
    padding: 40px 20px;
    color: rgba(255, 255, 255, 0.5);
}

.no-channels a {
    display: inline-block;
    margin-top: 10px;
    padding: 8px 16px;
    background: #667eea;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 13px;
    transition: all 0.2s ease;
}

.no-channels a:hover {
    background: #5568d3;
}

.mattermost-chat {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: white;
}

.mattermost-chat-header {
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.channel-info h3 {
    margin: 0 0 5px 0;
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

.channel-info p {
    margin: 0;
    font-size: 13px;
    color: #666;
}

.chat-controls {
    display: flex;
    gap: 10px;
}

.btn-icon {
    background: transparent;
    border: 1px solid #e0e0e0;
    color: #666;
    cursor: pointer;
    padding: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.btn-icon:hover {
    background: #f5f5f5;
    border-color: #ccc;
}

.search-bar {
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    gap: 10px;
}

.search-bar input {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.search-bar button {
    padding: 10px 20px;
    background: #6c757d;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s ease;
}

.search-bar button:hover {
    background: #5a6268;
}

.mattermost-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #f8f9fa;
}

.messages-container {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.message {
    display: flex;
    gap: 12px;
    padding: 10px;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.message:hover {
    background: rgba(0, 0, 0, 0.02);
}

.message-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
    flex-shrink: 0;
}

.message-content {
    flex: 1;
    min-width: 0;
}

.message-header {
    display: flex;
    align-items: baseline;
    gap: 10px;
    margin-bottom: 5px;
}

.message-author {
    font-weight: 600;
    color: #333;
    font-size: 15px;
}

.message-time {
    font-size: 12px;
    color: #999;
}

.message-text {
    color: #333;
    font-size: 14px;
    line-height: 1.5;
    word-wrap: break-word;
}

.message-text a {
    color: #667eea;
    text-decoration: none;
}

.message-text a:hover {
    text-decoration: underline;
}

.empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #999;
    font-size: 16px;
}

.loading-indicator {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    gap: 15px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.mattermost-input {
    padding: 20px;
    border-top: 1px solid #e0e0e0;
    background: white;
}

.mattermost-input form {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.mattermost-input textarea {
    flex: 1;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    resize: none;
    max-height: 120px;
    transition: border-color 0.2s ease;
}

.mattermost-input textarea:focus {
    outline: none;
    border-color: #667eea;
}

.btn-send {
    padding: 12px 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn-send:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

.btn-send:active {
    transform: translateY(0);
}

.mattermost-error {
    padding: 20px;
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    border-radius: 8px;
    margin: 20px;
}

@media (max-width: 768px) {
    .mattermost-widget {
        flex-direction: column;
        height: 100vh;
    }

    .mattermost-sidebar {
        width: 100%;
        max-height: 150px;
    }

    .mattermost-channel-list {
        display: flex;
        flex-direction: row;
        overflow-x: auto;
        overflow-y: hidden;
    }

    .channel-item {
        white-space: nowrap;
    }
}
</style>

<script>
const MattermostWidget = {
    currentChannelId: <?php echo $defaultChannel ? "'" . $defaultChannel['channel_id'] . "'" : 'null'; ?>,
    currentChannelName: <?php echo $defaultChannel ? "'" . htmlspecialchars($defaultChannel['channel_display_name'], ENT_QUOTES) . "'" : 'null'; ?>,
    pollInterval: <?php echo $mattermostConfig['poll_interval'] ?? 5; ?> * 1000,
    pollTimer: null,

    init: function() {
        if (this.currentChannelId) {
            this.loadMessages(this.currentChannelId);
            this.startPolling();
        }

        // Auto-resize textarea
        const textarea = document.getElementById('message-input');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
        }
    },

    switchChannel: function(channelId, channelName) {
        this.currentChannelId = channelId;
        this.currentChannelName = channelName;

        // Update UI
        document.querySelectorAll('.channel-item').forEach(item => {
            item.classList.remove('active');
        });

        document.querySelector(`[data-channel-id="${channelId}"]`).classList.add('active');
        document.getElementById('current-channel-name').textContent = channelName;

        // Load messages
        this.loadMessages(channelId);
    },

    loadMessages: async function(channelId) {
        const container = document.getElementById('messages-container');
        const loading = document.querySelector('.loading-indicator');

        if (loading) loading.style.display = 'flex';
        container.innerHTML = '';

        try {
            const response = await fetch(`/api/mattermost-integration.php?action=get_messages&channel_id=${channelId}&per_page=50`);
            const result = await response.json();

            if (loading) loading.style.display = 'none';

            if (result.success && result.posts) {
                this.renderMessages(result.posts);
            } else {
                container.innerHTML = '<div class="empty-state"><p>Failed to load messages</p></div>';
            }
        } catch (error) {
            if (loading) loading.style.display = 'none';
            container.innerHTML = '<div class="empty-state"><p>Error loading messages</p></div>';
            console.error('Error loading messages:', error);
        }
    },

    renderMessages: function(posts) {
        const container = document.getElementById('messages-container');
        container.innerHTML = '';

        if (!posts.order || posts.order.length === 0) {
            container.innerHTML = '<div class="empty-state"><p>No messages yet</p></div>';
            return;
        }

        // Reverse order to show oldest first
        const messageIds = [...posts.order].reverse();

        messageIds.forEach(messageId => {
            const post = posts.posts[messageId];
            if (!post) return;

            const messageDiv = document.createElement('div');
            messageDiv.className = 'message';
            messageDiv.setAttribute('data-message-id', post.id);

            const username = post.user_id && posts.users && posts.users[post.user_id]
                ? posts.users[post.user_id].username
                : 'Unknown';

            const avatar = username.charAt(0).toUpperCase();
            const timestamp = new Date(post.create_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            messageDiv.innerHTML = `
                <div class="message-avatar">${avatar}</div>
                <div class="message-content">
                    <div class="message-header">
                        <span class="message-author">${username}</span>
                        <span class="message-time">${timestamp}</span>
                    </div>
                    <div class="message-text">${this.formatMessage(post.message)}</div>
                </div>
            `;

            container.appendChild(messageDiv);
        });

        // Scroll to bottom
        const messagesArea = document.getElementById('mattermost-messages');
        messagesArea.scrollTop = messagesArea.scrollHeight;
    },

    formatMessage: function(text) {
        // Basic formatting - escape HTML and convert URLs to links
        text = text.replace(/&/g, '&amp;')
                   .replace(/</g, '&lt;')
                   .replace(/>/g, '&gt;');

        // Convert URLs to links
        text = text.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');

        // Convert line breaks
        text = text.replace(/\n/g, '<br>');

        return text;
    },

    sendMessage: async function(event) {
        event.preventDefault();

        const textarea = document.getElementById('message-input');
        const message = textarea.value.trim();

        if (!message || !this.currentChannelId) return;

        try {
            const response = await fetch('/api/mattermost-integration.php?action=post_message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    channel_id: this.currentChannelId,
                    message: message
                })
            });

            const result = await response.json();

            if (result.success) {
                textarea.value = '';
                textarea.style.height = 'auto';
                this.loadMessages(this.currentChannelId);
            } else {
                alert('Failed to send message: ' + (result.error || 'Unknown error'));
            }
        } catch (error) {
            alert('Error sending message: ' + error.message);
            console.error('Error sending message:', error);
        }
    },

    handleKeyPress: function(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            this.sendMessage(event);
        }
    },

    startPolling: function() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
        }

        this.pollTimer = setInterval(() => {
            if (this.currentChannelId) {
                this.loadMessages(this.currentChannelId);
            }
        }, this.pollInterval);
    },

    stopPolling: function() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
    },

    refreshChannels: function() {
        location.reload();
    },

    toggleSearch: function() {
        const searchBar = document.getElementById('search-bar');
        searchBar.style.display = searchBar.style.display === 'none' ? 'flex' : 'none';

        if (searchBar.style.display === 'flex') {
            document.getElementById('search-input').focus();
        }
    },

    searchMessages: function(event) {
        // Implement search functionality
        console.log('Search:', event.target.value);
    },

    openInMattermost: function() {
        if (this.currentChannelId) {
            const serverUrl = '<?php echo $mattermostConfig['server_url'] ?? 'https://chat.tappedin.fm'; ?>';
            window.open(`${serverUrl}/`, '_blank');
        }
    }
};

// Initialize widget when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => MattermostWidget.init());
} else {
    MattermostWidget.init();
}

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    MattermostWidget.stopPolling();
});
</script>
