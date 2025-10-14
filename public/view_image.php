<?php
/**
 * Image Viewer
 * Serves profile pictures from the uploads directory
 */

// Get the filename from the URL parameter
$filename = $_GET['file'] ?? '';

// Validate filename (security check)
if (empty($filename) || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
    http_response_code(404);
    exit('File not found');
}

// Construct the full file path (absolute path from project root)
$filePath = __DIR__ . '/../uploads/profiles/' . $filename;

// Check if file exists
if (!file_exists($filePath)) {
    // Debug: Log the file path and check
    error_log("File not found: $filePath");
    error_log("Current directory: " . getcwd());
    error_log("File path exists: " . (file_exists($filePath) ? 'YES' : 'NO'));
    
    http_response_code(404);
    exit('File not found: ' . $filePath);
}

// Get file info
$fileInfo = pathinfo($filePath);
$extension = strtolower($fileInfo['extension']);

// Set appropriate content type
switch ($extension) {
    case 'jpg':
    case 'jpeg':
        header('Content-Type: image/jpeg');
        break;
    case 'png':
        header('Content-Type: image/png');
        break;
    case 'gif':
        header('Content-Type: image/gif');
        break;
    case 'webp':
        header('Content-Type: image/webp');
        break;
    default:
        http_response_code(404);
        exit('Unsupported file type');
}

// Set cache headers
header('Cache-Control: public, max-age=3600');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

// Output the file
readfile($filePath);
?>
