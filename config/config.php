<?php
// Configuration settings for the BaaS platform

// Error reporting
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Define storage paths
define('STORAGE_PATH', BASE_PATH . '/storage');

// API settings
define('API_VERSION', '1.0');
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your_jwt_secret_key');
define('JWT_EXPIRY', 3600); // 1 hour

// CORS settings
define('ALLOWED_ORIGINS', '*'); // Change to specific domains in production

// File storage settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,pdf,txt,doc,docx,xls,xlsx,zip');

// Database settings
define('DB_HOST', 'srv1742.hstgr.io');
define('DB_PORT', '3306');
define('DB_NAME', 'u257862117_baas_pro');
define('DB_USER', 'u257862117_baas_pro');
define('DB_PASS', '$Krunal@7223$');
define('DB_CHARSET', 'utf8mb4');

// Create data directory if it doesn't exist
if (!file_exists(__DIR__ . '/../data')) {
    mkdir(__DIR__ . '/../data', 0755, true);
}

// Application configuration
define('APP_NAME', 'BaaS Platform');
define('APP_URL', 'http://localhost');
define('APP_ENV', 'development');

// Security configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 300); // 5 minutes

// Error reporting based on environment
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
