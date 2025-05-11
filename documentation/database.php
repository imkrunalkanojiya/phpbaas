<?php
require_once __DIR__ . '/../config/config.php';

// Start session for auth status
session_start();
$isLoggedIn = isset($_SESSION['user_id']);

include_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-database me-2"></i>Database API</h2>
            <a href="/documentation/index.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i>Back to Documentation
            </a>
        </div>
        
        <!-- Database Overview -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Overview</h5>
            </div>
            <div class="card-body">
                <p>The Database API allows you to store, retrieve, update, and delete data in your PHPBaaS projects. Data is organized into collections and documents, similar to a NoSQL database like MongoDB.</p>
                
                <h6>Base URL</h6>
                <pre><code>https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/database.php</code></pre>
                
                <h6>Authentication</h6>
                <p>All database API endpoints require an API key to be included in the header:</p>
                <pre><code>X-API-Key: YOUR_API_KEY</code></pre>
                
                <h6>Available Endpoints</h6>
                <ul>
                    <li><strong>Collections</strong>
                        <ul>
                            <li><code>GET /api/database.php?route=collections</code> - List all collections</li>
                            <li><code>GET /api/database.php?route=collections&id={collection_id}</code> - Get a specific collection</li>
                            <li><code>POST /api/database.php?route=collections</code> - Create a new collection</li>
                            <li><code>PUT /api/database.php?route=collections&id={collection_id}</code> - Update a collection</li>
                            <li><code>DELETE /api/database.php?route=collections&id={collection_id}</code> - Delete a collection</li>
                        </ul>
                    </li>
                    <li><strong>Documents</strong>
                        <ul>
                            <li><code>GET /api/database.php?route=documents&collection_id={collection_id}</code> - List all documents in a collection</li>
                            <li><code>GET /api/database.php?route=documents&collection_id={collection_id}&document_id={document_id}</code> - Get a specific document</li>
                            <li><code>POST /api/database.php?route=documents&collection_id={collection_id}</code> - Create a new document</li>
                            <li><code>PUT /api/database.php?route=documents&collection_id={collection_id}&document_id={document_id}</code> - Update a document</li>
                            <li><code>DELETE /api/database.php?route=documents&collection_id={collection_id}&document_id={document_id}</code> - Delete a document</li>
                        </ul>
                    </li>
                    <li><strong>Joins</strong>
                        <ul>
                            <li><code>GET /api/database.php?route=joins&source_collection={source_collection_id}&target_collection={target_collection_id}&join_field={join_field}</code> - Join collections on matching fields</li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Collections API -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Collections API</h5>
            </div>
            <div class="card-body">
                <p>Collections are containers for your documents, similar to tables in a relational database.</p>
                
                <h6 class="mt-4">List All Collections</h6>
                <pre><code>GET /api/database.php?route=collections</code></pre>
                
                <p><strong>Headers:</strong></p>
                <pre><code>X-API-Key: YOUR_API_KEY</code></pre>
                
                <p><strong>Response:</strong></p>
                <pre><code>{
  "success": true,
  "collections": [
    {
      "id": 1,
      "project_id": 123,
      "name": "users",
      "description": "Collection for user data",
      "created_at": "2023-01-01 12:00:00",
      "updated_at": "2023-01-01 12:00:00"
    },
    {
      "id": 2,
      "project_id": 123,
      "name": "products",
      "description": "Collection for product data",
      "created_at": "2023-01-02 12:00:00",
      "updated_at": "2023-01-02 12:00:00"
    }
  ]
}</code></pre>
                
                <h6 class="mt-4">Get a Specific Collection</h6>
                <pre><code>GET /api/database.php?route=collections&id={collection_id}</code></pre>
                
                <p><strong>Headers:</strong></p>
                <pre><code>X-API-Key: YOUR_API_KEY</code></pre>
                
                <p><strong>Response:</strong></p>
                <pre><code>{
  "success": true,
  "collection": {
    "id": 1,
    "project_id": 123,
    "name": "users",
    "description": "Collection for user data",
    "created_at": "2023-01-01 12:00:00",
    "updated_at": "2023-01-01 12:00:00",
    "document_count": 42
  }
}</code></pre>
                
                <h6 class="mt-4">Create a New Collection</h6>
                <pre><code>POST /api/database.php?route=collections</code></pre>
                
                <p><strong>Headers:</strong></p>
                <pre><code>X-API-Key: YOUR_API_KEY
