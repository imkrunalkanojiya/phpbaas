<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$currentUser = null;

// Get current user information if logged in
if ($isLoggedIn) {
    require_once __DIR__ . '/../config/database.php';
    
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, email, name, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error getting current user: ' . $e->getMessage());
    }
}

// Get the current page
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<!-- Enhanced SEO <head> for PHPBaaS -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHPBaaS - PHP Backend as a Service</title>
    <link rel="icon" href="/assets/img/cloud.png" type="image/x-icon">
    
    <meta name="description" content="PHPBaaS - PHP Backend as a Service">
    <meta name="keywords" content="PHP, Backend, Service, API, Database, Files, Authentication, Keys">
    <meta name="author" content="Your Name">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    <meta name="bingbot" content="index, follow">
    <meta name="yandexbot" content="index, follow">
    <meta name="msnbot" content="index, follow">
    <meta name="slurp" content="index, follow">

      <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://phpbaas.com<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES); ?>">
    <meta property="og:title" content="PHPBaaS – Powerful PHP Backend as a Service">
    <meta property="og:description" content="Fully managed PHP backend platform: APIs, databases, file storage, authentication. Deploy in minutes!">
    <meta property="og:image" content="https://phpbaas.com/assets/img/cloud.png">
    
      <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://phpbaas.com<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES); ?>">
    <meta name="twitter:title" content="PHPBaaS – Powerful PHP Backend as a Service">
    <meta name="twitter:description" content="Secure, scalable PHP backend hosting with APIs, DB, auth & more. Start for free.">
    <meta name="twitter:image" content="https://phpbaas.com/assets/img/cloud.png">
    <meta property="og:image" content="/assets/img/cloud.png">
    
    <link rel="canonical" href="https://phpbaas.com/<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

     <!-- Stylesheets -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" integrity="sha384-..." crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha384-..." crossorigin="anonymous">
    
     <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    
    <script type="application/ld+json">
      {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "url": "https://phpbaas.com/",
        "name": "PHPBaaS",
        "potentialAction": {
          "@type": "SearchAction",
          "target": "https://phpbaas.com/search?q={search_term_string}",
          "query-input": "required name=search_term_string"
        }
      }
     </script>
    
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-55PBJ5DF1V"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
    
      gtag('config', 'G-55PBJ5DF1V');
    </script>
</head>

<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="/dashboard/index.php">
                    <i class="fas fa-database me-2"></i>PHPBaaS
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <?php if ($isLoggedIn): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>" href="/dashboard/index.php">
                                    <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage == 'projects.php' ? 'active' : ''; ?>" href="/dashboard/projects.php">
                                    <i class="fas fa-project-diagram me-1"></i> Projects
                                </a>
                            </li>
                            <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage == 'users.php' ? 'active' : ''; ?>" href="/dashboard/users.php">
                                    <i class="fas fa-users me-1"></i> Users
                                </a>
                            </li>
                            <?php endif; ?>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/documentation/index.php">
                                <i class="fas fa-book me-1"></i> Documentation
                            </a>
                        </li>
                    </ul>
                    <div class="d-flex">
                        <?php if ($isLoggedIn): ?>
                            <div class="dropdown">
                                <button class="btn btn-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($currentUser['name']); ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li><a class="dropdown-item" href="/dashboard/settings.php"><i class="fas fa-cog me-1"></i> Settings</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="/dashboard/index.php" method="post">
                                            <input type="hidden" name="action" value="logout">
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="fas fa-sign-out-alt me-1"></i> Logout
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <a href="/dashboard/index.php" class="btn btn-light">
                                <i class="fas fa-sign-in-alt me-1"></i> Login
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>
    <main class="container my-4">
