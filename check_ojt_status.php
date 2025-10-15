<?php
require_once 'vendor/autoload.php';

$database = \App\Utils\Database::getInstance();

// Check student's OJT status
$stmt = $database->prepare("
    SELECT u.id, u.school_id, u.section_id, s.section_name, s.ojt_start_date, s.ojt_end_date,
           CASE 
               WHEN s.ojt_start_date IS NULL OR s.ojt_end_date IS NULL THEN 'not_set'
               WHEN CURDATE() < s.ojt_start_date THEN 'not_started'
               WHEN CURDATE() > s.ojt_end_date THEN 'ended'
               WHEN CURDATE() BETWEEN s.ojt_start_date AND s.ojt_end_date THEN 'active'
               ELSE 'unknown'
           END as ojt_status
    FROM users u 
    LEFT JOIN sections s ON u.section_id = s.id
    WHERE u.id = 1
");
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Student OJT Status:\n";
print_r($student);

// Check if section has OJT dates set
if ($student['ojt_start_date'] && $student['ojt_end_date']) {
    echo "OJT Period: " . $student['ojt_start_date'] . " to " . $student['ojt_end_date'] . "\n";
    echo "Current Date: " . date('Y-m-d') . "\n";
    echo "Status: " . $student['ojt_status'] . "\n";
} else {
    echo "OJT dates not set for section\n";
}
?>
