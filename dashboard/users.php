<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Check if user is admin
if (!isset($currentUser) || $currentUser['role'] !== 'admin') {
    header('Location: /dashboard/index.php');
    exit;
}

// Get user ID if set
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Handle user creation
$createError = '';
$createSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    
    if (empty($name) || empty($email) || empty($password)) {
        $createError = 'Name, email and password are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $createError = 'Invalid email format';
    } elseif (strlen($password) < 8) {
        $createError = 'Password must be at least 8 characters long';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $createError = 'Email already registered';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                $success = $stmt->execute([$name, $email, $hashedPassword, $role]);
                
                if ($success) {
                    $createSuccess = 'User created successfully';
                } else {
                    $createError = 'Failed to create user';
                }
            }
        } catch (Exception $e) {
            error_log('Create user error: ' . $e->getMessage());
            $createError = 'An error occurred. Please try again later.';
        }
    }
}

// Handle user update
$updateError = '';
$updateSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'user';
    
    if (empty($name) || empty($email)) {
        $updateError = 'Name and email are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $updateError = 'Invalid email format';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Check if email is already taken (by another user)
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            
            if ($stmt->fetch()) {
                $updateError = 'Email is already taken by another user';
            } else {
                $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, role = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $success = $stmt->execute([$name, $email, $role, $id]);
                
                if ($success) {
                    $updateSuccess = 'User updated successfully';
                } else {
                    $updateError = 'Failed to update user';
                }
            }
        } catch (Exception $e) {
            error_log('Update user error: ' . $e->getMessage());
            $updateError = 'An error occurred. Please try again later.';
        }
    }
}

// Handle password reset
$resetError = '';
$resetSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $id = $_POST['id'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($password) || strlen($password) < 8) {
        $resetError = 'Password must be at least 8 characters long';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $success = $stmt->execute([$hashedPassword, $id]);
            
            if ($success) {
                $resetSuccess = 'Password reset successfully';
            } else {
                $resetError = 'Failed to reset password';
            }
        } catch (Exception $e) {
            error_log('Reset password error: ' . $e->getMessage());
            $resetError = 'An error occurred. Please try again later.';
        }
    }
}

// Handle user deletion
$deleteError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $id = $_POST['id'] ?? '';
    
    // Don't allow deleting self
    if ($id == $_SESSION['user_id']) {
        $deleteError = 'You cannot delete your own account';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Begin transaction
            $db->beginTransaction();
            
            // Delete user's projects (cascades to delete collections, documents, files, api keys)
            $stmt = $db->prepare("DELETE FROM projects WHERE user_id = ?");
            $stmt->execute([$id]);
            
            // Delete user
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                $db->commit();
                header('Location: /dashboard/users.php');
                exit;
            } else {
                $db->rollBack();
                $deleteError = 'Failed to delete user';
            }
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Delete user error: ' . $e->getMessage());
            $deleteError = 'An error occurred. Please try again later.';
        }
    }
}

// Get user details if ID is provided
$userDetails = null;
if ($userId) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, email, name, role, created_at, updated_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userDetails) {
            // Get user stats
            $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ?");
            $stmt->execute([$userId]);
            $userDetails['projects_count'] = $stmt->fetchColumn();
            
            $stmt = $db->prepare("SELECT COUNT(*) FROM collections c JOIN projects p ON c.project_id = p.id WHERE p.user_id = ?");
            $stmt->execute([$userId]);
            $userDetails['collections_count'] = $stmt->fetchColumn();
            
            $stmt = $db->prepare("SELECT COUNT(*) FROM files f JOIN projects p ON f.project_id = p.id WHERE p.user_id = ?");
            $stmt->execute([$userId]);
            $userDetails['files_count'] = $stmt->fetchColumn();
        }
    } catch (Exception $e) {
        error_log('Get user details error: ' . $e->getMessage());
    }
}

// Get all users
$users = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, email, name, role, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count projects for each user
    foreach ($users as &$user) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $user['projects_count'] = $stmt->fetchColumn();
    }
} catch (Exception $e) {
    error_log('Get users error: ' . $e->getMessage());
}

