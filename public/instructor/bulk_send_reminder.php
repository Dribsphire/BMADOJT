<?php

/**
 * Bulk Send Reminder
 * OJT Route - Send attendance reminders to multiple students
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;

// Start session
session_start();
date_default_timezone_set('Asia/Manila');

// Set JSON header
header('Content-Type: application/json');

try {
    // Initialize authentication
    $authService = new AuthenticationService();
    $authMiddleware = new AuthMiddleware();

    // Check authentication and authorization
    if (!$authMiddleware->check()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }

    if (!$authMiddleware->requireRole('instructor')) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }

    // Get current user
    $user = $authMiddleware->getCurrentUser();

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['student_ids']) || !is_array($input['student_ids'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid student IDs']);
        exit;
    }

    $studentIds = array_map('intval', $input['student_ids']);
    $pdo = Database::getInstance();

    // Verify students belong to instructor's section
    $placeholders = str_repeat('?,', count($studentIds) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT u.id, u.school_id, u.full_name, u.email
        FROM users u
        INNER JOIN sections s ON u.section_id = s.id
        WHERE u.id IN ($placeholders) AND s.instructor_id = ? AND u.role = 'student'
    ");
    $params = array_merge($studentIds, [$user->id]);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        echo json_encode(['success' => false, 'message' => 'No valid students found']);
        exit;
    }

    // Get instructor's section info
    $stmt = $pdo->prepare("
        SELECT section_name, section_code
        FROM sections
        WHERE instructor_id = ?
    ");
    $stmt->execute([$user->id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);

    $reminderCount = 0;
    $errors = [];

    foreach ($students as $student) {
        try {
            // Create reminder message
            $message = "Hello {$student['full_name']},\n\n";
            $message .= "This is a friendly reminder about your OJT attendance.\n\n";
            $message .= "Please ensure you:\n";
            $message .= "• Time in and out properly for each attendance block\n";
            $message .= "• Submit any required documents on time\n";
            $message .= "• Contact your instructor if you have any concerns\n\n";
            $message .= "Section: {$section['section_name']} ({$section['section_code']})\n";
            $message .= "Instructor: {$user->full_name}\n\n";
            $message .= "Best regards,\n";
            $message .= "OJT Route System";

            // Log the reminder activity
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, details, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user->id,
                'bulk_reminder_sent',
                "Sent attendance reminder to student {$student['school_id']} ({$student['full_name']})"
            ]);

            $reminderCount++;
        } catch (Exception $e) {
            $errors[] = "Failed to send reminder to {$student['full_name']}: " . $e->getMessage();
        }
    }

    echo json_encode([
        'success' => true,
        'count' => $reminderCount,
        'errors' => $errors,
        'message' => "Reminders sent to {$reminderCount} students successfully"
    ]);

} catch (Exception $e) {
    error_log("Bulk reminder error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
