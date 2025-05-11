<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../api/utils.php';

// Get project ID if set
$projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;

// Check if creating a new project
$isNewProject = isset($_GET['action']) && $_GET['action'] === 'new';

// Process project creation
$createError = '';
$createSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_project') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($name)) {
        $createError = 'Project name is required';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("INSERT INTO projects (name, description, user_id) VALUES (?, ?, ?)");
            $success = $stmt->execute([$name, $description, $_SESSION['user_id']]);
            
            if ($success) {
                $newProjectId = $db->lastInsertId();
                
                // Create a default API key for the project
                $apiKey = ApiUtils::generateToken(64);
                $keyName = "Default Key";
                
                $stmt = $db->prepare("INSERT INTO api_keys (project_id, api_key, name, permissions) VALUES (?, ?, ?, 'read,write')");
                $stmt->execute([$newProjectId, $apiKey, $keyName]);
                
                $createSuccess = 'Project created successfully';
                header('Location: /dashboard/projects.php?project_id=' . $newProjectId);
                exit;
            } else {
                $createError = 'Failed to create project';
            }
        } catch (Exception $e) {
            error_log('Create project error: ' . $e->getMessage());
            $createError = 'An error occurred. Please try again later.';
        }
    }
}

// Process project update
$updateError = '';
$updateSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_project') {
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($name)) {
        $updateError = 'Project name is required';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Check if project belongs to the current user
            $stmt = $db->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            
            if (!$stmt->fetch()) {
                $updateError = 'Project not found or access denied';
            } else {
                $stmt = $db->prepare("UPDATE projects SET name = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $success = $stmt->execute([$name, $description, $id]);
                
                if ($success) {
                    $updateSuccess = 'Project updated successfully';
                } else {
                    $updateError = 'Failed to update project';
                }
            }
        } catch (Exception $e) {
            error_log('Update project error: ' . $e->getMessage());
            $updateError = 'An error occurred. Please try again later.';
        }
    }
}

// Process project deletion
$deleteError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_project') {
    $id = $_POST['id'] ?? '';
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if project belongs to the current user
        $stmt = $db->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            $deleteError = 'Project not found or access denied';
        } else {
            // Begin transaction
            $db->beginTransaction();
            
            // Delete all files associated with the project from the filesystem
            $stmt = $db->prepare("SELECT file_path FROM files WHERE project_id = ?");
            $stmt->execute([$id]);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($files as $file) {
                $filePath = $file['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Delete the project (cascades to delete associated records)
            $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                $db->commit();
                // Redirect to projects page
                header('Location: /dashboard/projects.php');
                exit;
            } else {
                $db->rollBack();
                $deleteError = 'Failed to delete project';
            }
        }
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Delete project error: ' . $e->getMessage());
        $deleteError = 'An error occurred. Please try again later.';
    }
}

// Get project list or project details
$projects = [];
$projectDetails = null;
$apiKeys = [];
$collections = [];
$fileCount = 0;

