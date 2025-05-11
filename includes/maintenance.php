<?php
$maintenance = require __DIR__ . '/../config/maintenance.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333;
        }
        .maintenance-container {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 90%;
        }
        .maintenance-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #6c757d;
        }
        h1 {
            color: #343a40;
            margin-bottom: 1rem;
        }
        p {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        .duration {
            font-size: 0.9rem;
            color: #868e96;
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">🔧</div>
        <h1>We'll be back soon!</h1>
        <p><?php echo htmlspecialchars($maintenance['message']); ?></p>
        <?php if (!empty($maintenance['estimated_duration'])): ?>
            <p class="duration">See you soon!</p>
        <?php endif; ?>
    </div>
</body>
</html> 