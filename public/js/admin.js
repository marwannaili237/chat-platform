class AdminPanel {
    constructor(chatClient) {
        this.chatClient = chatClient;
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadUsers();
        this.loadStats();
    }

    bindEvents() {
        // Broadcast message form
        const broadcastForm = document.getElementById('broadcastForm');
        if (broadcastForm) {
            broadcastForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.sendBroadcast();
            });
        }

        // User management actions
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('ban-user-btn')) {
                const userId = e.target.dataset.userId;
                this.banUser(userId);
            } else if (e.target.classList.contains('unban-user-btn')) {
                const userId = e.target.dataset.userId;
                this.unbanUser(userId);
            } else if (e.target.classList.contains('kick-user-btn')) {
                const userId = e.target.dataset.userId;
                this.kickUser(userId);
            } else if (e.target.classList.contains('delete-message-btn')) {
                const messageId = e.target.dataset.messageId;
                this.deleteMessage(messageId);
            }
        });

        // Refresh buttons
        const refreshUsersBtn = document.getElementById('refreshUsers');
        if (refreshUsersBtn) {
            refreshUsersBtn.addEventListener('click', () => {
                this.loadUsers();
            });
        }

        const refreshStatsBtn = document.getElementById('refreshStats');
        if (refreshStatsBtn) {
            refreshStatsBtn.addEventListener('click', () => {
                this.loadStats();
            });
        }
    }

    loadUsers() {
        this.apiCall('/api/admin/users.php')
            .then(data => {
                if (data.success) {
                    this.displayUsers(data.users);
                } else {
                    this.showError(data.message);
                }
            })
            .catch(error => {
                console.error('Error loading users:', error);
                this.showError('Failed to load users');
            });
    }

    loadStats() {
        this.apiCall('/api/admin/stats.php')
            .then(data => {
                if (data.success) {
                    this.displayStats(data.stats);
                } else {
                    this.showError(data.message);
                }
            })
            .catch(error => {
                console.error('Error loading stats:', error);
                this.showError('Failed to load statistics');
            });
    }

    displayUsers(users) {
        const userManagement = document.getElementById('userManagement');
        if (!userManagement) return;

        userManagement.innerHTML = '';

        users.forEach(user => {
            const userItem = document.createElement('div');
            userItem.className = 'user-management-item';
            
            const isOnline = this.chatClient.onlineUsers.has(user.id);
            const statusBadge = isOnline ? '<span class="text-success">●</span>' : '<span class="text-danger">●</span>';
            
            userItem.innerHTML = `
                <div>
                    <strong>${this.escapeHtml(user.username)}</strong>
                    ${statusBadge}
                    ${user.is_admin ? '<span class="text-danger">(Admin)</span>' : ''}
                    ${user.is_banned ? '<span class="text-danger">(Banned)</span>' : ''}
                    <br>
                    <small>Joined: ${new Date(user.created_at).toLocaleDateString()}</small>
                    <br>
                    <small>Last seen: ${new Date(user.last_seen).toLocaleString()}</small>
                </div>
                <div>
                    ${!user.is_admin ? this.getUserActionButtons(user) : ''}
                </div>
            `;
            
            userManagement.appendChild(userItem);
        });
    }

    getUserActionButtons(user) {
        const buttons = [];
        
        if (user.is_banned) {
            buttons.push(`<button class="btn btn-small btn-secondary unban-user-btn" data-user-id="${user.id}">Unban</button>`);
        } else {
            buttons.push(`<button class="btn btn-small btn-danger ban-user-btn" data-user-id="${user.id}">Ban</button>`);
            buttons.push(`<button class="btn btn-small btn-secondary kick-user-btn" data-user-id="${user.id}">Kick</button>`);
        }
        
        return buttons.join(' ');
    }

    displayStats(stats) {
        const statsContainer = document.getElementById('statsContainer');
        if (!statsContainer) return;

        statsContainer.innerHTML = `
            <div class="admin-section">
                <h4>Server Statistics</h4>
                <p><strong>Total Users:</strong> ${stats.total_users}</p>
                <p><strong>Online Users:</strong> ${stats.online_users}</p>
                <p><strong>Total Messages:</strong> ${stats.total_messages}</p>
                <p><strong>Messages Today:</strong> ${stats.messages_today}</p>
                <p><strong>Banned Users:</strong> ${stats.banned_users}</p>
            </div>
        `;
    }

    sendBroadcast() {
        const messageInput = document.getElementById('broadcastMessage');
        const message = messageInput.value.trim();
        
        if (!message) {
            this.showError('Please enter a message');
            return;
        }

        this.apiCall('/api/admin/broadcast.php', {
            message: message
        })
        .then(data => {
            if (data.success) {
                messageInput.value = '';
                this.showSuccess('Broadcast message sent');
            } else {
                this.showError(data.message);
            }
        })
        .catch(error => {
            console.error('Error sending broadcast:', error);
            this.showError('Failed to send broadcast');
        });
    }

    banUser(userId) {
        if (!confirm('Are you sure you want to ban this user?')) {
            return;
        }

        this.apiCall('/api/admin/ban-user.php', {
            user_id: userId
        })
        .then(data => {
            if (data.success) {
                this.showSuccess('User banned successfully');
                this.loadUsers();
            } else {
                this.showError(data.message);
            }
        })
        .catch(error => {
            console.error('Error banning user:', error);
            this.showError('Failed to ban user');
        });
    }

    unbanUser(userId) {
        this.apiCall('/api/admin/unban-user.php', {
            user_id: userId
        })
        .then(data => {
            if (data.success) {
                this.showSuccess('User unbanned successfully');
                this.loadUsers();
            } else {
                this.showError(data.message);
            }
        })
        .catch(error => {
            console.error('Error unbanning user:', error);
            this.showError('Failed to unban user');
        });
    }

    kickUser(userId) {
        if (!confirm('Are you sure you want to kick this user?')) {
            return;
        }

        this.apiCall('/api/admin/kick-user.php', {
            user_id: userId
        })
        .then(data => {
            if (data.success) {
                this.showSuccess('User kicked successfully');
            } else {
                this.showError(data.message);
            }
        })
        .catch(error => {
            console.error('Error kicking user:', error);
            this.showError('Failed to kick user');
        });
    }

    deleteMessage(messageId) {
        if (!confirm('Are you sure you want to delete this message?')) {
            return;
        }

        this.apiCall('/api/admin/delete-message.php', {
            message_id: messageId
        })
        .then(data => {
            if (data.success) {
                this.showSuccess('Message deleted successfully');
                // Remove message from UI
                const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
                if (messageElement) {
                    messageElement.remove();
                }
            } else {
                this.showError(data.message);
            }
        })
        .catch(error => {
            console.error('Error deleting message:', error);
            this.showError('Failed to delete message');
        });
    }

    apiCall(url, data = null) {
        const options = {
            method: data ? 'POST' : 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (data) {
            options.body = JSON.stringify({
                ...data,
                csrf_token: this.getCSRFToken()
            });
        }

        return fetch(url, options)
            .then(response => response.json());
    }

    getCSRFToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showNotification(message, type) {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px;
            border-radius: 8px;
            color: white;
            z-index: 1002;
            max-width: 300px;
            background: ${type === 'error' ? '#dc3545' : '#28a745'};
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        `;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize admin panel when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('adminPanel') && window.chatClient) {
        const adminPanel = new AdminPanel(window.chatClient);
        window.adminPanel = adminPanel;
    }
});

