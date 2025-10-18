<?php
require_once '../../vendor/autoload.php';

use App\Middleware\AuthMiddleware;

// Authentication
$auth = new AuthMiddleware();
if (!$auth->check() || !$auth->requireRole('student')) {
    $auth->redirectToLogin();
}

$file = $_GET['file'] ?? '';
if (empty($file)) {
    die('No file specified');
}

// Clean the file path - remove double extensions
$file = preg_replace('/\.pdf\.pdf$/', '.pdf', $file);
$file = preg_replace('/\.doc\.doc$/', '.doc', $file);
$file = preg_replace('/\.docx\.docx$/', '.docx', $file);

// Simple path resolution - try multiple variations
$fullPath = null;
$possiblePaths = [
    '../../' . $file,
    '../' . $file,
    $file
];

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $fullPath = $path;
        break;
    }
}

// If file doesn't exist, try with double extension (common issue)
if (!$fullPath) {
    $doubleExtFile = preg_replace('/\.pdf$/', '.pdf.pdf', $file);
    $doubleExtFile = preg_replace('/\.doc$/', '.doc.doc', $doubleExtFile);
    $doubleExtFile = preg_replace('/\.docx$/', '.docx.docx', $doubleExtFile);
    
    $possiblePaths = [
        '../../' . $doubleExtFile,
        '../' . $doubleExtFile,
        $doubleExtFile
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $fullPath = $path;
            break;
        }
    }
}

// If still not found, try to find similar files in templates directory
if (!$fullPath) {
    $templatesDir = '../../uploads/templates/';
    if (is_dir($templatesDir)) {
        $files = scandir($templatesDir);
        $fileName = basename($file, '.pdf');
        
        foreach ($files as $templateFile) {
            if (strpos($templateFile, $fileName) !== false && pathinfo($templateFile, PATHINFO_EXTENSION) === 'pdf') {
                $fullPath = $templatesDir . $templateFile;
                break;
            }
        }
    }
}

if (!$fullPath) {
    die('File not found: ' . htmlspecialchars($file));
}

// Get file extension
$extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

// Set headers for PDF
if ($extension === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($file) . '"');
    header('Cache-Control: public, max-age=3600');
} else {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
}

// Output file
readfile($fullPath);
?>
