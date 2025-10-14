<?php

namespace App\Controllers;

use App\Services\OverdueService;
use App\Services\EmailService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;
use PDO;
use Exception;

class OverdueController
{
    private OverdueService $overdueService;
    private EmailService $emailService;
    private AuthMiddleware $authMiddleware;
    private PDO $pdo;

    public function __construct()
    {
        $this->overdueService = new OverdueService();
        $this->emailService = new EmailService();
        $this->authMiddleware = new AuthMiddleware();
        $this->pdo = Database::getInstance();
    }

    /**
     * Get overdue documents for instructor
     */
    public function getOverdueDocumentsForInstructor(int $instructorId): array
    {
        // Check authentication
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }

        if (!$this->authMiddleware->requireRole('instructor')) {
            $this->authMiddleware->redirectToUnauthorized();
        }

        // Get instructor's section
        $stmt = $this->pdo->prepare("SELECT section_id FROM users WHERE id = ?");
        $stmt->execute([$instructorId]);
        $instructor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$instructor || !$instructor['section_id']) {
            throw new Exception('Instructor must be assigned to a section');
        }

        return $this->overdueService->getOverdueDocumentsForSection($instructor['section_id']);
    }

    /**
     * Get overdue documents for student
     */
    public function getOverdueDocumentsForStudent(int $studentId): array
    {
        // Check authentication
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }

        if (!$this->authMiddleware->requireRole('student')) {
            $this->authMiddleware->redirectToUnauthorized();
        }

        return $this->overdueService->getOverdueDocumentsForStudent($studentId);
    }

    /**
     * Get overdue statistics
     */
    public function getOverdueStatistics(): array
    {
        // Check authentication
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }

        if (!$this->authMiddleware->requireRole('instructor')) {
            $this->authMiddleware->redirectToUnauthorized();
        }

        return $this->overdueService->getOverdueStatistics();
    }

    /**
     * Send overdue notifications
     */
    public function sendOverdueNotifications(): array
    {
        try {
            $overdueDocuments = $this->overdueService->getOverdueDocumentsForNotification();
            $notificationsSent = 0;
            $errors = [];

            foreach ($overdueDocuments as $document) {
                try {
                    // Send notification to student
                    $this->emailService->sendOverdueNotification(
                        $document['student_email'],
                        $document['student_name'],
                        $document['document_name'],
                        $document['deadline']
                    );
                    $notificationsSent++;
                } catch (Exception $e) {
                    $errors[] = "Failed to send notification to {$document['student_name']}: " . $e->getMessage();
                }
            }

            return [
                'success' => true,
                'notifications_sent' => $notificationsSent,
                'errors' => $errors
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Mark overdue document as resolved
     */
    public function markOverdueAsResolved(int $documentId, int $studentId, int $instructorId): array
    {
        try {
            // Check authentication
            if (!$this->authMiddleware->check()) {
                $this->authMiddleware->redirectToLogin();
            }

            if (!$this->authMiddleware->requireRole('instructor')) {
                $this->authMiddleware->redirectToUnauthorized();
            }

            $success = $this->overdueService->markOverdueAsResolved($documentId, $studentId);

            if ($success) {
                // Log activity
                $this->logActivity($instructorId, 'resolve_overdue', "Resolved overdue document ID: {$documentId} for student ID: {$studentId}");

                return [
                    'success' => true,
                    'message' => 'Overdue document marked as resolved'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to mark document as resolved'
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get overdue report data
     */
    public function getOverdueReport(array $filters = []): array
    {
        // Check authentication
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }

        if (!$this->authMiddleware->requireRole('instructor')) {
            $this->authMiddleware->redirectToUnauthorized();
        }

        $overdueDocuments = $this->overdueService->getOverdueDocuments();
        $statistics = $this->overdueService->getOverdueStatistics();

        return [
            'documents' => $overdueDocuments,
            'statistics' => $statistics,
            'filters' => $filters
        ];
    }

    /**
     * Log activity
     */
    private function logActivity(int $userId, string $action, string $description): void
    {
        try {
            // Validate that the user exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            if (!$stmt->fetch()) {
                error_log("OverdueController: User ID {$userId} not found in users table, skipping activity log");
                return;
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $action, $description]);
        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}
