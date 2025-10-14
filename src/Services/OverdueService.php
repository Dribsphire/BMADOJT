<?php

namespace App\Services;

use App\Utils\Database;
use PDO;

/**
 * Overdue Service
 * Handles overdue document detection and management
 */
class OverdueService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Get all overdue documents
     */
    public function getOverdueDocuments(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, u.id as student_id, u.full_name as student_name, u.email as student_email, u.section_id,
                   sd.id as submission_id, sd.status as submission_status, sd.submitted_at,
                   i.full_name as instructor_name, i.email as instructor_email
            FROM documents d
            JOIN users u ON d.uploaded_for_section = u.section_id
            LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = u.id
            LEFT JOIN users i ON d.uploaded_by = i.id
            WHERE d.deadline IS NOT NULL 
            AND d.deadline < CURDATE()
            AND (sd.id IS NULL OR sd.status IN ('pending', 'revision_required', 'rejected'))
            ORDER BY d.deadline ASC, u.full_name ASC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get overdue documents for a specific section
     */
    public function getOverdueDocumentsForSection(int $sectionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, u.id as student_id, u.full_name as student_name, u.email as student_email,
                   sd.id as submission_id, sd.status as submission_status, sd.submitted_at,
                   i.full_name as instructor_name, i.email as instructor_email
            FROM documents d
            JOIN users u ON d.uploaded_for_section = u.section_id
            LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = u.id
            LEFT JOIN users i ON d.uploaded_by = i.id
            WHERE d.uploaded_for_section = ?
            AND d.deadline IS NOT NULL 
            AND d.deadline < CURDATE()
            AND (sd.id IS NULL OR sd.status IN ('pending', 'revision_required', 'rejected'))
            ORDER BY d.deadline ASC, u.full_name ASC
        ");
        $stmt->execute([$sectionId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get overdue documents for a specific student
     */
    public function getOverdueDocumentsForStudent(int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, u.id as student_id, u.full_name as student_name, u.email as student_email,
                   sd.id as submission_id, sd.status as submission_status, sd.submitted_at,
                   i.full_name as instructor_name, i.email as instructor_email
            FROM documents d
            JOIN users u ON d.uploaded_for_section = u.section_id
            LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = u.id
            LEFT JOIN users i ON d.uploaded_by = i.id
            WHERE u.id = ?
            AND d.deadline IS NOT NULL 
            AND d.deadline < CURDATE()
            AND (sd.id IS NULL OR sd.status IN ('pending', 'revision_required', 'rejected'))
            ORDER BY d.deadline ASC
        ");
        $stmt->execute([$studentId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get overdue statistics
     */
    public function getOverdueStatistics(): array
    {
        // Total overdue documents
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total_overdue
            FROM documents d
            JOIN users u ON d.uploaded_for_section = u.section_id
            LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = u.id
            WHERE d.deadline IS NOT NULL 
            AND d.deadline < CURDATE()
            AND (sd.id IS NULL OR sd.status IN ('pending', 'revision_required', 'rejected'))
        ");
        $stmt->execute();
        $totalOverdue = $stmt->fetchColumn();

        // Overdue by section
        $stmt = $this->pdo->prepare("
            SELECT u.section_id, COUNT(*) as overdue_count
            FROM documents d
            JOIN users u ON d.uploaded_for_section = u.section_id
            LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = u.id
            WHERE d.deadline IS NOT NULL 
            AND d.deadline < CURDATE()
            AND (sd.id IS NULL OR sd.status IN ('pending', 'revision_required', 'rejected'))
            GROUP BY u.section_id
        ");
        $stmt->execute();
        $overdueBySection = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Overdue by document type
        $stmt = $this->pdo->prepare("
            SELECT d.document_type, COUNT(*) as overdue_count
            FROM documents d
            JOIN users u ON d.uploaded_for_section = u.section_id
            LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = u.id
            WHERE d.deadline IS NOT NULL 
            AND d.deadline < CURDATE()
            AND (sd.id IS NULL OR sd.status IN ('pending', 'revision_required', 'rejected'))
            GROUP BY d.document_type
        ");
        $stmt->execute();
        $overdueByType = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total_overdue' => $totalOverdue,
            'by_section' => $overdueBySection,
            'by_type' => $overdueByType
        ];
    }

    /**
     * Check if a document is overdue
     */
    public function isDocumentOverdue(int $documentId, int $studentId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT d.deadline, sd.status
            FROM documents d
            LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = ?
            WHERE d.id = ?
        ");
        $stmt->execute([$studentId, $documentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || !$result['deadline']) {
            return false;
        }

        $deadline = strtotime($result['deadline']);
        $today = time();
        
        // Document is overdue if deadline has passed and not approved
        return $deadline < $today && $result['status'] !== 'approved';
    }

    /**
     * Get days overdue for a document
     */
    public function getDaysOverdue(int $documentId, int $studentId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT d.deadline
            FROM documents d
            WHERE d.id = ?
        ");
        $stmt->execute([$documentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || !$result['deadline']) {
            return 0;
        }

        $deadline = strtotime($result['deadline']);
        $today = time();
        
        $daysOverdue = floor(($today - $deadline) / (24 * 60 * 60));
        return max(0, $daysOverdue);
    }

    /**
     * Mark overdue document as resolved
     */
    public function markOverdueAsResolved(int $documentId, int $studentId): bool
    {
        try {
            // Update the submission status to approved
            $stmt = $this->pdo->prepare("
                UPDATE student_documents 
                SET status = 'approved', updated_at = NOW()
                WHERE document_id = ? AND student_id = ?
            ");
            $stmt->execute([$documentId, $studentId]);
            
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get overdue documents that need notification
     */
    public function getOverdueDocumentsForNotification(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT d.*, u.full_name as student_name, u.email as student_email,
                   i.full_name as instructor_name, i.email as instructor_email
            FROM documents d
            JOIN users u ON d.uploaded_for_section = u.section_id
            LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = u.id
            LEFT JOIN users i ON d.uploaded_by = i.id
            WHERE d.deadline IS NOT NULL 
            AND d.deadline < CURDATE()
            AND (sd.id IS NULL OR sd.status IN ('pending', 'revision_required', 'rejected'))
            AND d.deadline >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY d.deadline ASC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
