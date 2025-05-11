<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/utils.php';

/**
 * Files API Endpoints
 */

// Check for API key
$apiKey = ApiUtils::getApiKey();
if (!$apiKey) {
    ApiUtils::sendError('API key required', 401);
}

// Verify API key and get project details
$projectDetails = ApiUtils::verifyApiKey($apiKey);
if (!$projectDetails) {
    ApiUtils::sendError('Invalid API key', 401);
}

// Ensure storage directory exists
if (!file_exists(STORAGE_PATH)) {
    mkdir(STORAGE_PATH, 0755, true);
}

// Create project storage directory if it doesn't exist
$projectStoragePath = STORAGE_PATH . '/' . $projectDetails['project_id'];
if (!file_exists($projectStoragePath)) {
    mkdir($projectStoragePath, 0755, true);
}

// Route handler
$route = isset($_GET['route']) ? $_GET['route'] : '';
$method = $_SERVER['REQUEST_METHOD'];
$fileId = isset($_GET['id']) ? intval($_GET['id']) : null;

switch ($route) {
    case 'upload':
        if ($method === 'POST') {
            uploadFile($projectDetails);
        } else {
            ApiUtils::sendError('Method not allowed', 405);
        }
        break;
    
    case 'list':
        if ($method === 'GET') {
            listFiles($projectDetails);
        } else {
            ApiUtils::sendError('Method not allowed', 405);
        }
        break;
    
    case 'get':
        if ($method === 'GET' && $fileId) {
            getFile($projectDetails, $fileId);
        } else {
            ApiUtils::sendError('Method not allowed or missing file ID', 405);
        }
        break;
    
    case 'download':
        if ($method === 'GET' && $fileId) {
            downloadFile($projectDetails, $fileId);
        } else {
            ApiUtils::sendError('Method not allowed or missing file ID', 405);
        }
        break;
    
    case 'delete':
        if ($method === 'DELETE' && $fileId) {
            deleteFile($projectDetails, $fileId);
        } else {
            ApiUtils::sendError('Method not allowed or missing file ID', 405);
        }
        break;
    
    default:
        ApiUtils::sendError('Endpoint not found', 404);
}

/**
 * Upload a file
 */
function uploadFile($projectDetails) {
    // Check write permission
    if (strpos($projectDetails['permissions'], 'write') === false) {
        ApiUtils::sendError('API key does not have write permission', 403);
    }
    
    // Validate file upload
    if (empty($_FILES['file'])) {
        ApiUtils::sendError('No file uploaded', 400);
    }
    
    $file = $_FILES['file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        
        $errorMessage = isset($errorMessages[$file['error']]) 
            ? $errorMessages[$file['error']] 
            : 'Unknown upload error';
            
        ApiUtils::sendError($errorMessage, 400);
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        ApiUtils::sendError('File size exceeds the maximum limit', 400);
    }
    
    // Get file extension and check if it's allowed
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    
    $allowedExtensions = explode(',', ALLOWED_FILE_TYPES);
    if (!in_array($extension, $allowedExtensions)) {
        ApiUtils::sendError('File type not allowed', 400);
    }
    
    // Generate unique filename
    $fileName = $fileInfo['filename'] . '_' . time() . '.' . $extension;
    $projectStoragePath = STORAGE_PATH . '/' . $projectDetails['project_id'];
    $filePath = $projectStoragePath . '/' . $fileName;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        ApiUtils::sendError('Failed to save file', 500);
    }
    
    // Save file info to database
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("INSERT INTO files (project_id, file_name, file_path, file_size, file_type, uploaded_by) 
                             VALUES (?, ?, ?, ?, ?, ?)");
        
        $success = $stmt->execute([
            $projectDetails['project_id'],
            $fileName,
            $filePath,
            $file['size'],
            $file['type'],
            $projectDetails['user_id']
        ]);
        
        if ($success) {
            $fileId = $db->lastInsertId();
            
            ApiUtils::sendResponse([
                'success' => true,
                'message' => 'File uploaded successfully',
                'file' => [
                    'id' => $fileId,
                    'file_name' => $fileName,
                    'file_size' => $file['size'],
                    'file_type' => $file['type']
                ]
            ], 201);
        } else {
            // Remove file if database insert fails
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            ApiUtils::sendError('Failed to record file information', 500);
        }
    } catch (Exception $e) {
        // Remove file if database insert fails
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        error_log('File upload error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to upload file', 500);
    }
}

/**
 * List files for a project
 */
function listFiles($projectDetails) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Add pagination
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
        $offset = ($page - 1) * $limit;
        
        // Get total count
        $stmt = $db->prepare("SELECT COUNT(*) FROM files WHERE project_id = ?");
        $stmt->execute([$projectDetails['project_id']]);
        $total = $stmt->fetchColumn();
        
        // Get files
        $stmt = $db->prepare("SELECT id, file_name, file_size, file_type, created_at 
                             FROM files WHERE project_id = ? 
                             ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$projectDetails['project_id'], $limit, $offset]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ApiUtils::sendResponse([
            'success' => true,
            'files' => $files,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ]);
    } catch (Exception $e) {
        error_log('List files error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to retrieve files', 500);
    }
}

/**
 * Get file details
 */
function getFile($projectDetails, $fileId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT * FROM files WHERE id = ? AND project_id = ?");
        $stmt->execute([$fileId, $projectDetails['project_id']]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            ApiUtils::sendError('File not found or access denied', 404);
        }
        
        // Remove sensitive information
        unset($file['file_path']);
        
        ApiUtils::sendResponse([
            'success' => true,
            'file' => $file
        ]);
    } catch (Exception $e) {
        error_log('Get file error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to retrieve file details', 500);
    }
}

/**
 * Download a file
 */
function downloadFile($projectDetails, $fileId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT * FROM files WHERE id = ? AND project_id = ?");
        $stmt->execute([$fileId, $projectDetails['project_id']]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            ApiUtils::sendError('File not found or access denied', 404);
        }
        
        $filePath = $file['file_path'];
        
        if (!file_exists($filePath)) {
            ApiUtils::sendError('File not found on server', 404);
        }
        
        // Set appropriate headers for download
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $file['file_type']);
        header('Content-Disposition: attachment; filename="' . $file['file_name'] . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        
        // Clear output buffer
        ob_clean();
        flush();
        
        // Read file and output to client
        readfile($filePath);
        exit;
    } catch (Exception $e) {
        error_log('Download file error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to download file', 500);
    }
}

/**
 * Delete a file
 */
function deleteFile($projectDetails, $fileId) {
    // Check write permission
    if (strpos($projectDetails['permissions'], 'write') === false) {
        ApiUtils::sendError('API key does not have write permission', 403);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get file path
        $stmt = $db->prepare("SELECT file_path FROM files WHERE id = ? AND project_id = ?");
        $stmt->execute([$fileId, $projectDetails['project_id']]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            ApiUtils::sendError('File not found or access denied', 404);
        }
        
        $filePath = $file['file_path'];
        
        // Delete file from filesystem
        if (file_exists($filePath)) {
            if (!unlink($filePath)) {
                ApiUtils::sendError('Failed to delete file from storage', 500);
            }
        }
        
        // Delete from database
        $stmt = $db->prepare("DELETE FROM files WHERE id = ?");
        $success = $stmt->execute([$fileId]);
        
        if ($success) {
            ApiUtils::sendResponse([
                'success' => true,
                'message' => 'File deleted successfully'
            ]);
        } else {
            ApiUtils::sendError('Failed to delete file record', 500);
        }
    } catch (Exception $e) {
        error_log('Delete file error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to delete file', 500);
    }
}
