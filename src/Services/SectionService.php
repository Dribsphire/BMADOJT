<?php

namespace App\Services;

use App\Utils\Database;

/**
 * Section Service
 * OJT Route - Section management business logic
 */
class SectionService
{
    private \PDO $pdo;
    
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    
    /**
     * Get all sections with pagination and search
     */
    public function getAllSections(int $limit = 25, int $offset = 0, string $search = ''): array
    {
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = 'WHERE s.section_code LIKE ? OR s.section_name LIKE ?';
            $params = ["%{$search}%", "%{$search}%"];
        }
        
        $sql = "
            SELECT 
                s.id,
                s.section_code,
                s.section_name,
                s.created_at,
                u.full_name as instructor_name,
                u.school_id as instructor_school_id,
                COUNT(st.id) as student_count
            FROM sections s
            LEFT JOIN users u ON s.id = u.section_id AND u.role = 'instructor'
            LEFT JOIN users st ON s.id = st.section_id AND st.role = 'student'
            {$whereClause}
            GROUP BY s.id, s.section_code, s.section_name, s.created_at, u.full_name, u.school_id
            ORDER BY s.section_code ASC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get total sections count
     */
    public function getTotalSections(string $search = ''): int
    {
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = 'WHERE section_code LIKE ? OR section_name LIKE ?';
            $params = ["%{$search}%", "%{$search}%"];
        }
        
        $sql = "SELECT COUNT(*) FROM sections {$whereClause}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Get all instructors
     */
    public function getAllInstructors(): array
    {
        $sql = "
            SELECT id, school_id, full_name, email
            FROM users 
            WHERE role = 'instructor'
            ORDER BY full_name ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Create new section
     */
    public function createSection(array $sectionData): array
    {
        try {
            // Validate required fields
            if (empty($sectionData['section_code'])) {
                return ['success' => false, 'message' => 'Section code is required.'];
            }
            
            // Check if section code already exists
            $stmt = $this->pdo->prepare("SELECT id FROM sections WHERE section_code = ?");
            $stmt->execute([$sectionData['section_code']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Section code already exists.'];
            }
            
            // Insert section
            $stmt = $this->pdo->prepare("
                INSERT INTO sections (section_code, section_name, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([
                $sectionData['section_code'],
                $sectionData['section_name']
            ]);
            
            $sectionId = $this->pdo->lastInsertId();
            
            // Assign instructor if provided
            if (!empty($sectionData['instructor_id'])) {
                $this->assignInstructor($sectionId, $sectionData['instructor_id']);
            }
            
            // Log activity
            $this->logActivity('section_create', "Created section {$sectionData['section_code']}");
            
            return ['success' => true, 'message' => 'Section created successfully.'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error creating section: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update section
     */
    public function updateSection(int $sectionId, array $sectionData): array
    {
        try {
            // Check if section exists
            $stmt = $this->pdo->prepare("SELECT section_code FROM sections WHERE id = ?");
            $stmt->execute([$sectionId]);
            $section = $stmt->fetch();
            
            if (!$section) {
                return ['success' => false, 'message' => 'Section not found.'];
            }
            
            // Update section
            $stmt = $this->pdo->prepare("
                UPDATE sections 
                SET section_name = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$sectionData['section_name'], $sectionId]);
            
            // Update instructor assignment
            if (isset($sectionData['instructor_id'])) {
                $this->assignInstructor($sectionId, $sectionData['instructor_id']);
            }
            
            // Log activity
            $this->logActivity('section_update', "Updated section {$section['section_code']}");
            
            return ['success' => true, 'message' => 'Section updated successfully.'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error updating section: ' . $e->getMessage()];
        }
    }
    
    /**
     * Delete section
     */
    public function deleteSection(int $sectionId): array
    {
        try {
            // Get section info for logging
            $stmt = $this->pdo->prepare("SELECT section_code FROM sections WHERE id = ?");
            $stmt->execute([$sectionId]);
            $section = $stmt->fetch();
            
            if (!$section) {
                return ['success' => false, 'message' => 'Section not found.'];
            }
            
            // Remove instructor assignments from junction table
            $stmt = $this->pdo->prepare("DELETE FROM instructor_sections WHERE section_id = ?");
            $stmt->execute([$sectionId]);
            
            // Remove student assignments (set section_id to NULL)
            $stmt = $this->pdo->prepare("UPDATE users SET section_id = NULL WHERE section_id = ? AND role = 'student'");
            $stmt->execute([$sectionId]);
            
            // Delete section
            $stmt = $this->pdo->prepare("DELETE FROM sections WHERE id = ?");
            $stmt->execute([$sectionId]);
            
            // Log activity
            $this->logActivity('section_delete', "Deleted section {$section['section_code']}");
            
            return ['success' => true, 'message' => 'Section deleted successfully.'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error deleting section: ' . $e->getMessage()];
        }
    }
    
    /**
     * Assign instructor to section (one instructor per section, using junction table)
     */
    public function assignInstructor(int $sectionId, ?int $instructorId): array
    {
        try {
            // Check if section exists
            $stmt = $this->pdo->prepare("SELECT section_code FROM sections WHERE id = ?");
            $stmt->execute([$sectionId]);
            $section = $stmt->fetch();
            
            if (!$section) {
                return ['success' => false, 'message' => 'Section not found.'];
            }
            
            // Remove all current instructor assignments for this section (only one instructor per section)
            $stmt = $this->pdo->prepare("DELETE FROM instructor_sections WHERE section_id = ?");
            $stmt->execute([$sectionId]);
            
            // Also clear any old assignments in users.section_id (migration cleanup)
            $stmt = $this->pdo->prepare("UPDATE users SET section_id = NULL WHERE section_id = ? AND role = 'instructor'");
            $stmt->execute([$sectionId]);
            
            // Assign new instructor if provided
            if ($instructorId) {
                // Check if instructor exists (can be either instructor or admin acting as instructor)
                $stmt = $this->pdo->prepare("SELECT full_name, role FROM users WHERE id = ? AND (role = 'instructor' OR role = 'admin')");
                $stmt->execute([$instructorId]);
                $instructor = $stmt->fetch();
                
                if (!$instructor) {
                    return ['success' => false, 'message' => 'Instructor not found.'];
                }
                
                // Insert into junction table (one instructor per section)
                $stmt = $this->pdo->prepare("
                    INSERT INTO instructor_sections (instructor_id, section_id) 
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE instructor_id = instructor_id
                ");
                $stmt->execute([$instructorId, $sectionId]);
                
                // Log activity
                $this->logActivity('instructor_assign', "Assigned instructor {$instructor['full_name']} to section {$section['section_code']}");
            } else {
                // Log activity
                $this->logActivity('instructor_unassign', "Removed instructor from section {$section['section_code']}");
            }
            
            return ['success' => true, 'message' => 'Instructor assignment updated successfully.'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error assigning instructor: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get students in a section
     */
    public function getStudentsInSection(int $sectionId): array
    {
        $sql = "
            SELECT id, school_id, full_name, email, contact
            FROM users 
            WHERE section_id = ? AND role = 'student'
            ORDER BY full_name ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$sectionId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Log activity
     */
    private function logActivity(string $action, string $description): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? 1; // Default to admin if session not available
            
            // Validate that the user exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            if (!$stmt->fetch()) {
                error_log("SectionService: User ID {$userId} not found in users table, skipping activity log");
                return;
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $action, $description]);
        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}
