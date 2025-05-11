<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/security.php';

/**
 * Database connection class for MySQL
 */
class Database {
    private $db;
    private static $instance = null;
    private $queryCount = 0;
    private $lastQueryTime = 0;
    private $maxQueriesPerSecond = 100;

    /**
     * Constructor - establishes MySQL connection
     */
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->db = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            $this->initDatabase();
        } catch (PDOException $e) {
            error_log('Database Connection Error: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }

    /**
     * Get database instance (Singleton pattern)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /**
     * Get PDO instance
     */
    public function getConnection() {
        return $this->db;
    }

    /**
     * Execute a prepared statement with rate limiting
     * @param string $query The SQL query
     * @param array $params The parameters for the prepared statement
     * @return PDOStatement|false The prepared statement or false on failure
     */
    public function executeQuery($query, $params = []) {
        // Rate limiting check
        $currentTime = microtime(true);
        if ($currentTime - $this->lastQueryTime < 1) {
            $this->queryCount++;
            if ($this->queryCount > $this->maxQueriesPerSecond) {
                throw new Exception('Query rate limit exceeded');
            }
        } else {
            $this->queryCount = 1;
            $this->lastQueryTime = $currentTime;
        }

        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Query execution error: ' . $e->getMessage());
            throw new Exception('Database query failed');
        }
    }

    /**
     * Initialize database with required tables
     */
    private function initDatabase() {
        $this->createUsersTable();
        $this->createProjectsTable();
        $this->createApiKeysTable();
        $this->createCollectionsTable();
        $this->createDocumentsTable();
        $this->createFilesTable();
        $this->createDeploymentsTable();
        $this->createDeploymentLogsTable();
        $this->createActivityLogsTable();
    }

    /**
     * Create users table if it doesn't exist
     */
    private function createUsersTable() {
        $query = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(255),
            role VARCHAR(50) DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->db->exec($query);
    }

    /**
     * Create projects table if it doesn't exist
     */
    private function createProjectsTable() {
        $query = "CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->db->exec($query);
    }

    /**
     * Create API keys table if it doesn't exist
     */
    private function createApiKeysTable() {
        $query = "CREATE TABLE IF NOT EXISTS api_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            api_key VARCHAR(255) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            permissions VARCHAR(50) DEFAULT 'read',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->db->exec($query);
    }

    /**
     * Create collections table if it doesn't exist
     */
    private function createCollectionsTable() {
        $query = "CREATE TABLE IF NOT EXISTS collections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            UNIQUE KEY unique_project_collection (project_id, name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->db->exec($query);
    }

    /**
     * Create documents table if it doesn't exist
     */
    private function createDocumentsTable() {
        $query = "CREATE TABLE IF NOT EXISTS documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            collection_id INT NOT NULL,
            document_id VARCHAR(255) NOT NULL,
            data JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
            UNIQUE KEY unique_collection_document (collection_id, document_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->db->exec($query);
    }

    /**
     * Create files table if it doesn't exist
     */
    private function createFilesTable() {
        $query = "CREATE TABLE IF NOT EXISTS files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_size INT NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            uploaded_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->db->exec($query);
    }

    /**
     * Create deployments table if it doesn't exist
     */
    private function createDeploymentsTable() {
        $query = "CREATE TABLE IF NOT EXISTS deployments (
            id VARCHAR(36) PRIMARY KEY,
            project_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            environment VARCHAR(50) NOT NULL,
            source_code TEXT,
            status VARCHAR(50) DEFAULT 'pending',
            url VARCHAR(255),
            zip_file_path VARCHAR(255),
            deployment_path VARCHAR(255),
            preview_url VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->db->exec($query);
    }

    /**
     * Create deployment logs table if it doesn't exist
     */
    private function createDeploymentLogsTable() {
        $query = "CREATE TABLE IF NOT EXISTS deployment_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            deployment_id VARCHAR(36) NOT NULL,
            message TEXT NOT NULL,
            level VARCHAR(20) DEFAULT 'info',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (deployment_id) REFERENCES deployments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->db->exec($query);
    }

    /**
     * Create activity logs table if it doesn't exist
     */
    private function createActivityLogsTable() {
        $query = "CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id VARCHAR(36),
            details JSON,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->db->exec($query);
    }

    /**
     * Create admin user if none exists
     */
    public function createAdminIfNotExists($email, $password, $name) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (email, password, name, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute([$email, $hashedPassword, $name]);
            return true;
        }
        return false;
    }
}