try {
    $db = Database::getInstance()->getConnection();
    
    if ($projectId) {
        // Get project details
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$projectId, $_SESSION['user_id']]);
        $projectDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($projectDetails) {
            // Get API keys for the project
            $stmt = $db->prepare("SELECT * FROM api_keys WHERE project_id = ?");
            $stmt->execute([$projectId]);
            $apiKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get collections for the project
            $stmt = $db->prepare("SELECT * FROM collections WHERE project_id = ?");
            $stmt->execute([$projectId]);
            $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Count documents in each collection
            foreach ($collections as $i => $collection) {
                $stmt = $db->prepare("SELECT COUNT(*) as doc_count FROM documents WHERE collection_id = ?");
                $stmt->execute([$collection['id']]);
                $collections[$i]['doc_count'] = $stmt->fetchColumn();
            }            
            
            // Get file count for the project
            $stmt = $db->prepare("SELECT COUNT(*) FROM files WHERE project_id = ?");
            $stmt->execute([$projectId]);
            $fileCount = $stmt->fetchColumn();
        }
    } else {
        // Get all projects for the current user
        $stmt = $db->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get collection, file, and deployment counts for each project
        foreach ($projects as $i => $p) {
            // Collections
            $stmt = $db->prepare("SELECT COUNT(*) FROM collections WHERE project_id = ?");
            $stmt->execute([$p['id']]);
            $projects[$i]['collection_count'] = $stmt->fetchColumn();
        
            // Files
            $stmt = $db->prepare("SELECT COUNT(*) FROM files WHERE project_id = ?");
            $stmt->execute([$p['id']]);
            $projects[$i]['file_count'] = $stmt->fetchColumn();
        
            // Deployments
            $stmt = $db->prepare("SELECT COUNT(*) FROM deployments WHERE project_id = ?");
            $stmt->execute([$p['id']]);
            $projects[$i]['deployment_count'] = $stmt->fetchColumn();
        }
        
    }
} catch (Exception $e) {
    error_log('Project list/details error: ' . $e->getMessage());
}

