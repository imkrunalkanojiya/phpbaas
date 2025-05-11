<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/cache.php';

$cache = new Cache();
$cacheKey = 'database_query_results';

/**
 * Database API Endpoints
 */

// Check for API key
$apiKey = ApiUtils::getApiKey();
if (!$apiKey) {
    ApiUtils::sendError('API key required', 401);
}

// Verify API key and get project details
$projectDetails = ApiUtils::verifyApiKey($apiKey);
if (!$projectDetails) {
    ApiUtils::sendError('Invalid API key', 401);
}

// Route handler
$route = isset($_GET['route']) ? $_GET['route'] : '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($route) {
    case 'collections':
        handleCollections($projectDetails, $method);
        break;
    
    case 'documents':
        handleDocuments($projectDetails, $method);
        break;
        
    case 'joins':
        // This endpoint is kept for backward compatibility, but joins are now handled automatically
        ApiUtils::sendResponse([
            'success' => true,
            'message' => 'Joins are now handled automatically when retrieving documents. Any document containing references to other collections will be automatically populated with the referenced data.'
        ]);
        break;
    
    default:
        ApiUtils::sendError('Endpoint not found', 404);
}

/**
 * Handle collection endpoints
 */
function handleCollections($projectDetails, $method) {
    $collectionId = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    switch ($method) {
        case 'GET':
            if ($collectionId) {
                getCollection($projectDetails, $collectionId);
            } else {
                getCollections($projectDetails);
            }
            break;
        
        case 'POST':
            createCollection($projectDetails);
            break;
        
        case 'PUT':
            if (!$collectionId) {
                ApiUtils::sendError('Collection ID is required', 400);
            }
            updateCollection($projectDetails, $collectionId);
            break;
        
        case 'DELETE':
            if (!$collectionId) {
                ApiUtils::sendError('Collection ID is required', 400);
            }
            deleteCollection($projectDetails, $collectionId);
            break;
        
        default:
            ApiUtils::sendError('Method not allowed', 405);
    }
}

/**
 * Handle document endpoints
 */
function handleDocuments($projectDetails, $method) {
    $collectionId = isset($_GET['collection_id']) ? intval($_GET['collection_id']) : null;
    $documentId = isset($_GET['document_id']) ? $_GET['document_id'] : null;
    
    if (!$collectionId) {
        ApiUtils::sendError('Collection ID is required', 400);
    }
    
    // Verify collection belongs to project
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM collections WHERE id = ? AND project_id = ?");
        $stmt->execute([$collectionId, $projectDetails['project_id']]);
        
        if (!$stmt->fetch()) {
            ApiUtils::sendError('Collection not found or access denied', 404);
        }
    } catch (Exception $e) {
        error_log('Verify collection error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to verify collection', 500);
    }
    
    switch ($method) {
        case 'GET':
            if ($documentId) {
                getDocument($collectionId, $documentId);
            } else {
                getDocuments($collectionId);
            }
            break;
        
        case 'POST':
            createDocument($collectionId);
            break;
        
        case 'PUT':
            if (!$documentId) {
                ApiUtils::sendError('Document ID is required', 400);
            }
            updateDocument($collectionId, $documentId);
            break;
        
        case 'DELETE':
            if (!$documentId) {
                ApiUtils::sendError('Document ID is required', 400);
            }
            deleteDocument($collectionId, $documentId);
            break;
        
        default:
            ApiUtils::sendError('Method not allowed', 405);
    }
}

/**
 * Get all collections for a project
 */
function getCollections($projectDetails) {
    global $cache, $cacheKey;

    // Check if cached data exists
    $cachedData = $cache->get($cacheKey);
    if ($cachedData) {
        ApiUtils::sendResponse([
            'success' => true,
            'collections' => $cachedData
        ]);
        return;
    }

    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT * FROM collections WHERE project_id = ? ORDER BY name ASC");
        $stmt->execute([$projectDetails['project_id']]);
        $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cache the results
        $cache->set($cacheKey, $collections);

        ApiUtils::sendResponse([
            'success' => true,
            'collections' => $collections
        ]);
    } catch (Exception $e) {
        error_log('Get collections error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to retrieve collections', 500);
    }
}

/**
 * Get a specific collection
 */
