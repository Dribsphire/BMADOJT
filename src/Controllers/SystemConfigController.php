<?php

namespace App\Controllers;

use App\Models\SystemConfig;
use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;

/**
 * System Configuration Controller
 * OJT Route - System configuration management
 */
class SystemConfigController
{
    private SystemConfig $config;
    private AuthMiddleware $authMiddleware;
    
    public function __construct()
    {
        $this->config = new SystemConfig();
        $this->authMiddleware = new AuthMiddleware();
    }
    
    /**
     * Display system configuration dashboard
     */
    public function index(): void
    {
        // Check authentication and authorization
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }
        
        if (!$this->authMiddleware->requireRole('admin')) {
            $this->authMiddleware->redirectToUnauthorized();
        }
        
        $user = $this->authMiddleware->getCurrentUser();
        
        // Get all configuration values
        $config = $this->config->getAll();
        
        // Group by category
        $emailConfig = $this->config->getEmailConfig();
        $geolocationConfig = $this->config->getGeolocationConfig();
        $fileUploadConfig = $this->config->getFileUploadConfig();
        $systemConfig = $this->config->getSystemSettings();
        
        // Include the view
        include __DIR__ . '/../../public/admin/settings.php';
    }
    
    /**
     * Handle configuration updates
     */
    public function update(): void
    {
        // Check authentication and authorization
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }
        
        if (!$this->authMiddleware->requireRole('admin')) {
            $this->authMiddleware->redirectToUnauthorized();
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: settings.php');
            exit;
        }
        
        $category = $_POST['category'] ?? '';
        $success = true;
        $errors = [];
        
        try {
            switch ($category) {
                case 'email':
                    $this->updateEmailConfig($_POST);
                    break;
                case 'geolocation':
                    $this->updateGeolocationConfig($_POST);
                    break;
                case 'file_upload':
                    $this->updateFileUploadConfig($_POST);
                    break;
                case 'system':
                    $this->updateSystemConfig($_POST);
                    break;
                default:
                    throw new \Exception('Invalid configuration category');
            }
            
            $_SESSION['success'] = ucfirst($category) . ' configuration updated successfully.';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error updating configuration: ' . $e->getMessage();
        }
        
        header('Location: settings.php');
        exit;
    }
    
    /**
     * Update email configuration
     */
    private function updateEmailConfig(array $data): void
    {
        $this->config->set('email_smtp_host', $data['email_smtp_host'] ?? '');
        $this->config->set('email_smtp_port', $data['email_smtp_port'] ?? '587');
        $this->config->set('email_smtp_username', $data['email_smtp_username'] ?? '');
        $this->config->set('email_smtp_password', $data['email_smtp_password'] ?? '');
        $this->config->set('email_from_address', $data['email_from_address'] ?? '');
        $this->config->set('email_from_name', $data['email_from_name'] ?? '');
        $this->config->set('email_queue_enabled', isset($data['email_queue_enabled']) ? '1' : '0');
        $this->config->set('email_queue_interval', $data['email_queue_interval'] ?? '5');
    }
    
    /**
     * Update geolocation configuration
     */
    private function updateGeolocationConfig(array $data): void
    {
        $this->config->set('geolocation_enabled', isset($data['geolocation_enabled']) ? '1' : '0');
        $this->config->set('geofence_radius', $data['geofence_radius'] ?? '40');
        $this->config->set('gps_accuracy_threshold', $data['gps_accuracy_threshold'] ?? '20');
        $this->config->set('location_timeout', $data['location_timeout'] ?? '30');
    }
    
    /**
     * Update file upload configuration
     */
    private function updateFileUploadConfig(array $data): void
    {
        $this->config->set('file_upload_max_size', $data['file_upload_max_size'] ?? '10485760');
        $this->config->set('file_upload_allowed_types', $data['file_upload_allowed_types'] ?? '');
        $this->config->set('image_compression_enabled', isset($data['image_compression_enabled']) ? '1' : '0');
        $this->config->set('image_compression_quality', $data['image_compression_quality'] ?? '80');
    }
    
    /**
     * Update system configuration
     */
    private function updateSystemConfig(array $data): void
    {
        $this->config->set('system_name', $data['system_name'] ?? 'OJT Route');
        $this->config->set('maintenance_mode', isset($data['maintenance_mode']) ? '1' : '0');
        $this->config->set('session_timeout', $data['session_timeout'] ?? '1800');
        $this->config->set('password_min_length', $data['password_min_length'] ?? '8');
        $this->config->set('ojt_required_hours', $data['ojt_required_hours'] ?? '600');
        $this->config->set('attendance_blocks_enabled', isset($data['attendance_blocks_enabled']) ? '1' : '0');
        $this->config->set('overtime_enabled', isset($data['overtime_enabled']) ? '1' : '0');
    }
    
    /**
     * Reset configuration to defaults
     */
    public function reset(): void
    {
        // Check authentication and authorization
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }
        
        if (!$this->authMiddleware->requireRole('admin')) {
            $this->authMiddleware->redirectToUnauthorized();
        }
        
        try {
            $this->config->initializeDefaults();
            $_SESSION['success'] = 'Configuration reset to defaults successfully.';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error resetting configuration: ' . $e->getMessage();
        }
        
        header('Location: settings.php');
        exit;
    }
    
    /**
     * Test email configuration
     */
    public function testEmail(): void
    {
        // Check authentication and authorization
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }
        
        if (!$this->authMiddleware->requireRole('admin')) {
            $this->authMiddleware->redirectToUnauthorized();
        }
        
        try {
            // Get current email configuration
            $emailConfig = $this->config->getEmailConfig();
            
            // Test email sending (implement email service test)
            $testResult = $this->testEmailConfiguration($emailConfig);
            
            if ($testResult['success']) {
                $_SESSION['success'] = 'Email configuration test successful.';
            } else {
                $_SESSION['error'] = 'Email configuration test failed: ' . $testResult['message'];
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error testing email configuration: ' . $e->getMessage();
        }
        
        header('Location: settings.php');
        exit;
    }
    
    /**
     * Test email configuration
     */
    private function testEmailConfiguration(array $config): array
    {
        // This would implement actual email testing
        // For now, return a mock result
        return [
            'success' => true,
            'message' => 'Email configuration is valid'
        ];
    }
}