Content-Type: application/json</code></pre>
                
                <p><strong>Request Body:</strong></p>
                <pre><code>{
  "name": "customers",
  "description": "Collection for customer data"
}</code></pre>
                
                <p><strong>Response:</strong></p>
                <pre><code>{
  "success": true,
  "message": "Collection created successfully",
  "collection": {
    "id": 3,
    "project_id": 123,
    "name": "customers",
    "description": "Collection for customer data",
    "created_at": "2023-01-03 12:00:00"
  }
}</code></pre>
                
                <h6 class="mt-4">Update a Collection</h6>
                <pre><code>PUT /api/database.php?route=collections&id={collection_id}</code></pre>
                
                <p><strong>Headers:</strong></p>
                <pre><code>X-API-Key: YOUR_API_KEY
Content-Type: application/json</code></pre>
                
                <p><strong>Request Body:</strong></p>
                <pre><code>{
  "name": "active_customers",
  "description": "Collection for active customer data"
}</code></pre>
                
                <p><strong>Response:</strong></p>
                <pre><code>{
  "success": true,
  "message": "Collection updated successfully",
  "collection": {
    "id": 3,
    "name": "active_customers",
    "description": "Collection for active customer data"
  }
}</code></pre>
                
                <h6 class="mt-4">Delete a Collection</h6>
                <pre><code>DELETE /api/database.php?route=collections&id={collection_id}</code></pre>
                
                <p><strong>Headers:</strong></p>
                <pre><code>X-API-Key: YOUR_API_KEY</code></pre>
                
                <p><strong>Response:</strong></p>
                <pre><code>{
  "success": true,
  "message": "Collection deleted successfully"
}</code></pre>
                
                <p><strong>Note:</strong> Deleting a collection will also delete all documents within that collection.</p>
            </div>
        </div>
        
        <!-- Documents API -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Documents API</h5>
            </div>
            <div class="card-body">
                <p>Documents are JSON objects stored within collections. Each document has a unique ID within its collection.</p>
                
                <h6 class="mt-4">List Documents in a Collection</h6>
                <pre><code>GET /api/database.php?route=documents&collection_id={collection_id}</code></pre>
                
                <p><strong>Headers:</strong></p>
                <pre><code>X-API-Key: YOUR_API_KEY</code></pre>
                
                <p><strong>Optional Query Parameters:</strong></p>
                <ul>
                    <li><code>page</code> - Page number for pagination (default: 1)</li>
                    <li><code>limit</code> - Number of documents per page (default: 20, max: 100)</li>
                </ul>
                
                <p><strong>Response:</strong></p>
                <pre><code>{
  "success": true,
  "documents": [
    {
      "id": 1,
      "collection_id": 1,
      "document_id": "abc123",
      "data": {
        "name": "John Doe",
        "email": "john@example.com",
        "age": 30
      },
      "created_at": "2023-01-01 12:00:00",
      "updated_at": "2023-01-01 12:00:00"
    },
    {
      "id": 2,
      "collection_id": 1,
      "document_id": "def456",
      "data": {
        "name": "Jane Smith",
        "email": "jane@example.com",
        "age": 25
      },
      "created_at": "2023-01-02 12:00:00",
      "updated_at": "2023-01-02 12:00:00"
    }
  ],
  "pagination": {
    "total": 42,
    "page": 1,
    "limit": 20,
    "pages": 3
  }
}</code></pre>
                
                <h6 class="mt-4">Get a Specific Document</h6>
                <pre><code>GET /api/database.php?route=documents&collection_id={collection_id}&document_id={document_id}</code></pre>
                
                <p><strong>Headers:</strong></p>
                <pre><code>X-API-Key: YOUR_API_KEY</code></pre>
                
                <p><strong>Response:</strong></p>
                <pre><code>{
  "success": true,
  "document": {
    "id": 1,
    "collection_id": 1,
    "document_id": "abc123",
    "data": {
      "name": "John Doe",
      "email": "john@example.com",
      "age": 30
    },
    "created_at": "2023-01-01 12:00:00",
    "updated_at": "2023-01-01 12:00:00"
  }
}</code></pre>
                
                <h6 class="mt-4">Create a New Document</h6>
                <pre><code>POST /api/database.php?route=documents&collection_id={collection_id}</code></pre>
                
                <p><strong>Headers:</strong></p>
                <pre><code>X-API-Key: YOUR_API_KEY
