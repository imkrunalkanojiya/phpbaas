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
            <h2><i class="fas fa-file me-2"></i>File Storage API</h2>
            <a href="/documentation/index.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i>Back to Documentation
            </a>
        </div>
        
        <!-- File Storage Overview -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Overview</h5>
            </div>
            <div class="card-body">
                <p>The File Storage API allows you to upload, download, list, and delete files in your PHPBaaS projects. Files are stored securely on the server and can be accessed via API.</p>
                
                <h6>Base URL</h6>
                <pre><code>https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/files.php</code></pre>
                
                <h6>Authentication</h6>
                <p>All file storage API endpoints require an API key to be included in the header:</p>
                <pre><code>X-API-Key: YOUR_API_KEY</code></pre>
                
                <h6>Available Endpoints</h6>
                <ul>
                    <li><code>POST /api/files.php?route=upload</code> - Upload a file</li>
                    <li><code>GET /api/files.php?route=list</code> - List all files</li>
                    <li><code>GET /api/files.php?route=get&id={file_id}</code> - Get file details</li>
                    <li><code>GET /api/files.php?route=download&id={file_id}</code> - Download a file</li>
                    <li><code>DELETE /api/files.php?route=delete&id={file_id}</code> - Delete a file</li>
                </ul>
                
                <h6>File Constraints</h6>
                <ul>
                    <li>Maximum file size: <?php echo MAX_FILE_SIZE / (1024 * 1024); ?> MB</li>
                    <li>Allowed file types: <?php echo ALLOWED_FILE_TYPES; ?></li>
                </ul>
            </div>
        </div>
        
        <!-- Upload File -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cloud-upload-alt me-2"></i>Upload a File</h5>
            </div>
            <div class="card-body">
                <h6>Endpoint</h6>
                <pre><code>POST /api/files.php?route=upload</code></pre>
                
                <h6>Description</h6>
                <p>Uploads a file to the server and associates it with your project.</p>
                
                <h6>Headers</h6>
                <pre><code>X-API-Key: YOUR_API_KEY</code></pre>
                
                <h6>Request</h6>
                <p>This endpoint expects a <code>multipart/form-data</code> request with a file field named "file".</p>
                
                <h6>Response</h6>
                <pre><code>{
  "success": true,
  "message": "File uploaded successfully",
  "file": {
    "id": 123,
    "file_name": "document_1638276363.pdf",
    "file_size": 1048576,
    "file_type": "application/pdf"
  }
}</code></pre>
                
                <h6>Error Responses</h6>
                <ul>
                    <li><code>400 Bad Request</code> - No file uploaded</li>
                    <li><code>400 Bad Request</code> - File size exceeds the maximum limit</li>
                    <li><code>400 Bad Request</code> - File type not allowed</li>
                    <li><code>401 Unauthorized</code> - API key required or invalid</li>
                    <li><code>403 Forbidden</code> - API key does not have write permission</li>
                    <li><code>500 Internal Server Error</code> - Failed to save file</li>
                </ul>
                
                <h6>Example Request (cURL)</h6>
                <pre><code>curl -X POST "https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/files.php?route=upload" \
  -H "X-API-Key: YOUR_API_KEY" \
  -F "file=@/path/to/your/file.pdf"</code></pre>
                
                <h6>Example Request (HTML Form)</h6>
                <pre><code>&lt;form method="POST" action="https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/files.php?route=upload" enctype="multipart/form-data"&gt;
  &lt;input type="hidden" name="X-API-Key" value="YOUR_API_KEY"&gt;
  &lt;input type="file" name="file"&gt;
  &lt;button type="submit"&gt;Upload&lt;/button&gt;
&lt;/form&gt;</code></pre>
                
                <h6>Example Request (JavaScript)</h6>
                <pre><code>const formData = new FormData();
formData.append('file', fileInput.files[0]);

