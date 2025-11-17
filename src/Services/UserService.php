<?php

namespace App\Services;

use App\Models\User;
use App\Models\Section;
use App\Utils\Database;
use PDO;

/**
 * User Service
 * OJT Route - User management business logic
 */
class UserService
{
    private PDO $pdo;
    
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    
    /**
     * Get user statistics
     */
    public function getUserStatistics(): array
    {
        $stats = [];
        
        // Count users by role
        $stmt = $this->pdo->query("
            SELECT role, COUNT(*) as count 
            FROM users 
            GROUP BY role
        ");
        $roleStats = $stmt->fetchAll();
        
        foreach ($roleStats as $stat) {
            $stats[$stat['role']] = $stat['count'];
        }
        
        // Count total users
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM users");
        $stats['total'] = $stmt->fetchColumn();
        
        // Count active users (logged in within last 7 days)
        $stmt = $this->pdo->query("
            SELECT COUNT(DISTINCT user_id) as active 
            FROM activity_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stats['active'] = $stmt->fetchColumn();
        
        return $stats;
    }
    
    /**
     * Get all users with pagination, search, and filters
     */
    public function getAllUsers(int $limit = 20, int $offset = 0, string $search = '', string $role = '', string $section = '', string $status = '', bool $showArchived = false): array
    {
        $whereConditions = [];
        $params = [];
        
        // Filter by archived status (default: show only non-archived)
        if (!$showArchived) {
            $whereConditions[] = "(u.archived = 0 OR u.archived IS NULL)";
        }
        
        // Add search condition
        if (!empty($search)) {
            $whereConditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.school_id LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        // Add role filter
        if (!empty($role)) {
            $whereConditions[] = "u.role = ?";
            $params[] = $role;
        }
        
        // Add section filter
        if (!empty($section)) {
            $whereConditions[] = "u.section_id = ?";
            $params[] = $section;
        }
        
        // Add status filter (for now, all users are considered active)
        // This can be extended later to include actual status checking
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "
            SELECT u.*, s.section_name, s.section_code
            FROM users u
            LEFT JOIN sections s ON u.section_id = s.id
            $whereClause
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get total user count with filters
     */
    public function getTotalUsers(string $search = '', string $role = '', string $section = '', string $status = '', bool $showArchived = false): int
    {
        $whereConditions = [];
        $params = [];
        
        // Filter by archived status (default: show only non-archived)
        if (!$showArchived) {
            $whereConditions[] = "(u.archived = 0 OR u.archived IS NULL)";
        }
        
        // Add search condition
        if (!empty($search)) {
            $whereConditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.school_id LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        // Add role filter
        if (!empty($role)) {
            $whereConditions[] = "u.role = ?";
            $params[] = $role;
        }
        
        // Add section filter
        if (!empty($section)) {
            $whereConditions[] = "u.section_id = ?";
            $params[] = $section;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT COUNT(*) FROM users u $whereClause";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn();
    }
    
    /**
     * Get all sections
     */
    public function getAllSections(): array
    {
        $stmt = $this->pdo->query("
            SELECT s.*, 
                   GROUP_CONCAT(u.full_name SEPARATOR ', ') as instructor_names,
                   COUNT(u.id) as instructor_count
            FROM sections s
            LEFT JOIN users u ON s.id = u.section_id AND u.role = 'instructor'
            GROUP BY s.id
            ORDER BY s.section_code
        ");
        
        return $stmt->fetchAll();
    }
    
    /**
     * Process bulk registration from CSV
     */
    public function processBulkRegistration(string $userType, array $csvFile): array
    {
        // Validate file type
        $allowedTypes = ['text/csv', 'application/csv', 'text/plain'];
        if (!in_array($csvFile['type'], $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type. Please upload a CSV file.'];
        }
        
        // Read CSV file
        $csvData = [];
        if (($handle = fopen($csvFile['tmp_name'], 'r')) !== false) {
            $header = fgetcsv($handle); // Skip header row
            
            while (($data = fgetcsv($handle)) !== false) {
                $csvData[] = array_combine($header, $data);
            }
            fclose($handle);
        }
        
        if (empty($csvData)) {
            return ['success' => false, 'message' => 'CSV file is empty or invalid.'];
        }
        
        $successCount = 0;
        $errors = [];
        
        foreach ($csvData as $rowIndex => $row) {
            try {
                $userData = $this->prepareUserDataFromCSV($userType, $row);
                $result = $this->createUser($userData);
                
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errors[] = "Row " . ($rowIndex + 2) . ": " . $result['message'];
                }
            } catch (\Exception $e) {
                $errors[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
            }
        }
        
        return [
            'success' => $successCount > 0,
            'count' => $successCount,
            'message' => $successCount > 0 ? "Successfully registered {$successCount} users." : "No users were registered.",
            'errors' => $errors
        ];
    }
    
    /**
     * Prepare user data from CSV row
     */
    private function prepareUserDataFromCSV(string $userType, array $row): array
    {
        $requiredFields = ['school_id', 'email', 'full_name'];
        $userData = [
            'role' => $userType
        ];
        
        // Map CSV columns to user data
        foreach ($requiredFields as $field) {
            if (empty($row[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
            $userData[$field] = trim($row[$field]);
        }
        
        // Handle password - use from CSV if provided, otherwise use default
        if (!empty($row['password']) && trim($row['password']) !== '') {
            $password = trim($row['password']);
            // Validate password length if provided
            if (strlen($password) < 8) {
                throw new \Exception("Password must be at least 8 characters long.");
            }
            $userData['password'] = $password;
        } else {
            // Default password if not provided in CSV or left blank
            $userData['password'] = 'Password@2025';
        }
        
        // Optional fields
        $optionalFields = ['gender', 'contact', 'facebook_name'];
        foreach ($optionalFields as $field) {
            $userData[$field] = !empty($row[$field]) ? trim($row[$field]) : null;
        }
        
        // Handle section_code - convert to section_id
        $sectionId = null;
        if (!empty($row['section_code'])) {
            $sectionCode = trim($row['section_code']);
            // Look up section_id from section_code (case-insensitive)
            $stmt = $this->pdo->prepare("SELECT id, section_code FROM sections WHERE UPPER(section_code) = UPPER(?)");
            $stmt->execute([$sectionCode]);
            $section = $stmt->fetch();
            
            if (!$section) {
                throw new \Exception("Section code '{$sectionCode}' not found in database. Available sections: " . $this->getAvailableSectionCodes());
            }
            
            $sectionId = (int) $section['id'];
        }
        
        $userData['section_id'] = $sectionId;
        
        return $userData;
    }
    
    /**
     * Create a new user
     */
    public function createUser(array $userData): array
    {
        // Validate required fields
        $requiredFields = ['school_id', 'email', 'full_name', 'role', 'password'];
        foreach ($requiredFields as $field) {
            if (empty($userData[$field])) {
                return ['success' => false, 'message' => "Missing required field: {$field}"];
            }
        }
        
        // Check if school_id already exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE school_id = ?");
        $stmt->execute([$userData['school_id']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'School ID already exists.'];
        }
        
        // Check if email already exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$userData['email']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already exists.'];
        }
        
        // Hash password
        $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $this->pdo->prepare("
            INSERT INTO users (
                school_id, password_hash, email, full_name, role, 
                section_id, gender, contact, facebook_name
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $userData['school_id'],
            $passwordHash,
            $userData['email'],
            $userData['full_name'],
            $userData['role'],
            $userData['section_id'] ?? null,
            $userData['gender'] ?? null,
            $userData['contact'] ?? null,
            $userData['facebook_name'] ?? null
        ]);
        
        if ($result) {
            return ['success' => true, 'message' => 'User created successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to create user.'];
        }
    }
    
    /**
     * Assign section to user
     */
    public function assignSection(int $userId, ?int $sectionId): array
    {
        $stmt = $this->pdo->prepare("UPDATE users SET section_id = ? WHERE id = ?");
        $result = $stmt->execute([$sectionId, $userId]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Section assignment updated.'];
        } else {
            return ['success' => false, 'message' => 'Failed to update section assignment.'];
        }
    }
    
    /**
     * Delete user
     */
    public function deleteUser(int $userId): array
    {
        // Check if user exists
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }
        
        // Prevent deleting admin users
        if ($user['role'] === 'admin') {
            return ['success' => false, 'message' => 'Cannot delete admin users.'];
        }
        
        // Delete user (cascade will handle related records)
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        $result = $stmt->execute([$userId]);
        
        if ($result) {
            return ['success' => true, 'message' => 'User deleted successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete user.'];
        }
    }
    
    /**
     * Generate default password
     */
    private function generateDefaultPassword(): string
    {
        return 'Password@2024';
    }
    
    /**
     * Create a new section
     */
    public function createSection(array $sectionData): array
    {
        $requiredFields = ['section_code', 'section_name'];
        foreach ($requiredFields as $field) {
            if (empty($sectionData[$field])) {
                return ['success' => false, 'message' => "Missing required field: {$field}"];
            }
        }
        
        // Check if section code already exists
        $stmt = $this->pdo->prepare("SELECT id FROM sections WHERE section_code = ?");
        $stmt->execute([$sectionData['section_code']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Section code already exists.'];
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO sections (section_code, section_name) 
            VALUES (?, ?)
        ");
        
        $result = $stmt->execute([
            $sectionData['section_code'],
            $sectionData['section_name']
        ]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Section created successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to create section.'];
        }
    }
    
    /**
     * Get available section codes for error messages
     */
    private function getAvailableSectionCodes(): string
    {
        $stmt = $this->pdo->query("SELECT section_code FROM sections ORDER BY section_code");
        $sections = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return !empty($sections) ? implode(', ', $sections) : 'None';
    }
    
    /**
     * Archive all users except admins
     * This is used when a batch completes OJT and admin wants to start fresh
     */
    public function archiveAllUsers(int $archivedBy): array
    {
        try {
            // Start transaction
            $this->pdo->beginTransaction();
            
            // Archive all users except admins
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET archived = 1, 
                    archived_at = NOW(), 
                    archived_by = ?
                WHERE role != 'admin' 
                AND (archived = 0 OR archived IS NULL)
            ");
            $stmt->execute([$archivedBy]);
            $archivedCount = $stmt->rowCount();
            
            // Commit transaction
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => "Successfully archived {$archivedCount} users.",
                'count' => $archivedCount
            ];
        } catch (\Exception $e) {
            // Rollback on error
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => 'Error archiving users: ' . $e->getMessage(),
                'count' => 0
            ];
        }
    }
}
