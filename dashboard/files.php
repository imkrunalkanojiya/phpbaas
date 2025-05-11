<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Get project ID
$projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;
if (!$projectId) {
    header('Location: /dashboard/projects.php');
    exit;
}

// Get file ID if set
$fileId = isset($_GET['file_id']) ? intval($_GET['file_id']) : null;

// Check if user has access to the project
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        // Project not found or doesn't belong to user
        header('Location: /dashboard/projects.php');
        exit;
    }
} catch (Exception $e) {
    error_log('Project access check error: ' . $e->getMessage());
    header('Location: /dashboard/projects.php');
    exit;
}

// Ensure storage directory exists
if (!file_exists(STORAGE_PATH)) {
    mkdir(STORAGE_PATH, 0755, true);
}

// Create project storage directory if it doesn't exist
$projectStoragePath = STORAGE_PATH . '/' . $projectId;
if (!file_exists($projectStoragePath)) {
    mkdir($projectStoragePath, 0755, true);
}

// Handle file upload
$uploadError = '';
$uploadSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_file') {
    // Validate file upload
    if (empty($_FILES['file'])) {
        $uploadError = 'No file uploaded';
    } else {
        $file = $_FILES['file'];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
                UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
            ];
            
            $uploadError = isset($errorMessages[$file['error']]) 
                ? $errorMessages[$file['error']] 
                : 'Unknown upload error';
        } else {
            // Check file size
            if ($file['size'] > MAX_FILE_SIZE) {
                $uploadError = 'File size exceeds the maximum limit';
            } else {
                // Get file extension and check if it's allowed
                $fileInfo = pathinfo($file['name']);
                $extension = strtolower($fileInfo['extension']);
                
                $allowedExtensions = explode(',', ALLOWED_FILE_TYPES);
                if (!in_array($extension, $allowedExtensions)) {
                    $uploadError = 'File type not allowed';
                } else {
                    // Generate unique filename
                    $fileName = $fileInfo['filename'] . '_' . time() . '.' . $extension;
                    $filePath = $projectStoragePath . '/' . $fileName;
                    
                    // Move uploaded file
                    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                        $uploadError = 'Failed to save file';
                    } else {
                        // Save file info to database
                        try {
                            $db = Database::getInstance()->getConnection();
                            
                            $stmt = $db->prepare("INSERT INTO files (project_id, file_name, file_path, file_size, file_type, uploaded_by) 
                                             VALUES (?, ?, ?, ?, ?, ?)");
                            
                            $success = $stmt->execute([
                                $projectId,
                                $fileName,
                                $filePath,
                                $file['size'],
                                $file['type'],
                                $_SESSION['user_id']
                            ]);
                            
                            if ($success) {
                                $uploadSuccess = 'File uploaded successfully';
                            } else {
                                // Remove file if database insert fails
                                if (file_exists($filePath)) {
                                    unlink($filePath);
                                }
                                
                                $uploadError = 'Failed to record file information';
                            }
                        } catch (Exception $e) {
                            // Remove file if database insert fails
                            if (file_exists($filePath)) {
                                unlink($filePath);
                            }
                            
                            error_log('File upload error: ' . $e->getMessage());
                            $uploadError = 'Failed to upload file';
                        }
                    }
                }
            }
        }
    }
}

// Handle file deletion
$deleteError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_file') {
    $id = $_POST['id'] ?? '';
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get file path and verify project ownership
        $stmt = $db->prepare("SELECT f.file_path FROM files f
                             JOIN projects p ON f.project_id = p.id
                             WHERE f.id = ? AND p.id = ? AND p.user_id = ?");
        $stmt->execute([$id, $projectId, $_SESSION['user_id']]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            $deleteError = 'File not found or access denied';
        } else {
            $filePath = $file['file_path'];
            
            // Delete file from filesystem
            if (file_exists($filePath)) {
                if (!unlink($filePath)) {
                    $deleteError = 'Failed to delete file from storage';
                }
            }
            
            // Delete from database
            $stmt = $db->prepare("DELETE FROM files WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                header('Location: /dashboard/files.php?project_id=' . $projectId);
                exit;
            } else {
                $deleteError = 'Failed to delete file record';
            }
        }
    } catch (Exception $e) {
        error_log('Delete file error: ' . $e->getMessage());
        $deleteError = 'An error occurred. Please try again later.';
    }
}

