<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/utils.php';

/**
 * Projects API Endpoints
 */

// Verify authentication
$token = ApiUtils::getAuthToken();
if (!$token) {
    ApiUtils::sendError('Authentication required', 401);
}

$payload = ApiUtils::verifyJWT($token);
if (!$payload) {
    ApiUtils::sendError('Invalid or expired token', 401);
}

$userId = $payload['sub'];

// Route handler
$route = isset($_GET['route']) ? $_GET['route'] : '';
$method = $_SERVER['REQUEST_METHOD'];
$projectId = isset($_GET['id']) ? intval($_GET['id']) : null;

switch ($route) {
    case 'list':
        if ($method === 'GET') {
            getProjects($userId);
        } else {
            ApiUtils::sendError('Method not allowed', 405);
        }
        break;
    
    case 'create':
        if ($method === 'POST') {
            createProject($userId);
        } else {
            ApiUtils::sendError('Method not allowed', 405);
        }
        break;
    
    case 'get':
        if ($method === 'GET' && $projectId) {
            getProject($userId, $projectId);
        } else {
            ApiUtils::sendError('Method not allowed or missing project ID', 405);
        }
        break;
    
    case 'update':
        if ($method === 'PUT' && $projectId) {
            updateProject($userId, $projectId);
        } else {
            ApiUtils::sendError('Method not allowed or missing project ID', 405);
        }
        break;
    
    case 'delete':
        if ($method === 'DELETE' && $projectId) {
            deleteProject($userId, $projectId);
        } else {
            ApiUtils::sendError('Method not allowed or missing project ID', 405);
        }
        break;
    
    default:
        ApiUtils::sendError('Endpoint not found', 404);
}

/**
 * Get all projects for a user
 */
function getProjects($userId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ApiUtils::sendResponse([
            'success' => true,
            'projects' => $projects
        ]);
    } catch (Exception $e) {
        error_log('Get projects error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to retrieve projects', 500);
    }
}

/**
 * Create a new project
 */
function createProject($userId) {
    $data = ApiUtils::getJsonBody();
    
    // Validate input
    if (empty($data['name'])) {
        ApiUtils::sendError('Project name is required', 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $description = isset($data['description']) ? $data['description'] : '';
        
        $stmt = $db->prepare("INSERT INTO projects (name, description, user_id) VALUES (?, ?, ?)");
        $success = $stmt->execute([$data['name'], $description, $userId]);
        
        if ($success) {
            $projectId = $db->lastInsertId();
            
            // Create a default API key for the project
            $apiKey = ApiUtils::generateToken(64);
            $keyName = "Default Key";
            
            $stmt = $db->prepare("INSERT INTO api_keys (project_id, api_key, name, permissions) VALUES (?, ?, ?, 'read,write')");
            $stmt->execute([$projectId, $apiKey, $keyName]);
            
            $project = [
                'id' => $projectId,
                'name' => $data['name'],
                'description' => $description,
                'user_id' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'default_api_key' => $apiKey
            ];
            
            ApiUtils::sendResponse([
                'success' => true,
                'message' => 'Project created successfully',
                'project' => $project
            ], 201);
        } else {
            ApiUtils::sendError('Failed to create project', 500);
        }
    } catch (Exception $e) {
        error_log('Create project error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to create project', 500);
    }
}

/**
 * Get a specific project
 */
function getProject($userId, $projectId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if project belongs to user
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$projectId, $userId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            ApiUtils::sendError('Project not found or access denied', 404);
        }
        
        // Get API keys for the project
        $stmt = $db->prepare("SELECT id, api_key, name, permissions, created_at FROM api_keys WHERE project_id = ?");
        $stmt->execute([$projectId]);
        $apiKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get collections for the project
        $stmt = $db->prepare("SELECT * FROM collections WHERE project_id = ?");
        $stmt->execute([$projectId]);
        $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get file count for the project
        $stmt = $db->prepare("SELECT COUNT(*) as file_count FROM files WHERE project_id = ?");
        $stmt->execute([$projectId]);
        $fileCount = $stmt->fetch(PDO::FETCH_ASSOC)['file_count'];
        
        $project['api_keys'] = $apiKeys;
        $project['collections'] = $collections;
        $project['file_count'] = $fileCount;
        
        ApiUtils::sendResponse([
            'success' => true,
            'project' => $project
        ]);
    } catch (Exception $e) {
        error_log('Get project error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to retrieve project', 500);
    }
}

/**
 * Update a project
 */
function updateProject($userId, $projectId) {
    $data = ApiUtils::getJsonBody();
    
    // Validate input
    if (empty($data['name'])) {
        ApiUtils::sendError('Project name is required', 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if project belongs to user
        $stmt = $db->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$projectId, $userId]);
        
        if (!$stmt->fetch()) {
            ApiUtils::sendError('Project not found or access denied', 404);
        }
        
        $description = isset($data['description']) ? $data['description'] : '';
        
        $stmt = $db->prepare("UPDATE projects SET name = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $success = $stmt->execute([$data['name'], $description, $projectId]);
        
        if ($success) {
            ApiUtils::sendResponse([
                'success' => true,
                'message' => 'Project updated successfully',
                'project' => [
                    'id' => $projectId,
                    'name' => $data['name'],
                    'description' => $description
                ]
            ]);
        } else {
            ApiUtils::sendError('Failed to update project', 500);
        }
    } catch (Exception $e) {
        error_log('Update project error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to update project', 500);
    }
}

/**
 * Delete a project
 */
function deleteProject($userId, $projectId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if project belongs to user
        $stmt = $db->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$projectId, $userId]);
        
        if (!$stmt->fetch()) {
            ApiUtils::sendError('Project not found or access denied', 404);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        // Delete all files associated with the project
        $stmt = $db->prepare("SELECT file_path FROM files WHERE project_id = ?");
        $stmt->execute([$projectId]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($files as $file) {
            $filePath = $file['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Delete the project (cascades to delete associated records)
        $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
        $success = $stmt->execute([$projectId]);
        
        if ($success) {
            $db->commit();
            ApiUtils::sendResponse([
                'success' => true,
                'message' => 'Project deleted successfully'
            ]);
        } else {
            $db->rollBack();
            ApiUtils::sendError('Failed to delete project', 500);
        }
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Delete project error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to delete project', 500);
    }
}
