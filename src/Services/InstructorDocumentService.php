<?php

namespace App\Services;

use App\Utils\Database;
use PDO;

/**
 * Instructor Document Service
 * Handles instructor document review business logic
 */
class InstructorDocumentService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Get submissions for review (including weekly and monthly reports)
     */
    public function getSubmissionsForReview(
        int $sectionId,
        string $studentFilter = '',
        string $documentTypeFilter = '',
        string $statusFilter = '',
        string $dateFrom = '',
        string $dateTo = '',
        string $sortBy = 'submitted_at',
        string $sortOrder = 'DESC',
        int $page = 1,
        int $perPage = 10
    ): array {
        // Build WHERE conditions for regular documents
        $docWhereConditions = ["u.section_id = ?"];
        $docParams = [$sectionId];
        
        // Build WHERE conditions for reports
        $reportWhereConditions = ["u.section_id = ?"];
        $reportParams = [$sectionId];

        // Add filters for regular documents
        if (!empty($studentFilter)) {
            $docWhereConditions[] = "u.full_name LIKE ?";
            $docParams[] = "%{$studentFilter}%";
            $reportWhereConditions[] = "u.full_name LIKE ?";
            $reportParams[] = "%{$studentFilter}%";
        }

        if (!empty($documentTypeFilter)) {
            // For reports, check if filter is for weekly, monthly, or excuse
            if ($documentTypeFilter === 'weekly_report' || $documentTypeFilter === 'monthly_report' || $documentTypeFilter === 'excuse_document') {
                $reportType = $documentTypeFilter === 'weekly_report' ? 'weekly' : ($documentTypeFilter === 'monthly_report' ? 'monthly' : 'excuse');
                $reportWhereConditions[] = "sr.report_type = ?";
                $reportParams[] = $reportType;
                // Exclude regular documents when filtering by report type
                $docWhereConditions[] = "1 = 0"; // This will exclude all regular docs
            } else {
                $docWhereConditions[] = "d.document_type = ?";
                $docParams[] = $documentTypeFilter;
                // Exclude reports when filtering by regular document type
                $reportWhereConditions[] = "1 = 0"; // This will exclude all reports
            }
        }

        if (!empty($statusFilter)) {
            $docWhereConditions[] = "sd.status = ?";
            $docParams[] = $statusFilter;
            $reportWhereConditions[] = "sr.status = ?";
            $reportParams[] = $statusFilter;
        }

        if (!empty($dateFrom)) {
            $docWhereConditions[] = "DATE(sd.submitted_at) >= ?";
            $docParams[] = $dateFrom;
            $reportWhereConditions[] = "DATE(sr.submitted_at) >= ?";
            $reportParams[] = $dateFrom;
        }

        if (!empty($dateTo)) {
            $docWhereConditions[] = "DATE(sd.submitted_at) <= ?";
            $docParams[] = $dateTo;
            $reportWhereConditions[] = "DATE(sr.submitted_at) <= ?";
            $reportParams[] = $dateTo;
        }

        $docWhereClause = implode(' AND ', $docWhereConditions);
        $reportWhereClause = implode(' AND ', $reportWhereConditions);

        // Validate sort parameters
        $allowedSortFields = ['submitted_at', 'student_name', 'document_type', 'status'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'submitted_at';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        // Map sort field names for UNION query
        $sortFieldMap = [
            'student_name' => 'student_name',
            'document_type' => 'document_type',
            'status' => 'status',
            'submitted_at' => 'submitted_at'
        ];

        $orderBy = $sortFieldMap[$sortBy] ?? 'submitted_at';

        // Check if student_reports table exists
        $stmt = $this->pdo->query("SHOW TABLES LIKE 'student_reports'");
        $reportsTableExists = $stmt->rowCount() > 0;

        // Build UNION query for count
        if ($reportsTableExists) {
            $countSql = "
                SELECT COUNT(*) as total FROM (
                    SELECT sd.id
                    FROM student_documents sd
                    JOIN documents d ON sd.document_id = d.id
                    JOIN users u ON sd.student_id = u.id
                    WHERE {$docWhereClause}
                    UNION ALL
                    SELECT sr.id
                    FROM student_reports sr
                    JOIN users u ON sr.student_id = u.id
                    WHERE {$reportWhereClause}
                ) as combined
            ";
            $allParams = array_merge($docParams, $reportParams);
        } else {
            $countSql = "
                SELECT COUNT(*) as total
                FROM student_documents sd
                JOIN documents d ON sd.document_id = d.id
                JOIN users u ON sd.student_id = u.id
                WHERE {$docWhereClause}
            ";
            $allParams = $docParams;
        }

        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($allParams);
        $total = (int)$stmt->fetchColumn();
        
        // Calculate pagination
        $totalPages = ceil($total / $perPage);
        $page = max(1, min($page, $totalPages)); // Ensure page is within bounds
        $offset = ($page - 1) * $perPage;
        
        // Build UNION query for results
        if ($reportsTableExists) {
            $sql = "
                SELECT * FROM (
                    SELECT 
                        sd.id,
                        sd.student_id,
                        sd.document_id,
                        sd.submission_file_path as file_path,
                        sd.status,
                        sd.submitted_at,
                        sd.reviewed_at,
                        sd.instructor_feedback,
                        sd.created_at,
                        sd.updated_at,
                        d.document_name,
                        d.document_type,
                        d.file_path as template_path,
                        u.full_name as student_name,
                        u.email as student_email,
                        u.section_id,
                        NULL as excuse_date,
                        'document' as submission_type
                    FROM student_documents sd
                    JOIN documents d ON sd.document_id = d.id
                    JOIN users u ON sd.student_id = u.id
                    WHERE {$docWhereClause}
                    UNION ALL
                    SELECT 
                        sr.id,
                        sr.student_id,
                        NULL as document_id,
                        sr.file_path,
                        sr.status,
                        sr.submitted_at,
                        sr.reviewed_at,
                        sr.instructor_feedback,
                        sr.created_at,
                        sr.updated_at,
                        CASE 
                            WHEN sr.report_type = 'excuse' THEN 'Excuse Document'
                            ELSE CONCAT(UCASE(LEFT(sr.report_type, 1)), SUBSTRING(sr.report_type, 2), ' Report')
                        END as document_name,
                        CASE 
                            WHEN sr.report_type = 'excuse' THEN 'excuse_document'
                            ELSE CONCAT(sr.report_type, '_report')
                        END as document_type,
                        NULL as template_path,
                        u.full_name as student_name,
                        u.email as student_email,
                        u.section_id,
                        sr.excuse_date,
                        'report' as submission_type
                    FROM student_reports sr
                    JOIN users u ON sr.student_id = u.id
                    WHERE {$reportWhereClause}
                ) as combined
                ORDER BY {$orderBy} {$sortOrder}
                LIMIT ? OFFSET ?
            ";
            $allParams = array_merge($docParams, $reportParams, [(int)$perPage, (int)$offset]);
        } else {
            $sql = "
                SELECT sd.*, d.document_name, d.document_type, d.file_path as template_path,
                       u.full_name as student_name, u.email as student_email, u.section_id,
                       'document' as submission_type
                FROM student_documents sd
                JOIN documents d ON sd.document_id = d.id
                JOIN users u ON sd.student_id = u.id
                WHERE {$docWhereClause}
                ORDER BY {$orderBy} {$sortOrder}
                LIMIT ? OFFSET ?
            ";
            $allParams = array_merge($docParams, [(int)$perPage, (int)$offset]);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($allParams);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'data' => $results,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ];
    }

    /**
     * Get review statistics
     */
    public function getReviewStatistics(int $sectionId): array
    {
        // Total submissions
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total_submissions
            FROM student_documents sd
            JOIN users u ON sd.student_id = u.id
            WHERE u.section_id = ?
        ");
        $stmt->execute([$sectionId]);
        $totalSubmissions = $stmt->fetchColumn();

        // Pending review
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as pending_review
            FROM student_documents sd
            JOIN users u ON sd.student_id = u.id
            WHERE u.section_id = ? AND sd.status = 'pending'
        ");
        $stmt->execute([$sectionId]);
        $pendingReview = $stmt->fetchColumn();

        // Approved
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as approved
            FROM student_documents sd
            JOIN users u ON sd.student_id = u.id
            WHERE u.section_id = ? AND sd.status = 'approved'
        ");
        $stmt->execute([$sectionId]);
        $approved = $stmt->fetchColumn();

        // Needs revision
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as needs_revision
            FROM student_documents sd
            JOIN users u ON sd.student_id = u.id
            WHERE u.section_id = ? AND sd.status = 'revision_required'
        ");
        $stmt->execute([$sectionId]);
        $needsRevision = $stmt->fetchColumn();

        // Rejected
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as rejected
            FROM student_documents sd
            JOIN users u ON sd.student_id = u.id
            WHERE u.section_id = ? AND sd.status = 'rejected'
        ");
        $stmt->execute([$sectionId]);
        $rejected = $stmt->fetchColumn();

        return [
            'total_submissions' => (int)$totalSubmissions,
            'pending_review' => (int)$pendingReview,
            'approved' => (int)$approved,
            'needs_revision' => (int)$needsRevision,
            'rejected' => (int)$rejected
        ];
    }

    /**
     * Get submission details
     */
    public function getSubmissionDetails(int $submissionId, int $instructorId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT sd.*, d.document_name, d.document_type, d.file_path as template_path,
                   u.full_name as student_name, u.email as student_email, u.section_id
            FROM student_documents sd
            JOIN documents d ON sd.document_id = d.id
            JOIN users u ON sd.student_id = u.id
            WHERE sd.id = ? AND u.section_id = (
                SELECT section_id FROM users WHERE id = ?
            )
        ");
        $stmt->execute([$submissionId, $instructorId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get submission history for a document
     */
    public function getSubmissionHistory(int $studentId, int $documentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT sd.*
            FROM student_documents sd
            WHERE sd.student_id = ? AND sd.document_id = ?
            ORDER BY sd.submitted_at DESC
        ");
        $stmt->execute([$studentId, $documentId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get students with incomplete documents
     */
    public function getStudentsWithIncompleteDocuments(int $sectionId): array
    {
        // Get all students in section
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.full_name, u.email
            FROM users u
            WHERE u.section_id = ? AND u.role = 'student'
            ORDER BY u.full_name
        ");
        $stmt->execute([$sectionId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get required document types
        $stmt = $this->pdo->query("
            SELECT DISTINCT document_type 
            FROM documents 
            WHERE uploaded_for_section IS NULL OR uploaded_for_section = ?
        ");
        $stmt->execute([$sectionId]);
        $requiredTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $result = [];
        foreach ($students as $student) {
            // Get student's submitted documents
            $stmt = $this->pdo->prepare("
                SELECT sd.document_id, sd.status, d.document_type
                FROM student_documents sd
                JOIN documents d ON sd.document_id = d.id
                WHERE sd.student_id = ?
            ");
            $stmt->execute([$student['id']]);
            $submittedDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Create map of submitted document types
            $submittedTypes = [];
            foreach ($submittedDocs as $doc) {
                $submittedTypes[$doc['document_type']] = $doc['status'];
            }

            // Check completion status
            $totalRequired = count($requiredTypes);
            $approvedCount = 0;
            $pendingCount = 0;
            $needsRevisionCount = 0;

            foreach ($requiredTypes as $type) {
                if (isset($submittedTypes[$type])) {
                    switch ($submittedTypes[$type]) {
                        case 'approved':
                            $approvedCount++;
                            break;
                        case 'pending':
                            $pendingCount++;
                            break;
                        case 'revision_required':
                            $needsRevisionCount++;
                            break;
                    }
                }
            }

            $result[] = [
                'student_id' => $student['id'],
                'student_name' => $student['full_name'],
                'student_email' => $student['email'],
                'total_required' => $totalRequired,
                'approved' => $approvedCount,
                'pending' => $pendingCount,
                'needs_revision' => $needsRevisionCount,
                'not_submitted' => $totalRequired - count($submittedTypes),
                'completion_percentage' => $totalRequired > 0 ? ($approvedCount / $totalRequired) * 100 : 0,
                'is_complete' => $approvedCount >= $totalRequired
            ];
        }

        return $result;
    }

    /**
     * Get recent activity
     */
    public function getRecentActivity(int $sectionId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT sd.*, d.document_name, d.document_type, u.full_name as student_name
            FROM student_documents sd
            JOIN documents d ON sd.document_id = d.id
            JOIN users u ON sd.student_id = u.id
            WHERE u.section_id = ?
            ORDER BY sd.updated_at DESC
            LIMIT ?
        ");
        $stmt->execute([$sectionId, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
