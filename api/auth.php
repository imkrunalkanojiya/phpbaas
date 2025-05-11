<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/utils.php';

/**
 * Authentication API Endpoints
 */

// Route handler
$route = isset($_GET['route']) ? $_GET['route'] : '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($route) {
    case 'register':
        if ($method === 'POST') {
            register();
        } else {
            ApiUtils::sendError('Method not allowed', 405);
        }
        break;
    
    case 'login':
        if ($method === 'POST') {
            login();
        } else {
            ApiUtils::sendError('Method not allowed', 405);
        }
        break;
    
    case 'logout':
        if ($method === 'POST') {
            logout();
        } else {
            ApiUtils::sendError('Method not allowed', 405);
        }
        break;
    
    case 'me':
        if ($method === 'GET') {
            getCurrentUser();
        } else {
            ApiUtils::sendError('Method not allowed', 405);
        }
        break;
    
    default:
        ApiUtils::sendError('Endpoint not found', 404);
}

/**
 * Register a new user
 */
function register() {
    $data = ApiUtils::getJsonBody();
    
    // Validate input
    if (empty($data['email']) || empty($data['password']) || empty($data['name'])) {
        ApiUtils::sendError('Email, password and name are required', 400);
    }
    
    // Validate email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        ApiUtils::sendError('Invalid email format', 400);
    }
    
    // Validate password
    if (strlen($data['password']) < 8) {
        ApiUtils::sendError('Password must be at least 8 characters', 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        
        if ($stmt->fetch()) {
            ApiUtils::sendError('Email already registered', 409);
        }
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $db->prepare("INSERT INTO users (email, password, name) VALUES (?, ?, ?)");
        $success = $stmt->execute([$data['email'], $hashedPassword, $data['name']]);
        
        if ($success) {
            $userId = $db->lastInsertId();
            $token = ApiUtils::generateJWT($userId, $data['email']);
            
            ApiUtils::sendResponse([
                'success' => true,
                'message' => 'User registered successfully',
                'user' => [
                    'id' => $userId,
                    'email' => $data['email'],
                    'name' => $data['name']
                ],
                'token' => $token
            ], 201);
        } else {
            ApiUtils::sendError('Registration failed', 500);
        }
    } catch (Exception $e) {
        error_log('Registration error: ' . $e->getMessage());
        ApiUtils::sendError('Registration failed', 500);
    }
}

/**
 * Login an existing user
 */
function login() {
    $data = ApiUtils::getJsonBody();
    
    // Validate input
    if (empty($data['email']) || empty($data['password'])) {
        ApiUtils::sendError('Email and password are required', 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get user
        $stmt = $db->prepare("SELECT id, email, password, name, role FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($data['password'], $user['password'])) {
            ApiUtils::sendError('Invalid credentials', 401);
        }
        
        // Generate JWT token
        $token = ApiUtils::generateJWT($user['id'], $user['email']);
        
        // Remove password from user data
        unset($user['password']);
        
        ApiUtils::sendResponse([
            'success' => true,
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token
        ]);
    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
        ApiUtils::sendError('Login failed', 500);
    }
}

/**
 * Logout the current user (client-side operation, just return success)
 */
function logout() {
    ApiUtils::sendResponse([
        'success' => true,
        'message' => 'Logout successful'
    ]);
}

/**
 * Get the current authenticated user
 */
function getCurrentUser() {
    $token = ApiUtils::getAuthToken();
    
    if (!$token) {
        ApiUtils::sendError('Authentication required', 401);
    }
    
    $payload = ApiUtils::verifyJWT($token);
    
    if (!$payload) {
        ApiUtils::sendError('Invalid or expired token', 401);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT id, email, name, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$payload['sub']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            ApiUtils::sendError('User not found', 404);
        }
        
        ApiUtils::sendResponse([
            'success' => true,
            'user' => $user
        ]);
    } catch (Exception $e) {
        error_log('Get user error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to retrieve user data', 500);
    }
}
