<?php
/**
 * Deployments Dashboard
 * Allows users to manage their application deployments
 */
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../api/utils.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// Get project ID if provided
$projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$activeProject = null;

// Get all user projects for sidebar
$stmt = $db->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY name");
$stmt->execute([$userId]);
$userProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If project ID is provided, get project details
if ($projectId) {
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$projectId, $userId]);
    $activeProject = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$activeProject) {
        $errorMessage = "Project not found";
    }
}

// Handle specific actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$deploymentId = isset($_GET['deployment_id']) ? $_GET['deployment_id'] : '';
$message = '';
$errorMessage = '';
$successMessage = '';

// Get deployment details if deployment ID is provided
$deploymentDetails = null;
if ($deploymentId) {
    $stmt = $db->prepare("SELECT * FROM deployments WHERE id = ? AND project_id = ?");
    $stmt->execute([$deploymentId, $projectId]);
    $deploymentDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$deploymentDetails) {
        $errorMessage = "Deployment not found";
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new deployment
    if ($action === 'create' && $projectId) {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $environment = isset($_POST['environment']) ? trim($_POST['environment']) : '';
        $sourceCode = isset($_POST['source_code']) ? trim($_POST['source_code']) : '';
        $deploymentMethod = isset($_POST['deployment_method']) ? trim($_POST['deployment_method']) : 'url';
        
        $zipFilePath = null;
        $deploymentPath = null;
        $previewUrl = null;
        
        // Validate required fields based on deployment method
        if (empty($name) || empty($environment)) {
            $errorMessage = "Name and environment are required fields";
        } elseif ($deploymentMethod === 'url' && empty($sourceCode)) {
            $errorMessage = "Source code URL is required for repository deployment";
        } elseif ($deploymentMethod === 'upload' && empty($_FILES['zip_file']['name'])) {
            $errorMessage = "Zip file is required for file upload deployment";
        } else {
            // Generate unique deployment ID
            $deploymentId = uniqid('deploy_');
            
            // Process file upload if method is upload
            if ($deploymentMethod === 'upload' && isset($_FILES['zip_file']) && $_FILES['zip_file']['error'] === UPLOAD_ERR_OK) {
                $uploadedFile = $_FILES['zip_file'];
                $fileName = $uploadedFile['name'];
                $tmpPath = $uploadedFile['tmp_name'];
                
                // Validate file is a ZIP archive
                $fileInfo = pathinfo($fileName);
                if (strtolower($fileInfo['extension']) !== 'zip') {
                    $errorMessage = "Only ZIP files are allowed";
                } else {
                    // Create directories for this deployment
                    $relativePath = "phpbaas/deployed_apps/{$projectId}/{$deploymentId}";
                    $uploadDir = __DIR__ . "/../{$relativePath}";
                    $extractDir = $uploadDir . "/extracted";
                    
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    if (!is_dir($extractDir)) {
                        mkdir($extractDir, 0755, true);
                    }
                    
                    // Save the uploaded file
                    $zipFilePath = $uploadDir . "/" . $fileName;
                    if (move_uploaded_file($tmpPath, $zipFilePath)) {
                        // Extract the zip file
                        $zip = new ZipArchive;
                        if ($zip->open($zipFilePath) === true) {
                            $zip->extractTo($extractDir);
                            $zip->close();
                            
                            // Set deployment paths
                            $deploymentPath = $extractDir;
                            $previewUrl = "/deployed_apps.php?id={$deploymentId}";
                            $sourceCode = "Uploaded: {$fileName}";
                        } else {
                            $errorMessage = "Failed to extract ZIP file";
                        }
                    } else {
                        $errorMessage = "Failed to upload file";
                    }
                }
            }
            
            // If no errors, proceed with deployment
            if (empty($errorMessage)) {
                try {
                    // Insert deployment
                    $stmt = $db->prepare("
                        INSERT INTO deployments (
                            id, project_id, name, description, environment, 
                            source_code, status, zip_file_path, deployment_path, preview_url,
                            created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
                    ");
                    
                    $stmt->execute([
                        $deploymentId,
                        $projectId,
                        $name,
                        $description,
                        $environment,
                        $sourceCode,
                        'pending',   // status
                        $zipFilePath,
                        $deploymentPath,
                        $previewUrl
                    ]);
                    
                    // Simulate a successful deployment
                    $stmt = $db->prepare("
                        UPDATE deployments 
                        SET status = 'active', url = ?, updated_at = datetime('now')
                        WHERE id = ?
                    ");
                    
                    // Generate a URL
                    $deploymentUrl = ($previewUrl) ? $previewUrl : "https://" . $name . "-" . substr($deploymentId, 7) . ".example.com";
                    $stmt->execute([$deploymentUrl, $deploymentId]);
                    
                    $successMessage = "Deployment created successfully";
                    
                    // Insert deployment logs
                    $stmt = $db->prepare("
                        INSERT INTO deployment_logs (
                            deployment_id, message, level, created_at
                        ) VALUES (?, ?, ?, datetime('now'))
                    ");
                    
                    if ($deploymentMethod === 'upload') {
                        $stmt->execute([$deploymentId, 'Zip file uploaded successfully', 'info']);
                        $stmt->execute([$deploymentId, 'Extracted application files', 'info']);
                    }
                    
                    $stmt->execute([$deploymentId, 'Deployment created and activated', 'success']);
                    
                    // Redirect to avoid form resubmission
                    header("Location: /dashboard/deployments.php?project_id=$projectId&action=view&deployment_id={$deploymentId}");
                    exit;
                } catch (Exception $e) {
                    $errorMessage = "Failed to create deployment: " . $e->getMessage();
                }
            }
        }
    }
    
    // Delete deployment
    if ($action === 'delete' && $deploymentId) {
        try {
            $stmt = $db->prepare("DELETE FROM deployments WHERE id = ? AND project_id = ?");
            $stmt->execute([$deploymentId, $projectId]);
            
            $successMessage = "Deployment deleted successfully";
            
            // Redirect to avoid form resubmission
            header("Location: /dashboard/deployments.php?project_id=$projectId&success=deleted");
            exit;
        } catch (Exception $e) {
            $errorMessage = "Failed to delete deployment: " . $e->getMessage();
        }
    }
}

// Process success messages from redirects
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $successMessage = "Deployment created successfully";
            break;
        case 'deleted':
            $successMessage = "Deployment deleted successfully";
            break;
    }
}

// Get deployments for the current project
$deployments = [];
if ($projectId) {
    $stmt = $db->prepare("SELECT * FROM deployments WHERE project_id = ? ORDER BY created_at DESC");
    $stmt->execute([$projectId]);
    $deployments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get deployment logs if viewing a specific deployment
$deploymentLogs = [];
if ($deploymentId && $action === 'view') {
    $stmt = $db->prepare("
        SELECT * FROM deployment_logs 
        WHERE deployment_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$deploymentId]);
    $deploymentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no logs exist, create sample logs
    if (empty($deploymentLogs) && $deploymentDetails) {
        // Sample log entries
        $sampleLogs = [
            [
                'deployment_id' => $deploymentId,
                'message' => 'Deployment started',
                'level' => 'info',
                'created_at' => $deploymentDetails['created_at']
            ],
            [
                'deployment_id' => $deploymentId,
                'message' => 'Building application',
                'level' => 'info',
                'created_at' => $deploymentDetails['created_at']
            ],
            [
                'deployment_id' => $deploymentId,
                'message' => 'Application built successfully',
                'level' => 'info',
                'created_at' => $deploymentDetails['created_at']
            ],
            [
                'deployment_id' => $deploymentId,
                'message' => 'Deployment completed',
                'level' => 'success',
                'created_at' => $deploymentDetails['updated_at']
            ]
        ];
        
        // Insert sample logs
        foreach ($sampleLogs as $log) {
            $stmt = $db->prepare("
                INSERT INTO deployment_logs (
                    deployment_id, message, level
                ) VALUES (?, ?, ?)
            ");
            
            $stmt->execute([
                $log['deployment_id'],
                $log['message'],
                $log['level']
            ]);
        }
        
        // Fetch the newly created logs
        $stmt = $db->prepare("
            SELECT * FROM deployment_logs 
            WHERE deployment_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$deploymentId]);
        $deploymentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Page title
$pageTitle = "Deployments";
if ($activeProject) {
    $pageTitle .= " - " . htmlspecialchars($activeProject['name']);
}

// Include header and sidebar
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
            <?php include_once '../includes/sidebar.php'; ?>
        </div>

        <!-- Main content -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <?php if ($activeProject): ?>
                        <a href="/dashboard/projects.php?project_id=<?php echo $activeProject['id']; ?>" class="text-decoration-none">
                            <?php echo htmlspecialchars($activeProject['name']); ?>
                        </a> 
                        <i class="fas fa-angle-right mx-2"></i>
                    <?php endif; ?>
                    Deployments
                </h1>
                
                <!--  -->
            </div>

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($errorMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($successMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!$projectId): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Select a project from the sidebar to manage its deployments.
                </div>
            <?php elseif ($action === 'new'): ?>
                <!-- New Deployment Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">New Deployment</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="/dashboard/deployments.php?project_id=<?php echo $projectId; ?>&action=create" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="name" class="form-label">Deployment Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required>
                                <small class="text-muted">A unique name for this deployment</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                                <small class="text-muted">Optional description of this deployment</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="environment" class="form-label">Environment <span class="text-danger">*</span></label>
                                <select class="form-select" id="environment" name="environment" required>
                                    <option value="production">Production</option>
                                    <option value="staging">Staging</option>
                                    <option value="development">Development</option>
                                    <option value="testing">Testing</option>
                                </select>
                                <small class="text-muted">The environment where this deployment will run</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Deployment Method <span class="text-danger">*</span></label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="deployment_method" id="method_url" value="url" checked onclick="toggleDeploymentMethod('url')">
                                    <label class="form-check-label" for="method_url">
                                        Deploy from Git Repository
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="deployment_method" id="method_upload" value="upload" onclick="toggleDeploymentMethod('upload')">
                                    <label class="form-check-label" for="method_upload">
                                        Upload ZIP Archive
                                    </label>
                                </div>
                            </div>
                            
                            <div id="url_section" class="mb-3">
                                <label for="source_code" class="form-label">Source Code URL <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="source_code" name="source_code" 
                                       placeholder="https://github.com/username/repo">
                                <small class="text-muted">URL to your Git repository</small>
                            </div>
                            
                            <div id="upload_section" class="mb-3" style="display: none;">
                                <label for="zip_file" class="form-label">ZIP File <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="zip_file" name="zip_file" accept=".zip">
                                <small class="text-muted">Upload a ZIP file containing your PHP application</small>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <a href="/dashboard/deployments.php?project_id=<?php echo $projectId; ?>" class="btn btn-secondary me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Create Deployment</button>
                            </div>
                        </form>
                        
                        <script>
                            function toggleDeploymentMethod(method) {
                                if (method === 'url') {
                                    document.getElementById('url_section').style.display = 'block';
                                    document.getElementById('upload_section').style.display = 'none';
                                    document.getElementById('source_code').setAttribute('required', 'required');
                                    document.getElementById('zip_file').removeAttribute('required');
                                } else {
                                    document.getElementById('url_section').style.display = 'none';
                                    document.getElementById('upload_section').style.display = 'block';
                                    document.getElementById('source_code').removeAttribute('required');
                                    document.getElementById('zip_file').setAttribute('required', 'required');
                                }
                            }
                        </script>
                    </div>
                </div>
            <?php elseif ($action === 'view' && $deploymentDetails): ?>
                <!-- Deployment Details -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    Deployment Details
                                    <span class="badge <?php echo $deploymentDetails['status'] === 'active' ? 'bg-success' : 'bg-warning'; ?> float-end">
                                        <?php echo ucfirst($deploymentDetails['status']); ?>
                                    </span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Name:</strong> <?php echo htmlspecialchars($deploymentDetails['name']); ?>
                                </div>
                                
                                <?php if ($deploymentDetails['description']): ?>
                                <div class="mb-3">
                                    <strong>Description:</strong> <?php echo htmlspecialchars($deploymentDetails['description']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <strong>Environment:</strong> 
                                    <span class="badge bg-info"><?php echo ucfirst($deploymentDetails['environment']); ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Source Code:</strong> 
                                    <a href="<?php echo htmlspecialchars($deploymentDetails['source_code']); ?>" target="_blank">
                                        <?php echo htmlspecialchars($deploymentDetails['source_code']); ?>
                                    </a>
                                </div>
                                
                                <?php if ($deploymentDetails['url']): ?>
                                <div class="mb-3">
                                    <strong>Deployment URL:</strong> 
                                    <a href="<?php echo htmlspecialchars($deploymentDetails['url']); ?>" target="_blank">
                                        <?php echo htmlspecialchars($deploymentDetails['url']); ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($deploymentDetails['preview_url']): ?>
                                <div class="mb-3">
                                    <strong>Live Preview:</strong> 
                                    <a href="<?php echo htmlspecialchars($deploymentDetails['preview_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> View Application
                                    </a>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($deploymentDetails['zip_file_path']): ?>
                                <div class="mb-3">
                                    <strong>Deployed From:</strong> 
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-file-archive me-1"></i> ZIP Upload
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <strong>Created:</strong> <?php echo htmlspecialchars($deploymentDetails['created_at']); ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Last Updated:</strong> <?php echo htmlspecialchars($deploymentDetails['updated_at']); ?>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <a href="/dashboard/deployments.php?project_id=<?php echo $projectId; ?>" class="btn btn-secondary me-2">
                                        Back to List
                                    </a>
                                    
                                    <?php if ($deploymentDetails['status'] === 'active'): ?>
                                    <form method="post" action="/dashboard/deployments.php?project_id=<?php echo $projectId; ?>&action=delete&deployment_id=<?php echo $deploymentDetails['id']; ?>"
                                          onsubmit="return confirm('Are you sure you want to delete this deployment? This action cannot be undone.');">
                                        <button type="submit" class="btn btn-danger">Delete Deployment</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Deployment Logs</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php if (empty($deploymentLogs)): ?>
                                        <div class="list-group-item">
                                            <p class="mb-0 text-muted">No logs available for this deployment.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($deploymentLogs as $log): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1">
                                                        <span class="badge <?php echo $log['level'] === 'error' ? 'bg-danger' : ($log['level'] === 'warning' ? 'bg-warning' : ($log['level'] === 'success' ? 'bg-success' : 'bg-info')); ?>">
                                                            <?php echo ucfirst($log['level']); ?>
                                                        </span>
                                                    </h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($log['created_at']); ?></small>
                                                </div>
                                                <p class="mb-1"><?php echo htmlspecialchars($log['message']); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Deployments List -->
                <?php if (empty($deployments)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Project Deployment Comming Soon.
                        
                    </div>
                <?php else: ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">All Deployments</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Environment</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>URL</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($deployments as $deployment): ?>
                                            <tr>
                                                <td>
                                                    <a href="/dashboard/deployments.php?project_id=<?php echo $projectId; ?>&action=view&deployment_id=<?php echo $deployment['id']; ?>">
                                                        <?php echo htmlspecialchars($deployment['name']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucfirst($deployment['environment']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $deployment['status'] === 'active' ? 'bg-success' : 'bg-warning'; ?>">
                                                        <?php echo ucfirst($deployment['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($deployment['created_at']); ?></td>
                                                <td>
                                                    <?php if ($deployment['url']): ?>
                                                        <a href="<?php echo htmlspecialchars($deployment['url']); ?>" target="_blank" class="text-truncate">
                                                            <i class="fas fa-external-link-alt me-1"></i> Visit
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="/dashboard/deployments.php?project_id=<?php echo $projectId; ?>&action=view&deployment_id=<?php echo $deployment['id']; ?>" class="btn btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($deployment['status'] === 'active'): ?>
                                                            <form method="post" action="/dashboard/deployments.php?project_id=<?php echo $projectId; ?>&action=delete&deployment_id=<?php echo $deployment['id']; ?>"
                                                                  onsubmit="return confirm('Are you sure you want to delete this deployment? This action cannot be undone.');">
                                                                <button type="submit" class="btn btn-outline-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>