// Get project details
$projectDetails = null;
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT p.*, ak.api_key FROM projects p 
                         LEFT JOIN api_keys ak ON p.id = ak.project_id 
                         WHERE p.id = ? AND p.user_id = ? 
                         ORDER BY ak.id DESC LIMIT 1");
    $stmt->execute([$projectId, $_SESSION['user_id']]);
    $projectDetails = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Get project details error: ' . $e->getMessage());
}

// Get file details if file ID is provided
$fileDetails = null;
if ($fileId) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM files WHERE id = ? AND project_id = ?");
        $stmt->execute([$fileId, $projectId]);
        $fileDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Get file details error: ' . $e->getMessage());
    }
}

// Get list of files for the project
$files = [];
$pagination = [
    'total' => 0,
    'page' => 1,
    'limit' => 10,
    'pages' => 1
];

try {
    $db = Database::getInstance()->getConnection();
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Get total count
    $stmt = $db->prepare("SELECT COUNT(*) FROM files WHERE project_id = ?");
    $stmt->execute([$projectId]);
    $total = $stmt->fetchColumn();
    
    // Get files
    $stmt = $db->prepare("SELECT * FROM files WHERE project_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$projectId, $limit, $offset]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set pagination info
    $pagination = [
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit)
    ];
} catch (Exception $e) {
    error_log('Get files error: ' . $e->getMessage());
}

// Get a list of allowed file types for display
$allowedFileTypes = explode(',', ALLOWED_FILE_TYPES);

// Calculate max file size in MB
$maxFileSize = MAX_FILE_SIZE / (1024 * 1024);

