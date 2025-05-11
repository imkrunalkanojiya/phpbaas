<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/utils.php';

/**
 * Check API access and return project details
 * 
 * @param bool $requireWrite Whether write permission is required
 * @return array Project details including API key information
 */
function checkApiAccess($requireWrite = false) {
    // Get API key from header
    $apiKey = ApiUtils::getApiKey();
    
    if (!$apiKey) {
        ApiUtils::sendError('API key required', 401);
    }
    
    // Verify API key
    $projectDetails = ApiUtils::verifyApiKey($apiKey);
    
    if (!$projectDetails) {
        ApiUtils::sendError('Invalid API key', 401);
    }
    
    // Check write permission if required
    if ($requireWrite && strpos($projectDetails['permissions'], 'write') === false) {
        ApiUtils::sendError('API key does not have write permission', 403);
    }
    
    return $projectDetails;
}
