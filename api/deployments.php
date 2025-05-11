<?php
/**
 * Deployments API
 * Handles deployment of PHP applications
 */
require_once '../config/config.php';
require_once '../config/database.php';
require_once 'utils.php';
require_once __DIR__ . '/cache.php';

$cache = new Cache();
$cacheKey = 'deployments_data';

// Get project ID from URL parameter
$projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

// Handle different routes
$route = isset($_GET['route']) ? $_GET['route'] : '';

// Verify API key
$apiKey = ApiUtils::getApiKey();
if (!$apiKey) {
    ApiUtils::sendError('API key is required', 401);
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT ak.*, u.id as user_id FROM api_keys ak 
                      JOIN users u ON ak.user_id = u.id 
                      WHERE ak.token = ?");
$stmt->execute([$apiKey]);
$keyData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$keyData) {
    ApiUtils::sendError('Invalid API key', 401);
}

$userId = $keyData['user_id'];

// Handle routes
switch ($route) {
    case 'create':
        createDeployment($projectId, $userId);
        break;
    case 'list':
        getDeployments($projectId, $userId);
        break;
    case 'get':
        getDeployment($projectId, $userId);
        break;
    case 'delete':
        deleteDeployment($projectId, $userId);
        break;
    case 'logs':
        getDeploymentLogs($projectId, $userId);
        break;
    default:
        ApiUtils::sendError('Invalid route', 404);
}

/**
 * Create a new deployment
 */
function createDeployment($projectId, $userId) {
    ApiUtils::validateMethod('POST');
    
    // Validate project ID
    if (!$projectId) {
        ApiUtils::sendError('Project ID is required', 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if project exists and belongs to user
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$projectId, $userId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            ApiUtils::sendError('Project not found', 404);
        }
        
        // Get request data
        $data = ApiUtils::getJsonBody();
        
        // Required fields
        $requiredFields = ['name', 'description', 'environment', 'source_code'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                ApiUtils::sendError("$field is required", 400);
            }
        }
        
        // Generate unique deployment ID
        $deploymentId = uniqid('deploy_');
        
        // Insert deployment
        $stmt = $db->prepare("
            INSERT INTO deployments (
                id, project_id, name, description, environment, 
                source_code, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', datetime('now'), datetime('now'))
        ");
        
        $stmt->execute([
            $deploymentId,
            $projectId,
            $data['name'],
            $data['description'],
            $data['environment'],
            $data['source_code']
        ]);
        
        // Start deployment process asynchronously (in a real environment)
        // For now, simulate a successful deployment
        $stmt = $db->prepare("
            UPDATE deployments 
            SET status = 'active', url = ?, updated_at = datetime('now')
            WHERE id = ?
        ");
        
        // Generate a demo URL (in production, this would be the actual deployment URL)
        $deploymentUrl = "https://" . $data['name'] . "-" . substr($deploymentId, 7) . ".example.com";
        $stmt->execute([$deploymentUrl, $deploymentId]);
        
        // Get the created deployment
        $stmt = $db->prepare("SELECT * FROM deployments WHERE id = ?");
        $stmt->execute([$deploymentId]);
        $deployment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        ApiUtils::sendResponse([
            'success' => true,
            'message' => 'Deployment created successfully',
            'deployment' => $deployment
        ]);
    } catch (Exception $e) {
        ApiUtils::sendError('Failed to create deployment: ' . $e->getMessage(), 500);
    }
}

/**
 * Get deployments for a project
 */
function getDeployments($projectId, $userId) {
    ApiUtils::validateMethod('GET');
    
    // Validate project ID
    if (!$projectId) {
        ApiUtils::sendError('Project ID is required', 400);
    }
    
    // Check if cached data exists
    global $cache, $cacheKey;
    $cachedData = $cache->get($cacheKey);
    if ($cachedData) {
        echo json_encode($cachedData);
        exit;
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if project exists and belongs to user
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$projectId, $userId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            ApiUtils::sendError('Project not found', 404);
        }
        
        // Get all deployments for this project
        $stmt = $db->prepare("SELECT * FROM deployments WHERE project_id = ? ORDER BY created_at DESC");
        $stmt->execute([$projectId]);
        $deployments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cache the fetched data
        $cache->set($cacheKey, $deployments);
        
        ApiUtils::sendResponse([
            'success' => true,
            'deployments' => $deployments
        ]);
    } catch (Exception $e) {
        ApiUtils::sendError('Failed to get deployments: ' . $e->getMessage(), 500);
    }
}

/**
 * Get a specific deployment
 */
function getDeployment($projectId, $userId) {
    ApiUtils::validateMethod('GET');
    
    // Validate deployment ID
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        ApiUtils::sendError('Deployment ID is required', 400);
    }
    
    $deploymentId = $_GET['id'];
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if project exists and belongs to user
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$projectId, $userId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            ApiUtils::sendError('Project not found', 404);
        }
        
        // Get the deployment
        $stmt = $db->prepare("
            SELECT * FROM deployments 
            WHERE id = ? AND project_id = ?
        ");
        $stmt->execute([$deploymentId, $projectId]);
        $deployment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deployment) {
            ApiUtils::sendError('Deployment not found', 404);
        }
        
        ApiUtils::sendResponse([
            'success' => true,
            'deployment' => $deployment
        ]);
    } catch (Exception $e) {
        ApiUtils::sendError('Failed to get deployment: ' . $e->getMessage(), 500);
    }
}

