<?php
/**
 * Cron job to check for overdue documents and send notifications
 * Run this script daily to check for overdue documents
 * 
 * Usage: php cron/check_overdue_documents.php
 * Or add to crontab: 0 9 * * * php /path/to/cron/check_overdue_documents.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Utils/Database.php';
require_once __DIR__ . '/../src/Services/OverdueService.php';
require_once __DIR__ . '/../src/Services/EmailService.php';

use App\Services\OverdueService;
use App\Services\EmailService;

echo "=== Overdue Documents Check - " . date('Y-m-d H:i:s') . " ===\n\n";

try {
    $overdueService = new OverdueService();
    $emailService = new EmailService();
    
    // Get overdue documents
    $overdueDocuments = $overdueService->getOverdueDocumentsForNotification();
    
    echo "Found " . count($overdueDocuments) . " overdue documents\n\n";
    
    if (empty($overdueDocuments)) {
        echo "No overdue documents found. Exiting.\n";
        exit(0);
    }
    
    // Group overdue documents by student
    $overdueByStudent = [];
    foreach ($overdueDocuments as $doc) {
        $studentId = $doc['student_id'];
        if (!isset($overdueByStudent[$studentId])) {
            $overdueByStudent[$studentId] = [
                'student_name' => $doc['student_name'],
                'student_email' => $doc['student_email'],
                'documents' => []
            ];
        }
        $overdueByStudent[$studentId]['documents'][] = $doc;
    }
    
    echo "Sending notifications to " . count($overdueByStudent) . " students...\n\n";
    
    $notificationsSent = 0;
    $errors = [];
    
    // Send notifications to students
    foreach ($overdueByStudent as $studentId => $studentData) {
        try {
            echo "Sending notification to {$studentData['student_name']} ({$studentData['student_email']})...\n";
            
            // Send notification for each overdue document
            foreach ($studentData['documents'] as $doc) {
                $success = $emailService->sendOverdueNotification(
                    $studentData['student_email'],
                    $studentData['student_name'],
                    $doc['document_name'],
                    $doc['deadline']
                );
                
                if ($success) {
                    $notificationsSent++;
                    echo "  ✅ Notification sent for: {$doc['document_name']}\n";
                } else {
                    $errors[] = "Failed to send notification to {$studentData['student_name']} for {$doc['document_name']}";
                    echo "  ❌ Failed to send notification for: {$doc['document_name']}\n";
                }
            }
            
        } catch (Exception $e) {
            $errors[] = "Error sending notification to {$studentData['student_name']}: " . $e->getMessage();
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // Group overdue documents by instructor for summary emails
    $overdueByInstructor = [];
    foreach ($overdueDocuments as $doc) {
        $instructorId = $doc['uploaded_by'];
        if (!isset($overdueByInstructor[$instructorId])) {
            $overdueByInstructor[$instructorId] = [
                'instructor_name' => $doc['instructor_name'],
                'instructor_email' => $doc['instructor_email'],
                'documents' => []
            ];
        }
        $overdueByInstructor[$instructorId]['documents'][] = $doc;
    }
    
    echo "\nSending summary emails to " . count($overdueByInstructor) . " instructors...\n\n";
    
    // Send summary emails to instructors
    foreach ($overdueByInstructor as $instructorId => $instructorData) {
        try {
            echo "Sending summary to {$instructorData['instructor_name']} ({$instructorData['instructor_email']})...\n";
            
            $success = $emailService->sendOverdueSummaryToInstructor(
                $instructorData['instructor_email'],
                $instructorData['instructor_name'],
                $instructorData['documents']
            );
            
            if ($success) {
                echo "  ✅ Summary sent\n";
            } else {
                $errors[] = "Failed to send summary to {$instructorData['instructor_name']}";
                echo "  ❌ Failed to send summary\n";
            }
            
        } catch (Exception $e) {
            $errors[] = "Error sending summary to {$instructorData['instructor_name']}: " . $e->getMessage();
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Total overdue documents: " . count($overdueDocuments) . "\n";
    echo "Students notified: " . count($overdueByStudent) . "\n";
    echo "Instructors notified: " . count($overdueByInstructor) . "\n";
    echo "Notifications sent: $notificationsSent\n";
    echo "Errors: " . count($errors) . "\n";
    
    if (!empty($errors)) {
        echo "\nErrors:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
    
    echo "\nOverdue check completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