function getCollection($projectDetails, $collectionId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT * FROM collections WHERE id = ? AND project_id = ?");
        $stmt->execute([$collectionId, $projectDetails['project_id']]);
        $collection = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$collection) {
            ApiUtils::sendError('Collection not found', 404);
        }
        
        // Get document count for this collection
        $stmt = $db->prepare("SELECT COUNT(*) as document_count FROM documents WHERE collection_id = ?");
        $stmt->execute([$collectionId]);
        $documentCount = $stmt->fetch(PDO::FETCH_ASSOC)['document_count'];
        
        $collection['document_count'] = $documentCount;
        
        ApiUtils::sendResponse([
            'success' => true,
            'collection' => $collection
        ]);
    } catch (Exception $e) {
        error_log('Get collection error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to retrieve collection', 500);
    }
}

/**
 * Create a new collection
 */
function createCollection($projectDetails) {
    // Check write permission
    if (strpos($projectDetails['permissions'], 'write') === false) {
        ApiUtils::sendError('API key does not have write permission', 403);
    }
    
    $data = ApiUtils::getJsonBody();
    
    // Validate input
    if (empty($data['name'])) {
        ApiUtils::sendError('Collection name is required', 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if collection name already exists in this project
        $stmt = $db->prepare("SELECT id FROM collections WHERE project_id = ? AND name = ?");
        $stmt->execute([$projectDetails['project_id'], $data['name']]);
        
        if ($stmt->fetch()) {
            ApiUtils::sendError('Collection name already exists in this project', 409);
        }
        
        $description = isset($data['description']) ? $data['description'] : '';
        
        $stmt = $db->prepare("INSERT INTO collections (project_id, name, description) VALUES (?, ?, ?)");
        $success = $stmt->execute([$projectDetails['project_id'], $data['name'], $description]);
        
        if ($success) {
            $collectionId = $db->lastInsertId();
            
            ApiUtils::sendResponse([
                'success' => true,
                'message' => 'Collection created successfully',
                'collection' => [
                    'id' => $collectionId,
                    'project_id' => $projectDetails['project_id'],
                    'name' => $data['name'],
                    'description' => $description,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ], 201);
        } else {
            ApiUtils::sendError('Failed to create collection', 500);
        }
    } catch (Exception $e) {
        error_log('Create collection error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to create collection', 500);
    }
}

/**
 * Update a collection
 */
function updateCollection($projectDetails, $collectionId) {
    // Check write permission
    if (strpos($projectDetails['permissions'], 'write') === false) {
        ApiUtils::sendError('API key does not have write permission', 403);
    }
    
    $data = ApiUtils::getJsonBody();
    
    // Validate input
    if (empty($data['name'])) {
        ApiUtils::sendError('Collection name is required', 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if collection exists and belongs to project
        $stmt = $db->prepare("SELECT id FROM collections WHERE id = ? AND project_id = ?");
        $stmt->execute([$collectionId, $projectDetails['project_id']]);
        
        if (!$stmt->fetch()) {
            ApiUtils::sendError('Collection not found or access denied', 404);
        }
        
        // Check if new name already exists (if name is being changed)
        $stmt = $db->prepare("SELECT id FROM collections WHERE project_id = ? AND name = ? AND id != ?");
        $stmt->execute([$projectDetails['project_id'], $data['name'], $collectionId]);
        
        if ($stmt->fetch()) {
            ApiUtils::sendError('Collection name already exists in this project', 409);
        }
        
        $description = isset($data['description']) ? $data['description'] : '';
        
        $stmt = $db->prepare("UPDATE collections SET name = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $success = $stmt->execute([$data['name'], $description, $collectionId]);
        
        if ($success) {
            ApiUtils::sendResponse([
                'success' => true,
                'message' => 'Collection updated successfully',
                'collection' => [
                    'id' => $collectionId,
                    'name' => $data['name'],
                    'description' => $description
                ]
            ]);
        } else {
            ApiUtils::sendError('Failed to update collection', 500);
        }
    } catch (Exception $e) {
        error_log('Update collection error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to update collection', 500);
    }
}

/**
 * Delete a collection
 */
function deleteCollection($projectDetails, $collectionId) {
    // Check write permission
    if (strpos($projectDetails['permissions'], 'write') === false) {
        ApiUtils::sendError('API key does not have write permission', 403);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if collection exists and belongs to project
        $stmt = $db->prepare("SELECT id FROM collections WHERE id = ? AND project_id = ?");
        $stmt->execute([$collectionId, $projectDetails['project_id']]);
        
        if (!$stmt->fetch()) {
            ApiUtils::sendError('Collection not found or access denied', 404);
        }
        
        // Delete the collection (cascades to delete documents)
        $stmt = $db->prepare("DELETE FROM collections WHERE id = ?");
        $success = $stmt->execute([$collectionId]);
        
        if ($success) {
            ApiUtils::sendResponse([
                'success' => true,
                'message' => 'Collection deleted successfully'
            ]);
        } else {
            ApiUtils::sendError('Failed to delete collection', 500);
        }
    } catch (Exception $e) {
        error_log('Delete collection error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to delete collection', 500);
    }
}

/**
 * Get all documents in a collection
 */
function getDocuments($collectionId) {
    try {
        $db = Database::getInstance()->getConnection();

        // Handle pagination params
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 20;
        $offset = ($page - 1) * $limit;

        // Count total documents
        $stmtCount = $db->prepare("SELECT COUNT(*) as total FROM documents WHERE collection_id = ?");
        $stmtCount->execute([$collectionId]);
        $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

        // Fetch paginated documents
        $stmt = $db->prepare("SELECT id, collection_id, document_id, data, created_at, updated_at FROM documents WHERE collection_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $collectionId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse JSON data for each document and populate references
        foreach ($documents as $i => $doc) {
            $data = isset($doc['data']) && is_string($doc['data'])
                ? json_decode($doc['data'], true)
                : $doc['data'];

            $data = processDocumentReferences($data, 0);
            $documents[$i]['data'] = $data;
        }

        ApiUtils::sendResponse([
            'success'    => true,
            'documents'  => $documents,
            'pagination' => [
                'total' => (int)$total,
                'page'  => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ],
        ]);
    } catch (Exception $e) {
        error_log('Get documents error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to retrieve documents', 500);
    }
}



/**
 * Get a specific document
 */
function getDocument($collectionId, $documentId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT id, collection_id, document_id, data, created_at, updated_at FROM documents WHERE collection_id = ? AND document_id = ?");
        $stmt->execute([$collectionId, $documentId]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$document) {
            ApiUtils::sendError('Document not found', 404);
        }
        
        // Parse JSON data
        if (isset($document['data']) && is_string($document['data'])) {
            $document['data'] = json_decode($document['data'], true);
            
            // Process document for automatic references (MongoDB-style aggregation)
            $document['data'] = processDocumentReferences($document['data'], 0);
        }
        
        ApiUtils::sendResponse([
            'success' => true,
            'document' => $document
        ]);
    } catch (Exception $e) {
        error_log('Get document error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to retrieve document', 500);
    }
}

/**
 * Create a new document
 */
function createDocument($collectionId) {
    $data = ApiUtils::getJsonBody();
    
    // Validate input
    if (!isset($data['data']) || !is_array($data['data'])) {
        ApiUtils::sendError('Document data is required and must be an object', 400);
    }
    
    // Always auto-generate a document ID if not provided
    $documentId = isset($data['document_id']) ? $data['document_id'] : bin2hex(random_bytes(12));
    
    // Add id to the data if not present
    if (!isset($data['data']['id'])) {
        $data['data']['id'] = $documentId;
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if document ID already exists in this collection
        $stmt = $db->prepare("SELECT id FROM documents WHERE collection_id = ? AND document_id = ?");
        $stmt->execute([$collectionId, $documentId]);
        
        if ($stmt->fetch()) {
            ApiUtils::sendError('Document ID already exists in this collection', 409);
        }
        
        // Insert document
        $stmt = $db->prepare("INSERT INTO documents (collection_id, document_id, data) VALUES (?, ?, ?)");
        $success = $stmt->execute([$collectionId, $documentId, json_encode($data['data'])]);
        
        if ($success) {
            $id = $db->lastInsertId();
            
            ApiUtils::sendResponse([
                'success' => true,
                'message' => 'Document created successfully',
                'document' => [
                    'id' => $id,
                    'collection_id' => $collectionId,
                    'document_id' => $documentId,
                    'data' => $data['data'],
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ], 201);
        } else {
            ApiUtils::sendError('Failed to create document', 500);
        }
    } catch (Exception $e) {
        error_log('Create document error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to create document', 500);
    }
}

/**
 * Update a document
 */
function updateDocument($collectionId, $documentId) {
    $data = ApiUtils::getJsonBody();
    
    // Validate input
    if (!isset($data['data']) || !is_array($data['data'])) {
        ApiUtils::sendError('Document data is required and must be an object', 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if document exists
        $stmt = $db->prepare("SELECT id FROM documents WHERE collection_id = ? AND document_id = ?");
        $stmt->execute([$collectionId, $documentId]);
        
        if (!$stmt->fetch()) {
            ApiUtils::sendError('Document not found', 404);
        }
        
        // Update document
        $stmt = $db->prepare("UPDATE documents SET data = ?, updated_at = CURRENT_TIMESTAMP WHERE collection_id = ? AND document_id = ?");
        $success = $stmt->execute([json_encode($data['data']), $collectionId, $documentId]);
        
        if ($success) {
            ApiUtils::sendResponse([
                'success' => true,
                'message' => 'Document updated successfully',
                'document' => [
                    'collection_id' => $collectionId,
                    'document_id' => $documentId,
                    'data' => $data['data'],
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            ApiUtils::sendError('Failed to update document', 500);
        }
    } catch (Exception $e) {
        error_log('Update document error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to update document', 500);
    }
}

/**
 * Delete a document
 */
function deleteDocument($collectionId, $documentId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if document exists
        $stmt = $db->prepare("SELECT id FROM documents WHERE collection_id = ? AND document_id = ?");
        $stmt->execute([$collectionId, $documentId]);
        
        if (!$stmt->fetch()) {
            ApiUtils::sendError('Document not found', 404);
        }
        
        // Delete document
        $stmt = $db->prepare("DELETE FROM documents WHERE collection_id = ? AND document_id = ?");
        $success = $stmt->execute([$collectionId, $documentId]);
        
        if ($success) {
            ApiUtils::sendResponse([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);
        } else {
            ApiUtils::sendError('Failed to delete document', 500);
        }
    } catch (Exception $e) {
        error_log('Delete document error: ' . $e->getMessage());
        ApiUtils::sendError('Failed to delete document', 500);
    }
}

/**
 * Process document references (MongoDB-style automatic aggregation)
 * This function processes document data to resolve references to other documents
 * 
 * @param array $documentData The document data to process
 * @param int $depth Current recursion depth to prevent infinite loops
 * @return array The processed document data with references resolved
 */
function processDocumentReferences($documentData, $depth = 0) {
    // Skip if not an array or null, or if we've reached maximum recursion depth
    if (!is_array($documentData) || empty($documentData) || $depth > 2) {
        return $documentData;
    }
    
    $processed = $documentData;
    
    // Look for fields that might be references to other documents
    foreach ($processed as $field => &$value) {
        // Skip null values
        if ($value === null) {
            continue;
        }
        
        // Skip fields that might cause circular references
        if ($field === '_collection' || strpos($field, '_data') !== false) {
            continue;
        }
        
        // If the field name ends with _id or _ref or _document, it might be a reference
        if (preg_match('/(^|_)(id|ref|document)$/i', $field) && is_string($value)) {
            // Try to locate the referenced document
            $referencedDoc = findReferencedDocument($value);
            if ($referencedDoc) {
                // Replace the value with the referenced document data
                $refFieldBase = preg_replace('/(^|_)(id|ref|document)$/i', '', $field);
                $processed[$refFieldBase . '_data'] = $referencedDoc;
            }
        } 
        // Recursively process nested objects with increased depth
        else if (is_array($value)) {
            $processed[$field] = processDocumentReferences($value, $depth + 1);
        }
    }
    
    return $processed;
}

/**
 * Find a referenced document by its ID
 * 
 * @param string $documentId The ID of the document to find
 * @return array|null The document data if found, null otherwise
 */
function findReferencedDocument($documentId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Search in all collections
        $stmt = $db->prepare("SELECT collection_id, document_id, data FROM documents WHERE document_id = ?");
        $stmt->execute([$documentId]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($document) {
            // Found a document with this ID
            $data = json_decode($document['data'], true);
            
            // Get collection name for additional context
            $stmt = $db->prepare("SELECT name FROM collections WHERE id = ?");
            $stmt->execute([$document['collection_id']]);
            $collection = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($collection) {
                $data['_collection'] = $collection['name'];
            }
            
            return $data;
        }
        
        return null;
    } catch (Exception $e) {
        error_log('Find referenced document error: ' . $e->getMessage());
        return null;
    }
}
