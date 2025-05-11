<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Utility functions for API endpoints
 */
class ApiUtils {
    /**
     * Send JSON response
     */
    public static function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Send error response
     */
    public static function sendError($message, $statusCode = 400) {
        self::sendResponse(['error' => true, 'message' => $message], $statusCode);
    }

    /**
     * Generate a secure random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Generate a JWT token
     */
    public static function generateJWT($userId, $email, $expiry = null) {
        $expiry = $expiry ?: time() + JWT_EXPIRY;
        
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'sub' => $userId,
            'email' => $email,
            'exp' => $expiry,
            'iat' => time()
        ]);
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Verify JWT token
     */
    public static function verifyJWT($token) {
        $parts = explode('.', $token);
        if (count($parts) != 3) {
            return false;
        }
        
        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
        
        $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $base64UrlSignature));
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }
        
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64UrlPayload)), true);
        
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }

    /**
     * Verify API key and get project
     */
    public static function verifyApiKey($apiKey) {
        if (empty($apiKey)) {
            return false;
        }
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT ak.*, p.id as project_id, p.user_id, p.name as project_name FROM api_keys ak
                             JOIN projects p ON ak.project_id = p.id
                             WHERE ak.api_key = ?");
        $stmt->execute([$apiKey]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: false;
    }

    /**
     * Validate project access for a user
     */
    public static function validateProjectAccess($userId, $projectId) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$projectId, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
    }

    /**
     * Set CORS headers
     */
    public static function setCorsHeaders() {
        if (isset($_SERVER['HTTP_ORIGIN']) && ALLOWED_ORIGINS === '*') {
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        } else {
            header("Access-Control-Allow-Origin: " . ALLOWED_ORIGINS);
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization, X-API-Key');
        header('Access-Control-Allow-Credentials: true');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }
    }

    /**
     * Get authorization token from headers
     */
    public static function getAuthToken() {
        $headers = getallheaders();
        $auth = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        if (preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Get API key from headers
     */
    public static function getApiKey() {
        // Fetch headers if available
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        // Look for X-API-Key in a case-insensitive way
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'X-API-Key') === 0) {
                return $value;
            }
        }

        // Fallback to ?api_key=... in query parameters
        if (isset($_GET['api_key']) && !empty($_GET['api_key'])) {
            return $_GET['api_key'];
        }

        // Fallback to Authorization header with Bearer token
        $auth = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        if (preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Validate request method
     */
    public static function validateMethod($method) {
        if ($_SERVER['REQUEST_METHOD'] !== $method) {
            self::sendError('Method not allowed', 405);
        }
    }

    /**
     * Get JSON from request body
     */
    public static function getJsonBody() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::sendError('Invalid JSON format', 400);
        }
        
        return $data;
    }

    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::sanitizeInput($value);
            }
        } else {
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }
        
        return $data;
    }
}

// Set CORS headers for all API requests
ApiUtils::setCorsHeaders();
