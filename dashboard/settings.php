<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Handle profile update
$updateError = '';
$updateSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    
    if (empty($name) || empty($email)) {
        $updateError = 'Name and email are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $updateError = 'Invalid email format';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Check if email is already taken (by another user)
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            
            if ($stmt->fetch()) {
                $updateError = 'Email is already taken by another user';
            } else {
                $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $success = $stmt->execute([$name, $email, $_SESSION['user_id']]);
                
                if ($success) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $updateSuccess = 'Profile updated successfully';
                } else {
                    $updateError = 'Failed to update profile';
                }
            }
        } catch (Exception $e) {
            error_log('Update profile error: ' . $e->getMessage());
            $updateError = 'An error occurred. Please try again later.';
        }
    }
}

// Handle password change
$passwordError = '';
$passwordSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $passwordError = 'All password fields are required';
    } elseif (strlen($newPassword) < 8) {
        $passwordError = 'New password must be at least 8 characters long';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordError = 'New passwords do not match';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Verify current password
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($currentPassword, $user['password'])) {
                $passwordError = 'Current password is incorrect';
            } else {
                // Hash new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $success = $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
                
                if ($success) {
                    $passwordSuccess = 'Password changed successfully';
                } else {
                    $passwordError = 'Failed to change password';
                }
            }
        } catch (Exception $e) {
            error_log('Change password error: ' . $e->getMessage());
            $passwordError = 'An error occurred. Please try again later.';
        }
    }
}

// Get user details
$userDetails = null;
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, email, name, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Get user details error: ' . $e->getMessage());
}

// Include header
include_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-cog me-2"></i>Account Settings</h2>
        </div>
        
        <div class="row">
            <div class="col-lg-8">
                <!-- Profile Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Profile Settings</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($updateSuccess): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($updateSuccess); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($updateError): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($updateError); ?></div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($userDetails['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($userDetails['email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Account Role</label>
                                <input type="text" class="form-control" value="<?php echo ucfirst(htmlspecialchars($userDetails['role'])); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Member Since</label>
                                <input type="text" class="form-control" value="<?php echo date('F j, Y', strtotime($userDetails['created_at'])); ?>" readonly>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
                
                <!-- Change Password -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($passwordSuccess): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($passwordSuccess); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($passwordError): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($passwordError); ?></div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                <div class="form-text">Password must be at least 8 characters long</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Account Summary -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Account Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="avatar-placeholder bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                                <?php echo strtoupper(substr($userDetails['name'], 0, 1)); ?>
                            </div>
                            <h5><?php echo htmlspecialchars($userDetails['name']); ?></h5>
                            <p class="text-muted"><?php echo htmlspecialchars($userDetails['email']); ?></p>
                        </div>
                        
                        <?php
                        // Get user stats
                        try {
                            $db = Database::getInstance()->getConnection();
                            
                            // Count projects
                            $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            $projectsCount = $stmt->fetchColumn();
                            
                            // Count collections
                            $stmt = $db->prepare("SELECT COUNT(*) FROM collections c 
                                                JOIN projects p ON c.project_id = p.id 
                                                WHERE p.user_id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            $collectionsCount = $stmt->fetchColumn();
                            
                            // Count files
                            $stmt = $db->prepare("SELECT COUNT(*) FROM files f 
                                                JOIN projects p ON f.project_id = p.id 
                                                WHERE p.user_id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            $filesCount = $stmt->fetchColumn();
                        } catch (Exception $e) {
                            error_log('Get user stats error: ' . $e->getMessage());
                            $projectsCount = 0;
                            $collectionsCount = 0;
                            $filesCount = 0;
                        }
                        ?>
                        
                        <ul class="list-group mb-3">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Projects
                                <span class="badge bg-primary rounded-pill"><?php echo $projectsCount; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Collections
                                <span class="badge bg-primary rounded-pill"><?php echo $collectionsCount; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Files
                                <span class="badge bg-primary rounded-pill"><?php echo $filesCount; ?></span>
                            </li>
                        </ul>
                        
                        <div class="d-grid">
                            <a href="/dashboard/projects.php" class="btn btn-outline-primary">
                                <i class="fas fa-project-diagram me-1"></i>Manage Projects
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-link me-2"></i>Quick Links</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item">
                                <a href="/documentation/index.php" class="text-decoration-none">
                                    <i class="fas fa-book me-2"></i>Documentation
                                </a>
                            </li>
                            <li class="list-group-item">
                                <a href="/dashboard/index.php" class="text-decoration-none">
                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                </a>
                            </li>
                            <?php if ($userDetails['role'] === 'admin'): ?>
                            <li class="list-group-item">
                                <a href="/dashboard/users.php" class="text-decoration-none">
                                    <i class="fas fa-users me-2"></i>Manage Users
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>
