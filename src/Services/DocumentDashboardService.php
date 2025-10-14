<?php

namespace App\Services;

use App\Utils\Database;
use PDO;

/**
 * Document Dashboard Service
 * Handles document analytics, monitoring, and dashboard data
 */
class DocumentDashboardService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Get document overview for instructor's section
     */
    public function getDocumentOverview(int $sectionId): array
    {
        // Get total students in section
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total_students
            FROM users 
            WHERE section_id = ? AND role = 'student'
        ");
        $stmt->execute([$sectionId]);
        $totalStudents = $stmt->fetchColumn();

        // Get document completion statistics
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT sd.student_id) as students_with_submissions,
                COUNT(sd.id) as total_submissions,
                SUM(CASE WHEN sd.status = 'approved' THEN 1 ELSE 0 END) as approved_documents,
                SUM(CASE WHEN sd.status = 'pending' THEN 1 ELSE 0 END) as pending_documents,
                SUM(CASE WHEN sd.status = 'revision_required' THEN 1 ELSE 0 END) as revision_required,
                SUM(CASE WHEN sd.status = 'rejected' THEN 1 ELSE 0 END) as rejected_documents
            FROM student_documents sd
            JOIN users u ON sd.student_id = u.id
            WHERE u.section_id = ?
        ");
        $stmt->execute([$sectionId]);
        $completionStats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get overdue documents count
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as overdue_count
            FROM documents d
            JOIN users u ON d.uploaded_for_section = u.section_id
            LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = u.id
            WHERE d.uploaded_for_section = ?
            AND d.deadline IS NOT NULL 
            AND d.deadline < CURDATE()
            AND (sd.id IS NULL OR sd.status IN ('pending', 'revision_required', 'rejected'))
        ");
        $stmt->execute([$sectionId]);
        $overdueCount = $stmt->fetchColumn();

        return [
            'total_students' => $totalStudents,
            'students_with_submissions' => $completionStats['students_with_submissions'] ?? 0,
            'total_submissions' => $completionStats['total_submissions'] ?? 0,
            'approved_documents' => $completionStats['approved_documents'] ?? 0,
            'pending_documents' => $completionStats['pending_documents'] ?? 0,
            'revision_required' => $completionStats['revision_required'] ?? 0,
            'rejected_documents' => $completionStats['rejected_documents'] ?? 0,
            'overdue_count' => $overdueCount,
            'completion_rate' => $totalStudents > 0 ? round((($completionStats['students_with_submissions'] ?? 0) / $totalStudents) * 100, 2) : 0,
            'approval_rate' => $completionStats['total_submissions'] > 0 ? round((($completionStats['approved_documents'] ?? 0) / $completionStats['total_submissions']) * 100, 2) : 0
        ];
    }

    /**
     * Get document analytics and trends
     */
    public function getDocumentAnalytics(int $sectionId, int $days = 30): array
    {
        // Get submission trends over time
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(sd.submitted_at) as submission_date,
                COUNT(*) as submissions_count,
                SUM(CASE WHEN sd.status = 'approved' THEN 1 ELSE 0 END) as approved_count
            FROM student_documents sd
            JOIN users u ON sd.student_id = u.id
            WHERE u.section_id = ?
            AND sd.submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(sd.submitted_at)
            ORDER BY submission_date ASC
        ");
        $stmt->execute([$sectionId, $days]);
        $submissionTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get document type analytics
        $stmt = $this->pdo->prepare("
            SELECT 
                d.document_type,
                COUNT(sd.id) as total_submissions,
                SUM(CASE WHEN sd.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                AVG(CASE WHEN sd.status = 'approved' THEN 
                    TIMESTAMPDIFF(HOUR, d.created_at, sd.submitted_at) 
                END) as avg_approval_time_hours
            FROM documents d
            LEFT JOIN student_documents sd ON d.id = sd.document_id
            WHERE d.uploaded_for_section = ?
            GROUP BY d.document_type
            ORDER BY total_submissions DESC
        ");
        $stmt->execute([$sectionId]);
        $documentTypeAnalytics = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get student performance analytics
        $stmt = $this->pdo->prepare("
            SELECT 
                u.id,
                u.full_name,
                COUNT(sd.id) as total_submissions,
                SUM(CASE WHEN sd.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN sd.status = 'revision_required' THEN 1 ELSE 0 END) as revision_count,
                SUM(CASE WHEN sd.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                MAX(sd.submitted_at) as last_submission
            FROM users u
            LEFT JOIN student_documents sd ON u.id = sd.student_id
            WHERE u.section_id = ? AND u.role = 'student'
            GROUP BY u.id, u.full_name
            ORDER BY total_submissions DESC
        ");
        $stmt->execute([$sectionId]);
        $studentPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'submission_trends' => $submissionTrends,
            'document_type_analytics' => $documentTypeAnalytics,
            'student_performance' => $studentPerformance,
            'analysis_period_days' => $days
        ];
    }

    /**
     * Get document monitoring data
     */
    public function getDocumentMonitoring(int $sectionId): array
    {
        // Get at-risk students (students with overdue or rejected documents)
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT
                u.id,
                u.full_name,
                u.email,
                COUNT(DISTINCT CASE WHEN d.deadline < CURDATE() AND (sd.id IS NULL OR sd.status IN ('pending', 'revision_required', 'rejected')) THEN d.id END) as overdue_count,
                COUNT(DISTINCT CASE WHEN sd.status = 'rejected' THEN sd.id END) as rejected_count,
                COUNT(DISTINCT CASE WHEN sd.status = 'revision_required' THEN sd.id END) as revision_count
            FROM users u
            LEFT JOIN documents d ON d.uploaded_for_section = u.section_id
            LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = u.id
            WHERE u.section_id = ? AND u.role = 'student'
            GROUP BY u.id, u.full_name, u.email
            HAVING COUNT(DISTINCT CASE WHEN d.deadline < CURDATE() AND (sd.id IS NULL OR sd.status IN ('pending', 'revision_required', 'rejected')) THEN d.id END) > 0 
                OR COUNT(DISTINCT CASE WHEN sd.status = 'rejected' THEN sd.id END) > 0 
                OR COUNT(DISTINCT CASE WHEN sd.status = 'revision_required' THEN sd.id END) > 0
            ORDER BY (COUNT(DISTINCT CASE WHEN d.deadline < CURDATE() AND (sd.id IS NULL OR sd.status IN ('pending', 'revision_required', 'rejected')) THEN d.id END) + 
                     COUNT(DISTINCT CASE WHEN sd.status = 'rejected' THEN sd.id END) + 
                     COUNT(DISTINCT CASE WHEN sd.status = 'revision_required' THEN sd.id END)) DESC
        ");
        $stmt->execute([$sectionId]);
        $atRiskStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get recent document activity
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
            ORDER BY sd.updated_at DESC
            LIMIT 20
        ");
        $stmt->execute([$sectionId]);
        $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get compliance status
        $stmt = $this->pdo->prepare("
            SELECT 
                u.id,
                u.full_name,
                COUNT(DISTINCT d.id) as total_required_documents,
                COUNT(DISTINCT CASE WHEN sd.status = 'approved' THEN sd.document_id END) as approved_documents,
                CASE 
                    WHEN COUNT(DISTINCT d.id) = COUNT(DISTINCT CASE WHEN sd.status = 'approved' THEN sd.document_id END) 
                    THEN 'compliant'
                    ELSE 'non_compliant'
                END as compliance_status
            FROM users u
            LEFT JOIN documents d ON d.uploaded_for_section = u.section_id AND d.is_required = 1
            LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = u.id
            WHERE u.section_id = ? AND u.role = 'student'
            GROUP BY u.id, u.full_name
        ");
        $stmt->execute([$sectionId]);
        $complianceStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'at_risk_students' => $atRiskStudents,
            'recent_activity' => $recentActivity,
            'compliance_status' => $complianceStatus
        ];
    }

    /**
     * Generate document compliance report
     */
    public function generateComplianceReport(int $sectionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.email,
                d.document_name,
                d.document_type,
                d.deadline,
                sd.status,
                sd.submitted_at,
                sd.updated_at,
                CASE 
                    WHEN d.deadline IS NOT NULL AND d.deadline < CURDATE() AND sd.status != 'approved' 
                    THEN 'overdue'
                    WHEN sd.status = 'approved' 
                    THEN 'compliant'
                    WHEN sd.status IS NULL 
                    THEN 'not_submitted'
                    ELSE 'pending'
                END as compliance_status
            FROM users u
            CROSS JOIN documents d
            LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = u.id
            WHERE u.section_id = ? AND u.role = 'student' AND d.uploaded_for_section = ?
            ORDER BY u.full_name, d.document_type
        ");
        $stmt->execute([$sectionId, $sectionId]);
        $complianceData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $complianceData;
    }

    /**
     * Get document performance metrics
     */
    public function getDocumentPerformanceMetrics(int $sectionId): array
    {
        // Average time to approval
        $stmt = $this->pdo->prepare("
            SELECT 
                AVG(TIMESTAMPDIFF(HOUR, sd.submitted_at, sd.updated_at)) as avg_approval_time_hours,
                MIN(TIMESTAMPDIFF(HOUR, sd.submitted_at, sd.updated_at)) as min_approval_time_hours,
                MAX(TIMESTAMPDIFF(HOUR, sd.submitted_at, sd.updated_at)) as max_approval_time_hours
            FROM student_documents sd
            JOIN users u ON sd.student_id = u.id
            WHERE u.section_id = ? AND sd.status = 'approved'
        ");
        $stmt->execute([$sectionId]);
        $approvalMetrics = $stmt->fetch(PDO::FETCH_ASSOC);

        // Document completion rates by type
        $stmt = $this->pdo->prepare("
            SELECT 
                d.document_type,
                COUNT(DISTINCT u.id) as total_students,
                COUNT(DISTINCT CASE WHEN sd.status = 'approved' THEN u.id END) as completed_students,
                ROUND((COUNT(DISTINCT CASE WHEN sd.status = 'approved' THEN u.id END) / COUNT(DISTINCT u.id)) * 100, 2) as completion_rate
            FROM documents d
            CROSS JOIN users u
            LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = u.id
            WHERE u.section_id = ? AND u.role = 'student' AND d.uploaded_for_section = ?
            GROUP BY d.document_type
        ");
        $stmt->execute([$sectionId, $sectionId]);
        $completionRates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'approval_metrics' => $approvalMetrics,
            'completion_rates' => $completionRates
        ];
    }

    /**
     * Export document data to CSV format
     */
    public function exportDocumentData(int $sectionId, string $format = 'csv'): string
    {
        $data = $this->generateComplianceReport($sectionId);
        
        if ($format === 'csv') {
            $csv = "Student Name,Email,Document Name,Document Type,Deadline,Status,Submitted At,Compliance Status\n";
            
            foreach ($data as $row) {
                $csv .= sprintf(
                    "%s,%s,%s,%s,%s,%s,%s,%s\n",
                    $row['full_name'],
                    $row['email'],
                    $row['document_name'],
                    $row['document_type'],
                    $row['deadline'] ?? 'N/A',
                    $row['status'] ?? 'Not Submitted',
                    $row['submitted_at'] ?? 'N/A',
                    $row['compliance_status']
                );
            }
            
            return $csv;
        }
        
        return json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Get dashboard summary statistics
     */
    public function getDashboardSummary(int $sectionId): array
    {
        $overview = $this->getDocumentOverview($sectionId);
        $monitoring = $this->getDocumentMonitoring($sectionId);
        $performance = $this->getDocumentPerformanceMetrics($sectionId);

        return [
            'overview' => $overview,
            'monitoring' => [
                'at_risk_students_count' => count($monitoring['at_risk_students']),
                'recent_activity_count' => count($monitoring['recent_activity']),
                'compliant_students' => count(array_filter($monitoring['compliance_status'], fn($s) => $s['compliance_status'] === 'compliant')),
                'non_compliant_students' => count(array_filter($monitoring['compliance_status'], fn($s) => $s['compliance_status'] === 'non_compliant'))
            ],
            'performance' => $performance
        ];
    }
}
