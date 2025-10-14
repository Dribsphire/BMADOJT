<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../index.php");
    exit();
}
require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Services\MessageService;
use App\Utils\Database;

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check authentication and authorization
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('instructor')) {
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
$messagingUsers = $messageService->getMessagingUsers($user->id, 'instructor');

// Handle AJAX requests
if (isset($_GET['action'])) {
    // Clear any output buffer and ensure clean JSON response
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    try {
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
                echo json_encode(['error' => 'Invalid action']);
                exit;
        }
    } catch (Exception $e) {
        error_log("AJAX Error: " . $e->getMessage());
        echo json_encode(['error' => 'Server error']);
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
        }
        
        .conversation-item {
            padding: 1rem;
            border-bottom: 1px solid #f1f3f4;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .profile-picture {
            width: 45px;
            height: 45px;
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
        }
        
        .conversation-item:hover {
            background-color: #f8f9fa;
        }
        
        .conversation-item.active {
            background-color:rgb(223, 219, 219);
            color: white;
        }
        
        .conversation-item.active .text-muted {
            color: rgba(255, 255, 255, 0.8) !important;
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

    <?php include 'teacher-sidebar.php'; ?>

    <main>
    <?php include 'navigation-header.php'; ?>
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
                                    
                                    <!-- Students List -->
                                    <div id="conversationsList" style="margin-left: 20px;">
                                        <h6 class="mb-3" style="margin-top: 10px;">Students</h6>
                                        <?php if (empty($messagingUsers)): ?>
                                            <div class="text-center py-4">
                                                <i class="bi bi-person-x fs-1 text-muted"></i>
                                                <p class="text-muted mt-2">No students available</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($messagingUsers as $student): ?>
                                                <div class="conversation-item" onclick="openConversation(<?= $student['id'] ?>, event)">
                                                    <?php 
                                                    // Get profile picture path
                                                    $profilePicture = '../uploads/profile_pictures/' . $student['id'] . '.jpg';
                                                    $defaultPicture = '../assets/images/default-avatar.svg';
                                                    $picturePath = file_exists($profilePicture) ? $profilePicture : $defaultPicture;
                                                    ?>
                                                    <img src="<?= $picturePath ?>" 
                                                         alt="<?= htmlspecialchars($student['full_name']) ?>" 
                                                         class="profile-picture"
                                                         onerror="this.src='<?= $defaultPicture ?>'">
                                                    <div class="conversation-content">
                                                        <h6 class="mb-1"><?= htmlspecialchars($student['full_name']) ?></h6>
                                                        <small class="text-muted d-block">
                                                            <?= ucfirst($student['role']) ?>
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
                                                    <i class="bi bi-send" style="color: white;"></i>
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


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/messaging.js"></script>
    
    <script>
        // Set current user ID for messaging system
        window.currentUserId = <?= $user->id ?>;
        
        // Simple JavaScript functions that don't conflict with messaging.js
        function refreshMessages() {
            console.log('refreshMessages called');
            location.reload();
        }
        
        function searchConversations() {
            console.log('searchConversations called');
            const searchInput = document.getElementById('searchInput');
            const filter = searchInput.value.toLowerCase();
            const conversationItems = document.querySelectorAll('.conversation-item');
            
            conversationItems.forEach(item => {
                const studentName = item.querySelector('h6').textContent.toLowerCase();
                if (studentName.includes(filter)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }
        
        function searchInChat() {
            console.log('searchInChat called');
            // This will be implemented later
        }
        
        function deleteConversation() {
            console.log('deleteConversation called');
            // This will be implemented later
        }
        
        // Debug: Verify functions are defined
        console.log('Functions defined:', {
            refreshMessages: typeof window.refreshMessages,
            openConversation: typeof window.openConversation,
            sendMessage: typeof window.sendMessage,
            loadConversation: typeof window.loadConversation
        });
    </script>
</body>
</html>