<?php
require_once __DIR__ . '/security.php';

class Middleware {
    /**
     * Initialize security measures
     */
    public static function init() {
        try {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Set secure headers
            Security::setSecureHeaders();

            // Rate limiting
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            if (!Security::checkRateLimit($ipAddress)) {
                header('HTTP/1.1 429 Too Many Requests');
                exit('Rate limit exceeded. Please try again later.');
            }

            // CSRF protection for POST requests
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
                    header('HTTP/1.1 403 Forbidden');
                    exit('Invalid CSRF token');
                }
            }

            // Sanitize all input
            $_GET = Security::sanitizeInput($_GET);
            $_POST = Security::sanitizeInput($_POST);
            $_REQUEST = Security::sanitizeInput($_REQUEST);
        } catch (Exception $e) {
            error_log('Middleware initialization error: ' . $e->getMessage());
            // Don't expose error details to user
            header('HTTP/1.1 500 Internal Server Error');
            exit('An error occurred. Please try again later.');
        }
    }

    /**
     * Validate user authentication
     * @param bool $requireAdmin Whether to require admin role
     */
    public static function requireAuth($requireAdmin = false) {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            header('Location: /dashboard/index.php');
            exit;
        }

        if ($requireAdmin && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin')) {
            header('HTTP/1.1 403 Forbidden');
            exit('Access denied');
        }
    }

    /**
     * Validate API key
     * @param string $apiKey The API key to validate
     * @return array|false Project details if valid, false otherwise
     */
    public static function validateApiKey($apiKey) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT p.*, ak.permissions 
                FROM api_keys ak 
                JOIN projects p ON ak.project_id = p.id 
                WHERE ak.api_key = ?
            ");
            $stmt->execute([$apiKey]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('API key validation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log security event
     * @param string $event The event to log
     * @param array $details Additional details
     */
    public static function logSecurityEvent($event, $details = []) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO activity_logs (
                    user_id, action, entity_type, details, ip_address
                ) VALUES (?, ?, 'security', ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $event,
                json_encode($details),
                $_SERVER['REMOTE_ADDR']
            ]);
        } catch (Exception $e) {
            error_log('Security event logging error: ' . $e->getMessage());
        }
    }
} 