<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../api/utils.php';

// Get project ID
$projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;
if (!$projectId) {
    header('Location: /dashboard/projects.php');
    exit;
}

// Get key ID if set
$keyId = isset($_GET['key_id']) ? intval($_GET['key_id']) : null;

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

// Handle key creation
$createError = '';
$createSuccess = '';
$newApiKey = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_key') {
    $name = $_POST['name'] ?? '';
    $permissions = $_POST['permissions'] ?? 'read';
    
    if (empty($name)) {
        $createError = 'API key name is required';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Generate a new API key
            $apiKey = ApiUtils::generateToken(64);
            
            $stmt = $db->prepare("INSERT INTO api_keys (project_id, api_key, name, permissions) VALUES (?, ?, ?, ?)");
            $success = $stmt->execute([$projectId, $apiKey, $name, $permissions]);
            
            if ($success) {
                $newKeyId = $db->lastInsertId();
                $createSuccess = 'API key created successfully';
                $newApiKey = $apiKey;
            } else {
                $createError = 'Failed to create API key';
            }
        } catch (Exception $e) {
            error_log('Create key error: ' . $e->getMessage());
            $createError = 'An error occurred. Please try again later.';
        }
    }
}

// Handle key update
$updateError = '';
$updateSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_key') {
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $permissions = $_POST['permissions'] ?? 'read';
    
    if (empty($name)) {
        $updateError = 'API key name is required';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Check if key exists and belongs to the project
            $stmt = $db->prepare("SELECT id FROM api_keys WHERE id = ? AND project_id = ?");
            $stmt->execute([$id, $projectId]);
            
            if (!$stmt->fetch()) {
                $updateError = 'API key not found or access denied';
            } else {
                $stmt = $db->prepare("UPDATE api_keys SET name = ?, permissions = ? WHERE id = ?");
                $success = $stmt->execute([$name, $permissions, $id]);
                
                if ($success) {
                    $updateSuccess = 'API key updated successfully';
                } else {
                    $updateError = 'Failed to update API key';
                }
            }
        } catch (Exception $e) {
            error_log('Update key error: ' . $e->getMessage());
            $updateError = 'An error occurred. Please try again later.';
        }
    }
}

// Handle key deletion
$deleteError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_key') {
    $id = $_POST['id'] ?? '';
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if key exists and belongs to the project
        $stmt = $db->prepare("SELECT id FROM api_keys WHERE id = ? AND project_id = ?");
        $stmt->execute([$id, $projectId]);
        
        if (!$stmt->fetch()) {
            $deleteError = 'API key not found or access denied';
        } else {
            $stmt = $db->prepare("DELETE FROM api_keys WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                header('Location: /dashboard/keys.php?project_id=' . $projectId);
                exit;
            } else {
                $deleteError = 'Failed to delete API key';
            }
        }
    } catch (Exception $e) {
        error_log('Delete key error: ' . $e->getMessage());
        $deleteError = 'An error occurred. Please try again later.';
    }
}

// Get project details
$projectDetails = null;
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT name FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $projectDetails = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Get project details error: ' . $e->getMessage());
}

// Get API key details if key ID is provided
$keyDetails = null;
if ($keyId) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM api_keys WHERE id = ? AND project_id = ?");
        $stmt->execute([$keyId, $projectId]);
        $keyDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Get key details error: ' . $e->getMessage());
    }
}

// Get list of API keys for the project
$keys = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM api_keys WHERE project_id = ? ORDER BY created_at DESC");
    $stmt->execute([$projectId]);
    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Get keys error: ' . $e->getMessage());
}

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
                <li class="breadcrumb-item active">API Keys</li>
                <?php if ($keyDetails): ?>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($keyDetails['name']); ?></li>
                <?php endif; ?>
            </ol>
        </nav>
        
        <?php if ($keyDetails): ?>
        <!-- API Key Details -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-key me-2"></i><?php echo htmlspecialchars($keyDetails['name']); ?></h4>
            <div>
                <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#editKeyModal">
                    <i class="fas fa-edit me-1"></i>Edit
                </button>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteKeyModal">
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
        
        <?php if ($deleteError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($deleteError); ?></div>
        <?php endif; ?>
        
        <!-- API Key Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>API Key Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">API Key</div>
                    <div class="col-md-9">
                        <div class="input-group">
                            <input type="text" class="form-control" id="api-key" value="<?php echo htmlspecialchars($keyDetails['api_key']); ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('api-key')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Permissions</div>
                    <div class="col-md-9">
                        <?php if ($keyDetails['permissions'] === 'read,write'): ?>
                        <span class="badge bg-success">Read & Write</span>
                        <?php elseif ($keyDetails['permissions'] === 'read'): ?>
                        <span class="badge bg-info">Read Only</span>
                        <?php else: ?>
                        <span class="badge bg-warning">Write Only</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 fw-bold">Created</div>
                    <div class="col-md-9"><?php echo date('F j, Y, g:i a', strtotime($keyDetails['created_at'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Usage Examples -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-code me-2"></i>API Usage Examples</h5>
            </div>
            <div class="card-body">
                <h6>Authentication Header</h6>
                <pre class="bg-light p-3 border rounded"><code>X-API-Key: <?php echo htmlspecialchars($keyDetails['api_key']); ?></code></pre>
                
                <h6 class="mt-4">Sample API Requests</h6>
                <ul class="nav nav-tabs" id="apiExampleTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="curl-tab" data-bs-toggle="tab" data-bs-target="#curl" type="button" role="tab" aria-controls="curl" aria-selected="true">cURL</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="js-tab" data-bs-toggle="tab" data-bs-target="#js" type="button" role="tab" aria-controls="js" aria-selected="false">JavaScript</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="php-tab" data-bs-toggle="tab" data-bs-target="#php" type="button" role="tab" aria-controls="php" aria-selected="false">PHP</button>
                    </li>
                </ul>
                <div class="tab-content p-3 border border-top-0 rounded-bottom" id="apiExampleTabsContent">
                    <div class="tab-pane fade show active" id="curl" role="tabpanel" aria-labelledby="curl-tab">
                        <pre><code># Get all collections
curl -X GET "https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/database.php?route=collections" \
  -H "X-API-Key: <?php echo htmlspecialchars($keyDetails['api_key']); ?>"

# Create a document
curl -X POST "https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/database.php?route=documents&collection_id=YOUR_COLLECTION_ID" \
  -H "X-API-Key: <?php echo htmlspecialchars($keyDetails['api_key']); ?>" \
  -H "Content-Type: application/json" \
  -d '{"data": {"name": "John Doe", "email": "john@example.com"}}'</code></pre>
                    </div>
                    <div class="tab-pane fade" id="js" role="tabpanel" aria-labelledby="js-tab">
                        <pre><code>// Get all collections
fetch('https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/database.php?route=collections', {
  method: 'GET',
  headers: {
    'X-API-Key': '<?php echo htmlspecialchars($keyDetails['api_key']); ?>'
  }
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));

// Create a document
fetch('https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/database.php?route=documents&collection_id=YOUR_COLLECTION_ID', {
  method: 'POST',
  headers: {
    'X-API-Key': '<?php echo htmlspecialchars($keyDetails['api_key']); ?>',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    data: {
      name: 'John Doe',
      email: 'john@example.com'
    }
  })
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));</code></pre>
                    </div>
                    <div class="tab-pane fade" id="php" role="tabpanel" aria-labelledby="php-tab">
                        <pre><code>// Get all collections
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/database.php?route=collections');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: <?php echo htmlspecialchars($keyDetails['api_key']); ?>'
]);

$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);

