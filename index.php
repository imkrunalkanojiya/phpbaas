<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Check maintenance mode
    $maintenance = require __DIR__ . '/config/maintenance.php';
    if ($maintenance['enabled']) {
        // Check if user's IP is in allowed list
        $userIP = $_SERVER['REMOTE_ADDR'];
        if (!in_array($userIP, $maintenance['allowed_ips'])) {
            require __DIR__ . '/includes/maintenance.php';
            exit;
        }
    }

    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/includes/middleware.php';

    // Initialize security measures
    Middleware::init();

    // Add CSRF token to all forms
    $csrfToken = Security::generateCSRFToken();

    // Main entry point for the BaaS platform
    session_start();

    // Redirect to dashboard if logged in, otherwise to login page
    if (isset($_SESSION['user_id'])) {
        header('Location: /dashboard/index.php');
        exit;
    } else {
        header('Location: /dashboard/index.php');
        exit;
    }
} catch (Exception $e) {
    error_log('Index page error: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('An error occurred. Please try again later.');
}
