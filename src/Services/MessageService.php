<?php

namespace App\Services;

use App\Utils\Database;
use PDO;
use Exception;

/**
 * Message Service
 * Handles private messaging functionality
 */
class MessageService
{
    private PDO $pdo;
    
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    
    /**
     * Send a new message
     */
    public function sendMessage(int $senderId, int $recipientId, string $messageBody, ?int $replyToId = null): array
    {
        try {
            // Validate message content
            if (empty(trim($messageBody))) {
                return ['success' => false, 'message' => 'Message content cannot be empty'];
            }
            
            if (strlen($messageBody) > 1000) {
                return ['success' => false, 'message' => 'Message is too long (max 1000 characters)'];
            }
            
            // Check if recipient exists
            $stmt = $this->pdo->prepare("SELECT id, role FROM users WHERE id = ?");
            $stmt->execute([$recipientId]);
            $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$recipient) {
                return ['success' => false, 'message' => 'Recipient not found'];
            }
            
            // Insert message
            $stmt = $this->pdo->prepare("
                INSERT INTO messages (sender_id, recipient_id, message_body, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $senderId,
                $recipientId,
                trim($messageBody)
            ]);
            
            if ($result) {
                $messageId = $this->pdo->lastInsertId();
                return [
                    'success' => true, 
                    'message' => 'Message sent successfully',
                    'message_id' => $messageId
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to send message'];
            }
            
        } catch (Exception $e) {
            error_log("MessageService::sendMessage error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while sending the message'];
        }
    }
    
    /**
     * Get conversation between two users
     */
    public function getConversation(int $userId1, int $userId2, int $limit = 50, int $offset = 0): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    m.id,
                    m.sender_id,
                    m.recipient_id,
                    m.message_body,
                    m.is_read,
                    m.created_at,
                    sender.full_name as sender_name,
                    sender.role as sender_role,
                    recipient.full_name as recipient_name,
                    recipient.role as recipient_role
                FROM messages m
                JOIN users sender ON m.sender_id = sender.id
                JOIN users recipient ON m.recipient_id = recipient.id
                WHERE (m.sender_id = ? AND m.recipient_id = ?) 
                   OR (m.sender_id = ? AND m.recipient_id = ?)
                ORDER BY m.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute([$userId1, $userId2, $userId2, $userId1, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("MessageService::getConversation error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all conversations for a user
     */
    public function getConversations(int $userId): array
    {
        try {
            // Much simpler query to avoid parameter issues
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT
                    CASE 
                        WHEN m.sender_id = ? THEN m.recipient_id
                        ELSE m.sender_id
                    END as other_user_id,
                    u.full_name as other_user_name,
                    u.role as other_user_role,
                    (SELECT message_body FROM messages m2 
                     WHERE (m2.sender_id = ? AND m2.recipient_id = other_user_id) 
                        OR (m2.recipient_id = ? AND m2.sender_id = other_user_id)
                     ORDER BY m2.created_at DESC LIMIT 1) as latest_message,
                    (SELECT created_at FROM messages m2 
                     WHERE (m2.sender_id = ? AND m2.recipient_id = other_user_id) 
                        OR (m2.recipient_id = ? AND m2.sender_id = other_user_id)
                     ORDER BY m2.created_at DESC LIMIT 1) as latest_message_time,
                    (SELECT is_read FROM messages m2 
                     WHERE (m2.sender_id = ? AND m2.recipient_id = other_user_id) 
                        OR (m2.recipient_id = ? AND m2.sender_id = other_user_id)
                     ORDER BY m2.created_at DESC LIMIT 1) as latest_message_read,
                    (SELECT COUNT(*) FROM messages m2 
                     WHERE m2.recipient_id = ? AND m2.sender_id = other_user_id AND m2.is_read = 0) as unread_count
                FROM messages m
                JOIN users u ON (
                    CASE 
                        WHEN m.sender_id = ? THEN m.recipient_id
                        ELSE m.sender_id
                    END = u.id
                )
                WHERE m.sender_id = ? OR m.recipient_id = ?
                ORDER BY latest_message_time DESC
            ");
            
            $stmt->execute([
                $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("MessageService::getConversations error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark messages as read
     */
    public function markAsRead(int $messageId, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE messages 
                SET is_read = 1, read_at = NOW() 
                WHERE id = ? AND recipient_id = ? AND is_read = 0
            ");
            
            return $stmt->execute([$messageId, $userId]);
            
        } catch (Exception $e) {
            error_log("MessageService::markAsRead error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all messages in a conversation as read
     */
    public function markConversationAsRead(int $userId1, int $userId2, int $currentUserId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE messages 
                SET is_read = 1 
                WHERE ((sender_id = ? AND recipient_id = ?) 
                    OR (sender_id = ? AND recipient_id = ?))
                AND recipient_id = ? AND is_read = 0
            ");
            
            return $stmt->execute([$userId1, $userId2, $userId2, $userId1, $currentUserId]);
            
        } catch (Exception $e) {
            error_log("MessageService::markConversationAsRead error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread message count for a user
     */
    public function getUnreadCount(int $userId): int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM messages 
                WHERE recipient_id = ? AND is_read = 0
            ");
            
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log("MessageService::getUnreadCount error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Delete a message (soft delete)
     */
    public function deleteMessage(int $messageId, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE messages 
                SET deleted_at = NOW() 
                WHERE id = ? AND (sender_id = ? OR recipient_id = ?)
            ");
            
            return $stmt->execute([$messageId, $userId, $userId]);
            
        } catch (Exception $e) {
            error_log("MessageService::deleteMessage error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Search messages
     */
    public function searchMessages(int $userId, string $query, int $limit = 20): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    m.id,
                    m.sender_id,
                    m.recipient_id,
                    m.message_body,
                    m.created_at,
                    m.is_read,
                    sender.full_name as sender_name,
                    recipient.full_name as recipient_name
                FROM messages m
                JOIN users sender ON m.sender_id = sender.id
                JOIN users recipient ON m.recipient_id = recipient.id
                WHERE (m.sender_id = ? OR m.recipient_id = ?)
                AND m.message_body LIKE ?
                AND m.deleted_at IS NULL
                ORDER BY m.created_at DESC
                LIMIT ?
            ");
            
            $searchTerm = "%{$query}%";
            $stmt->execute([$userId, $userId, $searchTerm, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("MessageService::searchMessages error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user list for messaging (students and instructors)
     */
    public function getMessagingUsers(int $currentUserId, string $currentUserRole): array
    {
        try {
            if ($currentUserRole === 'student') {
                // Students can only message instructors
                $stmt = $this->pdo->prepare("
                    SELECT DISTINCT u.id, u.full_name, u.role, u.section_id, s.section_name
                    FROM users u
                    LEFT JOIN sections s ON u.section_id = s.id
                    WHERE u.id != ? AND u.role = 'instructor'
                    ORDER BY u.full_name
                ");
                $stmt->execute([$currentUserId]);
            } else {
                // Instructors can message students in their section and other instructors
                $stmt = $this->pdo->prepare("
                    SELECT DISTINCT u.id, u.full_name, u.role, u.section_id, s.section_name
                    FROM users u
                    LEFT JOIN sections s ON u.section_id = s.id
                    WHERE u.id != ? 
                    AND (u.role = 'instructor' 
                         OR (u.role = 'student' AND u.section_id = (
                             SELECT section_id FROM users WHERE id = ?
                         )))
                    ORDER BY u.role DESC, u.full_name
                ");
                $stmt->execute([$currentUserId, $currentUserId]);
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("MessageService::getMessagingUsers error: " . $e->getMessage());
            return [];
        }
    }
}
