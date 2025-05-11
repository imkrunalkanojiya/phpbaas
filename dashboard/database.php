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

// Get action and IDs
$action = isset($_GET['action']) ? $_GET['action'] : '';
$collectionId = isset($_GET['collection_id']) ? intval($_GET['collection_id']) : null;
$documentId = isset($_GET['document_id']) ? $_GET['document_id'] : null;

// No need for the join functionality anymore as it's automatic
if ($action === 'join') {
    // Redirect to collections page since joins are now automatic
    header('Location: /dashboard/database.php?project_id=' . $projectId);
    exit;
}

// Handle collection creation
$createCollectionError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_collection') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($name)) {
        $createCollectionError = 'Collection name is required';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Check if collection name already exists for this project
            $stmt = $db->prepare("SELECT id FROM collections WHERE project_id = ? AND name = ?");
            $stmt->execute([$projectId, $name]);
            
            if ($stmt->fetch()) {
                $createCollectionError = 'Collection with this name already exists';
            } else {
                $stmt = $db->prepare("INSERT INTO collections (project_id, name, description) VALUES (?, ?, ?)");
                $success = $stmt->execute([$projectId, $name, $description]);
                
                if ($success) {
                    $newCollectionId = $db->lastInsertId();
                    header('Location: /dashboard/database.php?project_id=' . $projectId . '&collection_id=' . $newCollectionId);
                    exit;
                } else {
                    $createCollectionError = 'Failed to create collection';
                }
            }
        } catch (Exception $e) {
            error_log('Create collection error: ' . $e->getMessage());
            $createCollectionError = 'An error occurred. Please try again later.';
        }
    }
}

// Handle collection update
$updateCollectionError = '';
$updateCollectionSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_collection') {
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($name)) {
        $updateCollectionError = 'Collection name is required';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Check if collection belongs to the current project
            $stmt = $db->prepare("SELECT id FROM collections WHERE id = ? AND project_id = ?");
            $stmt->execute([$id, $projectId]);
            
            if (!$stmt->fetch()) {
                $updateCollectionError = 'Collection not found or access denied';
            } else {
                // Check if new name conflicts with existing collection
                $stmt = $db->prepare("SELECT id FROM collections WHERE project_id = ? AND name = ? AND id != ?");
                $stmt->execute([$projectId, $name, $id]);
                
                if ($stmt->fetch()) {
                    $updateCollectionError = 'Another collection with this name already exists';
                } else {
                    $stmt = $db->prepare("UPDATE collections SET name = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $success = $stmt->execute([$name, $description, $id]);
                    
                    if ($success) {
                        $updateCollectionSuccess = 'Collection updated successfully';
                    } else {
                        $updateCollectionError = 'Failed to update collection';
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Update collection error: ' . $e->getMessage());
            $updateCollectionError = 'An error occurred. Please try again later.';
        }
    }
}

// Handle collection deletion
$deleteCollectionError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_collection') {
    $id = $_POST['id'] ?? '';
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if collection belongs to the current project
        $stmt = $db->prepare("SELECT id FROM collections WHERE id = ? AND project_id = ?");
        $stmt->execute([$id, $projectId]);
        
        if (!$stmt->fetch()) {
            $deleteCollectionError = 'Collection not found or access denied';
        } else {
            $stmt = $db->prepare("DELETE FROM collections WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                header('Location: /dashboard/database.php?project_id=' . $projectId);
                exit;
            } else {
                $deleteCollectionError = 'Failed to delete collection';
            }
        }
    } catch (Exception $e) {
        error_log('Delete collection error: ' . $e->getMessage());
        $deleteCollectionError = 'An error occurred. Please try again later.';
    }
}

