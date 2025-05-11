<?php
// Get current user projects if user is logged in
$userProjects = [];

if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT id, name FROM projects WHERE user_id = ? ORDER BY name ASC LIMIT 10");
        $stmt->execute([$_SESSION['user_id']]);
        $userProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error getting user projects for sidebar: ' . $e->getMessage());
    }
}

// Get active project ID if set
$activeProjectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
?>

<div class="list-group mb-4">
    <div class="list-group-item list-group-item-primary d-flex justify-content-between align-items-center">
        <strong><i class="fas fa-star me-2"></i>Quick Access</strong>
    </div>
    <a href="/dashboard/index.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
        <span><i class="fas fa-tachometer-alt me-2"></i>Dashboard</span>
    </a>
    <a href="/dashboard/projects.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
        <span><i class="fas fa-project-diagram me-2"></i>All Projects</span>
        <span class="badge bg-primary rounded-pill"><?php echo count($userProjects); ?></span>
    </a>
</div>

<?php if (!empty($userProjects)): ?>
<div class="list-group mb-4">
    <div class="list-group-item list-group-item-primary d-flex justify-content-between align-items-center">
        <strong><i class="fas fa-folder me-2"></i>Projects</strong>
        
    </div>
    <?php foreach ($userProjects as $project): ?>
    <a href="/dashboard/projects.php?project_id=<?php echo $project['id']; ?>" 
       class="list-group-item list-group-item-action <?php echo $activeProjectId == $project['id'] ? 'active' : ''; ?>">
        <i class="fas fa-angle-right me-2"></i><?php echo htmlspecialchars($project['name']); ?>
    </a>
    <?php endforeach; ?>
    <?php if (count($userProjects) >= 10): ?>
    <a href="/dashboard/projects.php" class="list-group-item list-group-item-action text-center">
        <i class="fas fa-ellipsis-h me-2"></i>View All
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="list-group mb-4">
    <div class="list-group-item list-group-item-primary">
        <strong><i class="fas fa-book me-2"></i>Documentation</strong>
    </div>
    <a href="/documentation/auth.php" class="list-group-item list-group-item-action">
        <i class="fas fa-key me-2"></i>Authentication
    </a>
    <a href="/documentation/database.php" class="list-group-item list-group-item-action">
        <i class="fas fa-database me-2"></i>Database
    </a>
    <a href="/documentation/files.php" class="list-group-item list-group-item-action">
        <i class="fas fa-file me-2"></i>File Storage
    </a>
    <a href="/documentation/keys.php" class="list-group-item list-group-item-action">
        <i class="fas fa-key me-2"></i>API Keys
    </a>
</div>

<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
<div class="list-group mb-4">
    <div class="list-group-item list-group-item-primary">
        <strong><i class="fas fa-shield-alt me-2"></i>Admin</strong>
    </div>
    <a href="/dashboard/admin/users.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['REQUEST_URI'], '/dashboard/admin/users.php') !== false ? 'active' : ''; ?>">
        <i class="fas fa-users me-2"></i>Users
    </a>
    <a href="/dashboard/admin/create_admin.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['REQUEST_URI'], '/dashboard/admin/create_admin.php') !== false ? 'active' : ''; ?>">
        <i class="fas fa-user-shield me-2"></i>Create Admin
    </a>
    <a href="/dashboard/admin/activity_logs.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['REQUEST_URI'], '/dashboard/admin/activity_logs.php') !== false ? 'active' : ''; ?>">
        <i class="fas fa-history me-2"></i>Activity Logs
    </a>
</div>
<?php endif; ?>
