<?php
require_once __DIR__ . '/../config/database.php';

class ActivityLogger {
    private static $instance = null;
    private $db;

    private function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new ActivityLogger();
        }
        return self::$instance;
    }

    /**
     * Log an activity
     * 
     * @param string $action The action performed (e.g., 'create', 'update', 'delete')
     * @param string $entityType The type of entity affected (e.g., 'user', 'project', 'document')
     * @param string|null $entityId The ID of the entity affected
     * @param array|null $details Additional details about the activity
     * @param int|null $userId The ID of the user who performed the action (defaults to current user)
     * @return bool Whether the log was successfully created
     */
    public function log($action, $entityType, $entityId = null, $details = null, $userId = null) {
        try {
            // If no user ID provided, try to get from session
            if ($userId === null && isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }

            // Get IP address
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

            // Convert details to JSON if it's an array
            if (is_array($details)) {
                $details = json_encode($details, JSON_PRETTY_PRINT);
            }

            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (
                    user_id, action, entity_type, entity_id, details, ip_address
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");

            return $stmt->execute([
                $userId,
                $action,
                $entityType,
                $entityId,
                $details,
                $ipAddress
            ]);
        } catch (Exception $e) {
            error_log('Activity logging error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log a user activity
     * 
     * @param string $action The action performed
     * @param string $entityType The type of entity affected
     * @param string|null $entityId The ID of the entity affected
     * @param array|null $details Additional details about the activity
     * @return bool Whether the log was successfully created
     */
    public function logUserActivity($action, $entityType, $entityId = null, $details = null) {
        return $this->log($action, $entityType, $entityId, $details);
    }

    /**
     * Log a system activity
     * 
     * @param string $action The action performed
     * @param string $entityType The type of entity affected
     * @param string|null $entityId The ID of the entity affected
     * @param array|null $details Additional details about the activity
     * @return bool Whether the log was successfully created
     */
    public function logSystemActivity($action, $entityType, $entityId = null, $details = null) {
        return $this->log($action, $entityType, $entityId, $details, null);
    }
} 