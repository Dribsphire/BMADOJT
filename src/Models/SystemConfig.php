<?php

namespace App\Models;

use App\Utils\Database;
use PDO;

/**
 * System Configuration Model
 * OJT Route - System-wide configuration management
 */
class SystemConfig
{
    private PDO $pdo;
    
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    
    /**
     * Get system configuration value
     */
    public function get(string $key, $default = null)
    {
        $stmt = $this->pdo->prepare("SELECT value FROM system_config WHERE config_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        
        return $result !== false ? $result : $default;
    }
    
    /**
     * Set system configuration value
     */
    public function set(string $key, $value): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO system_config (config_key, value, updated_at) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()
        ");
        
        return $stmt->execute([$key, $value]);
    }
    
    /**
     * Get all configuration values
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT config_key, value FROM system_config ORDER BY config_key");
        $results = $stmt->fetchAll();
        
        $config = [];
        foreach ($results as $row) {
            $config[$row['config_key']] = $row['value'];
        }
        
        return $config;
    }
    
    /**
     * Get configuration by category
     */
    public function getByCategory(string $category): array
    {
        $stmt = $this->pdo->prepare("
            SELECT config_key, value 
            FROM system_config 
            WHERE config_key LIKE ? 
            ORDER BY config_key
        ");
        $stmt->execute([$category . '%']);
        
        $results = $stmt->fetchAll();
        $config = [];
        foreach ($results as $row) {
            $config[$row['config_key']] = $row['value'];
        }
        
        return $config;
    }
    
    /**
     * Delete configuration key
     */
    public function delete(string $key): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM system_config WHERE config_key = ?");
        return $stmt->execute([$key]);
    }
    
    /**
     * Get email configuration
     */
    public function getEmailConfig(): array
    {
        return $this->getByCategory('email');
    }
    
    /**
     * Get geolocation configuration
     */
    public function getGeolocationConfig(): array
    {
        return $this->getByCategory('geolocation');
    }
    
    /**
     * Get file upload configuration
     */
    public function getFileUploadConfig(): array
    {
        return $this->getByCategory('file_upload');
    }
    
    /**
     * Get system settings
     */
    public function getSystemSettings(): array
    {
        return $this->getByCategory('system');
    }
    
    /**
     * Initialize default configuration
     */
    public function initializeDefaults(): void
    {
        $defaults = [
            // Email Settings
            'email_smtp_host' => 'smtp.gmail.com',
            'email_smtp_port' => '587',
            'email_smtp_username' => '',
            'email_smtp_password' => '',
            'email_from_address' => 'noreply@chmsu.edu.ph',
            'email_from_name' => 'OJT Route System',
            'email_queue_enabled' => '1',
            'email_queue_interval' => '5',
            
            // Geolocation Settings
            'geolocation_enabled' => '1',
            'geofence_radius' => '40',
            'gps_accuracy_threshold' => '20',
            'location_timeout' => '30',
            
            // File Upload Settings
            'file_upload_max_size' => '10485760', // 10MB
            'file_upload_allowed_types' => 'pdf,doc,docx,jpg,jpeg,png',
            'image_compression_enabled' => '1',
            'image_compression_quality' => '80',
            
            // System Settings
            'system_name' => 'OJT Route',
            'system_version' => '1.0.0',
            'maintenance_mode' => '0',
            'session_timeout' => '1800',
            'password_min_length' => '8',
            'ojt_required_hours' => '600',
            'attendance_blocks_enabled' => '1',
            'overtime_enabled' => '1',
            
            // Security Settings
            'login_attempts_limit' => '5',
            'login_lockout_duration' => '900', // 15 minutes
            'password_reset_enabled' => '1',
            'two_factor_enabled' => '0',
            
            // Notification Settings
            'notification_email_enabled' => '1',
            'notification_sms_enabled' => '0',
            'notification_push_enabled' => '0',
            'notification_reminder_days' => '7',
            
            // Backup Settings
            'backup_enabled' => '1',
            'backup_interval' => '24', // hours
            'backup_retention_days' => '30',
            'backup_location' => '../backups/'
        ];
        
        foreach ($defaults as $key => $value) {
            $this->set($key, $value);
        }
    }
}

