/**
 * Messaging JavaScript
 * OJT Route - Private messaging system
 */

class MessagingSystem {
    constructor() {
        console.log('MessagingSystem constructor called');
        this.currentConversationId = null;
        this.messageInterval = null;
        this.isInstructor = window.location.pathname.includes('/instructor/');
        this.baseUrl = this.isInstructor ? 'messages.php' : 'messages.php';
        
        this.init();
        console.log('MessagingSystem initialized');
    }
    
    init() {
        // Auto-refresh conversations every 30 seconds
        this.messageInterval = setInterval(() => this.refreshConversations(), 30000);
        
        // Set up event listeners
        this.setupEventListeners();
    }
    
    setupEventListeners() {
        // Message form submission
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', (e) => this.sendMessage(e));
        }
        
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => this.searchConversations(e.target.value));
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboardShortcuts(e));
    }
    
    handleKeyboardShortcuts(e) {
        // Ctrl/Cmd + Enter to send message
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const messageInput = document.getElementById('messageInput');
            if (messageInput && messageInput === document.activeElement) {
                this.sendMessage(e);
            }
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal.show');
            modals.forEach(modal => {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            });
        }
    }
    
    openConversation(userId, event = null) {
        console.log('openConversation called with userId:', userId);
        this.currentConversationId = userId;
        console.log('currentConversationId set to:', this.currentConversationId);
        
        // Update active conversation
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Add active class to clicked item
        if (event && event.currentTarget) {
            event.currentTarget.classList.add('active');
        } else {
            // Find the conversation item by userId
            const conversationItems = document.querySelectorAll('.conversation-item');
            conversationItems.forEach(item => {
                if (item.getAttribute('onclick') && item.getAttribute('onclick').includes(userId)) {
                    item.classList.add('active');
                }
            });
        }
        
        // Update chat header with user info
        const clickedItem = document.querySelector(`[onclick*="${userId}"]`);
        if (clickedItem) {
            const userName = clickedItem.querySelector('h6').textContent;
            const userRole = clickedItem.querySelector('small').textContent;
            const userAvatar = clickedItem.querySelector('.profile-picture').src;
            
            document.getElementById('chatUserName').textContent = userName;
            document.getElementById('chatUserRole').textContent = userRole;
            document.getElementById('chatUserAvatar').src = userAvatar;
        }
        
        // Show chat interface
        document.getElementById('chatHeader').style.display = 'block';
        document.getElementById('chatInput').style.display = 'block';
        document.getElementById('chatMessages').innerHTML = '<div class="text-center py-4"><i class="bi bi-hourglass-split"></i> Loading...</div>';
        
        // Load conversation
        this.loadConversation(userId);
    }
    
    async loadConversation(userId) {
        try {
            const response = await fetch(`${this.baseUrl}?action=get_conversation&user_id=${userId}`);
            const data = await response.json();
            
            if (data.success) {
                this.displayMessages(data.messages);
                this.updateChatHeader(data.messages);
            } else {
                this.showError('Failed to load conversation: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            this.showError('Failed to load conversation');
        }
    }
    
    displayMessages(messages) {
        const messagesContainer = document.getElementById('chatMessages');
        messagesContainer.innerHTML = '';
        
        if (messages.length === 0) {
            messagesContainer.innerHTML = '<div class="text-center py-4"><i class="bi bi-chat-dots"></i><p class="mt-2">No messages yet</p></div>';
            return;
        }
        
        messages.reverse().forEach(message => {
            const messageDiv = document.createElement('div');
            // Ensure both values are numbers for proper comparison
            const senderId = parseInt(message.sender_id);
            const currentUserId = parseInt(window.currentUserId);
            const isCurrentUser = senderId === currentUserId;
            messageDiv.className = `message ${isCurrentUser ? 'sent' : 'received'}`;
            
            const bubble = document.createElement('div');
            bubble.className = 'message-bubble';
            bubble.innerHTML = `
                <div>${this.escapeHtml(message.message_body)}</div>
                <div class="message-time">${this.formatTime(message.created_at)}</div>
            `;
            
            messageDiv.appendChild(bubble);
            messagesContainer.appendChild(messageDiv);
        });
        
        // Scroll to bottom
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    updateChatHeader(messages) {
        if (messages.length > 0) {
            const message = messages[0];
            const userName = message.sender_id == window.currentUserId ? message.recipient_name : message.sender_name;
            const userRole = message.sender_id == window.currentUserId ? message.recipient_role : message.sender_role;
            
            document.getElementById('chatUserName').textContent = userName;
            document.getElementById('chatUserRole').textContent = this.ucfirst(userRole);
        }
    }
    
    async sendMessage(event) {
        if (event) {
            event.preventDefault();
        }
        
        console.log('sendMessage called, currentConversationId:', this.currentConversationId);
        
        // Prevent duplicate submissions
        if (this.isSending) {
            console.log('Message already being sent, ignoring duplicate call');
            return;
        }
        this.isSending = true;
        
        if (!this.currentConversationId) {
            console.log('No conversation selected, showing error');
            this.showError('Please select a conversation');
            this.isSending = false;
            return;
        }
        
        const messageInput = document.getElementById('messageInput');
        const messageText = messageInput.value.trim();
        
        if (!messageText) {
            this.isSending = false;
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('recipient_id', this.currentConversationId);
            formData.append('message_body', messageText);
            
            const response = await fetch(`${this.baseUrl}?action=send_message`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                messageInput.value = '';
                this.loadConversation(this.currentConversationId);
                this.refreshConversations();
            } else {
                this.showError('Failed to send message: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            this.showError('Failed to send message');
        } finally {
            this.isSending = false;
        }
    }
    
    startNewConversation() {
        const modal = new bootstrap.Modal(document.getElementById('newMessageModal'));
        modal.show();
    }
    
    async sendNewMessage() {
        const recipientId = document.getElementById('recipientSelect').value;
        const messageText = document.getElementById('newMessageText').value.trim();
        
        if (!recipientId || !messageText) {
            this.showError('Please select a recipient and enter a message');
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('recipient_id', recipientId);
            formData.append('message_body', messageText);
            
            const response = await fetch(`${this.baseUrl}?action=send_message`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('newMessageModal')).hide();
                document.getElementById('recipientSelect').value = '';
                document.getElementById('newMessageText').value = '';
                this.refreshConversations();
                this.openConversation(recipientId);
            } else {
                this.showError('Failed to send message: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            this.showError('Failed to send message');
        }
    }
    
    async refreshConversations() {
        try {
            const response = await fetch(`${this.baseUrl}?action=get_conversations`);
            const data = await response.json();
            
            if (data.success) {
                this.updateConversationsList(data.conversations);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }
    
    updateConversationsList(conversations) {
        const container = document.getElementById('conversationsList');
        
        if (conversations.length === 0) {
            container.innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-chat-dots fs-1 text-muted"></i>
                    <p class="text-muted mt-2">No conversations yet</p>
                    <button class="btn btn-primary btn-sm" onclick="messagingSystem.startNewConversation()">
                        Start a conversation
                    </button>
                </div>
            `;
            return;
        }
        
        container.innerHTML = conversations.map(conv => `
            <div class="conversation-item" onclick="messagingSystem.openConversation(${conv.other_user_id})">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${this.escapeHtml(conv.other_user_name)}</h6>
                        <small class="text-muted d-block">${this.escapeHtml(conv.latest_message)}</small>
                        <small class="text-muted">${this.formatTime(conv.latest_message_time)}</small>
                    </div>
                    ${conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : ''}
                </div>
            </div>
        `).join('');
    }
    
    refreshMessages() {
        if (this.currentConversationId) {
            this.loadConversation(this.currentConversationId);
        }
        this.refreshConversations();
    }
    
    async searchConversations(query) {
        if (!query.trim()) {
            this.refreshConversations();
            return;
        }
        
        try {
            const response = await fetch(`${this.baseUrl}?action=search&q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success) {
                this.displaySearchResults(data.messages);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }
    
    displaySearchResults(messages) {
        const container = document.getElementById('conversationsList');
        
        if (messages.length === 0) {
            container.innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-search fs-1 text-muted"></i>
                    <p class="text-muted mt-2">No messages found</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = messages.map(message => `
            <div class="conversation-item" onclick="messagingSystem.openConversation(${message.sender_id == window.currentUserId ? message.recipient_id : message.sender_id})">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${this.escapeHtml(message.sender_id == window.currentUserId ? message.recipient_name : message.sender_name)}</h6>
                        <small class="text-muted d-block">${this.escapeHtml(message.message_body)}</small>
                        <small class="text-muted">${this.formatTime(message.created_at)}</small>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    searchInChat() {
        // Implement in-chat search
        console.log('Searching in chat');
    }
    
    deleteConversation() {
        if (confirm('Are you sure you want to delete this conversation?')) {
            // Implement delete functionality
            console.log('Deleting conversation');
        }
    }
    
    showError(message) {
        // Create a better error display system
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger alert-dismissible fade show position-fixed';
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 5000);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) { // Less than 1 minute
            return 'Just now';
        } else if (diff < 3600000) { // Less than 1 hour
            return Math.floor(diff / 60000) + 'm ago';
        } else if (diff < 86400000) { // Less than 1 day
            return Math.floor(diff / 3600000) + 'h ago';
        } else {
            return date.toLocaleDateString();
        }
    }
    
    ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    destroy() {
        if (this.messageInterval) {
            clearInterval(this.messageInterval);
        }
    }
}

// Initialize messaging system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded event fired');
    if (!window.messagingSystem) {
        window.messagingSystem = new MessagingSystem();
        console.log('messagingSystem created:', !!window.messagingSystem);
    } else {
        console.log('messagingSystem already exists, skipping initialization');
    }
});

// Global functions for backward compatibility
function openConversation(userId, event = null) {
    console.log('Global openConversation called with userId:', userId);
    console.log('messagingSystem exists:', !!window.messagingSystem);
    
    if (window.messagingSystem) {
        window.messagingSystem.openConversation(userId, event);
    } else {
        console.error('messagingSystem not initialized yet');
        // Try to initialize it
        if (!window.messagingSystem) {
            window.messagingSystem = new MessagingSystem();
            window.messagingSystem.openConversation(userId, event);
        }
    }
}

function sendMessage(event) {
    console.log('Global sendMessage called');
    console.log('messagingSystem exists:', !!window.messagingSystem);
    if (window.messagingSystem) {
        window.messagingSystem.sendMessage(event);
    } else {
        console.error('messagingSystem not available for sendMessage');
    }
}

function startNewConversation() {
    if (window.messagingSystem) {
        window.messagingSystem.startNewConversation();
    }
}

function sendNewMessage() {
    if (window.messagingSystem) {
        window.messagingSystem.sendNewMessage();
    }
}

function refreshMessages() {
    if (window.messagingSystem) {
        window.messagingSystem.refreshMessages();
    }
}

function searchConversations() {
    if (window.messagingSystem) {
        const query = document.getElementById('searchInput').value;
        window.messagingSystem.searchConversations(query);
    }
}

function searchInChat() {
    if (window.messagingSystem) {
        window.messagingSystem.searchInChat();
    }
}

function deleteConversation() {
    if (window.messagingSystem) {
        window.messagingSystem.deleteConversation();
    }
}

function loadConversation(userId) {
    if (window.messagingSystem) {
        window.messagingSystem.loadConversation(userId);
    }
}