// Create a document
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/database.php?route=documents&collection_id=YOUR_COLLECTION_ID');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'data' => [
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: <?php echo htmlspecialchars($keyDetails['api_key']); ?>',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);</code></pre>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Edit API Key Modal -->
        <div class="modal fade" id="editKeyModal" tabindex="-1" aria-labelledby="editKeyModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editKeyModalLabel">Edit API Key</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="update_key">
                            <input type="hidden" name="id" value="<?php echo $keyId; ?>">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">API Key Name</label>
                                <input type="text" class="form-control" id="edit_name" name="name" value="<?php echo htmlspecialchars($keyDetails['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_permissions" class="form-label">Permissions</label>
                                <select class="form-select" id="edit_permissions" name="permissions">
                                    <option value="read" <?php echo $keyDetails['permissions'] === 'read' ? 'selected' : ''; ?>>Read Only</option>
                                    <option value="write" <?php echo $keyDetails['permissions'] === 'write' ? 'selected' : ''; ?>>Write Only</option>
                                    <option value="read,write" <?php echo $keyDetails['permissions'] === 'read,write' ? 'selected' : ''; ?>>Read & Write</option>
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
        
        <!-- Delete API Key Modal -->
        <div class="modal fade" id="deleteKeyModal" tabindex="-1" aria-labelledby="deleteKeyModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteKeyModalLabel">Delete API Key</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this API key? This action cannot be undone and any applications using this key will no longer be able to access the API.</p>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="delete_key">
                            <input type="hidden" name="id" value="<?php echo $keyId; ?>">
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete API Key</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- API Keys List and Creation Form -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-key me-2"></i>API Keys</h4>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createKeyModal">
                <i class="fas fa-plus me-1"></i>Create API Key
            </button>
        </div>
        
        <?php if ($createSuccess): ?>
        <div class="alert alert-success">
            <p><?php echo htmlspecialchars($createSuccess); ?></p>
            <?php if ($newApiKey): ?>
            <div class="mt-3">
                <strong>Your new API key:</strong>
                <div class="input-group mt-2">
                    <input type="text" class="form-control" id="new-api-key" value="<?php echo htmlspecialchars($newApiKey); ?>" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('new-api-key')">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <small class="text-danger">Make sure to copy this key now. You won't be able to see it again!</small>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($createError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($createError); ?></div>
        <?php endif; ?>
        
        <?php if ($deleteError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($deleteError); ?></div>
        <?php endif; ?>
        
        <!-- API Keys List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>API Keys</h5>
            </div>
            <div class="card-body">
                <?php if (empty($keys)): ?>
                <div class="text-center py-4">
                    <p class="text-muted">No API keys found</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createKeyModal">
                        <i class="fas fa-plus me-1"></i>Create API Key
                    </button>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Key (masked)</th>
                                <th>Permissions</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($keys as $key): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($key['name']); ?></td>
                                <td>
                                    <?php 
                                    $apiKey = $key['api_key'];
                                    $maskedKey = substr($apiKey, 0, 8) . '...' . substr($apiKey, -8);
                                    echo htmlspecialchars($maskedKey); 
                                    ?>
                                </td>
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
        
        <!-- Create API Key Modal -->
        <div class="modal fade" id="createKeyModal" tabindex="-1" aria-labelledby="createKeyModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createKeyModalLabel">Create API Key</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="create_key">
                            <div class="mb-3">
                                <label for="name" class="form-label">API Key Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                                <div class="form-text">Give your API key a descriptive name (e.g., "Web App", "Mobile App")</div>
                            </div>
                            <div class="mb-3">
                                <label for="permissions" class="form-label">Permissions</label>
                                <select class="form-select" id="permissions" name="permissions">
                                    <option value="read">Read Only</option>
                                    <option value="write">Write Only</option>
                                    <option value="read,write" selected>Read & Write</option>
                                </select>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Create API Key</button>
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
