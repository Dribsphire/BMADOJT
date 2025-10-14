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
     * Get submissions for review
     */
    public function getSubmissionsForReview(
        int $sectionId,
        string $studentFilter = '',
        string $documentTypeFilter = '',
        string $statusFilter = '',
        string $dateFrom = '',
        string $dateTo = '',
        string $sortBy = 'submitted_at',
        string $sortOrder = 'DESC'
    ): array {
        $whereConditions = ["u.section_id = ?"];
        $params = [$sectionId];

        // Add filters
        if (!empty($studentFilter)) {
            $whereConditions[] = "u.full_name LIKE ?";
            $params[] = "%{$studentFilter}%";
        }

        if (!empty($documentTypeFilter)) {
            $whereConditions[] = "d.document_type = ?";
            $params[] = $documentTypeFilter;
        }

        if (!empty($statusFilter)) {
            $whereConditions[] = "sd.status = ?";
            $params[] = $statusFilter;
        }

        if (!empty($dateFrom)) {
            $whereConditions[] = "DATE(sd.submitted_at) >= ?";
            $params[] = $dateFrom;
        }

        if (!empty($dateTo)) {
            $whereConditions[] = "DATE(sd.submitted_at) <= ?";
            $params[] = $dateTo;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Validate sort parameters
        $allowedSortFields = ['submitted_at', 'student_name', 'document_type', 'status'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'submitted_at';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        // Map sort field names
        $sortFieldMap = [
            'student_name' => 'u.full_name',
            'document_type' => 'd.document_type',
            'status' => 'sd.status',
            'submitted_at' => 'sd.submitted_at'
        ];

        $orderBy = $sortFieldMap[$sortBy] ?? 'sd.submitted_at';

        $sql = "
            SELECT sd.*, d.document_name, d.document_type, d.file_path as template_path,
                   u.full_name as student_name, u.email as student_email, u.section_id
            FROM student_documents sd
            JOIN documents d ON sd.document_id = d.id
            JOIN users u ON sd.student_id = u.id
            WHERE {$whereClause}
            ORDER BY {$orderBy} {$sortOrder}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