Content-Type: application/json</code></pre>
                
                <p><strong>Request Body:</strong></p>
                <pre><code>{
  "document_id": "ghi789", // Optional - will be generated if not provided
  "data": {
    "name": "Bob Johnson",
    "email": "bob@example.com",
    "age": 35
  }
}</code></pre>
                
                <p><strong>Response:</strong></p>
                <pre><code>{
  "success": true,
  "message": "Document created successfully",
  "document": {
    "id": 3,
    "collection_id": 1,
    "document_id": "ghi789",
    "data": {
      "name": "Bob Johnson",
      "email": "bob@example.com",
      "age": 35
    },
    "created_at": "2023-01-03 12:00:00"
  }
}</code></pre>
                
                <h6 class="mt-4">Update a Document</h6>
                <pre><code>PUT /api/database.php?route=documents&collection_id={collection_id}&document_id={document_id}</code></pre>
                
                <p><strong>Headers:</strong></p>
                <pre><code>X-API-Key: YOUR_API_KEY
Content-Type: application/json</code></pre>
                
                <p><strong>Request Body:</strong></p>
                <pre><code>{
  "data": {
    "name": "Bob Johnson",
    "email": "bob.johnson@example.com",
    "age": 36
  }
}</code></pre>
                
                <p><strong>Response:</strong></p>
                <pre><code>{
  "success": true,
  "message": "Document updated successfully",
  "document": {
    "collection_id": 1,
    "document_id": "ghi789",
    "data": {
      "name": "Bob Johnson",
      "email": "bob.johnson@example.com",
      "age": 36
    },
    "updated_at": "2023-01-04 12:00:00"
  }
}</code></pre>
                
                <h6 class="mt-4">Delete a Document</h6>
                <pre><code>DELETE /api/database.php?route=documents&collection_id={collection_id}&document_id={document_id}</code></pre>
                
                <p><strong>Headers:</strong></p>
                <pre><code>X-API-Key: YOUR_API_KEY</code></pre>
                
                <p><strong>Response:</strong></p>
                <pre><code>{
  "success": true,
  "message": "Document deleted successfully"
}</code></pre>
            </div>
        </div>
        
        <!-- Automatic Document References -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-link me-2"></i>Automatic Document References</h5>
            </div>
            <div class="card-body">
                <p>The Database API now supports automatic document references between collections in a MongoDB-style aggregation system. This allows you to seamlessly link and retrieve related data without manual joins.</p>
                
                <h6>How Document References Work</h6>
                <p>When you retrieve a document, the system automatically detects and resolves references to other documents:</p>
                <ol>
                    <li>The system identifies reference fields in your document data (fields ending with <code>_id</code>, <code>_ref</code>, or <code>_document</code>)</li>
                    <li>For each reference field, the system looks for a document with a matching document ID</li>
                    <li>If a matching document is found, its data is automatically included in the response as a new field</li>
                </ol>
                
                <h6>Example</h6>
                <p>If you have a document in the "students" collection that references a school:</p>
                <pre><code>{
  "name": "John Smith",
  "age": 18,
  "school_id": "school123"  // Reference to a document in the schools collection
}</code></pre>
                
                <p>When you retrieve this document, the system automatically adds the school data:</p>
                <pre><code>{
  "name": "John Smith",
  "age": 18,
  "school_id": "school123",
  "school_data": {  // Automatically populated from the referenced document
    "id": "school123",
    "name": "Springfield High School",
    "address": "123 Main St, Springfield",
    "_collection": "schools"  // Indicates the source collection
  }
}</code></pre>
                
                <h6>Using Document References</h6>
                <p>To set up document references in your application:</p>
                <ol>
                    <li>Create your documents in different collections as needed</li>
                    <li>Use consistent naming conventions for reference fields (ending with <code>_id</code>, <code>_ref</code>, or <code>_document</code>)</li>
                    <li>Store the document ID of the referenced document in the reference field</li>
                    <li>Use standard GET requests to retrieve your documents - references are resolved automatically</li>
                </ol>
                
                <h6>Use Cases</h6>
                <p>Some common use cases for automatic document references:</p>
                <ul>
                    <li>Connecting users to their profile data</li>
                    <li>Linking products to their categories</li>
                    <li>Associating comments with posts</li>
                    <li>Implementing complex data relationships like many-to-many mappings</li>
                </ul>
                
                <div class="alert alert-info">
                    <strong>Tip:</strong> Document references work across all collections and with any document ID. You can create complex nested relationships, with references inside referenced documents also being automatically resolved.
                </div>
            </div>
        </div>
        
        <!-- Data Modeling Tips -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>Data Modeling Tips</h5>
            </div>
            <div class="card-body">
                <p>Here are some tips for effectively modeling your data with the PHPBaaS Database API:</p>
                
                <h6>1. Collection Design</h6>
                <ul>
                    <li>Create separate collections for different types of data (e.g., users, products, orders)</li>
                    <li>Use descriptive names for your collections</li>
                    <li>Consider the access patterns when deciding how to structure your collections</li>
                </ul>
                
                <h6>2. Document Structure</h6>
                <ul>
                    <li>Store related data together in a single document</li>
                    <li>Avoid deeply nested structures that are difficult to query</li>
                    <li>Consider denormalizing data for faster read access</li>
                </ul>
                
                <h6>3. Document IDs</h6>
                <ul>
                    <li>Use meaningful IDs when possible (e.g., usernames, slugs)</li>
                    <li>Let the system generate random IDs when the ID doesn't need to be meaningful</li>
                    <li>Consider using UUIDs for distributed systems</li>
                </ul>
                
                <h6>4. Document References and Relationships</h6>
                <p>The API now supports automatic document references, allowing you to create MongoDB-style relationships between collections:</p>
                <ul>
                    <li>Use fields ending with <code>_id</code>, <code>_ref</code>, or <code>_document</code> to create references</li>
                    <li>Store document IDs in these reference fields to link to other documents</li>
                    <li>References are automatically resolved when documents are retrieved</li>
                    <li>Nested references (references inside referenced documents) are also resolved</li>
                </ul>
                
                <p>Best practices for document references:</p>
                <ul>
                    <li>Use consistent naming conventions for your reference fields</li>
                    <li>Consider the depth of your reference chains to avoid performance issues</li>
                    <li>Structure your collections to optimize for your most common access patterns</li>
                    <li>Use document references for one-to-many and many-to-many relationships</li>
                </ul>
            </div>
        </div>
        
        <!-- Error Handling -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Error Handling</h5>
            </div>
            <div class="card-body">
                <p>The Database API returns standardized error responses in the following format:</p>
                
                <pre><code>{
  "error": true,
  "message": "Error message describing what went wrong"
}</code></pre>
                
                <h6>Common Error Codes</h6>
                <ul>
                    <li><code>400 Bad Request</code> - Invalid input data or missing required fields</li>
                    <li><code>401 Unauthorized</code> - Missing or invalid API key</li>
                    <li><code>403 Forbidden</code> - API key does not have required permissions</li>
                    <li><code>404 Not Found</code> - Collection or document not found</li>
                    <li><code>409 Conflict</code> - Document ID or collection name already exists</li>
                    <li><code>500 Internal Server Error</code> - Server-side error</li>
                </ul>
                
                <h6>Error Handling Best Practices</h6>
                <ul>
                    <li>Always check the response for errors before processing the data</li>
                    <li>Implement retry logic for transient failures (5xx errors)</li>
                    <li>Provide meaningful error messages to your users</li>
                    <li>Log errors on the client side for debugging</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>
