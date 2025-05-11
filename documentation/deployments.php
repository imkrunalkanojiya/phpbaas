<?php
/**
 * Deployments Documentation
 * Provides documentation for the deployments API
 */
require_once '../config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

// Page title
$pageTitle = "Deployments Documentation";

// Include header
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
                <h1 class="h2">Deployments Documentation</h1>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Overview</h5>
                            <p class="card-text">
                                The Deployments API allows you to deploy your PHP applications directly from your
                                code repository. You can create, manage, and monitor your deployments through
                                both the dashboard and the API.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Authentication</h5>
                        </div>
                        <div class="card-body">
                            <p>
                                All API requests must include your API key in the request headers. You can obtain an API key from the
                                <a href="/dashboard/keys.php">API Keys</a> section in your dashboard.
                            </p>
                            
                            <pre><code class="language-bash">curl -H "X-API-Key: your-api-key" https://your-domain.com/api/deployments.php</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">API Endpoints</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="border-bottom pb-2 mb-3">Create a Deployment</h6>
                            <p>
                                <code>POST /api/deployments.php?project_id={project_id}&route=create</code>
                            </p>
                            <p>
                                Creates a new deployment for a specific project.
                            </p>
                            
                            <h6 class="mt-4">Request Body</h6>
                            <pre><code class="language-json">{
  "name": "production-deployment",
  "description": "Production deployment for my PHP app",
  "environment": "production",
  "source_code": "https://github.com/username/repo"
}</code></pre>

                            <h6 class="mt-4">Response</h6>
                            <pre><code class="language-json">{
  "success": true,
  "message": "Deployment created successfully",
  "deployment": {
    "id": "deploy_1234567890",
    "project_id": 1,
    "name": "production-deployment",
    "description": "Production deployment for my PHP app",
    "environment": "production",
    "source_code": "https://github.com/username/repo",
    "status": "active",
    "url": "https://production-deployment-1234567.example.com",
    "created_at": "2023-06-01 12:00:00",
    "updated_at": "2023-06-01 12:01:00"
  }
}</code></pre>
                            
                            <h6 class="border-bottom pb-2 mb-3 mt-5">List Deployments</h6>
                            <p>
                                <code>GET /api/deployments.php?project_id={project_id}&route=list</code>
                            </p>
                            <p>
                                Lists all deployments for a specific project.
                            </p>
                            
                            <h6 class="mt-4">Response</h6>
                            <pre><code class="language-json">{
  "success": true,
  "deployments": [
    {
      "id": "deploy_1234567890",
      "project_id": 1,
      "name": "production-deployment",
      "description": "Production deployment for my PHP app",
      "environment": "production",
      "source_code": "https://github.com/username/repo",
      "status": "active",
      "url": "https://production-deployment-1234567.example.com",
      "created_at": "2023-06-01 12:00:00",
      "updated_at": "2023-06-01 12:01:00"
    },
    {
      "id": "deploy_0987654321",
      "project_id": 1,
      "name": "staging-deployment",
      "description": "Staging deployment for my PHP app",
      "environment": "staging",
      "source_code": "https://github.com/username/repo",
      "status": "active",
      "url": "https://staging-deployment-0987654.example.com",
      "created_at": "2023-05-01 12:00:00",
      "updated_at": "2023-05-01 12:01:00"
    }
  ]
}</code></pre>
                            
                            <h6 class="border-bottom pb-2 mb-3 mt-5">Get Deployment</h6>
                            <p>
                                <code>GET /api/deployments.php?project_id={project_id}&route=get&id={deployment_id}</code>
                            </p>
                            <p>
                                Gets a specific deployment for a project.
                            </p>
                            
                            <h6 class="mt-4">Response</h6>
                            <pre><code class="language-json">{
  "success": true,
  "deployment": {
    "id": "deploy_1234567890",
    "project_id": 1,
    "name": "production-deployment",
    "description": "Production deployment for my PHP app",
    "environment": "production",
    "source_code": "https://github.com/username/repo",
    "status": "active",
    "url": "https://production-deployment-1234567.example.com",
    "created_at": "2023-06-01 12:00:00",
    "updated_at": "2023-06-01 12:01:00"
  }
}</code></pre>
                            
                            <h6 class="border-bottom pb-2 mb-3 mt-5">Delete Deployment</h6>
                            <p>
                                <code>DELETE /api/deployments.php?project_id={project_id}&route=delete&id={deployment_id}</code>
                            </p>
                            <p>
                                Deletes a specific deployment.
                            </p>
                            
                            <h6 class="mt-4">Response</h6>
                            <pre><code class="language-json">{
  "success": true,
  "message": "Deployment deleted successfully"
}</code></pre>
                            
                            <h6 class="border-bottom pb-2 mb-3 mt-5">Get Deployment Logs</h6>
                            <p>
                                <code>GET /api/deployments.php?project_id={project_id}&route=logs&id={deployment_id}</code>
                            </p>
                            <p>
                                Gets logs for a specific deployment.
                            </p>
                            
                            <h6 class="mt-4">Response</h6>
                            <pre><code class="language-json">{
  "success": true,
  "logs": [
    {
      "id": 1,
      "deployment_id": "deploy_1234567890",
      "message": "Deployment started",
      "level": "info",
      "created_at": "2023-06-01 12:00:00"
    },
    {
      "id": 2,
      "deployment_id": "deploy_1234567890",
      "message": "Building application",
      "level": "info",
      "created_at": "2023-06-01 12:00:10"
    },
    {
      "id": 3,
      "deployment_id": "deploy_1234567890",
      "message": "Application built successfully",
      "level": "info",
      "created_at": "2023-06-01 12:00:30"
    },
    {
      "id": 4,
      "deployment_id": "deploy_1234567890",
      "message": "Deployment completed",
      "level": "success",
      "created_at": "2023-06-01 12:01:00"
    }
  ]
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Code Examples</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="border-bottom pb-2 mb-3">JavaScript (Fetch API)</h6>
                            <pre><code class="language-javascript">// Create a new deployment
async function createDeployment() {
  const response = await fetch('/api/deployments.php?project_id=1&route=create', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-API-Key': 'your-api-key'
    },
    body: JSON.stringify({
      name: 'production-deployment',
      description: 'Production deployment for my PHP app',
      environment: 'production',
      source_code: 'https://github.com/username/repo'
    })
  });
  
  const data = await response.json();
  console.log(data);
}</code></pre>

                            <h6 class="border-bottom pb-2 mb-3 mt-4">PHP (cURL)</h6>
                            <pre><code class="language-php">// Create a new deployment
