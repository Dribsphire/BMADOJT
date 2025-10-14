<?php

namespace App\Models;

class Document
{
    public int $id;
    public string $document_name;
    public string $document_type;
    public string $file_path;
    public int $uploaded_by;
    public ?int $uploaded_for_section;
    public ?string $deadline;
    public ?string $description;
    public bool $is_required;
    public ?string $created_at;
    public ?string $uploaded_by_name;

    public function __construct(
        int $id = 0,
        string $document_name = '',
        string $document_type = '',
        string $file_path = '',
        int $uploaded_by = 0,
        ?int $uploaded_for_section = null,
        ?string $deadline = null,
        ?string $description = null,
        bool $is_required = true,
        ?string $created_at = null,
        ?string $uploaded_by_name = null
    ) {
        $this->id = $id;
        $this->document_name = $document_name;
        $this->document_type = $document_type;
        $this->file_path = $file_path;
        $this->uploaded_by = $uploaded_by;
        $this->uploaded_for_section = $uploaded_for_section;
        $this->deadline = $deadline;
        $this->description = $description;
        $this->is_required = $is_required;
        $this->created_at = $created_at;
        $this->uploaded_by_name = $uploaded_by_name;
    }

    /**
     * Create Document from database array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? 0,
            $data['document_name'] ?? '',
            $data['document_type'] ?? '',
            $data['file_path'] ?? '',
            $data['uploaded_by'] ?? 0,
            $data['uploaded_for_section'] ?? null,
            $data['deadline'] ?? null,
            $data['description'] ?? null,
            (bool)($data['is_required'] ?? true),
            $data['created_at'] ?? null,
            $data['uploaded_by_name'] ?? null
        );
    }

    /**
     * Get document type display name
     */
    public function getTypeDisplayName(): string
    {
        return match($this->document_type) {
            'moa' => 'MOA (Memorandum of Agreement)',
            'endorsement' => 'Endorsement Letter',
            'parental_consent' => 'Parental Consent',
            'misdemeanor_penalty' => 'Misdemeanor Penalty',
            'ojt_plan' => 'OJT Plan',
            'notarized_consent' => 'Notarized Parental Consent',
            'pledge' => 'Pledge of Good Conduct',
            'weekly_report' => 'Weekly Report',
            'other' => 'Other',
            default => ucfirst(str_replace('_', ' ', $this->document_type))
        };
    }

    /**
     * Check if document is a template
     */
    public function isTemplate(): bool
    {
        return $this->uploaded_for_section === null;
    }

    /**
     * Get file extension
     */
    public function getFileExtension(): string
    {
        return pathinfo($this->file_path, PATHINFO_EXTENSION);
    }

    /**
     * Get file size in human readable format
     */
    public function getFileSize(): string
    {
        if (!file_exists($this->file_path)) {
            return 'Unknown';
        }

        $bytes = filesize($this->file_path);
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if document has deadline
     */
    public function hasDeadline(): bool
    {
        return !empty($this->deadline);
    }

    /**
     * Check if document is overdue
     */
    public function isOverdue(): bool
    {
        if (!$this->hasDeadline()) {
            return false;
        }
        
        return strtotime($this->deadline) < time();
    }

    /**
     * Get days until deadline
     */
    public function getDaysUntilDeadline(): int
    {
        if (!$this->hasDeadline()) {
            return 0;
        }
        
        $deadline = strtotime($this->deadline);
        $now = time();
        $diff = $deadline - $now;
        
        return max(0, ceil($diff / (24 * 60 * 60)));
    }
}
