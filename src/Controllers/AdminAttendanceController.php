<?php

namespace App\Controllers;

use App\Services\AdminAttendanceService;
use App\Utils\Database;
use App\Utils\AdminAccess;
use PDO;

class AdminAttendanceController
{
    private $pdo;
    private $attendanceService;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        $this->attendanceService = new AdminAttendanceService($this->pdo);
    }

    /**
     * Handle attendance record override
     */
    public function overrideAttendanceRecord($recordId, $newData)
    {
        try {
            $this->pdo->beginTransaction();

            // Validate record exists
            $stmt = $this->pdo->prepare("SELECT * FROM attendance_records WHERE id = ?");
            $stmt->execute([$recordId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                throw new \Exception("Attendance record not found");
            }

            // Update record
            $updateFields = [];
            $params = [];

            if (isset($newData['time_in'])) {
                $updateFields[] = "time_in = ?";
                $params[] = $newData['time_in'];
            }

            if (isset($newData['time_out'])) {
                $updateFields[] = "time_out = ?";
                $params[] = $newData['time_out'];
            }

            if (isset($newData['hours_earned'])) {
                $updateFields[] = "hours_earned = ?";
                $params[] = $newData['hours_earned'];
            }

            if (isset($newData['location_lat_in'])) {
                $updateFields[] = "location_lat_in = ?";
                $params[] = $newData['location_lat_in'];
            }

            if (isset($newData['location_long_in'])) {
                $updateFields[] = "location_long_in = ?";
                $params[] = $newData['location_long_in'];
            }

            $updateFields[] = "updated_at = NOW()";
            $params[] = $recordId;

            $sql = "UPDATE attendance_records SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            // Log the override action
            $this->logActivity($_SESSION['user_id'], 'override_attendance', "Override attendance record ID: {$recordId}");

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Attendance record updated successfully'];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Handle bulk attendance corrections
     */
    public function bulkAttendanceCorrection($corrections)
    {
        try {
            $this->pdo->beginTransaction();

            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($corrections as $correction) {
                try {
                    $result = $this->overrideAttendanceRecord($correction['record_id'], $correction['data']);
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $errorCount++;
                        $errors[] = "Record {$correction['record_id']}: {$result['message']}";
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = "Record {$correction['record_id']}: {$e->getMessage()}";
                }
            }

            $this->pdo->commit();

            return [
                'success' => $errorCount === 0,
                'message' => "Bulk correction completed. Success: {$successCount}, Errors: {$errorCount}",
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create system-wide attendance announcement
     */
    public function createAttendanceAnnouncement($title, $message, $targetAudience = 'all')
    {
        try {
            $this->pdo->beginTransaction();

            // Insert announcement
            $stmt = $this->pdo->prepare("
                INSERT INTO announcements (title, message, target_audience, created_by, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$title, $message, $targetAudience, $_SESSION['user_id']]);

            $announcementId = $this->pdo->lastInsertId();

            // Log the action
            $this->logActivity($_SESSION['user_id'], 'create_announcement', "Created attendance announcement: {$title}");

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Announcement created successfully', 'id' => $announcementId];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get attendance policy settings
     */
    public function getAttendancePolicy()
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM system_config 
            WHERE config_key LIKE 'attendance_%'
        ");
        $stmt->execute();
        $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $policyData = [];
        foreach ($policies as $policy) {
            $policyData[$policy['config_key']] = $policy['config_value'];
        }

        return $policyData;
    }

    /**
     * Update attendance policy
     */
    public function updateAttendancePolicy($policyData)
    {
        try {
            $this->pdo->beginTransaction();

            foreach ($policyData as $key => $value) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO system_config (config_key, config_value, updated_at)
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()
                ");
                $stmt->execute([$key, $value]);
            }

            // Log the action
            $this->logActivity($_SESSION['user_id'], 'update_policy', "Updated attendance policy settings");

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Attendance policy updated successfully'];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create data backup
     */
    public function createDataBackup($backupType = 'attendance')
    {
        try {
            $backupFileName = 'attendance_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backupPath = __DIR__ . '/../../backups/' . $backupFileName;

            // Create backups directory if it doesn't exist
            if (!is_dir(dirname($backupPath))) {
                mkdir(dirname($backupPath), 0755, true);
            }

            $tables = ['attendance_records', 'users', 'sections', 'student_profiles', 'activity_logs'];

            $backupContent = "-- Attendance System Backup\n";
            $backupContent .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";

            foreach ($tables as $table) {
                // Get table structure
                $stmt = $this->pdo->query("SHOW CREATE TABLE {$table}");
                $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
                $backupContent .= $createTable['Create Table'] . ";\n\n";

                // Get table data
                $stmt = $this->pdo->query("SELECT * FROM {$table}");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    $columns = array_keys($rows[0]);
                    $backupContent .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES\n";

                    $values = [];
                    foreach ($rows as $row) {
                        $rowValues = [];
                        foreach ($row as $value) {
                            $rowValues[] = $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                        }
                        $values[] = "(" . implode(', ', $rowValues) . ")";
                    }
                    $backupContent .= implode(",\n", $values) . ";\n\n";
                }
            }

            file_put_contents($backupPath, $backupContent);

            // Log the action
            $this->logActivity($_SESSION['user_id'], 'create_backup', "Created data backup: {$backupFileName}");

            return ['success' => true, 'message' => 'Backup created successfully', 'filename' => $backupFileName];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Restore data from backup
     */
    public function restoreDataBackup($backupFile)
    {
        try {
            $backupPath = __DIR__ . '/../../backups/' . $backupFile;

            if (!file_exists($backupPath)) {
                throw new \Exception("Backup file not found");
            }

            $this->pdo->beginTransaction();

            // Read and execute backup file
            $backupContent = file_get_contents($backupPath);
            $statements = explode(';', $backupContent);

            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement) && !str_starts_with($statement, '--')) {
                    $this->pdo->exec($statement);
                }
            }

            // Log the action
            $this->logActivity($_SESSION['user_id'], 'restore_backup', "Restored data from backup: {$backupFile}");

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Data restored successfully'];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Log activity
     */
    private function logActivity($userId, $action, $description)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $action, $description]);
    }
}