// Include header
include_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/dashboard/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/dashboard/projects.php">Projects</a></li>
                <li class="breadcrumb-item"><a href="/dashboard/projects.php?project_id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($projectDetails['name']); ?></a></li>
                <li class="breadcrumb-item active">Files</li>
                <?php if ($fileDetails): ?>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($fileDetails['file_name']); ?></li>
                <?php endif; ?>
            </ol>
        </nav>
        
        <?php if ($fileDetails): ?>
        <!-- File Details -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-file me-2"></i><?php echo htmlspecialchars($fileDetails['file_name']); ?></h4>
            <div>
                <a href="/api/files.php?route=download&id=<?php echo $fileId; ?>&api_key=<?php echo htmlspecialchars($projectDetails['api_key'] ?? ''); ?>" class="btn btn-success me-2">
                    <i class="fas fa-download me-1"></i>Download
                </a>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteFileModal">
                    <i class="fas fa-trash-alt me-1"></i>Delete
                </button>
            </div>
        </div>
        
        <?php if ($deleteError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($deleteError); ?></div>
        <?php endif; ?>
        
        <!-- File Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>File Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">File Name</div>
                    <div class="col-md-9"><?php echo htmlspecialchars($fileDetails['file_name']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">File Type</div>
                    <div class="col-md-9"><?php echo htmlspecialchars($fileDetails['file_type']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">File Size</div>
                    <div class="col-md-9"><?php echo round($fileDetails['file_size'] / 1024, 2); ?> KB</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Uploaded</div>
                    <div class="col-md-9"><?php echo date('F j, Y, g:i a', strtotime($fileDetails['created_at'])); ?></div>
                </div>
                <div class="row">
                    <div class="col-md-3 fw-bold">Download URL</div>
                    <div class="col-md-9">
                        <div class="input-group">
                            <input type="text" class="form-control" id="download-url" value="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/api/files.php?route=download&id=' . $fileId . '&api_key=YOUR_API_KEY'; ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('download-url')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <small class="text-muted">Replace YOUR_API_KEY with your project's API key</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Delete File Modal -->
        <div class="modal fade" id="deleteFileModal" tabindex="-1" aria-labelledby="deleteFileModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteFileModalLabel">Delete File</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this file? This action cannot be undone.</p>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="delete_file">
                            <input type="hidden" name="id" value="<?php echo $fileId; ?>">
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete File</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (empty($projectDetails['api_key'])): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No API key found for this project. Please create an API key in the <a href="/dashboard/keys.php?project_id=<?php echo $projectId; ?>" class="alert-link">API Keys</a> section to enable file downloads.
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- Files List and Upload Form -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-file me-2"></i>Files</h4>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
                <i class="fas fa-upload me-1"></i>Upload File
            </button>
        </div>
        
        <?php if ($uploadSuccess): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($uploadSuccess); ?></div>
        <?php endif; ?>
        
        <?php if ($uploadError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($uploadError); ?></div>
        <?php endif; ?>
        
        <?php if ($deleteError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($deleteError); ?></div>
        <?php endif; ?>
        
        <?php if (empty($projectDetails['api_key'])): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No API key found for this project. Please create an API key in the <a href="/dashboard/keys.php?project_id=<?php echo $projectId; ?>" class="alert-link">API Keys</a> section to enable file downloads.
        </div>
        <?php endif; ?>
        
        <!-- Files List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Uploaded Files</h5>
            </div>
            <div class="card-body">
                <?php if (empty($files)): ?>
                <div class="text-center py-4">
                    <p class="text-muted">No files uploaded</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
                        <i class="fas fa-upload me-1"></i>Upload File
                    </button>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Uploaded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($file['file_name']); ?></td>
                                <td>
                                    <?php 
                                    $fileExt = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                    $fileIcon = 'file';
                                    
                                    switch ($fileExt) {
                                        case 'pdf': $fileIcon = 'file-pdf'; break;
                                        case 'doc':
                                        case 'docx': $fileIcon = 'file-word'; break;
                                        case 'xls':
                                        case 'xlsx': $fileIcon = 'file-excel'; break;
                                        case 'jpg':
                                        case 'jpeg':
                                        case 'png':
                                        case 'gif': $fileIcon = 'file-image'; break;
                                        case 'zip': $fileIcon = 'file-archive'; break;
                                        case 'txt': $fileIcon = 'file-alt'; break;
                                    }
                                    ?>
                                    <i class="fas fa-<?php echo $fileIcon; ?> me-1"></i> <?php echo strtoupper($fileExt); ?>
                                </td>
                                <td><?php echo round($file['file_size'] / 1024, 2); ?> KB</td>
                                <td><?php echo date('M j, Y', strtotime($file['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="/dashboard/files.php?project_id=<?php echo $projectId; ?>&file_id=<?php echo $file['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="/api/files.php?route=download&id=<?php echo $file['id']; ?>&api_key=<?php echo htmlspecialchars($projectDetails['api_key'] ?? ''); ?>" class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($pagination['pages'] > 1): ?>
                <!-- Pagination -->
                <nav aria-label="File pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($pagination['page'] > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="/dashboard/files.php?project_id=<?php echo $projectId; ?>&page=<?php echo $pagination['page'] - 1; ?>">
                                Previous
                            </a>
                        </li>
                        <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">Previous</span>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $pagination['pages']; $i++): ?>
                        <li class="page-item <?php echo $i == $pagination['page'] ? 'active' : ''; ?>">
                            <a class="page-link" href="/dashboard/files.php?project_id=<?php echo $projectId; ?>&page=<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($pagination['page'] < $pagination['pages']): ?>
                        <li class="page-item">
                            <a class="page-link" href="/dashboard/files.php?project_id=<?php echo $projectId; ?>&page=<?php echo $pagination['page'] + 1; ?>">
                                Next
                            </a>
                        </li>
                        <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">Next</span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Upload File Modal -->
        <div class="modal fade" id="uploadFileModal" tabindex="-1" aria-labelledby="uploadFileModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="uploadFileModalLabel">Upload File</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_file">
                            <div class="mb-3">
                                <label for="file" class="form-label">Select File</label>
                                <input type="file" class="form-control" id="file" name="file" required>
                                <div class="form-text">
                                    Max file size: <?php echo $maxFileSize; ?> MB<br>
                                    Allowed file types: <?php echo implode(', ', $allowedFileTypes); ?>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Upload</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    element.select();
    document.execCommand('copy');
    
    // Show a brief "Copied!" tooltip or notification
    const button = element.nextElementSibling;
    const originalHtml = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i> Copied!';
    setTimeout(() => {
        button.innerHTML = originalHtml;
    }, 2000);
}
</script>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>