// Handle document creation
$createDocumentError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_document') {
    $collId = $_POST['collection_id'] ?? '';
    $docId = $_POST['document_id'] ?? '';
    $jsonData = $_POST['json_data'] ?? '';
    
    // Generate document ID if empty
    if (empty($docId)) {
        $docId = bin2hex(random_bytes(8));
    }
    
    // Validate JSON data
    if (empty($jsonData)) {
        $createDocumentError = 'Document data is required';
    } else {
        $decodedData = json_decode($jsonData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $createDocumentError = 'Invalid JSON format: ' . json_last_error_msg();
        } else {
            try {
                $db = Database::getInstance()->getConnection();
                
                // Check if collection belongs to the current project
                $stmt = $db->prepare("SELECT id FROM collections WHERE id = ? AND project_id = ?");
                $stmt->execute([$collId, $projectId]);
                
                if (!$stmt->fetch()) {
                    $createDocumentError = 'Collection not found or access denied';
                } else {
                    // Check if document ID already exists in this collection
                    $stmt = $db->prepare("SELECT id FROM documents WHERE collection_id = ? AND document_id = ?");
                    $stmt->execute([$collId, $docId]);
                    
                    if ($stmt->fetch()) {
                        $createDocumentError = 'Document with this ID already exists';
                    } else {
                        $stmt = $db->prepare("INSERT INTO documents (collection_id, document_id, data) VALUES (?, ?, ?)");
                        $success = $stmt->execute([$collId, $docId, $jsonData]);
                        
                        if ($success) {
                            header('Location: /dashboard/database.php?project_id=' . $projectId . '&collection_id=' . $collId);
                            exit;
                        } else {
                            $createDocumentError = 'Failed to create document';
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Create document error: ' . $e->getMessage());
                $createDocumentError = 'An error occurred. Please try again later.';
            }
        }
    }
}

// Handle document update
$updateDocumentError = '';
$updateDocumentSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_document') {
    $collId = $_POST['collection_id'] ?? '';
    $docId = $_POST['document_id'] ?? '';
    $jsonData = $_POST['json_data'] ?? '';
    
    // Validate JSON data
    if (empty($jsonData)) {
        $updateDocumentError = 'Document data is required';
    } else {
        $decodedData = json_decode($jsonData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $updateDocumentError = 'Invalid JSON format: ' . json_last_error_msg();
        } else {
            try {
                $db = Database::getInstance()->getConnection();
                
                // Check if document exists and belongs to a collection in the current project
                $stmt = $db->prepare("SELECT d.id FROM documents d 
                                    JOIN collections c ON d.collection_id = c.id 
                                    WHERE d.collection_id = ? AND d.document_id = ? AND c.project_id = ?");
                $stmt->execute([$collId, $docId, $projectId]);
                
                if (!$stmt->fetch()) {
                    $updateDocumentError = 'Document not found or access denied';
                } else {
                    $stmt = $db->prepare("UPDATE documents SET data = ?, updated_at = CURRENT_TIMESTAMP WHERE collection_id = ? AND document_id = ?");
                    $success = $stmt->execute([$jsonData, $collId, $docId]);
                    
                    if ($success) {
                        $updateDocumentSuccess = 'Document updated successfully';
                    } else {
                        $updateDocumentError = 'Failed to update document';
                    }
                }
            } catch (Exception $e) {
                error_log('Update document error: ' . $e->getMessage());
                $updateDocumentError = 'An error occurred. Please try again later.';
            }
        }
    }
}

// No manual join implementation required here anymore

// Handle document deletion
$deleteDocumentError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_document') {
    $collId = $_POST['collection_id'] ?? '';
    $docId = $_POST['document_id'] ?? '';
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if document exists and belongs to a collection in the current project
        $stmt = $db->prepare("SELECT d.id FROM documents d 
                            JOIN collections c ON d.collection_id = c.id 
                            WHERE d.collection_id = ? AND d.document_id = ? AND c.project_id = ?");
        $stmt->execute([$collId, $docId, $projectId]);
        
        if (!$stmt->fetch()) {
            $deleteDocumentError = 'Document not found or access denied';
        } else {
            $stmt = $db->prepare("DELETE FROM documents WHERE collection_id = ? AND document_id = ?");
            $success = $stmt->execute([$collId, $docId]);
            
            if ($success) {
                header('Location: /dashboard/database.php?project_id=' . $projectId . '&collection_id=' . $collId);
                exit;
            } else {
                $deleteDocumentError = 'Failed to delete document';
            }
        }
    } catch (Exception $e) {
        error_log('Delete document error: ' . $e->getMessage());
        $deleteDocumentError = 'An error occurred. Please try again later.';
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

// Get collections for the project
$collections = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM collections WHERE project_id = ? ORDER BY name ASC");
    $stmt->execute([$projectId]);
    $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get document count for each collection
    foreach ($collections as $i => $collection) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE collection_id = ?");
        $stmt->execute([$collection['id']]);
        $collections[$i]['document_count'] = (int) $stmt->fetchColumn();
    }
} catch (Exception $e) {
    error_log('Get collections error: ' . $e->getMessage());
}


// Get collection details if selected
$collectionDetails = null;
if ($collectionId) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM collections WHERE id = ? AND project_id = ?");
        $stmt->execute([$collectionId, $projectId]);
        $collectionDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Get collection details error: ' . $e->getMessage());
    }
}

