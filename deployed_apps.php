<?php
/**
 * Live Preview of Deployed Applications
 * This script serves uploaded PHP applications
 */
require_once './config/config.php';
require_once './config/database.php';

// Get deployment ID from URL
$deploymentId = isset($_GET['id']) ? $_GET['id'] : null;

// Validate deployment ID
if (!$deploymentId) {
    die('Deployment ID is required');
}

// Get deployment details
try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM deployments WHERE id = ?");
    $stmt->execute([$deploymentId]);
    $deployment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$deployment) {
        die('Deployment not found');
    }
    
    // Verify this is a file upload deployment
    if (!$deployment['deployment_path']) {
        die('This deployment does not have an associated application');
    }
    
    // Get the entry file from the deployment
    $extractDir = $deployment['deployment_path'];
    
    // Look for common entry files (index.php, app.php, main.php, etc.)
    $entryFiles = ['index.php', 'app.php', 'main.php', 'public/index.php'];
    $foundEntryFile = null;
    
    foreach ($entryFiles as $file) {
        if (file_exists($extractDir . '/' . $file)) {
            $foundEntryFile = $file;
            break;
        }
    }
    
    if (!$foundEntryFile) {
        die('No PHP entry file found in the deployment');
    }
    
    // Set the base directory for includes
    $_SERVER['DOCUMENT_ROOT'] = $extractDir;
    chdir($extractDir);
    
    // Start output buffering to capture errors
    ob_start();
    
    // Include the entry file
    try {
        include $extractDir . '/' . $foundEntryFile;
    } catch (Exception $e) {
        ob_end_clean();
        echo '<div style="background-color: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px; border: 1px solid #f5c6cb;">';
        echo '<h3>Error in Deployed Application</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
        echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
        echo '<p><a href="./dashboard/deployments.php?project_id=' . $deployment['project_id'] . '&action=view&deployment_id=' . $deploymentId . '">Return to Deployment Details</a></p>';
        echo '</div>';
    }
    
    // Flush the output
    ob_end_flush();
    
} catch (Exception $e) {
    // Log and display error
    error_log('Deployment preview error: ' . $e->getMessage());
    die('Error accessing deployment: ' . $e->getMessage());
}