<?php
/**
 * Profile Management Functions
 * Handles secure profile updates: name, email, profile picture
 */

require_once __DIR__ . '/env.php';

/**
 * Validate email format
 * @param string $email
 * @return bool
 */
function is_valid_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate profile picture file upload
 * @param array $file $_FILES array element
 * @return array ['success' => bool, 'message' => string, 'filename' => string|null]
 */
function validate_profile_picture(array $file): array {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        return [
            'success' => false,
            'message' => $errorMessages[$file['error']] ?? 'Unknown upload error',
            'filename' => null
        ];
    }
    
    // Check file size (max 2MB)
    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxSize) {
        return [
            'success' => false,
            'message' => 'File size exceeds 2MB limit',
            'filename' => null
        ];
    }
    
    // Check file type (only images)
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return [
            'success' => false,
            'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed',
            'filename' => null
        ];
    }
    
    // Verify it's actually an image (additional security)
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return [
            'success' => false,
            'message' => 'File is not a valid image',
            'filename' => null
        ];
    }
    
    // Generate secure filename
    $extension = '';
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            $extension = 'jpg';
            break;
        case 'image/png':
            $extension = 'png';
            break;
        case 'image/gif':
            $extension = 'gif';
            break;
        case 'image/webp':
            $extension = 'webp';
            break;
    }
    
    // Generate unique filename: userid_timestamp_randomhash.extension
    $filename = uniqid('', true) . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    
    return [
        'success' => true,
        'message' => 'File validated successfully',
        'filename' => $filename,
        'mime_type' => $mimeType,
        'tmp_name' => $file['tmp_name']
    ];
}

/**
 * Save uploaded profile picture securely
 * @param array $file $_FILES array element
 * @param int $userId User ID
 * @return array ['success' => bool, 'message' => string, 'url' => string|null]
 */
function save_profile_picture(array $file, int $userId): array {
    // Validate the upload
    $validation = validate_profile_picture($file);
    if (!$validation['success']) {
        return $validation;
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../uploads/profiles/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return [
                'success' => false,
                'message' => 'Failed to create upload directory',
                'url' => null
            ];
        }
    }
    
    // Create user-specific subdirectory for better organization
    $userDir = $uploadDir . $userId . '/';
    if (!is_dir($userDir)) {
        if (!mkdir($userDir, 0755, true)) {
            return [
                'success' => false,
                'message' => 'Failed to create user directory',
                'url' => null
            ];
        }
    }
    
    // Full path to save file
    $filePath = $userDir . $validation['filename'];
    
    // Move uploaded file
    if (!move_uploaded_file($validation['tmp_name'], $filePath)) {
        return [
            'success' => false,
            'message' => 'Failed to save uploaded file',
            'url' => null
        ];
    }
    
    // Set proper permissions (readable by web server, not executable)
    chmod($filePath, 0644);
    
    // Generate URL path (relative to web root)
    $basePath = get_base_path();
    $url = $basePath . '/uploads/profiles/' . $userId . '/' . $validation['filename'];
    
    return [
        'success' => true,
        'message' => 'Profile picture uploaded successfully',
        'url' => $url
    ];
}

/**
 * Delete old profile picture if it exists
 * @param string|null $oldPictureUrl Old picture URL
 * @param int $userId User ID
 */
function delete_old_profile_picture(?string $oldPictureUrl, int $userId): void {
    if (empty($oldPictureUrl)) {
        return;
    }
    
    // Extract filename from URL
    $parsedUrl = parse_url($oldPictureUrl);
    if (!$parsedUrl || empty($parsedUrl['path'])) {
        return;
    }
    
    // Get just the filename
    $pathParts = explode('/', $parsedUrl['path']);
    $filename = end($pathParts);
    
    if (empty($filename)) {
        return;
    }
    
    // Build file path
    $filePath = __DIR__ . '/../uploads/profiles/' . $userId . '/' . $filename;
    
    // Delete file if it exists
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
}

/**
 * Update user profile (name, email, picture)
 * @param int $userId User ID
 * @param array $data ['name' => string, 'email' => string, 'picture' => string|null]
 * @return array ['success' => bool, 'message' => string]
 */
function update_user_profile(int $userId, array $data): array {
    $pdo = get_auth_db();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    // Validate email if provided
    if (!empty($data['email']) && !is_valid_email($data['email'])) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }
    
    // Validate name length
    if (!empty($data['name']) && strlen($data['name']) > 255) {
        return ['success' => false, 'message' => 'Name must be 255 characters or less'];
    }
    
    // Build update query dynamically
    $updates = [];
    $params = [];
    
    if (isset($data['name'])) {
        $updates[] = "`name` = ?";
        $params[] = !empty($data['name']) ? trim($data['name']) : null;
    }
    
    if (isset($data['email'])) {
        $updates[] = "`email` = ?";
        $params[] = !empty($data['email']) ? trim($data['email']) : null;
    }
    
    if (isset($data['picture'])) {
        $updates[] = "`picture` = ?";
        $params[] = !empty($data['picture']) ? $data['picture'] : null;
    }
    
    if (empty($updates)) {
        return ['success' => false, 'message' => 'No fields to update'];
    }
    
    // Add user ID to params
    $params[] = $userId;
    
    try {
        $sql = "UPDATE cardobot_users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return ['success' => true, 'message' => 'Profile updated successfully'];
    } catch (PDOException $e) {
        error_log("Error updating profile: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update profile'];
    }
}