// Get documents for the selected collection
$documents = [];
$pagination = [
    'total' => 0,
    'page'  => 1,
    'limit' => 10,
    'pages' => 1
];

if ($collectionId && $collectionDetails) {
    try {
        $db = Database::getInstance()->getConnection();

        // Pagination
        $page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit  = 10;
        $offset = ($page - 1) * $limit;

        // Get total count
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE collection_id = ?");
        $stmt->execute([$collectionId]);
        $total = (int) $stmt->fetchColumn();

        // Get documents
        $stmt = $db->prepare(
            "SELECT * FROM documents 
             WHERE collection_id = ? 
             ORDER BY updated_at DESC 
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$collectionId, $limit, $offset]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse JSON data for each document without a reference loop
        foreach ($documents as $i => $doc) {
            $documents[$i]['data'] = json_decode($doc['data'], true);
        }

        // Set pagination info
        $pagination = [
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
            'pages' => (int) ceil($total / $limit)
        ];
    } catch (Exception $e) {
        error_log('Get documents error: ' . $e->getMessage());
    }
}


// Get document details if selected
$documentDetails = null;
if ($collectionId && $documentId) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM documents WHERE collection_id = ? AND document_id = ?");
        $stmt->execute([$collectionId, $documentId]);
        $documentDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($documentDetails) {
            $documentDetails['data'] = json_decode($documentDetails['data'], true);
            $documentDetails['json'] = json_encode($documentDetails['data'], JSON_PRETTY_PRINT);
        }
    } catch (Exception $e) {
        error_log('Get document details error: ' . $e->getMessage());
    }
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
                <li class="breadcrumb-item active">Database</li>
                <?php if ($collectionDetails): ?>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($collectionDetails['name']); ?></li>
                <?php endif; ?>
                <?php if ($documentDetails): ?>
                <li class="breadcrumb-item active">Document: <?php echo htmlspecialchars($documentId); ?></li>
                <?php endif; ?>
            </ol>
        </nav>
        
        <?php if ($action === 'new'): ?>
        <!-- Create New Collection Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Create New Collection</h5>
            </div>
            <div class="card-body">
                <?php if ($createCollectionError): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($createCollectionError); ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <input type="hidden" name="action" value="create_collection">
                    <div class="mb-3">
                        <label for="name" class="form-label">Collection Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <div class="form-text">Collection names must be unique within a project</div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="/dashboard/database.php?project_id=<?php echo $projectId; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Collection</button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($action === 'new_document' && $collectionId): ?>
        <!-- Create New Document Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Create New Document</h5>
            </div>
            <div class="card-body">
                <?php if ($createDocumentError): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($createDocumentError); ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <input type="hidden" name="action" value="create_document">
                    <input type="hidden" name="collection_id" value="<?php echo $collectionId; ?>">
                    <div class="mb-3">
                        <label for="document_id" class="form-label">Document ID (optional)</label>
                        <input type="text" class="form-control" id="document_id" name="document_id">
                        <div class="form-text">Leave blank to generate a random ID</div>
                    </div>
                    <div class="mb-3">
                        <label for="json_data" class="form-label">Document Data (JSON)</label>
                        <textarea class="form-control" id="json_data" name="json_data" rows="10" required>{
    "key": "value"
}</textarea>
                        <div class="form-text">Enter valid JSON data for your document</div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="/dashboard/database.php?project_id=<?php echo $projectId; ?>&collection_id=<?php echo $collectionId; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Document</button>
                    </div>
                </form>
            </div>
        </div>
        

                
        <?php elseif ($documentDetails): ?>
        <!-- Document Details -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-file-alt me-2"></i>Document: <?php echo htmlspecialchars($documentId); ?></h4>
            <div>
                <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#editDocumentModal">
                    <i class="fas fa-edit me-1"></i>Edit
                </button>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteDocumentModal">
                    <i class="fas fa-trash-alt me-1"></i>Delete
                </button>
            </div>
        </div>
        
        <?php if ($updateDocumentSuccess): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($updateDocumentSuccess); ?></div>
        <?php endif; ?>
        
        <?php if ($updateDocumentError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($updateDocumentError); ?></div>
        <?php endif; ?>
        
        <!-- Document Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Document Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Document ID</div>
                    <div class="col-md-9"><?php echo htmlspecialchars($documentId); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Collection</div>
                    <div class="col-md-9"><?php echo htmlspecialchars($collectionDetails['name']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Created</div>
                    <div class="col-md-9"><?php echo date('F j, Y, g:i a', strtotime($documentDetails['created_at'])); ?></div>
                </div>
                <div class="row">
                    <div class="col-md-3 fw-bold">Last Updated</div>
                    <div class="col-md-9"><?php echo date('F j, Y, g:i a', strtotime($documentDetails['updated_at'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Document Data -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-code me-2"></i>Document Data</h5>
            </div>
            <div class="card-body">
                <pre class="bg-light p-3 border rounded"><code><?php echo htmlspecialchars($documentDetails['json']); ?></code></pre>
            </div>
        </div>
        
        <!-- Edit Document Modal -->
        <div class="modal fade" id="editDocumentModal" tabindex="-1" aria-labelledby="editDocumentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editDocumentModalLabel">Edit Document</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="update_document">
                            <input type="hidden" name="collection_id" value="<?php echo $collectionId; ?>">
                            <input type="hidden" name="document_id" value="<?php echo $documentId; ?>">
                            <div class="mb-3">
                                <label for="edit_json_data" class="form-label">Document Data (JSON)</label>
                                <textarea class="form-control" id="edit_json_data" name="json_data" rows="15" required><?php echo htmlspecialchars($documentDetails['json']); ?></textarea>
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
        
        <!-- Delete Document Modal -->
        <div class="modal fade" id="deleteDocumentModal" tabindex="-1" aria-labelledby="deleteDocumentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteDocumentModalLabel">Delete Document</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this document? This action cannot be undone.</p>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="delete_document">
                            <input type="hidden" name="collection_id" value="<?php echo $collectionId; ?>">
                            <input type="hidden" name="document_id" value="<?php echo $documentId; ?>">
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete Document</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php elseif ($collectionDetails): ?>
        <!-- Collection Details and Documents List -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-database me-2"></i><?php echo htmlspecialchars($collectionDetails['name']); ?></h4>
            <div>
                <a href="/dashboard/database.php?project_id=<?php echo $projectId; ?>&collection_id=<?php echo $collectionId; ?>&action=new_document" class="btn btn-primary me-2">
                    <i class="fas fa-plus me-1"></i>Add Document
                </a>
                <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#editCollectionModal">
                    <i class="fas fa-edit me-1"></i>Edit
                </button>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteCollectionModal">
                    <i class="fas fa-trash-alt me-1"></i>Delete
                </button>
            </div>
        </div>
        
        <?php if ($updateCollectionSuccess): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($updateCollectionSuccess); ?></div>
        <?php endif; ?>
        
        <?php if ($updateCollectionError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($updateCollectionError); ?></div>
        <?php endif; ?>
        
        <?php if ($deleteCollectionError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($deleteCollectionError); ?></div>
        <?php endif; ?>
        
        <!-- Collection Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Collection Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Description</div>
                    <div class="col-md-9"><?php echo htmlspecialchars($collectionDetails['description'] ?: 'No description'); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Document Count</div>
                    <div class="col-md-9"><?php echo $pagination['total']; ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Created</div>
                    <div class="col-md-9"><?php echo date('F j, Y, g:i a', strtotime($collectionDetails['created_at'])); ?></div>
                </div>
                <div class="row">
                    <div class="col-md-3 fw-bold">Last Updated</div>
                    <div class="col-md-9"><?php echo date('F j, Y, g:i a', strtotime($collectionDetails['updated_at'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Documents List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Documents</h5>
                <a href="/dashboard/database.php?project_id=<?php echo $projectId; ?>&collection_id=<?php echo $collectionId; ?>&action=new_document" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus me-1"></i>Add Document
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($documents)): ?>
                <div class="text-center py-4">
                    <p class="text-muted">No documents in this collection</p>
                    <a href="/dashboard/database.php?project_id=<?php echo $projectId; ?>&collection_id=<?php echo $collectionId; ?>&action=new_document" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Add Document
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Document ID</th>
                                <th>Preview</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $document): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($document['document_id']); ?></td>
                                <td>
                                    <code class="small">
                                        <?php 
                                        $preview = json_encode($document['data']);
                                        echo htmlspecialchars(
                                            strlen($preview) > 50 ? substr($preview, 0, 50) . '...' : $preview
                                        ); 
                                        ?>
                                    </code>
                                </td>
                                <td><?php echo date('M j, Y, g:i a', strtotime($document['updated_at'])); ?></td>
                                <td>
                                    <a href="/dashboard/database.php?project_id=<?php echo $projectId; ?>&collection_id=<?php echo $collectionId; ?>&document_id=<?php echo urlencode($document['document_id']); ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($pagination['pages'] > 1): ?>
                <!-- Pagination -->
                <nav aria-label="Document pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($pagination['page'] > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="/dashboard/database.php?project_id=<?php echo $projectId; ?>&collection_id=<?php echo $collectionId; ?>&page=<?php echo $pagination['page'] - 1; ?>">
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
                            <a class="page-link" href="/dashboard/database.php?project_id=<?php echo $projectId; ?>&collection_id=<?php echo $collectionId; ?>&page=<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($pagination['page'] < $pagination['pages']): ?>
                        <li class="page-item">
                            <a class="page-link" href="/dashboard/database.php?project_id=<?php echo $projectId; ?>&collection_id=<?php echo $collectionId; ?>&page=<?php echo $pagination['page'] + 1; ?>">
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
        
        <!-- Edit Collection Modal -->
        <div class="modal fade" id="editCollectionModal" tabindex="-1" aria-labelledby="editCollectionModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editCollectionModalLabel">Edit Collection</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="update_collection">
                            <input type="hidden" name="id" value="<?php echo $collectionId; ?>">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Collection Name</label>
                                <input type="text" class="form-control" id="edit_name" name="name" value="<?php echo htmlspecialchars($collectionDetails['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"><?php echo htmlspecialchars($collectionDetails['description']); ?></textarea>
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
        
        <!-- Delete Collection Modal -->
        <div class="modal fade" id="deleteCollectionModal" tabindex="-1" aria-labelledby="deleteCollectionModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteCollectionModalLabel">Delete Collection</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this collection? This action cannot be undone and will delete all documents in this collection.</p>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="delete_collection">
                            <input type="hidden" name="id" value="<?php echo $collectionId; ?>">
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete Collection</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Collections List -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-database me-2"></i>Database Collections</h4>
            <div>
                <a href="/dashboard/database.php?project_id=<?php echo $projectId; ?>&action=new" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>New Collection
                </a>
            </div>
        </div>
        
        <?php if (empty($collections)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-database fa-4x text-muted mb-3"></i>
                <h4>No Collections Found</h4>
                <p class="text-muted">Start by creating a new collection</p>
                <a href="/dashboard/database.php?project_id=<?php echo $projectId; ?>&action=new" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Create Collection
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="row">
            <?php foreach ($collections as $collection): ?>
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <?php echo $collection['name'] ?>
                        <h5 class="card-title"><?php echo htmlspecialchars($collection['name']); ?></h5>
                        <p class="card-text text-muted">
                            <?php echo htmlspecialchars($collection['description'] ?: 'No description'); ?>
                        </p>
                        <div class="d-flex">
                            <div class="me-3">
                                <small class="d-block text-muted">Documents</small>
                                <span class="fw-bold"><?php echo $collection['document_count']; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <small class="text-muted">Created: <?php echo date('M j, Y', strtotime($collection['created_at'])); ?></small>
                        <a href="/dashboard/database.php?project_id=<?php echo $projectId; ?>&collection_id=<?php echo $collection['id']; ?>" class="btn btn-sm btn-primary">
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
