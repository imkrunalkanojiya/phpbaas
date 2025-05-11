<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

session_start();

// Handle login form submission
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $loginError = 'Email and password are required';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("SELECT id, email, password, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['name'];
                
                // Redirect to dashboard or previously requested page
                $redirectUrl = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : '/dashboard/index.php';
                unset($_SESSION['redirect_url']);
                
                header('Location: ' . $redirectUrl);
                exit;
            } else {
                $loginError = 'Invalid email or password';
            }
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            $loginError = 'An error occurred. Please try again later.';
        }
    }
}

// Handle registration form submission
$registrationError = '';
$registrationSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
        $registrationError = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registrationError = 'Invalid email format';
    } elseif (strlen($password) < 8) {
        $registrationError = 'Password must be at least 8 characters long';
    } elseif ($password !== $confirmPassword) {
        $registrationError = 'Passwords do not match';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $registrationError = 'Email already registered';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                $success = $stmt->execute([$name, $email, $hashedPassword]);
                
                if ($success) {
                    $registrationSuccess = 'Registration successful! You can now log in.';
                } else {
                    $registrationError = 'Registration failed. Please try again.';
                }
            }
        } catch (Exception $e) {
            error_log('Registration error: ' . $e->getMessage());
            $registrationError = 'An error occurred. Please try again later.';
        }
    }
}