// Include header
include_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <?php if ($userDetails): ?>
        <!-- User Details -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($userDetails['name']); ?></h4>
            <div>
                <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#editUserModal">
                    <i class="fas fa-edit me-1"></i>Edit
                </button>
                <button type="button" class="btn btn-outline-warning me-2" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                    <i class="fas fa-key me-1"></i>Reset Password
                </button>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal">
                    <i class="fas fa-trash-alt me-1"></i>Delete
                </button>
            </div>
        </div>
        
        <?php if ($updateSuccess): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($updateSuccess); ?></div>
        <?php endif; ?>
        
        <?php if ($updateError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($updateError); ?></div>
        <?php endif; ?>
        
        <?php if ($resetSuccess): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($resetSuccess); ?></div>
        <?php endif; ?>
        
        <?php if ($resetError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($resetError); ?></div>
        <?php endif; ?>
        
        <?php if ($deleteError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($deleteError); ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <!-- User Info -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>User Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">User ID</div>
                            <div class="col-md-8"><?php echo $userDetails['id']; ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Email</div>
                            <div class="col-md-8"><?php echo htmlspecialchars($userDetails['email']); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Role</div>
                            <div class="col-md-8">
                                <?php if ($userDetails['role'] === 'admin'): ?>
                                <span class="badge bg-danger">Admin</span>
                                <?php else: ?>
                                <span class="badge bg-primary">User</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Created</div>
                            <div class="col-md-8"><?php echo date('F j, Y, g:i a', strtotime($userDetails['created_at'])); ?></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 fw-bold">Last Updated</div>
                            <div class="col-md-8"><?php echo date('F j, Y, g:i a', strtotime($userDetails['updated_at'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <!-- User Stats -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>User Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="card text-center h-100">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary">Projects</h5>
                                        <h2 class="display-4"><?php echo $userDetails['projects_count']; ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card text-center h-100">
                                    <div class="card-body">
                                        <h5 class="card-title text-success">Collections</h5>
                                        <h2 class="display-4"><?php echo $userDetails['collections_count']; ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card text-center h-100">
                                    <div class="card-body">
                                        <h5 class="card-title text-warning">Files</h5>
                                        <h2 class="display-4"><?php echo $userDetails['files_count']; ?></h2>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Edit User Modal -->
        <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="update_user">
                            <input type="hidden" name="id" value="<?php echo $userDetails['id']; ?>">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="edit_name" name="name" value="<?php echo htmlspecialchars($userDetails['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="edit_email" name="email" value="<?php echo htmlspecialchars($userDetails['email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_role" class="form-label">Role</label>
                                <select class="form-select" id="edit_role" name="role">
                                    <option value="user" <?php echo $userDetails['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="admin" <?php echo $userDetails['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Reset Password Modal -->
        <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="resetPasswordModalLabel">Reset User Password</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-warning">Warning: This will reset the user's password. The user will need to log in with the new password.</p>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="id" value="<?php echo $userDetails['id']; ?>">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="password" required minlength="8">
                                <div class="form-text">Password must be at least 8 characters long</div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-warning">Reset Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Delete User Modal -->
        <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteUserModalLabel">Delete User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-danger"><strong>Warning:</strong> This action is irreversible!</p>
                        <p>Are you sure you want to delete this user? This will delete all of the user's projects, collections, documents, and files.</p>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="id" value="<?php echo $userDetails['id']; ?>">
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete User</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Users List -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-users me-2"></i>User Management</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                <i class="fas fa-user-plus me-1"></i>Create User
            </button>
        </div>
        
        <?php if ($createSuccess): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($createSuccess); ?></div>
        <?php endif; ?>
        
        <?php if ($createError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($createError); ?></div>
        <?php endif; ?>
        
        <?php if ($deleteError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($deleteError); ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-friends me-2"></i>Users</h5>
            </div>
            <div class="card-body">
                <?php if (empty($users)): ?>
                <p class="text-muted">No users found.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Projects</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php if ($user['role'] === 'admin'): ?>
                                    <span class="badge bg-danger">Admin</span>
                                    <?php else: ?>
                                    <span class="badge bg-primary">User</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['projects_count']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <a href="/dashboard/users.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Create User Modal -->
        <div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createUserModalLabel">Create New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="create_user">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                <div class="form-text">Password must be at least 8 characters long</div>
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="user" selected>User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Create User</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>
