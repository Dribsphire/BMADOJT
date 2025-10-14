<?php

namespace App\Services;

use App\Models\Document;
use App\Utils\Database;
use PDO;

class DocumentService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Get all documents
     */
    public function getAllDocuments(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, u.full_name as uploaded_by_name
            FROM documents d
            LEFT JOIN users u ON d.uploaded_by = u.id
            ORDER BY d.created_at DESC
        ");
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([Document::class, 'fromArray'], $results);
    }

    /**
     * Get documents by type
     */
    public function getDocumentsByType(string $type): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, u.full_name as uploaded_by_name
            FROM documents d
            LEFT JOIN users u ON d.uploaded_by = u.id
            WHERE d.document_type = ?
            ORDER BY d.created_at DESC
        ");
        $stmt->execute([$type]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([Document::class, 'fromArray'], $results);
    }

    /**
     * Get template documents (pre-loaded)
     */
    public function getTemplateDocuments(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, u.full_name as uploaded_by_name
            FROM documents d
            LEFT JOIN users u ON d.uploaded_by = u.id
            WHERE d.uploaded_for_section IS NULL
            ORDER BY d.document_type, d.created_at DESC
        ");
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([Document::class, 'fromArray'], $results);
    }

    /**
     * Get documents for a specific section
     */
    public function getDocumentsForSection(int $sectionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, u.full_name as uploaded_by_name
            FROM documents d
            LEFT JOIN users u ON d.uploaded_by = u.id
            WHERE d.uploaded_for_section = ? OR d.uploaded_for_section IS NULL
            ORDER BY d.created_at DESC
        ");
        $stmt->execute([$sectionId]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([Document::class, 'fromArray'], $results);
    }

    /**
     * Get custom documents for a specific section
     */
    public function getCustomDocumentsForSection(int $sectionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, u.full_name as uploaded_by_name
            FROM documents d
            LEFT JOIN users u ON d.uploaded_by = u.id
            WHERE d.uploaded_for_section = ? AND d.document_type = 'other'
            ORDER BY d.created_at DESC
        ");
        $stmt->execute([$sectionId]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([Document::class, 'fromArray'], $results);
    }

    /**
     * Get documents for instructor (only their own uploads + pre-loaded templates)
     */
    public function getDocumentsForInstructor(int $instructorId, int $sectionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, u.full_name as uploaded_by_name
            FROM documents d
            LEFT JOIN users u ON d.uploaded_by = u.id
            WHERE (d.uploaded_by = ? AND d.uploaded_for_section = ?) 
               OR (d.uploaded_for_section IS NULL AND d.uploaded_by = 1)
            ORDER BY d.created_at DESC
        ");
        $stmt->execute([$instructorId, $sectionId]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([Document::class, 'fromArray'], $results);
    }

    /**
     * Get document by ID
     */
    public function getDocumentById(int $id): ?Document
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, u.full_name as uploaded_by_name
            FROM documents d
            LEFT JOIN users u ON d.uploaded_by = u.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? Document::fromArray($result) : null;
    }

    /**
     * Create a new document
     */
    public function createDocument(
        string $documentName,
        string $documentType,
        string $filePath,
        int $uploadedBy,
        ?int $uploadedForSection = null,
        ?string $deadline = null,
        bool $isRequired = true
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO documents (document_name, document_type, file_path, uploaded_by, uploaded_for_section, deadline, is_required)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $documentName,
            $documentType,
            $filePath,
            $uploadedBy,
            $uploadedForSection,
            $deadline,
            $isRequired ? 1 : 0
        ]);
        
        return $this->pdo->lastInsertId();
    }

    /**
     * Update document
     */
    public function updateDocument(
        int $id,
        string $documentName,
        string $documentType,
        ?string $deadline = null
    ): bool {
        $stmt = $this->pdo->prepare("
            UPDATE documents 
            SET document_name = ?, document_type = ?, deadline = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([$documentName, $documentType, $deadline, $id]);
    }

    /**
     * Delete document
     */
    public function deleteDocument(int $id): bool
    {
        // Get file path before deletion
        $document = $this->getDocumentById($id);
        if (!$document) {
            return false;
        }
        
        // Delete database record
        $stmt = $this->pdo->prepare("DELETE FROM documents WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        // Delete physical file
        if ($result && file_exists($document->file_path)) {
            unlink($document->file_path);
        }
        
        return $result;
    }

    /**
     * Get required document types
     */
    public function getRequiredDocumentTypes(): array
    {
        return [
            'moa' => 'MOA (Memorandum of Agreement)',
            'endorsement' => 'Endorsement Letter',
            'parental_consent' => 'Parental Consent',
            'misdemeanor_penalty' => 'Misdemeanor Penalty',
            'ojt_plan' => 'OJT Plan',
            'notarized_consent' => 'Notarized Parental Consent',
            'pledge' => 'Pledge of Good Conduct'
        ];
    }

    /**
     * Check if all required documents exist as templates
     */
    public function checkRequiredTemplatesExist(): array
    {
        $requiredTypes = array_keys($this->getRequiredDocumentTypes());
        $existingTypes = [];
        
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT document_type 
            FROM documents 
            WHERE uploaded_for_section IS NULL
        ");
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingTypes[] = $row['document_type'];
        }
        
        $missing = array_diff($requiredTypes, $existingTypes);
        $existing = array_intersect($requiredTypes, $existingTypes);
        
        return [
            'missing' => $missing,
            'existing' => $existing,
            'all_exist' => empty($missing)
        ];
    }

    /**
     * Get document statistics
     */
    public function getDocumentStatistics(): array
    {
        $stats = [];
        
        // Total documents
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM documents");
        $stmt->execute();
        $stats['total_documents'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Template documents
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM documents WHERE uploaded_for_section IS NULL");
        $stmt->execute();
        $stats['template_documents'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Documents by type
        $stmt = $this->pdo->prepare("
            SELECT document_type, COUNT(*) as count 
            FROM documents 
            GROUP BY document_type 
            ORDER BY count DESC
        ");
        $stmt->execute();
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }
}