/**
 * Delete a deployment
 */
function deleteDeployment($projectId, $userId) {
    ApiUtils::validateMethod('DELETE');
    
    // Validate deployment ID
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        ApiUtils::sendError('Deployment ID is required', 400);
    }
    
    $deploymentId = $_GET['id'];
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if project exists and belongs to user
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$projectId, $userId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            ApiUtils::sendError('Project not found', 404);
        }
        
        // Check if deployment exists
        $stmt = $db->prepare("
            SELECT * FROM deployments 
            WHERE id = ? AND project_id = ?
        ");
        $stmt->execute([$deploymentId, $projectId]);
        $deployment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deployment) {
            ApiUtils::sendError('Deployment not found', 404);
        }
        
        // Delete the deployment
        $stmt = $db->prepare("DELETE FROM deployments WHERE id = ?");
        $stmt->execute([$deploymentId]);
        
        ApiUtils::sendResponse([
            'success' => true,
            'message' => 'Deployment deleted successfully'
        ]);
    } catch (Exception $e) {
        ApiUtils::sendError('Failed to delete deployment: ' . $e->getMessage(), 500);
    }
}

/**
 * Get deployment logs
 */
function getDeploymentLogs($projectId, $userId) {
    ApiUtils::validateMethod('GET');
    
    // Validate deployment ID
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        ApiUtils::sendError('Deployment ID is required', 400);
    }
    
    $deploymentId = $_GET['id'];
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if project exists and belongs to user
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$projectId, $userId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            ApiUtils::sendError('Project not found', 404);
        }
        
        // Check if deployment exists
        $stmt = $db->prepare("
            SELECT * FROM deployments 
            WHERE id = ? AND project_id = ?
        ");
        $stmt->execute([$deploymentId, $projectId]);
        $deployment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deployment) {
            ApiUtils::sendError('Deployment not found', 404);
        }
        
        // Get deployment logs
        $stmt = $db->prepare("
            SELECT * FROM deployment_logs 
            WHERE deployment_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$deploymentId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no logs exist, return sample logs
        if (empty($logs)) {
            $logs = [
                [
                    'id' => 1,
                    'deployment_id' => $deploymentId,
                    'message' => 'Deployment started',
                    'level' => 'info',
                    'created_at' => $deployment['created_at']
                ],
                [
                    'id' => 2,
                    'deployment_id' => $deploymentId,
                    'message' => 'Building application',
                    'level' => 'info',
                    'created_at' => $deployment['created_at']
                ],
                [
                    'id' => 3,
                    'deployment_id' => $deploymentId,
                    'message' => 'Application built successfully',
                    'level' => 'info',
                    'created_at' => $deployment['created_at']
                ],
                [
                    'id' => 4,
                    'deployment_id' => $deploymentId,
                    'message' => 'Deployment completed',
                    'level' => 'success',
                    'created_at' => $deployment['updated_at']
                ]
            ];
        }
        
        ApiUtils::sendResponse([
            'success' => true,
            'logs' => $logs
        ]);
    } catch (Exception $e) {
        ApiUtils::sendError('Failed to get deployment logs: ' . $e->getMessage(), 500);
    }
}