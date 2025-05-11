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
            <h2><i class="fas fa-key me-2"></i>Authentication API</h2>
            <a href="/documentation/index.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i>Back to Documentation
            </a>
        </div>
        
        <!-- Authentication Overview -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Overview</h5>
            </div>
            <div class="card-body">
                <p>The Authentication API allows you to register users, authenticate them, and manage user sessions for your application. All authentication endpoints use JWT (JSON Web Tokens) for secure authentication.</p>
                
                <h6>Base URL</h6>
                <pre><code>https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/auth.php</code></pre>
                
                <h6>Available Endpoints</h6>
                <ul>
                    <li><code>POST /api/auth.php?route=register</code> - Register a new user</li>
                    <li><code>POST /api/auth.php?route=login</code> - Authenticate a user</li>
                    <li><code>POST /api/auth.php?route=logout</code> - Log out a user</li>
                    <li><code>GET /api/auth.php?route=me</code> - Get current user information</li>
                </ul>
            </div>
        </div>
        
        <!-- Register Endpoint -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Register a New User</h5>
            </div>
            <div class="card-body">
                <h6>Endpoint</h6>
                <pre><code>POST /api/auth.php?route=register</code></pre>
                
                <h6>Description</h6>
                <p>Creates a new user account with the provided email, password, and name.</p>
                
                <h6>Request Body</h6>
                <pre><code>{
  "email": "user@example.com",
  "password": "password123",
  "name": "John Doe"
}</code></pre>
                
                <h6>Response</h6>
                <pre><code>{
  "success": true,
  "message": "User registered successfully",
  "user": {
    "id": 123,
    "email": "user@example.com",
    "name": "John Doe"
  },
  "token": "jwt_token_here"
}</code></pre>
                
                <h6>Error Responses</h6>
                <ul>
                    <li><code>400 Bad Request</code> - Email, password, and name are required</li>
                    <li><code>400 Bad Request</code> - Invalid email format</li>
                    <li><code>400 Bad Request</code> - Password must be at least 8 characters</li>
                    <li><code>409 Conflict</code> - Email already registered</li>
                    <li><code>500 Internal Server Error</code> - Registration failed</li>
                </ul>
                
                <h6>Example Request (JavaScript)</h6>
                <pre><code>fetch('https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/auth.php?route=register', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'password123',
    name: 'John Doe'
  })
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));</code></pre>
            </div>
        </div>
        
        <!-- Login Endpoint -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-sign-in-alt me-2"></i>Login</h5>
            </div>
            <div class="card-body">
                <h6>Endpoint</h6>
                <pre><code>POST /api/auth.php?route=login</code></pre>
                
                <h6>Description</h6>
                <p>Authenticates a user and returns a JWT token for subsequent authenticated requests.</p>
                
                <h6>Request Body</h6>
                <pre><code>{
  "email": "user@example.com",
  "password": "password123"
}</code></pre>
                
                <h6>Response</h6>
                <pre><code>{
  "success": true,
  "message": "Login successful",
  "user": {
    "id": 123,
    "email": "user@example.com",
    "name": "John Doe",
    "role": "user"
  },
  "token": "jwt_token_here"
}</code></pre>
                
                <h6>Error Responses</h6>
                <ul>
                    <li><code>400 Bad Request</code> - Email and password are required</li>
                    <li><code>401 Unauthorized</code> - Invalid credentials</li>
                    <li><code>500 Internal Server Error</code> - Login failed</li>
                </ul>
                
                <h6>Example Request (JavaScript)</h6>
                <pre><code>fetch('https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/auth.php?route=login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'password123'
  })
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));</code></pre>
            </div>
        </div>
        
        <!-- Logout Endpoint -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-sign-out-alt me-2"></i>Logout</h5>
            </div>
            <div class="card-body">
                <h6>Endpoint</h6>
                <pre><code>POST ./api/auth.php?route=logout</code></pre>
                
                <h6>Description</h6>
                <p>Logs out the current user. This is a client-side operation that invalidates the JWT token.</p>
                
                <h6>Response</h6>
                <pre><code>{
  "success": true,
  "message": "Logout successful"
}</code></pre>
                
                <h6>Example Request (JavaScript)</h6>
                <pre><code>fetch('https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/auth.php?route=logout', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_JWT_TOKEN'
  }
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));</code></pre>
            </div>
        </div>
        
        <!-- Get Current User Endpoint -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Get Current User</h5>
            </div>
            <div class="card-body">
                <h6>Endpoint</h6>
                <pre><code>GET /api/auth.php?route=me</code></pre>
                
                <h6>Description</h6>
                <p>Retrieves information about the currently authenticated user.</p>
                
                <h6>Headers</h6>
                <pre><code>Authorization: Bearer YOUR_JWT_TOKEN</code></pre>
                
                <h6>Response</h6>
                <pre><code>{
  "success": true,
  "user": {
    "id": 123,
    "email": "user@example.com",
    "name": "John Doe",
    "role": "user",
    "created_at": "2023-01-01 12:00:00"
  }
}</code></pre>
                
                <h6>Error Responses</h6>
                <ul>
                    <li><code>401 Unauthorized</code> - Authentication required</li>
                    <li><code>401 Unauthorized</code> - Invalid or expired token</li>
                    <li><code>404 Not Found</code> - User not found</li>
                    <li><code>500 Internal Server Error</code> - Failed to retrieve user data</li>
                </ul>
                
                <h6>Example Request (JavaScript)</h6>
                <pre><code>fetch('https://<?php echo $_SERVER['HTTP_HOST']; ?>/api/auth.php?route=me', {
  method: 'GET',
  headers: {
    'Authorization': 'Bearer YOUR_JWT_TOKEN'
  }
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));</code></pre>
            </div>
        </div>
        
        <!-- Using JWT Tokens -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Using JWT Tokens</h5>
            </div>
            <div class="card-body">
                <p>After a successful login or registration, you will receive a JWT token. This token should be included in the <code>Authorization</code> header for all authenticated requests.</p>
                
                <h6>JWT Token Format</h6>
                <pre><code>Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjMiLCJlbWFpbCI6InVzZXJAZXhhbXBsZS5jb20iLCJleHAiOjE2MTIzNjM2MDB9.signature</code></pre>
                
                <h6>Token Expiration</h6>
                <p>JWT tokens expire after a period of time (default: 1 hour). When a token expires, the user will need to log in again to get a new token.</p>
                
                <h6>Security Best Practices</h6>
                <ul>
                    <li>Store tokens securely (e.g., HttpOnly cookies or secure localStorage)</li>
                    <li>Implement token refresh mechanisms for better user experience</li>
                    <li>Include CSRF protection for cookie-based token storage</li>
                    <li>Use HTTPS to prevent token interception</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../includes/footer.php';
?>
