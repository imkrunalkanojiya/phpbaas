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
            <h2><i class="fas fa-book me-2"></i>Documentation</h2>
            <?php if ($isLoggedIn): ?>
            <a href="/dashboard/index.php" class="btn btn-primary">
                <i class="fas fa-tachometer-alt me-1"></i>Go to Dashboard
            </a>
           
            <?php endif; ?>
        </div>
        
        <!-- Introduction -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Introduction</h5>
            </div>
            <div class="card-body">
                <p>Welcome to the PHPBaaS (PHP Backend as a Service) documentation. This guide will help you understand how to use our API to build your applications quickly and efficiently.</p>
                
                <p>PHPBaaS provides the following services:</p>
                <ul>
                    <li><strong>Authentication</strong> - User management and authentication</li>
                    <li><strong>Database</strong> - NoSQL-like database with collections and documents</li>
                    <li><strong>Storage</strong> - File upload and download capabilities</li>
                    <li><strong>API Keys</strong> - Secure access to your project's data</li>
                </ul>
                
                <p>All API endpoints return JSON responses and use RESTful conventions.</p>
            </div>
        </div>
        
        <!-- Getting Started -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-rocket me-2"></i>Getting Started</h5>
            </div>
            <div class="card-body">
                <h5>1. Create an Account</h5>
                <p>Start by <a href="/dashboard/index.php">creating an account</a> or logging in if you already have one.</p>
                
                <h5>2. Create a Project</h5>
                <p>After logging in, create a new project from your dashboard. Each project acts as a separate workspace with its own data, files, and API keys.</p>
                
                <h5>3. Get Your API Key</h5>
                <p>Every project comes with a default API key. You can also create additional keys with different permissions (read-only, write-only, or both).</p>
                
                <h5>4. Set Up Your Collections</h5>
                <p>Create collections to organize your data. Think of collections as tables in a traditional database.</p>
                
                <h5>5. Integrate with Your Application</h5>
                <p>Use our API endpoints to connect your application to PHPBaaS services.</p>
                
                <div class="mt-4">
                    <a href="/documentation/auth.php" class="btn btn-outline-primary me-2 mb-2">
                        <i class="fas fa-key me-1"></i>Authentication API
                    </a>
                    <a href="/documentation/database.php" class="btn btn-outline-primary me-2 mb-2">
                        <i class="fas fa-database me-1"></i>Database API
                    </a>
                    <a href="/documentation/files.php" class="btn btn-outline-primary me-2 mb-2">
                        <i class="fas fa-file me-1"></i>Storage API
                    </a>
                    <a href="/documentation/keys.php" class="btn btn-outline-primary mb-2">
                        <i class="fas fa-key me-1"></i>API Keys Management
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Authentication -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Authentication</h5>
            </div>
            <div class="card-body">
                <p>PHPBaaS offers two types of authentication:</p>
                
                <h6>1. User Authentication</h6>
                <p>For front-end applications where users need to register and log in.</p>
                <pre><code>POST /api/auth.php?route=register
POST ./api/auth.php?route=login</code></pre>
                
                <h6>2. API Key Authentication</h6>
                <p>For server-to-server communication and backend access.</p>
                <pre><code>// Include your API key in all requests
X-API-Key: your_api_key_here</code></pre>
                
                <p><a href="/documentation/auth.php" class="btn btn-sm btn-outline-primary">View Authentication Documentation</a></p>
            </div>
        </div>
        
        <!-- Database -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-database me-2"></i>Database</h5>
            </div>
            <div class="card-body">
                <p>Store and retrieve data using collections and documents.</p>
                
                <h6>Collections</h6>
                <p>Organize your data into collections, similar to tables in a traditional database.</p>
                <pre><code>GET /api/database.php?route=collections
POST ./api/database.php?route=collections</code></pre>
                
                <h6>Documents</h6>
                <p>Store JSON documents within collections.</p>
                <pre><code>GET /api/database.php?route=documents&collection_id=123
POST ./api/database.php?route=documents&collection_id=123</code></pre>
                
                <p><a href="/documentation/database.php" class="btn btn-sm btn-outline-primary">View Database Documentation</a></p>
            </div>
        </div>
        
        <!-- Storage -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cloud-upload-alt me-2"></i>File Storage</h5>
            </div>
            <div class="card-body">
                <p>Upload, download, and manage files.</p>
                
                <h6>File Upload</h6>
                <pre><code>POST /api/files.php?route=upload</code></pre>
                
                <h6>File Download</h6>
                <pre><code>GET /api/files.php?route=download&id=123</code></pre>
                
                <p><a href="/documentation/files.php" class="btn btn-sm btn-outline-primary">View Storage Documentation</a></p>
            </div>
        </div>
        
        <!-- API Keys -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-key me-2"></i>API Keys</h5>
            </div>
            <div class="card-body">
                <p>Generate and manage API keys for your projects.</p>
                
                <h6>Permissions</h6>
                <ul>
                    <li><strong>Read Only</strong> - Only allows GET requests</li>
                    <li><strong>Write Only</strong> - Only allows POST, PUT, DELETE requests</li>
                    <li><strong>Read & Write</strong> - Allows all request types</li>
                </ul>
                
                <p><a href="/documentation/keys.php" class="btn btn-sm btn-outline-primary">View API Keys Documentation</a></p>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>
