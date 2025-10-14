<?php

namespace App\Models;

use App\Utils\Database;
use PDO;

/**
 * User Model
 * OJT Route - User entity and database operations
 */
class User
{
    public int $id;
    public string $school_id;
    public string $email;
    public string $full_name;
    public string $role;
    public ?int $section_id;
    public ?string $profile_picture;
    public ?string $gender;
    public ?string $contact;
    public ?string $facebook_name;
    public ?string $created_at;
    public ?string $updated_at;
    
    /**
     * Find user by school ID
     */
    public static function findBySchoolId(string $schoolId): ?self
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE school_id = ?");
        $stmt->execute([$schoolId]);
        
        $data = $stmt->fetch();
        return $data ? self::fromArray($data) : null;
    }
    
    /**
     * Find user by email
     */
    public static function findByEmail(string $email): ?self
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        $data = $stmt->fetch();
        return $data ? self::fromArray($data) : null;
    }
    
    /**
     * Find user by ID
     */
    public static function findById(int $id): ?self
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        $data = $stmt->fetch();
        return $data ? self::fromArray($data) : null;
    }
    
    /**
     * Verify password
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->getPasswordHash());
    }
    
    /**
     * Get password hash (private method)
     */
    private function getPasswordHash(): string
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$this->id]);
        
        return $stmt->fetchColumn();
    }
    
    /**
     * Create user from array data
     */
    public static function fromArray(array $data): self
    {
        $user = new self();
        $user->id = (int) $data['id'];
        $user->school_id = $data['school_id'];
        $user->email = $data['email'];
        $user->full_name = $data['full_name'];
        $user->role = $data['role'];
        $user->section_id = $data['section_id'] ? (int) $data['section_id'] : null;
        $user->profile_picture = $data['profile_picture'];
        $user->gender = $data['gender'];
        $user->contact = $data['contact'];
        $user->facebook_name = $data['facebook_name'];
        $user->created_at = $data['created_at'];
        $user->updated_at = $data['updated_at'];
        
        return $user;
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
    
    /**
     * Check if user is instructor
     */
    public function isInstructor(): bool
    {
        return $this->role === 'instructor';
    }
    
    /**
     * Check if user is student
     */
    public function isStudent(): bool
    {
        return $this->role === 'student';
    }
    
    /**
     * Get user's display name
     */
    public function getDisplayName(): string
    {
        return $this->full_name;
    }
    
    /**
     * Get user's role display name
     */
    public function getRoleDisplayName(): string
    {
        return match($this->role) {
            'admin' => 'Administrator',
            'instructor' => 'Instructor',
            'student' => 'Student',
            default => 'Unknown'
        };
    }
}
