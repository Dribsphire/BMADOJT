<?php

namespace App\Services;

use App\Utils\Database;
use PDO;

/**
 * File Upload Service
 * Handles file uploads for the OJT Route system
 */
class FileUploadService
{
    private PDO $pdo;
    private array $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private int $maxFileSize = 5 * 1024 * 1024; // 5MB
    private string $uploadPath;
    
    public function __construct()
    {
        $this->pdo = Database::getInstance();
        
        // Set absolute path to uploads directory
        $this->uploadPath = __DIR__ . '/../../uploads/profiles/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }
    
    /**
     * Upload profile picture
     */
    public function uploadProfilePicture(array $file, int $userId): array
    {
        try {
            // Debug: Log upload attempt
            error_log("Upload attempt - User ID: $userId, File: " . ($file['name'] ?? 'unknown'));
            
            // Validate file
            $validation = $this->validateImageFile($file);
            if (!$validation['success']) {
                error_log("Upload validation failed: " . $validation['message']);
                return $validation;
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
            $filepath = $this->uploadPath . $filename;
            
            error_log("Generated filename: $filename, Filepath: $filepath");
            
            // Compress and resize image
            $compressed = $this->compressImage($file['tmp_name'], $filepath);
            if (!$compressed) {
                error_log("Image compression failed for: $filepath");
                return [
                    'success' => false,
                    'message' => 'Failed to process image'
                ];
            }
            
            // Check if file was actually created
            if (!file_exists($filepath)) {
                error_log("File was not created: $filepath");
                return [
                    'success' => false,
                    'message' => 'File was not saved'
                ];
            }
            
            error_log("File created successfully: $filepath");
            
            // Update user profile picture in database with full path
            $profilePicturePath = 'uploads/profiles/' . $filename;
            $stmt = $this->pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->execute([$profilePicturePath, $userId]);
            
            error_log("Database updated with full path: $profilePicturePath");
            
            return [
                'success' => true,
                'message' => 'Profile picture uploaded successfully',
                'filename' => $filename,
                'filepath' => $filepath,
                'profile_picture_path' => $profilePicturePath
            ];
            
        } catch (Exception $e) {
            error_log("Upload exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate image file
     */
    private function validateImageFile(array $file): array
    {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return [
                'success' => false,
                'message' => 'No file uploaded'
            ];
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return [
                'success' => false,
                'message' => 'File size too large. Maximum size is 5MB.'
            ];
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedImageTypes)) {
            return [
                'success' => false,
                'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.'
            ];
        }
        
        // Check if it's actually an image (only if GD is available)
        if (extension_loaded('gd')) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                return [
                    'success' => false,
                    'message' => 'File is not a valid image'
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Compress and resize image
     */
    private function compressImage(string $sourcePath, string $destinationPath): bool
    {
        try {
            // Check if GD extension is available
            if (!extension_loaded('gd')) {
                // Fallback: just copy the file without compression
                return copy($sourcePath, $destinationPath);
            }
            
            $imageInfo = getimagesize($sourcePath);
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $mimeType = $imageInfo['mime'];
            
            // Create image resource
            switch ($mimeType) {
                case 'image/jpeg':
                    $sourceImage = imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $sourceImage = imagecreatefrompng($sourcePath);
                    break;
                case 'image/gif':
                    $sourceImage = imagecreatefromgif($sourcePath);
                    break;
                case 'image/webp':
                    $sourceImage = imagecreatefromwebp($sourcePath);
                    break;
                default:
                    return false;
            }
            
            if (!$sourceImage) {
                return false;
            }
            
            // Calculate new dimensions (max 300x300)
            $maxSize = 300;
            if ($width > $height) {
                $newWidth = $maxSize;
                $newHeight = ($height / $width) * $maxSize;
            } else {
                $newHeight = $maxSize;
                $newWidth = ($width / $height) * $maxSize;
            }
            
            // Create new image
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency for PNG and GIF
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
            }
            
            // Resize image
            imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            
            // Save compressed image
            $result = false;
            switch ($mimeType) {
                case 'image/jpeg':
                    $result = imagejpeg($newImage, $destinationPath, 85); // 85% quality
                    break;
                case 'image/png':
                    $result = imagepng($newImage, $destinationPath, 8); // 8 compression level
                    break;
                case 'image/gif':
                    $result = imagegif($newImage, $destinationPath);
                    break;
                case 'image/webp':
                    $result = imagewebp($newImage, $destinationPath, 85); // 85% quality
                    break;
            }
            
            // Clean up
            imagedestroy($sourceImage);
            imagedestroy($newImage);
            
            return $result;
            
        } catch (Exception $e) {
            // Fallback: just copy the file without compression
            return copy($sourcePath, $destinationPath);
        }
    }
    
    /**
     * Delete profile picture
     */
    public function deleteProfilePicture(int $userId): array
    {
        try {
            // Get current profile picture
            $stmt = $this->pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $user['profile_picture']) {
                // Extract filename from full path if needed
                $filename = basename($user['profile_picture']);
                $filepath = $this->uploadPath . $filename;
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                
                // Update database
                $stmt = $this->pdo->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
                $stmt->execute([$userId]);
            }
            
            return [
                'success' => true,
                'message' => 'Profile picture deleted successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Delete failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get profile picture URL
     */
    public function getProfilePictureUrl(?string $profilePicturePath): string
    {
        if (!$profilePicturePath) {
            // Return default avatar with relative path
            return '../assets/images/default-avatar.svg';
        }
        
        // Check if it's already a full path or just filename
        if (strpos($profilePicturePath, 'uploads/profiles/') === 0) {
            // It's a full path like 'uploads/profiles/profile_4_1760828704.png'
            $filename = basename($profilePicturePath);
        } else {
            // It's just a filename like 'profile_4_1760828704.png'
            $filename = $profilePicturePath;
        }
        
        // Check if file exists
        if (!file_exists($this->uploadPath . $filename)) {
            return '../assets/images/default-avatar.svg';
        }
        
        // Use relative path to view_image.php
        return '../view_image.php?file=' . urlencode($filename);
    }
}