fetch('https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/files.php?route=upload', {
  method: 'POST',
  headers: {
    'X-API-Key': 'YOUR_API_KEY'
  },
  body: formData
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));</code></pre>
            </div>
        </div>
        
        <!-- List Files -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>List Files</h5>
            </div>
            <div class="card-body">
                <h6>Endpoint</h6>
                <pre><code>GET /api/files.php?route=list</code></pre>
                
                <h6>Description</h6>
                <p>Retrieves a list of all files associated with your project.</p>
                
                <h6>Headers</h6>
                <pre><code>X-API-Key: YOUR_API_KEY</code></pre>
                
                <h6>Optional Query Parameters</h6>
                <ul>
                    <li><code>page</code> - Page number for pagination (default: 1)</li>
                    <li><code>limit</code> - Number of files per page (default: 20, max: 100)</li>
                </ul>
                
                <h6>Response</h6>
                <pre><code>{
  "success": true,
  "files": [
    {
      "id": 123,
      "file_name": "document_1638276363.pdf",
      "file_size": 1048576,
      "file_type": "application/pdf",
      "created_at": "2023-01-01 12:00:00"
    },
    {
      "id": 124,
      "file_name": "image_1638276400.jpg",
      "file_size": 512000,
      "file_type": "image/jpeg",
      "created_at": "2023-01-02 12:00:00"
    }
  ],
  "pagination": {
    "total": 42,
    "page": 1,
    "limit": 20,
    "pages": 3
  }
}</code></pre>
                
                <h6>Error Responses</h6>
                <ul>
                    <li><code>401 Unauthorized</code> - API key required or invalid</li>
                    <li><code>500 Internal Server Error</code> - Failed to retrieve files</li>
                </ul>
                
                <h6>Example Request (cURL)</h6>
                <pre><code>curl -X GET "https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/files.php?route=list&page=1&limit=20" \
  -H "X-API-Key: YOUR_API_KEY"</code></pre>
                
                <h6>Example Request (JavaScript)</h6>
                <pre><code>fetch('https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/files.php?route=list&page=1&limit=20', {
  method: 'GET',
  headers: {
    'X-API-Key': 'YOUR_API_KEY'
  }
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));</code></pre>
            </div>
        </div>
        
        <!-- Get File Details -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info me-2"></i>Get File Details</h5>
            </div>
            <div class="card-body">
                <h6>Endpoint</h6>
                <pre><code>GET /api/files.php?route=get&id={file_id}</code></pre>
                
                <h6>Description</h6>
                <p>Retrieves detailed information about a specific file.</p>
                
                <h6>Headers</h6>
                <pre><code>X-API-Key: YOUR_API_KEY</code></pre>
                
                <h6>Response</h6>
                <pre><code>{
  "success": true,
  "file": {
    "id": 123,
    "file_name": "document_1638276363.pdf",
    "file_size": 1048576,
    "file_type": "application/pdf",
    "created_at": "2023-01-01 12:00:00"
  }
}</code></pre>
                
                <h6>Error Responses</h6>
                <ul>
                    <li><code>401 Unauthorized</code> - API key required or invalid</li>
                    <li><code>404 Not Found</code> - File not found or access denied</li>
                    <li><code>500 Internal Server Error</code> - Failed to retrieve file details</li>
                </ul>
                
                <h6>Example Request (cURL)</h6>
                <pre><code>curl -X GET "https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/files.php?route=get&id=123" \
  -H "X-API-Key: YOUR_API_KEY"</code></pre>
                
                <h6>Example Request (JavaScript)</h6>
                <pre><code>fetch('https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/files.php?route=get&id=123', {
  method: 'GET',
  headers: {
    'X-API-Key': 'YOUR_API_KEY'
  }
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));</code></pre>
            </div>
        </div>
        
        <!-- Download File -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-download me-2"></i>Download a File</h5>
            </div>
            <div class="card-body">
                <h6>Endpoint</h6>
                <pre><code>GET /api/files.php?route=download&id={file_id}</code></pre>
                
                <h6>Description</h6>
                <p>Downloads a file. This endpoint returns the file content directly, not a JSON response.</p>
                
                <h6>Headers</h6>
                <pre><code>X-API-Key: YOUR_API_KEY</code></pre>
                
                <h6>Response</h6>
                <p>The file content with appropriate Content-Type and Content-Disposition headers.</p>
                
                <h6>Error Responses</h6>
                <ul>
                    <li><code>401 Unauthorized</code> - API key required or invalid</li>
                    <li><code>404 Not Found</code> - File not found or access denied</li>
                    <li><code>500 Internal Server Error</code> - Failed to download file</li>
                </ul>
                
                <h6>Example Usage (Direct Link)</h6>
                <pre><code>&lt;a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/files.php?route=download&id=123&api_key=YOUR_API_KEY"&gt;Download File&lt;/a&gt;</code></pre>
                
                <h6>Example Request (JavaScript - Binary Download)</h6>
                <pre><code>fetch('https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/files.php?route=download&id=123', {
  method: 'GET',
  headers: {
    'X-API-Key': 'YOUR_API_KEY'
  }
})
.then(response => response.blob())
.then(blob => {
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'filename'; // You might want to get the actual filename from the Content-Disposition header
  document.body.appendChild(a);
  a.click();
  a.remove();
  window.URL.revokeObjectURL(url);
})
.catch(error => console.error('Error:', error));</code></pre>
            </div>
        </div>
        
        <!-- Delete File -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-trash-alt me-2"></i>Delete a File</h5>
            </div>
            <div class="card-body">
                <h6>Endpoint</h6>
                <pre><code>DELETE /api/files.php?route=delete&id={file_id}</code></pre>
                
                <h6>Description</h6>
                <p>Deletes a file from the server.</p>
                
                <h6>Headers</h6>
                <pre><code>X-API-Key: YOUR_API_KEY</code></pre>
                
                <h6>Response</h6>
                <pre><code>{
  "success": true,
  "message": "File deleted successfully"
}</code></pre>
                
                <h6>Error Responses</h6>
                <ul>
                    <li><code>401 Unauthorized</code> - API key required or invalid</li>
                    <li><code>403 Forbidden</code> - API key does not have write permission</li>
                    <li><code>404 Not Found</code> - File not found or access denied</li>
                    <li><code>500 Internal Server Error</code> - Failed to delete file</li>
                </ul>
                
                <h6>Example Request (cURL)</h6>
                <pre><code>curl -X DELETE "https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/files.php?route=delete&id=123" \
  -H "X-API-Key: YOUR_API_KEY"</code></pre>
                
                <h6>Example Request (JavaScript)</h6>
                <pre><code>fetch('https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/files.php?route=delete&id=123', {
  method: 'DELETE',
  headers: {
    'X-API-Key': 'YOUR_API_KEY'
  }
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));</code></pre>
            </div>
        </div>
        
        <!-- Best Practices -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Best Practices</h5>
            </div>
            <div class="card-body">
                <h6>File Management Tips</h6>
                <ul>
                    <li><strong>Security:</strong> Always validate file types and sizes on the client side before uploading</li>
                    <li><strong>Efficiency:</strong> Compress large files before uploading to reduce transfer time and storage usage</li>
                    <li><strong>Organization:</strong> Keep track of file IDs in your application's database for easier reference</li>
                    <li><strong>User Experience:</strong> Implement progress indicators for large file uploads</li>
                    <li><strong>Caching:</strong> Cache downloaded files when appropriate to reduce API calls</li>
                </ul>
                
                <h6>Error Handling</h6>
                <ul>
                    <li>Implement retry logic for failed uploads, especially for larger files</li>
                    <li>Provide clear error messages to users when file operations fail</li>
                    <li>Verify file integrity after upload when necessary (e.g., by comparing checksums)</li>
                </ul>
                
                <h6>File URLs</h6>
                <p>For public access to files, you can use the download URL directly:</p>
                <pre><code>https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/files.php?route=download&id={file_id}&api_key=YOUR_API_KEY</code></pre>
                
                <p>Note: Including the API key in URLs is convenient but less secure. For better security in production applications, consider:</p>
                <ul>
                    <li>Using the header-based authentication method</li>
                    <li>Implementing signed URLs with expiration times</li>
                    <li>Creating a proxy endpoint in your application that handles the authentication</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>