// Include header
include_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <?php if ($isNewProject): ?>
        <!-- Create New Project Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Create New Project</h5>
            </div>
            <div class="card-body">
                <?php if ($createError): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($createError); ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <input type="hidden" name="action" value="create_project">
                    <div class="mb-3">
                        <label for="name" class="form-label">Project Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="/dashboard/projects.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Project</button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($projectDetails): ?>
        <!-- Project Details -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><?php echo htmlspecialchars($projectDetails['name']); ?></h2>
            <div>
                <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#editProjectModal">
                    <i class="fas fa-edit me-1"></i>Edit
                </button>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteProjectModal">
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
        
        <!-- Project Stats -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-light h-100">
                    <div class="card-body">
                        <h5 class="card-title">Collections</h5>
                        <h2 class="display-4"><?php echo count($collections); ?></h2>
                    </div>
                    <div class="card-footer">
                        <a href="/dashboard/database.php?project_id=<?php echo $projectId; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-database me-1"></i>Manage
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-light h-100">
                    <div class="card-body">
                        <h5 class="card-title">Files</h5>
                        <h2 class="display-4"><?php echo $fileCount; ?></h2>
                    </div>
                    <div class="card-footer">
                        <a href="/dashboard/files.php?project_id=<?php echo $projectId; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-file me-1"></i>Manage
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-light h-100">
                    <div class="card-body">
                        <h5 class="card-title">API Keys</h5>
                        <h2 class="display-4"><?php echo count($apiKeys); ?></h2>
                    </div>
                    <div class="card-footer">
                        <a href="/dashboard/keys.php?project_id=<?php echo $projectId; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-key me-1"></i>Manage
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-light h-100">
                    <div class="card-body">
                        <h5 class="card-title">Deployments</h5>
                        <h2 class="display-4">
                            <?php 
                            // Get deployment count
                            $deploymentCount = 0;
                            try {
                                $stmt = $db->prepare("SELECT COUNT(*) FROM deployments WHERE project_id = ?");
                                $stmt->execute([$projectId]);
                                $deploymentCount = $stmt->fetchColumn();
                            } catch (Exception $e) {
                                // Ignore error
                            }
                            echo $deploymentCount; 
                            ?>
                        </h2>
                    </div>
                    <div class="card-footer">
                        <a href="/dashboard/deployments.php?project_id=<?php echo $projectId; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-rocket me-1"></i>Manage
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Project Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Project Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 fw-bold">Project ID</div>
                    <div class="col-md-9"><?php echo $projectId; ?></div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-3 fw-bold">Description</div>
                    <div class="col-md-9"><?php echo htmlspecialchars($projectDetails['description'] ?: 'No description'); ?></div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-3 fw-bold">Created</div>
                    <div class="col-md-9"><?php echo date('F j, Y, g:i a', strtotime($projectDetails['created_at'])); ?></div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-3 fw-bold">Last Updated</div>
                    <div class="col-md-9"><?php echo date('F j, Y, g:i a', strtotime($projectDetails['updated_at'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- API Keys -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-key me-2"></i>API Keys</h5>
                <a href="/dashboard/keys.php?project_id=<?php echo $projectId; ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus me-1"></i>Add Key
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($apiKeys)): ?>
                <p class="text-muted">No API keys found.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Permissions</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($apiKeys as $key): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($key['name']); ?></td>
                                <td>
                                    <?php if ($key['permissions'] === 'read,write'): ?>
                                    <span class="badge bg-success">Read & Write</span>
                                    <?php elseif ($key['permissions'] === 'read'): ?>
                                    <span class="badge bg-info">Read Only</span>
                                    <?php else: ?>
                                    <span class="badge bg-warning">Write Only</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($key['created_at'])); ?></td>
                                <td>
                                    <a href="/dashboard/keys.php?project_id=<?php echo $projectId; ?>&key_id=<?php echo $key['id']; ?>" class="btn btn-sm btn-outline-primary">
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
        
        <!-- Collections -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-database me-2"></i>Collections</h5>
                <a href="/dashboard/database.php?project_id=<?php echo $projectId; ?>&action=new" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus me-1"></i>Add Collection
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($collections)): ?>
                <p class="text-muted">No collections found.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Documents</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($collections as $collection): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($collection['name']); ?></td>
                                <td><?php echo $collection['doc_count']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($collection['created_at'])); ?></td>
                                <td>
                                    <a href="/dashboard/database.php?project_id=<?php echo $projectId; ?>&collection_id=<?php echo $collection['id']; ?>" class="btn btn-sm btn-outline-primary">
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
        
        <!-- Edit Project Modal -->
        <div class="modal fade" id="editProjectModal" tabindex="-1" aria-labelledby="editProjectModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editProjectModalLabel">Edit Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="update_project">
                            <input type="hidden" name="id" value="<?php echo $projectId; ?>">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Project Name</label>
                                <input type="text" class="form-control" id="edit_name" name="name" value="<?php echo htmlspecialchars($projectDetails['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"><?php echo htmlspecialchars($projectDetails['description']); ?></textarea>
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
        
        <!-- Delete Project Modal -->
        <div class="modal fade" id="deleteProjectModal" tabindex="-1" aria-labelledby="deleteProjectModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteProjectModalLabel">Delete Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this project? This action cannot be undone and will delete all associated collections, documents, and files.</p>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="delete_project">
                            <input type="hidden" name="id" value="<?php echo $projectId; ?>">
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete Project</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Projects List -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-project-diagram me-2"></i>Your Projects</h2>
            <a href="/dashboard/projects.php?action=new" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>New Project
            </a>
        </div>
        
        <?php if ($deleteError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($deleteError); ?></div>
        <?php endif; ?>
        
        <?php if (empty($projects)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                <h4>No Projects Found</h4>
                <p class="text-muted">Get started by creating your first project</p>
                <a href="/dashboard/projects.php?action=new" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create Project
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="row">
            <?php foreach ($projects as $project): ?>
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($project['name']); ?></h5>
                        <p class="card-text text-muted">
                            <?php echo htmlspecialchars($project['description'] ?: 'No description'); ?>
                        </p>
                        <div class="d-flex">
                            <div class="me-3">
                                <small class="d-block text-muted">Collections</small>
                                <span class="fw-bold"><?php echo $project['collection_count']; ?></span>
                            </div>
                            <div class="me-3">
                                <small class="d-block text-muted">Files</small>
                                <span class="fw-bold"><?php echo $project['file_count']; ?></span>
                            </div>
                            <div>
                                <small class="d-block text-muted">Deployments</small>
                                <span class="fw-bold"><?php echo $project['deployment_count']; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <small class="text-muted">Created: <?php echo date('M j, Y', strtotime($project['created_at'])); ?></small>
                        <a href="/dashboard/projects.php?project_id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-arrow-right me-1"></i>View
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>