function createDeployment() {
    $url = 'https://your-domain.com/api/deployments.php?project_id=1&route=create';
    $data = [
        'name' => 'production-deployment',
        'description' => 'Production deployment for my PHP app',
        'environment' => 'production',
        'source_code' => 'https://github.com/username/repo'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: your-api-key'
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Deployment Environments</h5>
                        </div>
                        <div class="card-body">
                            <p>
                                Each deployment is associated with an environment. The available environments are:
                            </p>
                            
                            <ul>
                                <li><strong>production</strong> - For live applications serving real users</li>
                                <li><strong>staging</strong> - For pre-production testing</li>
                                <li><strong>development</strong> - For development and testing</li>
                                <li><strong>testing</strong> - For automated testing environments</li>
                            </ul>
                            
                            <p>
                                Different environments may have different capabilities and resource allocations.
                                The production environment offers the highest level of performance and reliability.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Deployment Status</h5>
                        </div>
                        <div class="card-body">
                            <p>
                                Each deployment has a status that indicates its current state:
                            </p>
                            
                            <ul>
                                <li><strong>pending</strong> - The deployment is being created</li>
                                <li><strong>building</strong> - The application is being built</li>
                                <li><strong>deploying</strong> - The application is being deployed</li>
                                <li><strong>active</strong> - The deployment is live and serving traffic</li>
                                <li><strong>failed</strong> - The deployment process failed</li>
                                <li><strong>stopped</strong> - The deployment has been stopped</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>