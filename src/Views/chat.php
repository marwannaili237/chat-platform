<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <title>Secure Chat Platform</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <div id="chatContainer" class="chat-container">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <h2>Secure Chat</h2>
                    <div class="user-info" id="userInfo">
                        Logged in as: <?php echo htmlspecialchars($user['username']); ?>
                    </div>
                </div>
                
                <div class="online-users">
                    <h3>Online Users (<span id="onlineCount">0</span>)</h3>
                    <ul id="userList" class="user-list">
                        <!-- Online users will be populated here -->
                    </ul>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="chat-main">
                <div class="chat-header">
                    <div class="chat-title">General Chat</div>
                    <div class="chat-actions">
                        <?php if ($user['is_admin']): ?>
                            <button id="adminToggle" class="btn btn-small btn-danger">Admin Panel</button>
                        <?php endif; ?>
                        <button id="logoutBtn" class="btn btn-small btn-secondary">Logout</button>
                    </div>
                </div>

                <!-- Connection Status -->
                <div id="connectionStatus" class="connection-status disconnected">
                    Connecting...
                </div>

                <!-- Messages Container -->
                <div id="messagesContainer" class="messages-container">
                    <!-- Messages will be populated here -->
                </div>

                <!-- Typing Indicator -->
                <div id="typingIndicator" class="typing-indicator hidden">
                    <!-- Typing indicator will appear here -->
                </div>

                <!-- Message Input -->
                <div class="message-input-container">
                    <form id="messageForm" class="message-input-form">
                        <textarea id="messageInput" class="message-input" 
                                placeholder="Type your message..." rows="1"></textarea>
                        
                        <label for="fileInput" class="file-input-label" title="Upload File">
                            📎
                        </label>
                        <input type="file" id="fileInput" class="file-input" 
                               accept="image/*,.pdf,.txt,.doc,.docx">
                        
                        <button type="submit" class="btn">Send</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Panel -->
    <?php if ($user['is_admin']): ?>
    <div id="adminPanel" class="admin-panel">
        <div class="admin-panel-header">
            <h3>Admin Panel</h3>
            <button id="adminClose" class="btn btn-small">×</button>
        </div>
        
        <div class="admin-panel-content">
            <!-- Statistics -->
            <div class="admin-section">
                <h3>Statistics</h3>
                <button id="refreshStats" class="btn btn-small btn-secondary">Refresh</button>
                <div id="statsContainer">
                    <!-- Stats will be loaded here -->
                </div>
            </div>

            <!-- Broadcast Message -->
            <div class="admin-section">
                <h3>Broadcast Message</h3>
                <form id="broadcastForm">
                    <div class="form-group">
                        <textarea id="broadcastMessage" class="message-input" 
                                placeholder="Enter broadcast message..." rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger">Send Broadcast</button>
                </form>
            </div>

            <!-- User Management -->
            <div class="admin-section">
                <h3>User Management</h3>
                <button id="refreshUsers" class="btn btn-small btn-secondary">Refresh</button>
                <div id="userManagement">
                    <!-- User management will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scripts -->
    <script src="/js/chat.js"></script>
    <?php if ($user['is_admin']): ?>
        <script src="/js/admin.js"></script>
    <?php endif; ?>
</body>
</html>

