<?php

namespace App\Services;

use App\Utils\Config;
use App\Utils\Security;

class FileManager
{
    private $uploadDir;
    private $maxFileSize;
    private $allowedTypes;

    public function __construct()
    {
        $this->uploadDir = __DIR__ . '/../../public/uploads/';
        $this->maxFileSize = Config::get('MAX_FILE_SIZE', 10485760); // 10MB default
        $this->allowedTypes = explode(',', Config::get('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,gif,pdf,txt'));
        
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function uploadFile($file, $userId)
    {
        if (!$this->validateFile($file)) {
            throw new \Exception('Invalid file');
        }

        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileName = $userId . '_' . time() . '_' . Security::generateToken(8) . '.' . $fileExtension;
        $filePath = $this->uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new \Exception('Failed to upload file');
        }

        // Store relative path for database
        return 'uploads/' . $fileName;
    }

    private function validateFile($file)
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return false;
        }

        // Check file type
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $this->allowedTypes)) {
            return false;
        }

        // Additional security checks
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'text/plain',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        if (!in_array($mimeType, $allowedMimes)) {
            return false;
        }

        return true;
    }

    public function deleteFile($filePath)
    {
        $fullPath = __DIR__ . '/../../public/' . $filePath;
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }

    public function getFileInfo($filePath)
    {
        $fullPath = __DIR__ . '/../../public/' . $filePath;
        if (!file_exists($fullPath)) {
            return false;
        }

        return [
            'size' => filesize($fullPath),
            'type' => mime_content_type($fullPath),
            'modified' => filemtime($fullPath)
        ];
    }

    public function isImageFile($filePath)
    {
        $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $info = $this->getFileInfo($filePath);
        return $info && in_array($info['type'], $imageTypes);
    }

    public function generateThumbnail($filePath, $width = 200, $height = 200)
    {
        if (!$this->isImageFile($filePath)) {
            return false;
        }

        $fullPath = __DIR__ . '/../../public/' . $filePath;
        $thumbnailPath = str_replace('uploads/', 'uploads/thumbs/', $filePath);
        $thumbnailFullPath = __DIR__ . '/../../public/' . $thumbnailPath;

        // Create thumbs directory if it doesn't exist
        $thumbsDir = dirname($thumbnailFullPath);
        if (!is_dir($thumbsDir)) {
            mkdir($thumbsDir, 0755, true);
        }

        // Create thumbnail using GD
        $info = getimagesize($fullPath);
        if (!$info) {
            return false;
        }

        $srcWidth = $info[0];
        $srcHeight = $info[1];
        $mimeType = $info['mime'];

        // Calculate new dimensions
        $ratio = min($width / $srcWidth, $height / $srcHeight);
        $newWidth = intval($srcWidth * $ratio);
        $newHeight = intval($srcHeight * $ratio);

        // Create source image
        switch ($mimeType) {
            case 'image/jpeg':
                $srcImage = imagecreatefromjpeg($fullPath);
                break;
            case 'image/png':
                $srcImage = imagecreatefrompng($fullPath);
                break;
            case 'image/gif':
                $srcImage = imagecreatefromgif($fullPath);
                break;
            default:
                return false;
        }

        // Create thumbnail
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($thumbnail, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

        // Save thumbnail
        $result = imagejpeg($thumbnail, $thumbnailFullPath, 85);

        // Clean up
        imagedestroy($srcImage);
        imagedestroy($thumbnail);

        return $result ? $thumbnailPath : false;
    }
}