// Handle logout
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: /dashboard/index.php');
    exit;
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// If logged in, get user's dashboard data
$dashboardData = null;
if ($isLoggedIn) {
    try {
        $db = Database::getInstance()->getConnection();
        $userId = $_SESSION['user_id'];
        
        // Get projects count
        $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ?");
        $stmt->execute([$userId]);
        $projectsCount = $stmt->fetchColumn();
        
        // Get collections count
        $stmt = $db->prepare("SELECT COUNT(*) FROM collections c 
                             JOIN projects p ON c.project_id = p.id 
                             WHERE p.user_id = ?");
        $stmt->execute([$userId]);
        $collectionsCount = $stmt->fetchColumn();
        
        // Get documents count
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents d 
                             JOIN collections c ON d.collection_id = c.id 
                             JOIN projects p ON c.project_id = p.id 
                             WHERE p.user_id = ?");
        $stmt->execute([$userId]);
        $documentsCount = $stmt->fetchColumn();
        
        // Get files count
        $stmt = $db->prepare("SELECT COUNT(*) FROM files f 
                             JOIN projects p ON f.project_id = p.id 
                             WHERE p.user_id = ?");
        $stmt->execute([$userId]);
        $filesCount = $stmt->fetchColumn();
        
        // Get recent projects
        $stmt = $db->prepare("SELECT id, name, created_at FROM projects 
                             WHERE user_id = ? 
                             ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$userId]);
        $recentProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $dashboardData = [
            'projects_count' => $projectsCount,
            'collections_count' => $collectionsCount,
            'documents_count' => $documentsCount,
            'files_count' => $filesCount,
            'recent_projects' => $recentProjects
        ];
    } catch (Exception $e) {
        error_log('Dashboard data error: ' . $e->getMessage());
    }
}

// Include header
include_once __DIR__ . '/../includes/header.php';
?>

<?php if (!$isLoggedIn): ?>
<!-- Login/Register Section -->
<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card mb-4">
            <div class="card-body p-0">
                <div class="row g-0">
                    <div class="col-md-6 d-flex align-items-center bg-primary text-white p-5">
                        <div>
                            <h2 class="display-5">PHP Backend as a Service</h2>
                            <p class="lead">Build apps faster with our simple and powerful BaaS platform</p>
                            <hr class="my-4">
                            <ul class="fa-ul">
                                <li><span class="fa-li"><i class="fas fa-check-circle"></i></span> User Authentication</li>
                                <li><span class="fa-li"><i class="fas fa-check-circle"></i></span> Database Collections</li>
                                <li><span class="fa-li"><i class="fas fa-check-circle"></i></span> File Storage</li>
                                <li><span class="fa-li"><i class="fas fa-check-circle"></i></span> RESTful API</li>
                                <li><span class="fa-li"><i class="fas fa-check-circle"></i></span> API Key Management</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6 p-4">
                        <ul class="nav nav-tabs" id="authTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab" aria-controls="login" aria-selected="true">Login</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab" aria-controls="register" aria-selected="false">Register</button>
                            </li>
                        </ul>
                        <div class="tab-content pt-4" id="authTabsContent">
                            <div class="tab-pane fade show active" id="login" role="tabpanel" aria-labelledby="login-tab">
                                <h3 class="mb-4">Login to your account</h3>
                                
                                <?php if ($loginError): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($loginError); ?></div>
                                <?php endif; ?>
                                
                                <form method="post" action="">
                                    <input type="hidden" name="action" value="login">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email address</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Login</button>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="register" role="tabpanel" aria-labelledby="register-tab">
                                <h3 class="mb-4">Create a new account</h3>
                                
                                <?php if ($registrationError): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($registrationError); ?></div>
                                <?php endif; ?>
                                
                                <?php if ($registrationSuccess): ?>
                                <div class="alert alert-success"><?php echo htmlspecialchars($registrationSuccess); ?></div>
                                <?php endif; ?>
                                
                                <form method="post" action="">
                                    <input type="hidden" name="action" value="register">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="register-email" class="form-label">Email address</label>
                                        <input type="email" class="form-control" id="register-email" name="email" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="register-password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="register-password" name="password" required minlength="8">
                                        <div class="form-text">Must be at least 8 characters long</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm-password" class="form-label">Confirm Password</label>
                                        <input type="password" class="form-control" id="confirm-password" name="confirm_password" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Register</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Features -->
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-database fa-3x text-primary"></i>
                        </div>
                        <h4 class="card-title">Database Collections</h4>
                        <p class="card-text">Store and query structured data in collections with a simple REST API.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-file-upload fa-3x text-primary"></i>
                        </div>
                        <h4 class="card-title">File Storage</h4>
                        <p class="card-text">Upload, store, and serve files through a secure API.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-user-shield fa-3x text-primary"></i>
                        </div>
                        <h4 class="card-title">Authentication</h4>
                        <p class="card-text">Secure user authentication and API key management.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Dashboard -->
<div class="row">
    <div class="col-md-3">
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h2>
            <a href="/dashboard/projects.php?action=new" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>New Project
            </a>
        </div>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h5 class="card-title text-primary">Projects</h5>
                        <h2 class="display-4"><?php echo $dashboardData['projects_count']; ?></h2>
                    </div>
                    <div class="card-footer">
                        <a href="/dashboard/projects.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h5 class="card-title text-success">Collections</h5>
                        <h2 class="display-4"><?php echo $dashboardData['collections_count']; ?></h2>
                    </div>
                    <div class="card-footer">
                        <a href="/dashboard/database.php" class="btn btn-sm btn-outline-success">Manage</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h5 class="card-title text-info">Documents</h5>
                        <h2 class="display-4"><?php echo $dashboardData['documents_count']; ?></h2>
                    </div>
                    <div class="card-footer">
                        <a href="/dashboard/database.php" class="btn btn-sm btn-outline-info">Explore</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <h5 class="card-title text-warning">Files</h5>
                        <h2 class="display-4"><?php echo $dashboardData['files_count']; ?></h2>
                    </div>
                    <div class="card-footer">
                        <a href="/dashboard/files.php" class="btn btn-sm btn-outline-warning">View Files</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Projects -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Projects</h5>
                <a href="/dashboard/projects.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($dashboardData['recent_projects'])): ?>
                <div class="text-center py-4">
                    <p class="text-muted">You haven't created any projects yet.</p>
                    <a href="/dashboard/projects.php?action=new" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Create Your First Project
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dashboardData['recent_projects'] as $project): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($project['name']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($project['created_at'])); ?></td>
                                <td>
                                    <a href="/dashboard/projects.php?project_id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline-primary">
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
        
        <!-- Quick Start Guide -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Quick Start Guide</h5>
            </div>
            <div class="card-body">
                <ol class="mb-0">
                    <li class="mb-2"><strong>Create a Project</strong> - Start by creating a new project</li>
                    <li class="mb-2"><strong>Get API Keys</strong> - Generate API keys for your project</li>
                    <li class="mb-2"><strong>Create Collections</strong> - Define your data structure with collections</li>
                    <li class="mb-2"><strong>Add Documents</strong> - Store data in your collections</li>
                    <li><strong>Integrate</strong> - Use our API documentation to integrate with your application</li>
                </ol>
            </div>
            <div class="card-footer text-center">
                <a href="/documentation/index.php" class="btn btn-primary">
                    <i class="fas fa-book me-2"></i>View Documentation
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>
