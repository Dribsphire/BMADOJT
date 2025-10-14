<?php

namespace App\Controllers;

use App\Services\MessageService;
use App\Utils\ActivityLogger;
use Exception;

/**
 * Message Controller
 * Handles messaging API endpoints
 */
class MessageController
{
    private MessageService $messageService;
    private ActivityLogger $activityLogger;
    
    public function __construct()
    {
        $this->messageService = new MessageService();
        $this->activityLogger = new ActivityLogger();
    }
    
    /**
     * Send a message
     */
    public function sendMessage(): array
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return ['success' => false, 'message' => 'Invalid request method'];
            }
            
            $senderId = $_SESSION['user_id'] ?? null;
            $recipientId = $_POST['recipient_id'] ?? null;
            $messageBody = $_POST['message_body'] ?? '';
            $replyToId = $_POST['reply_to_id'] ?? null;
            
            if (!$senderId) {
                return ['success' => false, 'message' => 'User not authenticated'];
            }
            
            if (!$recipientId) {
                return ['success' => false, 'message' => 'Recipient is required'];
            }
            
            $result = $this->messageService->sendMessage(
                (int)$senderId,
                (int)$recipientId,
                $messageBody,
                $replyToId ? (int)$replyToId : null
            );
            
            if ($result['success']) {
                // Log activity
                $this->activityLogger->logActivity(
                    'message_sent',
                    "Sent message to user ID: {$recipientId}"
                );
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("MessageController::sendMessage error: " . $e->getMessage());
            error_log("MessageController::sendMessage stack trace: " . $e->getTraceAsString());
            return ['success' => false, 'message' => 'An error occurred while sending the message: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get conversation
     */
    public function getConversation(): array
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $otherUserId = $_GET['user_id'] ?? null;
            $limit = $_GET['limit'] ?? 50;
            $offset = $_GET['offset'] ?? 0;
            
            if (!$userId || !$otherUserId) {
                return ['success' => false, 'message' => 'Missing required parameters'];
            }
            
            $messages = $this->messageService->getConversation(
                (int)$userId,
                (int)$otherUserId,
                (int)$limit,
                (int)$offset
            );
            
            // Mark conversation as read
            $this->messageService->markConversationAsRead(
                (int)$userId,
                (int)$otherUserId,
                (int)$userId
            );
            
            return [
                'success' => true,
                'messages' => $messages
            ];
            
        } catch (Exception $e) {
            error_log("MessageController::getConversation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while fetching messages'];
        }
    }
    
    /**
     * Get conversations list
     */
    public function getConversations(): array
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$userId) {
                return ['success' => false, 'message' => 'User not authenticated'];
            }
            
            $conversations = $this->messageService->getConversations((int)$userId);
            
            return [
                'success' => true,
                'conversations' => $conversations
            ];
            
        } catch (Exception $e) {
            error_log("MessageController::getConversations error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while fetching conversations'];
        }
    }
    
    /**
     * Mark message as read
     */
    public function markAsRead(): array
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return ['success' => false, 'message' => 'Invalid request method'];
            }
            
            $userId = $_SESSION['user_id'] ?? null;
            $messageId = $_POST['message_id'] ?? null;
            
            if (!$userId || !$messageId) {
                return ['success' => false, 'message' => 'Missing required parameters'];
            }
            
            $result = $this->messageService->markAsRead((int)$messageId, (int)$userId);
            
            return [
                'success' => $result,
                'message' => $result ? 'Message marked as read' : 'Failed to mark message as read'
            ];
            
        } catch (Exception $e) {
            error_log("MessageController::markAsRead error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while marking message as read'];
        }
    }
    
    /**
     * Get unread count
     */
    public function getUnreadCount(): array
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$userId) {
                return ['success' => false, 'message' => 'User not authenticated'];
            }
            
            $count = $this->messageService->getUnreadCount((int)$userId);
            
            return [
                'success' => true,
                'unread_count' => $count
            ];
            
        } catch (Exception $e) {
            error_log("MessageController::getUnreadCount error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while fetching unread count'];
        }
    }
    
    /**
     * Delete message
     */
    public function deleteMessage(): array
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return ['success' => false, 'message' => 'Invalid request method'];
            }
            
            $userId = $_SESSION['user_id'] ?? null;
            $messageId = $_POST['message_id'] ?? null;
            
            if (!$userId || !$messageId) {
                return ['success' => false, 'message' => 'Missing required parameters'];
            }
            
            $result = $this->messageService->deleteMessage((int)$messageId, (int)$userId);
            
            if ($result) {
                $this->activityLogger->logActivity(
                    'message_deleted',
                    "Deleted message ID: {$messageId}"
                );
            }
            
            return [
                'success' => $result,
                'message' => $result ? 'Message deleted' : 'Failed to delete message'
            ];
            
        } catch (Exception $e) {
            error_log("MessageController::deleteMessage error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while deleting message'];
        }
    }
    
    /**
     * Search messages
     */
    public function searchMessages(): array
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $query = $_GET['q'] ?? '';
            $limit = $_GET['limit'] ?? 20;
            
            if (!$userId) {
                return ['success' => false, 'message' => 'User not authenticated'];
            }
            
            if (empty($query)) {
                return ['success' => false, 'message' => 'Search query is required'];
            }
            
            $messages = $this->messageService->searchMessages((int)$userId, $query, (int)$limit);
            
            return [
                'success' => true,
                'messages' => $messages
            ];
            
        } catch (Exception $e) {
            error_log("MessageController::searchMessages error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while searching messages'];
        }
    }
    
    /**
     * Get messaging users
     */
    public function getMessagingUsers(): array
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $userRole = $_SESSION['role'] ?? null;
            
            if (!$userId || !$userRole) {
                return ['success' => false, 'message' => 'User not authenticated'];
            }
            
            $users = $this->messageService->getMessagingUsers((int)$userId, $userRole);
            
            return [
                'success' => true,
                'users' => $users
            ];
            
        } catch (Exception $e) {
            error_log("MessageController::getMessagingUsers error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while fetching users'];
        }
    }
}
