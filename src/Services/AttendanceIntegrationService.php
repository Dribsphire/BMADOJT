<?php

namespace App\Services;

use App\Utils\Database;
use App\Utils\ActivityLogger;
use App\Middleware\AttendanceMiddleware;
use PDO;
use Exception;

/**
 * Attendance Integration Service
 * Handles integration between attendance system and other system components
 */
class AttendanceIntegrationService
{
    private PDO $pdo;
    private ActivityLogger $logger;
    private AttendanceMiddleware $attendanceMiddleware;
    private array $cache = [];

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        $this->logger = new ActivityLogger();
        $this->attendanceMiddleware = new AttendanceMiddleware();
    }

    /**
     * Integrate attendance system with document compliance
     * AC1: Document Compliance Integration
     */
    public function integrateWithDocumentCompliance(int $studentId): array
    {
        try {
            // Check document compliance using middleware
            $accessCheck = $this->attendanceMiddleware->checkAttendanceAccess($studentId);
            
            if (!$accessCheck['can_access']) {
                return [
                    'integrated' => false,
                    'blocked' => true,
                    'reason' => $accessCheck['reason'],
                    'message' => $accessCheck['message'],
                    'redirect_url' => $accessCheck['redirect_url'] ?? 'documents.php',
                    'compliance_data' => $accessCheck['compliance_data'] ?? null
                ];
            }

            return [
                'integrated' => true,
                'blocked' => false,
                'compliance_data' => $accessCheck['compliance_data'],
                'message' => 'Document compliance verified'
            ];

        } catch (Exception $e) {
            $this->logger->logActivity($studentId, 'document_compliance_integration_error', $e->getMessage());
            return [
                'integrated' => false,
                'blocked' => true,
                'reason' => 'system_error',
                'message' => 'Unable to verify document compliance'
            ];
        }
    }

    /**
     * Integrate with authentication system
     * AC2: User Authentication Integration
     */
    public function integrateWithAuthentication(int $userId, string $action = 'attendance'): array
    {
        try {
            // Validate session
            $sessionCheck = $this->attendanceMiddleware->validateAttendanceSession($userId);
            
            if (!$sessionCheck['valid']) {
                return [
                    'authenticated' => false,
                    'reason' => $sessionCheck['reason'],
                    'message' => $sessionCheck['message'],
                    'redirect_url' => $sessionCheck['redirect_url'] ?? 'login.php'
                ];
            }

            // Check concurrent access
            $concurrentCheck = $this->attendanceMiddleware->checkConcurrentAccess($userId, $action);
            if (!$concurrentCheck['allowed']) {
                return [
                    'authenticated' => true,
                    'blocked' => true,
                    'reason' => $concurrentCheck['reason'],
                    'message' => $concurrentCheck['message']
                ];
            }

            return [
                'authenticated' => true,
                'blocked' => false,
                'user_id' => $userId,
                'role' => $sessionCheck['role']
            ];

        } catch (Exception $e) {
            $this->logger->logActivity($userId, 'authentication_integration_error', $e->getMessage());
            return [
                'authenticated' => false,
                'reason' => 'system_error',
                'message' => 'Authentication integration failed'
            ];
        }
    }

    /**
     * Integrate with database system
     * AC3: Database Integration
     */
    public function integrateWithDatabase(callable $operation): array
    {
        try {
            return $this->attendanceMiddleware->handleAttendanceTransaction($operation);
        } catch (Exception $e) {
            $this->logger->logActivity(
                $_SESSION['user_id'] ?? 0, 
                'database_integration_error', 
                $e->getMessage()
            );
            
            return [
                'success' => false,
                'message' => 'Database integration failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Optimize database queries for attendance
     * AC4: System Performance
     */
    public function optimizeAttendanceQueries(int $studentId, string $queryType = 'attendance_data'): array
    {
        try {
            $cacheKey = "attendance_{$queryType}_{$studentId}";
            
            // Check cache first
            if (isset($this->cache[$cacheKey])) {
                $cacheData = $this->cache[$cacheKey];
                if (time() - $cacheData['timestamp'] < 300) { // 5 minutes cache
                    return [
                        'success' => true,
                        'data' => $cacheData['data'],
                        'cached' => true
                    ];
                }
            }

            // Execute optimized query based on type
            switch ($queryType) {
                case 'attendance_data':
                    $data = $this->getOptimizedAttendanceData($studentId);
                    break;
                case 'compliance_status':
                    $data = $this->getOptimizedComplianceStatus($studentId);
                    break;
                case 'recent_activity':
                    $data = $this->getOptimizedRecentActivity($studentId);
                    break;
                default:
                    throw new Exception("Unknown query type: {$queryType}");
            }

            // Cache the result
            $this->cache[$cacheKey] = [
                'data' => $data,
                'timestamp' => time()
            ];

            return [
                'success' => true,
                'data' => $data,
                'cached' => false
            ];

        } catch (Exception $e) {
            $this->logger->logActivity($studentId, 'query_optimization_error', $e->getMessage());
            return [
                'success' => false,
                'message' => 'Query optimization failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get optimized attendance data
     */
    private function getOptimizedAttendanceData(int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                ar.id,
                ar.date,
                ar.block_type,
                ar.time_in,
                ar.time_out,
                ar.hours_earned,
                ar.photo_path,
                ar.location_lat_in,
                ar.location_long_in,
                CASE 
                    WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 'completed'
                    WHEN ar.time_in IS NOT NULL AND ar.time_out IS NULL THEN 'incomplete'
                    ELSE 'missed'
                END as status
            FROM attendance_records ar
            WHERE ar.student_id = ?
            AND ar.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY ar.date DESC, ar.block_type
            LIMIT 50
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get optimized compliance status
     */
    private function getOptimizedComplianceStatus(int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT d.id) as total_required,
                COUNT(DISTINCT sd.document_id) as approved_count,
                ROUND(COUNT(DISTINCT sd.document_id) * 100.0 / COUNT(DISTINCT d.id), 2) as compliance_rate
            FROM documents d
            LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = ? AND sd.status = 'approved'
            WHERE d.is_required = 1
            AND (d.uploaded_for_section IS NULL OR d.uploaded_for_section = (
                SELECT section_id FROM users WHERE id = ?
            ))
        ");
        $stmt->execute([$studentId, $studentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get optimized recent activity
     */
    private function getOptimizedRecentActivity(int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                action,
                description,
                created_at
            FROM activity_logs
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Handle mobile performance optimization
     * AC4: System Performance
     */
    public function optimizeForMobile(int $studentId): array
    {
        try {
            // Reduce data payload for mobile
            $mobileData = [
                'attendance_summary' => $this->getMobileAttendanceSummary($studentId),
                'compliance_status' => $this->getMobileComplianceStatus($studentId),
                'recent_activity' => $this->getMobileRecentActivity($studentId)
            ];

            return [
                'success' => true,
                'data' => $mobileData,
                'optimized' => true
            ];

        } catch (Exception $e) {
            $this->logger->logActivity($studentId, 'mobile_optimization_error', $e->getMessage());
            return [
                'success' => false,
                'message' => 'Mobile optimization failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get mobile-optimized attendance summary
     */
    private function getMobileAttendanceSummary(int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_records,
                COUNT(CASE WHEN time_in IS NOT NULL AND time_out IS NOT NULL THEN 1 END) as completed,
                SUM(hours_earned) as total_hours,
                MAX(date) as last_attendance
            FROM attendance_records
            WHERE student_id = ?
            AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get mobile-optimized compliance status
     */
    private function getMobileComplianceStatus(int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT d.id) as required,
                COUNT(DISTINCT sd.document_id) as approved
            FROM documents d
            LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = ? AND sd.status = 'approved'
            WHERE d.is_required = 1
        ");
        $stmt->execute([$studentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $complianceRate = $result['required'] > 0 ? 
            round(($result['approved'] / $result['required']) * 100, 1) : 100;

        return [
            'required' => $result['required'],
            'approved' => $result['approved'],
            'compliance_rate' => $complianceRate,
            'compliant' => $complianceRate >= 100
        ];
    }

    /**
     * Get mobile-optimized recent activity
     */
    private function getMobileRecentActivity(int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                action,
                DATE(created_at) as activity_date,
                COUNT(*) as count
            FROM activity_logs
            WHERE user_id = ?
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY action, DATE(created_at)
            ORDER BY activity_date DESC
            LIMIT 5
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Handle GPS and camera operations efficiently
     * AC4: System Performance
     */
    public function handleLocationAndMediaOperations(int $studentId, array $locationData, string $mediaType = 'photo'): array
    {
        try {
            // Validate location data
            if (!isset($locationData['latitude']) || !isset($locationData['longitude'])) {
                return [
                    'success' => false,
                    'message' => 'Location data required'
                ];
            }

            // Check location accuracy
            $accuracy = $this->validateLocationAccuracy($locationData);
            if (!$accuracy['valid']) {
                return [
                    'success' => false,
                    'message' => 'Location accuracy insufficient: ' . $accuracy['message']
                ];
            }

            // Handle media optimization
            $mediaResult = $this->optimizeMediaOperation($mediaType, $studentId);
            if (!$mediaResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Media operation failed: ' . $mediaResult['message']
                ];
            }

            return [
                'success' => true,
                'location_valid' => true,
                'media_optimized' => true,
                'data' => [
                    'location' => $locationData,
                    'media' => $mediaResult['data']
                ]
            ];

        } catch (Exception $e) {
            $this->logger->logActivity($studentId, 'location_media_error', $e->getMessage());
            return [
                'success' => false,
                'message' => 'Location/media operation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate location accuracy
     */
    private function validateLocationAccuracy(array $locationData): array
    {
        $lat = $locationData['latitude'];
        $lng = $locationData['longitude'];
        $accuracy = $locationData['accuracy'] ?? 100;

        // Check if coordinates are valid
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return [
                'valid' => false,
                'message' => 'Invalid coordinates'
            ];
        }

        // Check accuracy (should be within 100 meters)
        if ($accuracy > 100) {
            return [
                'valid' => false,
                'message' => 'Location accuracy too low'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Location data valid'
        ];
    }

    /**
     * Optimize media operations
     */
    private function optimizeMediaOperation(string $mediaType, int $studentId): array
    {
        try {
            switch ($mediaType) {
                case 'photo':
                    return $this->optimizePhotoOperation($studentId);
                case 'video':
                    return $this->optimizeVideoOperation($studentId);
                default:
                    return [
                        'success' => false,
                        'message' => 'Unsupported media type'
                    ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Media optimization failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Optimize photo operations
     */
    private function optimizePhotoOperation(int $studentId): array
    {
        // Set photo quality and size limits for mobile
        $photoConfig = [
            'max_width' => 1920,
            'max_height' => 1080,
            'quality' => 85,
            'format' => 'jpeg'
        ];

        return [
            'success' => true,
            'data' => $photoConfig
        ];
    }

    /**
     * Optimize video operations
     */
    private function optimizeVideoOperation(int $studentId): array
    {
        // Set video quality and size limits for mobile
        $videoConfig = [
            'max_duration' => 30, // seconds
            'max_size' => 10 * 1024 * 1024, // 10MB
            'format' => 'mp4'
        ];

        return [
            'success' => true,
            'data' => $videoConfig
        ];
    }

    /**
     * Comprehensive error handling
     * AC5: Error Handling and Logging
     */
    public function handleSystemError(Exception $e, string $context, array $additionalData = []): array
    {
        $errorId = uniqid('att_integration_error_');
        
        // Log error with context
        $this->logger->logActivity(
            $_SESSION['user_id'] ?? 0,
            'attendance_integration_error',
            "Error ID: {$errorId}, Context: {$context}, Message: " . $e->getMessage() . 
            ", Additional Data: " . json_encode($additionalData)
        );

        // Log to system log
        error_log("Attendance Integration Error [{$errorId}]: {$context} - " . $e->getMessage());

        return [
            'success' => false,
            'error_id' => $errorId,
            'message' => 'System integration error occurred',
            'context' => $context,
            'support_message' => 'Contact support with Error ID: ' . $errorId
        ];
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'cache_entries' => count($this->cache),
            'cache_size' => strlen(serialize($this->cache)),
            'cache_hit_rate' => $this->calculateCacheHitRate()
        ];
    }

    /**
     * Calculate cache hit rate
     */
    private function calculateCacheHitRate(): float
    {
        // This would be implemented with proper cache hit/miss tracking
        return 0.0;
    }
}
