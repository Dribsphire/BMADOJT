<?php

/**
 * Student Messages Page
 * OJT Route - Private messaging system for students
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Services\MessageService;
use App\Utils\Database;

// Start session
session_start();

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check authentication and authorization
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('student')) {
    $authMiddleware->redirectToUnauthorized();
}

// Get current user
$user = $authMiddleware->getCurrentUser();

// Initialize message service
$messageService = new MessageService();

// Get user's conversations
$conversations = $messageService->getConversations($user->id);

// Get unread count
$unreadCount = $messageService->getUnreadCount($user->id);

// Get messaging users
$messagingUsers = $messageService->getMessagingUsers($user->id, 'student');

// Handle AJAX requests
if (isset($_GET['action'])) {
    try {
        // Ensure no output before this
        ob_clean();
        header('Content-Type: application/json');
        
        // Debug: Log the action being processed
        error_log("AJAX Action: " . $_GET['action']);
        
        $controller = new App\Controllers\MessageController();
        
        switch ($_GET['action']) {
            case 'get_conversation':
                echo json_encode($controller->getConversation());
                exit;
            case 'send_message':
                echo json_encode($controller->sendMessage());
                exit;
            case 'get_unread_count':
                echo json_encode($controller->getUnreadCount());
                exit;
            case 'mark_read':
                echo json_encode($controller->markAsRead());
                exit;
            case 'delete_message':
                echo json_encode($controller->deleteMessage());
                exit;
            case 'search':
                echo json_encode($controller->searchMessages());
                exit;
            case 'get_users':
                echo json_encode($controller->getMessagingUsers());
                exit;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit;
        }
    } catch (Exception $e) {
        // Ensure clean JSON response even on errors
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <script type="text/javascript" src="../js/sidebarSlide.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --chmsu-green: #0ea539;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        .navbar {
            background-color: var(--chmsu-green) !important;
            color: white;
        }
        
        .messages-container {
            height: calc(100vh - 120px);
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .conversations-sidebar {
            background: white;
            border-right: 1px solid #dee2e6;
            height: 100%;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .search-container {
            padding: 1rem;
            border-bottom: 1px solid #f1f3f4;
            flex-shrink: 0;
        }
        
        #conversationsList {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }
        
        .conversation-item {
            padding: 0.75rem;
            border-bottom: 1px solid #f1f3f4;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 0.5rem;
            border-radius: 0.5rem;
        }
        
        .profile-picture {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e9ecef;
            flex-shrink: 0;
        }
        
        .conversation-item.active .profile-picture {
            border-color: white;
        }
        
        .conversation-content {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }
        
        .conversation-content h6 {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversation-content small {
            font-size: 0.75rem;
            line-height: 1.2;
        }
        
        .conversation-item:hover {
            background-color: #f8f9fa;
        }
        
        .conversation-item.active {
            background-color: var(--chmsu-green);
            color: white;
        }
        
        .conversation-item.active .text-muted {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .conversations-sidebar {
                height: 300px;
                border-right: none;
                border-bottom: 1px solid #dee2e6;
            }
            
            .conversation-item {
                padding: 0.5rem;
                margin-bottom: 0.25rem;
            }
            
            .profile-picture {
                width: 35px;
                height: 35px;
            }
        }
        
        .unread-badge {
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 100%;
            max-height: calc(100vh - 120px);
        }
        
        .chat-header {
            background: white;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: #f8f9fa;
            max-height: calc(100vh - 200px);
            min-height: 300px;
        }
        
        .message {
            margin-bottom: 1rem;
            display: grid !important;
            width: 100%;
        }
        
        .message.sent {
            justify-items: end !important;
        }
        
        .message.received {
            justify-items: start !important;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            position: relative;
        }
        
        .message.sent .message-bubble {
            background-color: white;
            color: #333;
            border: 1px solid #dee2e6;
            border-bottom-right-radius: 0.25rem;
        }
        
        .message.received .message-bubble {
            background-color: white;
            color: #333;
            border: 1px solid #dee2e6;
            border-bottom-left-radius: 0.25rem;
        }
        
        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }
        
        .message-status {
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
        
        .chat-input {
            background: white;
            border-top: 1px solid #dee2e6;
            padding: 1rem;
        }
        
        .search-container {
            background: white;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem;
        }
        
        .no-conversation {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6c757d;
            flex-direction: column;
        }
        
        .btn-primary {
            background-color: var(--chmsu-green);
            border-color: var(--chmsu-green);
        }
        
        .btn-primary:hover {
            background-color: #0d8a2f;
            border-color: #0d8a2f;
        }
    </style>
</head>
<body>

    <?php include 'student-sidebar.php'; ?>

    <main>
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">
                                <i class="bi bi-chat-dots me-2"></i>Messages
                                <?php if ($unreadCount > 0): ?>
                                    <span class="badge bg-danger ms-2"><?= $unreadCount ?></span>
                                <?php endif; ?>
                            </h2>
                            <p class="text-muted mb-0">Send and receive private messages</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary" onclick="refreshMessages()">
                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                            </button>
                            <button class="btn btn-primary" onclick="startNewConversation()">
                                <i class="bi bi-plus-circle me-1"></i>New Message
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages Interface -->
            <div class="row">
                <div class="col-12">
                    <div class="messages-container">
                        <div class="row h-100">
                            <!-- Conversations Sidebar -->
                            <div class="col-md-4 p-0">
                                <div class="conversations-sidebar">
                                    <!-- Search -->
                                    <div class="search-container">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="searchInput" placeholder="Search conversations...">
                                            <button class="btn btn-outline-secondary" type="button" onclick="searchConversations()">
                                                <i class="bi bi-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Instructors List -->
                                    <div id="conversationsList">
                                        <h6 class="mb-3">Your Instructors</h6>
                                        <?php if (empty($messagingUsers)): ?>
                                            <div class="text-center py-4">
                                                <i class="bi bi-person-x fs-1 text-muted"></i>
                                                <p class="text-muted mt-2">No instructors available</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($messagingUsers as $instructor): ?>
                                                <div class="conversation-item" onclick="openConversation(<?= $instructor['id'] ?>, event)">
                                                    <?php 
                                                    // Get profile picture path
                                                    $profilePicture = '../uploads/profile_pictures/' . $instructor['id'] . '.jpg';
                                                    $defaultPicture = '../assets/images/default-avatar.svg';
                                                    $picturePath = file_exists($profilePicture) ? $profilePicture : $defaultPicture;
                                                    ?>
                                                    <img src="<?= $picturePath ?>" 
                                                         alt="<?= htmlspecialchars($instructor['full_name']) ?>" 
                                                         class="profile-picture"
                                                         onerror="this.src='<?= $defaultPicture ?>'">
                                                    <div class="conversation-content">
                                                        <h6 class="mb-1"><?= htmlspecialchars($instructor['full_name']) ?></h6>
                                                        <small class="text-muted d-block">
                                                            <?= ucfirst($instructor['role']) ?>
                                                        </small>
                                                        <small class="text-muted">
                                                            Click to start conversation
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Chat Area -->
                            <div class="col-md-8 p-0">
                                <div class="chat-container">
                                    <!-- Chat Header -->
                                    <div class="chat-header" id="chatHeader" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center gap-3">
                                                <img id="chatUserAvatar" src="../assets/images/default-avatar.svg" 
                                                     alt="User Avatar" class="profile-picture" style="width: 40px; height: 40px;">
                                                <div>
                                                    <h5 class="mb-0" id="chatUserName">Select a conversation</h5>
                                                    <small class="text-muted" id="chatUserRole"></small>
                                                </div>
                                            </div>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-secondary" onclick="searchInChat()">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary" onclick="deleteConversation()">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Messages Area -->
                                    <div class="chat-messages" id="chatMessages">
                                        <div class="no-conversation">
                                            <i class="bi bi-chat-dots fs-1 text-muted"></i>
                                            <h5 class="mt-3">Select a conversation</h5>
                                            <p class="text-muted">Choose a conversation from the sidebar to start messaging</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Message Input -->
                                    <div class="chat-input" id="chatInput" style="display: none;">
                                        <form id="messageForm" onsubmit="sendMessage(event)">
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="messageInput" placeholder="Type a message..." required>
                                                <button class="btn btn-primary" type="submit">
                                                    <i class="bi bi-send"></i>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- New Message Modal -->
    <div class="modal fade" id="newMessageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="recipientSelect" class="form-label">Send to:</label>
                        <select class="form-select" id="recipientSelect" required>
                            <option value="">Select a recipient...</option>
                            <?php foreach ($messagingUsers as $user): ?>
                                <option value="<?= $user['id'] ?>">
                                    <?= htmlspecialchars($user['full_name']) ?> 
                                    (<?= ucfirst($user['role']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="newMessageText" class="form-label">Message:</label>
                        <textarea class="form-control" id="newMessageText" rows="4" placeholder="Type your message..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="sendNewMessage()">Send Message</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/messaging.js"></script>
    
    <script>
        // Set global variables for JavaScript
        window.currentUserId = <?= json_encode($user['id']) ?>;
        
        let currentConversationId = null;
        let messageInterval = null;
        
        // Define ALL functions FIRST and immediately
        window.startNewConversation = function() {
            console.log('startNewConversation called');
            const modalElement = document.getElementById('newMessageModal');
            console.log('Modal element found:', modalElement);
            if (modalElement) {
                try {
                    const modal = new bootstrap.Modal(modalElement);
                    console.log('Modal created:', modal);
                    modal.show();
                    console.log('Modal show called');
                } catch (error) {
                    console.error('Error creating modal:', error);
                    // Fallback: show modal using jQuery if available
                    if (typeof $ !== 'undefined') {
                        $('#newMessageModal').modal('show');
                        console.log('Fallback modal show called');
                    } else {
                        // Last resort: show modal using data attributes
                        modalElement.setAttribute('data-bs-toggle', 'modal');
                        modalElement.setAttribute('data-bs-target', '#newMessageModal');
                        modalElement.click();
                        console.log('Data attribute fallback called');
                    }
                }
            } else {
                console.error('Modal element not found');
            }
        };
        
        window.refreshMessages = function() {
            console.log('refreshMessages called');
            loadConversations();
        };
        
        // openConversation is now handled by messaging.js
        
        // Debug: Verify functions are defined
        console.log('Functions defined:', {
            startNewConversation: typeof window.startNewConversation,
            refreshMessages: typeof window.refreshMessages,
            openConversation: typeof window.openConversation
        });
    </script>
</body>
</html>