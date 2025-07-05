class ChatClient {
    constructor() {
        this.ws = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;
        this.user = null;
        this.typingTimer = null;
        this.isTyping = false;
        this.onlineUsers = new Set();
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.connect();
    }

    bindEvents() {
        // Message form submission
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.sendMessage();
            });
        }

        // Message input events
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                } else {
                    this.handleTyping();
                }
            });

            messageInput.addEventListener('input', () => {
                this.handleTyping();
            });
        }

        // File input
        const fileInput = document.getElementById('fileInput');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                this.handleFileSelect(e);
            });
        }

        // Admin panel toggle
        const adminToggle = document.getElementById('adminToggle');
        if (adminToggle) {
            adminToggle.addEventListener('click', () => {
                this.toggleAdminPanel();
            });
        }

        // Admin panel close
        const adminClose = document.getElementById('adminClose');
        if (adminClose) {
            adminClose.addEventListener('click', () => {
                this.closeAdminPanel();
            });
        }

        // Logout button
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => {
                this.logout();
            });
        }
    }

    connect() {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const host = window.location.hostname;
        const port = 8080; // WebSocket port
        
        this.ws = new WebSocket(`${protocol}//${host}:${port}`);
        
        this.ws.onopen = () => {
            console.log('WebSocket connected');
            this.isConnected = true;
            this.reconnectAttempts = 0;
            this.updateConnectionStatus('connected');
            this.authenticate();
        };

        this.ws.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this.handleMessage(data);
            } catch (error) {
                console.error('Error parsing message:', error);
            }
        };

        this.ws.onclose = () => {
            console.log('WebSocket disconnected');
            this.isConnected = false;
            this.updateConnectionStatus('disconnected');
            this.attemptReconnect();
        };

        this.ws.onerror = (error) => {
            console.error('WebSocket error:', error);
            this.updateConnectionStatus('disconnected');
        };
    }

    authenticate() {
        const token = this.getSessionToken();
        if (token) {
            this.send({
                type: 'auth',
                token: token
            });
        } else {
            window.location.href = '/login.php';
        }
    }

    getSessionToken() {
        // Get session token from cookie or localStorage
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'session_token') {
                return value;
            }
        }
        return localStorage.getItem('session_token');
    }

    send(data) {
        if (this.isConnected && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(data));
        } else {
            console.warn('WebSocket not connected, message not sent:', data);
        }
    }

    handleMessage(data) {
        switch (data.type) {
            case 'auth_success':
                this.user = data.user;
                this.updateUserInfo();
                break;
            case 'message_history':
                this.displayMessageHistory(data.messages);
                break;
            case 'new_message':
                this.displayMessage(data.message);
                break;
            case 'user_joined':
                this.handleUserJoined(data.user);
                break;
            case 'user_left':
                this.handleUserLeft(data.user);
                break;
            case 'online_users':
                this.updateOnlineUsers(data.users);
                break;
            case 'typing':
                this.handleTypingIndicator(data);
                break;
            case 'error':
                this.showError(data.message);
                break;
            case 'kicked':
                this.handleKicked(data.message);
                break;
            case 'admin_message':
                this.displaySystemMessage(data.message);
                break;
            case 'pong':
                // Handle ping response
                break;
            default:
                console.log('Unknown message type:', data.type);
        }
    }

    sendMessage() {
        const messageInput = document.getElementById('messageInput');
        const content = messageInput.value.trim();
        
        if (!content) return;

        this.send({
            type: 'message',
            content: content,
            message_type: 'text'
        });

        messageInput.value = '';
        this.stopTyping();
    }

    displayMessage(message) {
        const messagesContainer = document.getElementById('messagesContainer');
        const messageElement = this.createMessageElement(message);
        messagesContainer.appendChild(messageElement);
        this.scrollToBottom();
    }

    displayMessageHistory(messages) {
        const messagesContainer = document.getElementById('messagesContainer');
        messagesContainer.innerHTML = '';
        
        messages.forEach(message => {
            const messageElement = this.createMessageElement(message);
            messagesContainer.appendChild(messageElement);
        });
        
        this.scrollToBottom();
    }

    createMessageElement(message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message';
        
        if (this.user && message.user_id === this.user.id) {
            messageDiv.classList.add('own');
        }

        const time = new Date(message.created_at).toLocaleTimeString();
        
        messageDiv.innerHTML = `
            <div class="message-header">
                <span class="message-author ${message.is_admin ? 'admin' : ''}">${this.escapeHtml(message.username)}</span>
                <span class="message-time">${time}</span>
            </div>
            <div class="message-content">${this.escapeHtml(message.content)}</div>
        `;

        return messageDiv;
    }

    displaySystemMessage(content) {
        const messagesContainer = document.getElementById('messagesContainer');
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message system';
        messageDiv.innerHTML = `<div class="message-content">${this.escapeHtml(content)}</div>`;
        messagesContainer.appendChild(messageDiv);
        this.scrollToBottom();
    }

    handleTyping() {
        if (!this.isTyping) {
            this.isTyping = true;
            this.send({
                type: 'typing',
                is_typing: true
            });
        }

        clearTimeout(this.typingTimer);
        this.typingTimer = setTimeout(() => {
            this.stopTyping();
        }, 1000);
    }

    stopTyping() {
        if (this.isTyping) {
            this.isTyping = false;
            this.send({
                type: 'typing',
                is_typing: false
            });
        }
        clearTimeout(this.typingTimer);
    }

    handleTypingIndicator(data) {
        const typingIndicator = document.getElementById('typingIndicator');
        if (!typingIndicator) return;

        if (data.is_typing) {
            typingIndicator.textContent = `${data.user.username} is typing...`;
            typingIndicator.classList.remove('hidden');
        } else {
            typingIndicator.classList.add('hidden');
        }
    }

    updateOnlineUsers(users) {
        this.onlineUsers = new Set(users.map(u => u.id));
        const userList = document.getElementById('userList');
        if (!userList) return;

        userList.innerHTML = '';
        users.forEach(user => {
            const userItem = document.createElement('li');
            userItem.className = `user-item ${user.is_admin ? 'admin' : ''}`;
            userItem.textContent = user.username;
            userList.appendChild(userItem);
        });
    }

    handleUserJoined(user) {
        this.onlineUsers.add(user.id);
        this.displaySystemMessage(`${user.username} joined the chat`);
        this.updateOnlineUsersList();
    }

    handleUserLeft(user) {
        this.onlineUsers.delete(user.id);
        this.displaySystemMessage(`${user.username} left the chat`);
        this.updateOnlineUsersList();
    }

    updateOnlineUsersList() {
        // This would typically refresh the online users list
        // For now, we'll just update the count if there's a counter element
        const onlineCount = document.getElementById('onlineCount');
        if (onlineCount) {
            onlineCount.textContent = this.onlineUsers.size;
        }
    }

    handleFileSelect(event) {
        const file = event.target.files[0];
        if (!file) return;

        // Basic file validation
        const maxSize = 10 * 1024 * 1024; // 10MB
        if (file.size > maxSize) {
            this.showError('File size must be less than 10MB');
            return;
        }

        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];
        if (!allowedTypes.includes(file.type)) {
            this.showError('File type not allowed');
            return;
        }

        // Upload file via AJAX
        this.uploadFile(file);
    }

    uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('csrf_token', this.getCSRFToken());

        fetch('/api/upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.send({
                    type: 'message',
                    content: `Shared a file: ${file.name}`,
                    message_type: 'file',
                    file_path: data.file_path
                });
            } else {
                this.showError(data.message || 'File upload failed');
            }
        })
        .catch(error => {
            console.error('Upload error:', error);
            this.showError('File upload failed');
        });
    }

    getCSRFToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    updateConnectionStatus(status) {
        const statusElement = document.getElementById('connectionStatus');
        if (!statusElement) return;

        statusElement.className = `connection-status ${status}`;
        
        switch (status) {
            case 'connected':
                statusElement.textContent = 'Connected';
                break;
            case 'disconnected':
                statusElement.textContent = 'Disconnected';
                break;
            case 'connecting':
                statusElement.textContent = 'Connecting...';
                break;
        }
    }

    updateUserInfo() {
        const userInfo = document.getElementById('userInfo');
        if (userInfo && this.user) {
            userInfo.textContent = `Logged in as: ${this.user.username}`;
        }

        // Show admin controls if user is admin
        const adminToggle = document.getElementById('adminToggle');
        if (adminToggle && this.user && this.user.is_admin) {
            adminToggle.classList.remove('hidden');
        }
    }

    attemptReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            this.updateConnectionStatus('connecting');
            
            setTimeout(() => {
                console.log(`Reconnection attempt ${this.reconnectAttempts}`);
                this.connect();
            }, this.reconnectDelay * this.reconnectAttempts);
        } else {
            this.showError('Unable to reconnect. Please refresh the page.');
        }
    }

    toggleAdminPanel() {
        const adminPanel = document.getElementById('adminPanel');
        if (adminPanel) {
            adminPanel.classList.toggle('open');
        }
    }

    closeAdminPanel() {
        const adminPanel = document.getElementById('adminPanel');
        if (adminPanel) {
            adminPanel.classList.remove('open');
        }
    }

    handleKicked(message) {
        this.showError(message);
        setTimeout(() => {
            window.location.href = '/login.php';
        }, 3000);
    }

    logout() {
        fetch('/api/logout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                csrf_token: this.getCSRFToken()
            })
        })
        .then(() => {
            window.location.href = '/login.php';
        })
        .catch(error => {
            console.error('Logout error:', error);
            window.location.href = '/login.php';
        });
    }

    showError(message) {
        // Create or update error message element
        let errorElement = document.getElementById('errorMessage');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.id = 'errorMessage';
            errorElement.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #dc3545;
                color: white;
                padding: 15px;
                border-radius: 8px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                z-index: 1001;
                max-width: 300px;
            `;
            document.body.appendChild(errorElement);
        }

        errorElement.textContent = message;
        errorElement.style.display = 'block';

        setTimeout(() => {
            errorElement.style.display = 'none';
        }, 5000);
    }

    scrollToBottom() {
        const messagesContainer = document.getElementById('messagesContainer');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Ping server periodically to keep connection alive
    startPing() {
        setInterval(() => {
            if (this.isConnected) {
                this.send({ type: 'ping' });
            }
        }, 30000); // Ping every 30 seconds
    }
}

// Initialize chat client when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('chatContainer')) {
        const chatClient = new ChatClient();
        chatClient.startPing();
        
        // Make chatClient globally available for debugging
        window.chatClient = chatClient;
    }
});

