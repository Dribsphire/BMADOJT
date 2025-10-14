<?php

namespace App\Services;

use App\Utils\Database;
use PDO;

/**
 * System Maintenance Service
 * OJT Route - System maintenance and health monitoring
 */
class SystemMaintenanceService
{
    private PDO $pdo;
    
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    
    /**
     * Get system health status
     */
    public function getSystemHealth(): array
    {
        $health = [
            'database' => $this->checkDatabaseHealth(),
            'storage' => $this->checkStorageHealth(),
            'email' => $this->checkEmailHealth(),
            'users' => $this->checkUserHealth(),
            'attendance' => $this->checkAttendanceHealth(),
            'documents' => $this->checkDocumentHealth()
        ];
        
        $overall = 'healthy';
        foreach ($health as $component => $status) {
            if ($status['status'] === 'warning' || $status['status'] === 'error') {
                $overall = $status['status'];
                break;
            }
        }
        
        return [
            'overall' => $overall,
            'components' => $health,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Check database health
     */
    private function checkDatabaseHealth(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT 1");
            $stmt->fetch();
            
            // Check table counts
            $tables = ['users', 'sections', 'attendance_records', 'student_documents'];
            $tableCounts = [];
            
            foreach ($tables as $table) {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM $table");
                $tableCounts[$table] = $stmt->fetchColumn();
            }
            
            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
                'details' => $tableCounts
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }
    
    /**
     * Check storage health
     */
    private function checkStorageHealth(): array
    {
        $uploadDir = __DIR__ . '/../../uploads';
        $backupDir = __DIR__ . '/../../backups';
        
        $issues = [];
        
        // Check upload directory
        if (!is_dir($uploadDir)) {
            $issues[] = 'Upload directory does not exist';
        } elseif (!is_writable($uploadDir)) {
            $issues[] = 'Upload directory is not writable';
        }
        
        // Check backup directory
        if (!is_dir($backupDir)) {
            $issues[] = 'Backup directory does not exist';
        } elseif (!is_writable($backupDir)) {
            $issues[] = 'Backup directory is not writable';
        }
        
        // Check disk space
        $freeSpace = disk_free_space($uploadDir);
        $totalSpace = disk_total_space($uploadDir);
        $usagePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
        
        if ($usagePercent > 90) {
            $issues[] = 'Disk usage is above 90%';
        }
        
        if (empty($issues)) {
            return [
                'status' => 'healthy',
                'message' => 'Storage is healthy',
                'details' => [
                    'disk_usage' => round($usagePercent, 2) . '%',
                    'free_space' => $this->formatBytes($freeSpace)
                ]
            ];
        } else {
            return [
                'status' => count($issues) > 2 ? 'error' : 'warning',
                'message' => implode(', ', $issues),
                'details' => [
                    'disk_usage' => round($usagePercent, 2) . '%',
                    'free_space' => $this->formatBytes($freeSpace)
                ]
            ];
        }
    }
    
    /**
     * Check email health
     */
    private function checkEmailHealth(): array
    {
        try {
            // Check email queue
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'");
            $pendingEmails = $stmt->fetchColumn();
            
            // Check failed emails
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM email_queue WHERE status = 'failed' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $failedEmails = $stmt->fetchColumn();
            
            if ($failedEmails > 10) {
                return [
                    'status' => 'error',
                    'message' => 'High number of failed emails in last 24 hours',
                    'details' => [
                        'pending_emails' => $pendingEmails,
                        'failed_emails_24h' => $failedEmails
                    ]
                ];
            } elseif ($pendingEmails > 100) {
                return [
                    'status' => 'warning',
                    'message' => 'Large number of pending emails in queue',
                    'details' => [
                        'pending_emails' => $pendingEmails,
                        'failed_emails_24h' => $failedEmails
                    ]
                ];
            } else {
                return [
                    'status' => 'healthy',
                    'message' => 'Email system is healthy',
                    'details' => [
                        'pending_emails' => $pendingEmails,
                        'failed_emails_24h' => $failedEmails
                    ]
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Email health check failed: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }
    
    /**
     * Check user health
     */
    private function checkUserHealth(): array
    {
        try {
            // Check for users without profiles
            $stmt = $this->pdo->query("
                SELECT COUNT(*) FROM users u 
                LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                WHERE u.role = 'student' AND sp.id IS NULL
            ");
            $studentsWithoutProfiles = $stmt->fetchColumn();
            
            // Check for instructors without sections
            $stmt = $this->pdo->query("
                SELECT COUNT(*) FROM users 
                WHERE role = 'instructor' AND section_id IS NULL
            ");
            $instructorsWithoutSections = $stmt->fetchColumn();
            
            $issues = [];
            if ($studentsWithoutProfiles > 0) {
                $issues[] = "$studentsWithoutProfiles students without profiles";
            }
            if ($instructorsWithoutSections > 0) {
                $issues[] = "$instructorsWithoutSections instructors without sections";
            }
            
            if (empty($issues)) {
                return [
                    'status' => 'healthy',
                    'message' => 'User data is consistent',
                    'details' => [
                        'students_without_profiles' => $studentsWithoutProfiles,
                        'instructors_without_sections' => $instructorsWithoutSections
                    ]
                ];
            } else {
                return [
                    'status' => 'warning',
                    'message' => implode(', ', $issues),
                    'details' => [
                        'students_without_profiles' => $studentsWithoutProfiles,
                        'instructors_without_sections' => $instructorsWithoutSections
                    ]
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'User health check failed: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }
    
    /**
     * Check attendance health
     */
    private function checkAttendanceHealth(): array
    {
        try {
            // Check for orphaned attendance records
            $stmt = $this->pdo->query("
                SELECT COUNT(*) FROM attendance_records ar 
                LEFT JOIN users u ON ar.student_id = u.id 
                WHERE u.id IS NULL
            ");
            $orphanedRecords = $stmt->fetchColumn();
            
            // Check for incomplete attendance (time-in without time-out)
            $stmt = $this->pdo->query("
                SELECT COUNT(*) FROM attendance_records 
                WHERE time_in IS NOT NULL AND time_out IS NULL 
                AND DATE(time_in) < CURDATE()
            ");
            $incompleteRecords = $stmt->fetchColumn();
            
            $issues = [];
            if ($orphanedRecords > 0) {
                $issues[] = "$orphanedRecords orphaned attendance records";
            }
            if ($incompleteRecords > 0) {
                $issues[] = "$incompleteRecords incomplete attendance records";
            }
            
            if (empty($issues)) {
                return [
                    'status' => 'healthy',
                    'message' => 'Attendance data is consistent',
                    'details' => [
                        'orphaned_records' => $orphanedRecords,
                        'incomplete_records' => $incompleteRecords
                    ]
                ];
            } else {
                return [
                    'status' => 'warning',
                    'message' => implode(', ', $issues),
                    'details' => [
                        'orphaned_records' => $orphanedRecords,
                        'incomplete_records' => $incompleteRecords
                    ]
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Attendance health check failed: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }
    
    /**
     * Check document health
     */
    private function checkDocumentHealth(): array
    {
        try {
            // Check for missing files
            $stmt = $this->pdo->query("
                SELECT COUNT(*) FROM student_documents 
                WHERE submission_file_path IS NOT NULL 
                AND submission_file_path != ''
            ");
            $totalDocuments = $stmt->fetchColumn();
            
            $missingFiles = 0;
            if ($totalDocuments > 0) {
                $stmt = $this->pdo->query("
                    SELECT submission_file_path FROM student_documents 
                    WHERE submission_file_path IS NOT NULL 
                    AND submission_file_path != ''
                ");
                $documents = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($documents as $filePath) {
                    $fullPath = __DIR__ . '/../../' . $filePath;
                    if (!file_exists($fullPath)) {
                        $missingFiles++;
                    }
                }
            }
            
            if ($missingFiles > 0) {
                return [
                    'status' => 'warning',
                    'message' => "$missingFiles document files are missing",
                    'details' => [
                        'total_documents' => $totalDocuments,
                        'missing_files' => $missingFiles
                    ]
                ];
            } else {
                return [
                    'status' => 'healthy',
                    'message' => 'All document files are present',
                    'details' => [
                        'total_documents' => $totalDocuments,
                        'missing_files' => $missingFiles
                    ]
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Document health check failed: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }
    
    /**
     * Clean up old data
     */
    public function cleanupOldData(): array
    {
        $results = [];
        
        try {
            // Clean up old email queue entries (older than 30 days)
            $stmt = $this->pdo->prepare("
                DELETE FROM email_queue 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) 
                AND status IN ('sent', 'failed')
            ");
            $stmt->execute();
            $results['email_queue'] = $stmt->rowCount();
            
            // Clean up old activity logs (older than 90 days)
            $stmt = $this->pdo->prepare("
                DELETE FROM activity_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
            ");
            $stmt->execute();
            $results['activity_logs'] = $stmt->rowCount();
            
            // Clean up old sessions (older than 7 days)
            $stmt = $this->pdo->prepare("
                DELETE FROM user_sessions 
                WHERE last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute();
            $results['user_sessions'] = $stmt->rowCount();
            
            return [
                'success' => true,
                'message' => 'Cleanup completed successfully',
                'results' => $results
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Cleanup failed: ' . $e->getMessage(),
                'results' => $results
            ];
        }
    }
    
    /**
     * Optimize database
     */
    public function optimizeDatabase(): array
    {
        $results = [];
        
        try {
            $tables = ['users', 'sections', 'attendance_records', 'student_documents', 'email_queue', 'activity_logs'];
            
            foreach ($tables as $table) {
                $stmt = $this->pdo->prepare("OPTIMIZE TABLE $table");
                $stmt->execute();
                $results[$table] = 'optimized';
            }
            
            return [
                'success' => true,
                'message' => 'Database optimization completed',
                'results' => $results
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Database optimization failed: ' . $e->getMessage(),
                'results' => $results
            ];
        }
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

