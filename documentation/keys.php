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
            <h2><i class="fas fa-key me-2"></i>API Keys</h2>
            <a href="/documentation/index.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i>Back to Documentation
            </a>
        </div>
        
        <!-- API Keys Overview -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Overview</h5>
            </div>
            <div class="card-body">
                <p>API Keys are used to authenticate and authorize requests to your PHPBaaS project's resources. Each project can have multiple API keys, and you can control the permissions for each key.</p>
                
                <h6>API Key Features</h6>
                <ul>
                    <li>Unique, secure key for each project or application</li>
                    <li>Granular permissions (read, write, or both)</li>
                    <li>Ability to revoke access without affecting other applications</li>
                    <li>Track usage and manage access from the dashboard</li>
                </ul>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Important:</strong> API keys should be kept secret. Do not expose your API keys in client-side code or public repositories.
                </div>
            </div>
        </div>
        
        <!-- Using API Keys -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Using API Keys</h5>
            </div>
            <div class="card-body">
                <p>To authenticate your API requests, you need to include your API key in the request headers. The API key should be passed in the <code>X-API-Key</code> header.</p>
                
                <h6>Example API Request with API Key</h6>
                <pre><code>// HTTP Header
X-API-Key: your_api_key_here</code></pre>
                
                <h6>Example Request (cURL)</h6>
                <pre><code>curl -X GET "https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/database.php?route=collections" \
  -H "X-API-Key: your_api_key_here"</code></pre>
                
                <h6>Example Request (JavaScript)</h6>
                <pre><code>fetch('https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/database.php?route=collections', {
  method: 'GET',
  headers: {
    'X-API-Key': 'your_api_key_here'
  }
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));</code></pre>
                
                <h6>Example Request (PHP)</h6>
                <pre><code>$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/database.php?route=collections');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: your_api_key_here'
]);

