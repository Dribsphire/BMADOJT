<?php

namespace App\Controllers;

use App\Services\DocumentDashboardService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;
use PDO;
use Exception;

class DocumentDashboardController
{
    private DocumentDashboardService $dashboardService;
    private AuthMiddleware $authMiddleware;
    private PDO $pdo;

    public function __construct()
    {
        $this->dashboardService = new DocumentDashboardService();
        $this->authMiddleware = new AuthMiddleware();
        $this->pdo = Database::getInstance();
    }

    /**
     * Get document dashboard data for instructor
     */
    public function getDashboardData(int $instructorId): array
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

        return $this->dashboardService->getDashboardSummary($instructor['section_id']);
    }

    /**
     * Get document analytics for instructor
     */
    public function getDocumentAnalytics(int $instructorId, int $days = 30): array
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

        return $this->dashboardService->getDocumentAnalytics($instructor['section_id'], $days);
    }

    /**
     * Get document monitoring data
     */
    public function getDocumentMonitoring(int $instructorId): array
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

        return $this->dashboardService->getDocumentMonitoring($instructor['section_id']);
    }

    /**
     * Generate compliance report
     */
    public function generateComplianceReport(int $instructorId): array
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

        return $this->dashboardService->generateComplianceReport($instructor['section_id']);
    }

    /**
     * Export document data
     */
    public function exportDocumentData(int $instructorId, string $format = 'csv'): array
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

        $data = $this->dashboardService->exportDocumentData($instructor['section_id'], $format);
        
        return [
            'success' => true,
            'data' => $data,
            'format' => $format,
            'filename' => 'document_report_' . date('Y-m-d_H-i-s') . '.' . $format
        ];
    }

    /**
     * Get real-time document status updates
     */
    public function getRealTimeUpdates(int $instructorId): array
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

        // Get recent updates (last 24 hours)
        $stmt = $this->pdo->prepare("
            SELECT 
                sd.*,
                d.document_name,
                d.document_type,
                u.full_name as student_name,
                u.email as student_email
            FROM student_documents sd
            JOIN documents d ON sd.document_id = d.id
            JOIN users u ON sd.student_id = u.id
            WHERE u.section_id = ?
            AND sd.updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY sd.updated_at DESC
        ");
        $stmt->execute([$instructor['section_id']]);
        $recentUpdates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'updates' => $recentUpdates,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get document alerts and notifications
     */
    public function getDocumentAlerts(int $instructorId): array
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

        $monitoring = $this->dashboardService->getDocumentMonitoring($instructor['section_id']);
        
        $alerts = [];
        
        // Add overdue alerts
        foreach ($monitoring['at_risk_students'] as $student) {
            if ($student['overdue_count'] > 0) {
                $alerts[] = [
                    'type' => 'overdue',
                    'priority' => 'high',
                    'message' => "{$student['full_name']} has {$student['overdue_count']} overdue document(s)",
                    'student_id' => $student['id'],
                    'student_name' => $student['full_name']
                ];
            }
        }

        // Add compliance alerts
        foreach ($monitoring['compliance_status'] as $status) {
            if ($status['compliance_status'] === 'non_compliant') {
                $alerts[] = [
                    'type' => 'compliance',
                    'priority' => 'medium',
                    'message' => "{$status['full_name']} is not compliant with document requirements",
                    'student_id' => $status['id'],
                    'student_name' => $status['full_name']
                ];
            }
        }

        return [
            'success' => true,
            'alerts' => $alerts,
            'alert_count' => count($alerts)
        ];
    }
}
