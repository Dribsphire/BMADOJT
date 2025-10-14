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
    public function getAllUsers(int $limit = 20, int $offset = 0, string $search = '', string $role = '', string $section = '', string $status = ''): array
    {
        $whereConditions = [];
        $params = [];
        
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
    public function getTotalUsers(string $search = '', string $role = '', string $section = '', string $status = ''): int
    {
        $whereConditions = [];
        $params = [];
        
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
            'role' => $userType,
            'password' => $this->generateDefaultPassword()
        ];
        
        // Map CSV columns to user data
        foreach ($requiredFields as $field) {
            if (empty($row[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
            $userData[$field] = trim($row[$field]);
        }
        
        // Optional fields
        $optionalFields = ['gender', 'contact', 'facebook_name', 'section_id'];
        foreach ($optionalFields as $field) {
            $userData[$field] = !empty($row[$field]) ? trim($row[$field]) : null;
        }
        
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
}
