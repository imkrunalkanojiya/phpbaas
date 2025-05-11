<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Store the requested URL for redirection after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to login page
    header('Location: /dashboard/index.php');
    exit;
}

// Get current user information
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, email, name, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentUser) {
        // User not found, session may be invalid
        session_unset();
        session_destroy();
        header('Location: /dashboard/index.php');
        exit;
    }
} catch (Exception $e) {
    error_log('Auth check error: ' . $e->getMessage());
    // Redirect to error page or display error message
    echo "An error occurred. Please try again later.";
    exit;
}

// Handle logout action
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: /dashboard/index.php');
    exit;
}