$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);</code></pre>
                
                <h6>Alternative: URL Parameter (Less Secure)</h6>
                <p>For simple scenarios or when headers are not easily accessible, you can also pass the API key as a URL parameter:</p>
                <pre><code>GET /api/database.php?route=collections&api_key=your_api_key_here</code></pre>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Note:</strong> Using the URL parameter method is less secure as API keys may be logged in server logs or browser history. Use the header method for production applications whenever possible.
                </div>
            </div>
        </div>
        
        <!-- API Key Permissions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>API Key Permissions</h5>
            </div>
            <div class="card-body">
                <p>When creating or managing API keys, you can assign specific permissions to control what operations the key can perform:</p>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Permission</th>
                                <th>Description</th>
                                <th>Allowed Methods</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-info">Read Only</span></td>
                                <td>Can only retrieve data, cannot modify or delete</td>
                                <td>GET</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-warning">Write Only</span></td>
                                <td>Can create, update, and delete data, but cannot retrieve</td>
                                <td>POST, PUT, DELETE</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-success">Read & Write</span></td>
                                <td>Full access to all operations</td>
                                <td>GET, POST, PUT, DELETE</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <h6>Permission Best Practices</h6>
                <ul>
                    <li><strong>Principle of Least Privilege:</strong> Give each API key only the permissions it needs</li>
                    <li><strong>Public-Facing Applications:</strong> Use read-only keys for client-side applications</li>
                    <li><strong>Backend Services:</strong> Use read & write keys for server-side applications</li>
                    <li><strong>Different Keys for Different Services:</strong> Create separate keys for different applications or services</li>
                </ul>
            </div>
        </div>
        
        <!-- Managing API Keys -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Managing API Keys</h5>
            </div>
            <div class="card-body">
                <p>You can manage your API keys through the dashboard. Here's how to perform common operations:</p>
                
                <h6>Creating a New API Key</h6>
                <ol>
                    <li>Log in to your PHPBaaS dashboard</li>
                    <li>Navigate to your project</li>
                    <li>Go to the API Keys section</li>
                    <li>Click "Create API Key"</li>
                    <li>Enter a name for the key and select the permissions</li>
                    <li>Click "Create API Key"</li>
                </ol>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> The full API key is only shown once when created. Make sure to copy it immediately, as you won't be able to see it again.
                </div>
                
                <h6>Viewing API Keys</h6>
                <ol>
                    <li>Log in to your PHPBaaS dashboard</li>
                    <li>Navigate to your project</li>
                    <li>Go to the API Keys section</li>
                </ol>
                
                <h6>Updating API Key Permissions</h6>
                <ol>
                    <li>Log in to your PHPBaaS dashboard</li>
                    <li>Navigate to your project</li>
                    <li>Go to the API Keys section</li>
                    <li>Click on the API key you want to update</li>
                    <li>Click "Edit"</li>
                    <li>Update the name or permissions</li>
                    <li>Click "Save Changes"</li>
                </ol>
                
                <h6>Deleting an API Key</h6>
                <ol>
                    <li>Log in to your PHPBaaS dashboard</li>
                    <li>Navigate to your project</li>
                    <li>Go to the API Keys section</li>
                    <li>Click on the API key you want to delete</li>
                    <li>Click "Delete"</li>
                    <li>Confirm the deletion</li>
                </ol>
                
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> Deleting an API key immediately revokes access for any application using that key. Make sure to update your applications before deleting an active key.
                </div>
            </div>
        </div>
        
        <!-- API Keys Management API -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-code me-2"></i>API Keys Management API</h5>
            </div>
            <div class="card-body">
                <p>PHPBaaS also provides an API for managing API keys programmatically. These endpoints require user authentication (JWT token) rather than an API key.</p>
                
                <h6>Base URL</h6>
                <pre><code>https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/keys.php</code></pre>
                
                <h6>Authentication</h6>
                <pre><code>Authorization: Bearer YOUR_JWT_TOKEN</code></pre>
                
                <h6>Available Endpoints</h6>
                <ul>
                    <li><code>GET /api/keys.php?route=list&project_id={project_id}</code> - List all API keys for a project</li>
                    <li><code>POST /api/keys.php?route=create&project_id={project_id}</code> - Create a new API key</li>
                    <li><code>GET /api/keys.php?route=get&id={key_id}</code> - Get details of a specific API key</li>
                    <li><code>PUT /api/keys.php?route=update&id={key_id}</code> - Update an API key</li>
                    <li><code>DELETE /api/keys.php?route=delete&id={key_id}</code> - Delete an API key</li>
                </ul>
                
                <p>For more details on these endpoints, please refer to the API documentation in your dashboard.</p>
            </div>
        </div>
        
        <!-- Security Best Practices -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Security Best Practices</h5>
            </div>
            <div class="card-body">
                <p>Here are some best practices for keeping your API keys secure:</p>
                
                <h6>Do's</h6>
                <ul>
                    <li>Store API keys securely in environment variables or a secure configuration system</li>
                    <li>Use different API keys for development and production environments</li>
                    <li>Rotate API keys periodically, especially for production systems</li>
                    <li>Revoke unused or compromised API keys immediately</li>
                    <li>Use the most restrictive permissions necessary for each key</li>
                    <li>Set up monitoring to detect unusual API usage patterns</li>
                </ul>
                
                <h6>Don'ts</h6>
                <ul>
                    <li>Never hardcode API keys in your application code</li>
                    <li>Avoid committing API keys to version control systems</li>
                    <li>Don't include API keys in client-side JavaScript code</li>
                    <li>Don't share API keys across different projects or applications</li>
                    <li>Don't send API keys over unsecured (non-HTTPS) connections</li>
                    <li>Avoid logging API keys in your application logs</li>
                </ul>
                
                <h6>For Frontend Applications</h6>
                <p>If you're building a frontend application that needs to access the API directly from the client:</p>
                <ul>
                    <li>Use a read-only API key with minimal permissions</li>
                    <li>Consider creating a backend proxy service that handles API requests and keeps the API key secure</li>
                    <li>Implement proper CORS settings to restrict which domains can use your API</li>
                    <li>Consider implementing a user authentication system instead of relying solely on API keys</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>
