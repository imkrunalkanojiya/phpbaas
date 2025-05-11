<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/utils.php';

/**
 * API Keys Management Endpoints
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
$projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;
$keyId = isset($_GET['id']) ? intval($_GET['id']) : null;

// Validate project access if project ID is provided
if ($projectId) {
    if (!ApiUtils::validateProjectAccess($userId, $projectId)) {
        ApiUtils::sendError('Project not found or access denied', 404);
    }
}

switch ($route) {
    case 'list':
        if ($method === 'GET' && $projectId) {
            listKeys($projectId);
        } else {
            ApiUtils::sendError('Method not allowed or missing project ID', 405);
        }
        break;
    
    case 'create':
        if ($method === 'POST' && $projectId) {
            createKey($projectId);
        } else {
            ApiUtils::sendError('Method not allowed or missing project ID', 405);
        }
        break;
    
    case 'get':
        if ($method === 'GET' && $keyId) {
            getKey($userId, $keyId);
        } else {
            ApiUtils::sendError('Method not allowed or missing key ID', 405);
        }
        break;
    
    case 'update':
        if ($method === 'PUT' && $keyId) {
            updateKey($userId, $keyId);
        } else {
            ApiUtils::sendError('Method not allowed or missing key ID', 405);
        }
        break;
    
    case 'delete':
        if ($method === 'DELETE' && $keyId) {
            deleteKey($userId, $keyId);
        } else {
            ApiUtils::sendError('Method not allowed or missing key ID', 405);
        }
        break;
    
    default:
        ApiUtils::sendError('Endpoint not found', 404);
}

/**
 * List API keys for a project
 */
function listKeys($projectId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT id, api_key, name, permissions, created_at FROM api_keys WHERE project_id = ?");
        $stmt->execute([$projectId]);
        $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ApiUtils::sendResponse([
            'success' => true,
            'keys' => $keys
        ]);
    } catch (Exception $e) {
        error_log('List keys error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to retrieve API keys', 500);
    }
}

/**
 * Create a new API key
 */
function createKey($projectId) {
    $data = ApiUtils::getJsonBody();
    
    // Validate input
    if (empty($data['name'])) {
        ApiUtils::sendError('API key name is required', 400);
    }
    
    // Get permissions (default to read-only if not specified)
    $permissions = isset($data['permissions']) ? $data['permissions'] : 'read';
    
    // Validate permissions
    $validPermissions = ['read', 'write', 'read,write'];
    if (!in_array($permissions, $validPermissions)) {
        ApiUtils::sendError('Invalid permissions. Valid values are: read, write, read,write', 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Generate a new API key
        $apiKey = ApiUtils::generateToken(64);
        
        $stmt = $db->prepare("INSERT INTO api_keys (project_id, api_key, name, permissions) VALUES (?, ?, ?, ?)");
        $success = $stmt->execute([$projectId, $apiKey, $data['name'], $permissions]);
        
        if ($success) {
            $keyId = $db->lastInsertId();
            
            ApiUtils::sendResponse([
                'success' => true,
                'message' => 'API key created successfully',
                'key' => [
                    'id' => $keyId,
                    'api_key' => $apiKey,
                    'name' => $data['name'],
                    'permissions' => $permissions,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ], 201);
        } else {
            ApiUtils::sendError('Failed to create API key', 500);
        }
    } catch (Exception $e) {
        error_log('Create key error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to create API key', 500);
    }
}

/**
 * Get API key details
 */
function getKey($userId, $keyId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT ak.id, ak.api_key, ak.name, ak.permissions, ak.created_at, p.id as project_id 
                             FROM api_keys ak
                             JOIN projects p ON ak.project_id = p.id
                             WHERE ak.id = ? AND p.user_id = ?");
        $stmt->execute([$keyId, $userId]);
        $key = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$key) {
            ApiUtils::sendError('API key not found or access denied', 404);
        }
        
        ApiUtils::sendResponse([
            'success' => true,
            'key' => $key
        ]);
    } catch (Exception $e) {
        error_log('Get key error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to retrieve API key', 500);
    }
}

/**
 * Update an API key
 */
function updateKey($userId, $keyId) {
    $data = ApiUtils::getJsonBody();
    
    // Validate input
    if (empty($data['name'])) {
        ApiUtils::sendError('API key name is required', 400);
    }
    
    // Get permissions (maintain existing if not specified)
    if (isset($data['permissions'])) {
        $permissions = $data['permissions'];
        
        // Validate permissions
        $validPermissions = ['read', 'write', 'read,write'];
        if (!in_array($permissions, $validPermissions)) {
            ApiUtils::sendError('Invalid permissions. Valid values are: read, write, read,write', 400);
        }
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if key exists and belongs to user
        $stmt = $db->prepare("SELECT ak.*, p.user_id 
                             FROM api_keys ak
                             JOIN projects p ON ak.project_id = p.id
                             WHERE ak.id = ? AND p.user_id = ?");
        $stmt->execute([$keyId, $userId]);
        $key = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$key) {
            ApiUtils::sendError('API key not found or access denied', 404);
        }
        
        // Update permissions if provided, otherwise keep existing
        $permissions = isset($data['permissions']) ? $data['permissions'] : $key['permissions'];
        
        // Update key
        $stmt = $db->prepare("UPDATE api_keys SET name = ?, permissions = ? WHERE id = ?");
        $success = $stmt->execute([$data['name'], $permissions, $keyId]);
        
        if ($success) {
            ApiUtils::sendResponse([
                'success' => true,
                'message' => 'API key updated successfully',
                'key' => [
                    'id' => $keyId,
                    'name' => $data['name'],
                    'permissions' => $permissions
                ]
            ]);
        } else {
            ApiUtils::sendError('Failed to update API key', 500);
        }
    } catch (Exception $e) {
        error_log('Update key error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to update API key', 500);
    }
}

/**
 * Delete an API key
 */
function deleteKey($userId, $keyId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if key exists and belongs to user
        $stmt = $db->prepare("SELECT ak.id 
                             FROM api_keys ak
                             JOIN projects p ON ak.project_id = p.id
                             WHERE ak.id = ? AND p.user_id = ?");
        $stmt->execute([$keyId, $userId]);
        
        if (!$stmt->fetch()) {
            ApiUtils::sendError('API key not found or access denied', 404);
        }
        
        // Delete key
        $stmt = $db->prepare("DELETE FROM api_keys WHERE id = ?");
        $success = $stmt->execute([$keyId]);
        
        if ($success) {
            ApiUtils::sendResponse([
                'success' => true,
                'message' => 'API key deleted successfully'
            ]);
        } else {
            ApiUtils::sendError('Failed to delete API key', 500);
        }
    } catch (Exception $e) {
        error_log('Delete key error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to delete API key', 500);
    }
}
