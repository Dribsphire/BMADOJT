<?php

/**
 * 403 Unauthorized Access Page
 * OJT Route - Unauthorized access error page
 */

require_once '../vendor/autoload.php';

use App\Middleware\AuthMiddleware;

// Start session
session_start();

// Initialize authentication
$authMiddleware = new AuthMiddleware();

// Get current user (if any)
$user = null;
if ($authMiddleware->check()) {
    $user = $authMiddleware->getCurrentUser();
}

// Log unauthorized access attempt
if ($user) {
    try {
        $pdo = \App\Utils\Database::getInstance();
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, 'unauthorized_access', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user->id,
            'Attempted to access unauthorized page: ' . $_SERVER['REQUEST_URI'],
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // Log error silently
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Denied | OJT Route</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --chmsu-green: #0ea539;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 100%;
            margin: 2rem;
        }
        
        .error-code {
            font-size: 6rem;
            font-weight: 700;
            color: var(--chmsu-green);
            margin-bottom: 1rem;
            line-height: 1;
        }
        
        .error-title {
            font-size: 2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
        }
        
        .error-message {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .btn-primary {
            background-color: var(--chmsu-green);
            border-color: var(--chmsu-green);
            padding: 0.75rem 2rem;
            font-weight: 500;
            border-radius: 10px;
        }
        
        .btn-primary:hover {
            background-color: #0d8a2f;
            border-color: #0d8a2f;
        }
        
        .btn-outline-primary {
            color: var(--chmsu-green);
            border-color: var(--chmsu-green);
            padding: 0.75rem 2rem;
            font-weight: 500;
            border-radius: 10px;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--chmsu-green);
            border-color: var(--chmsu-green);
        }
        
        .icon {
            font-size: 4rem;
            color: #ffc107;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="icon">
            <i class="bi bi-shield-exclamation"></i>
        </div>
        
        <div class="error-code">403</div>
        
        <h1 class="error-title">Access Denied</h1>
        
        <p class="error-message">
            You do not have permission to access this page. 
            <?php if ($user): ?>
                Your current role (<?= htmlspecialchars($user->role) ?>) does not have access to this resource.
            <?php else: ?>
                Please log in with appropriate credentials to access this page.
            <?php endif; ?>
        </p>
        
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <?php if ($user): ?>
                <a href="<?= $user->role === 'admin' ? 'admin/dashboard.php' : ($user->role === 'instructor' ? 'instructor/dashboard.php' : 'student/dashboard.php') ?>" 
                   class="btn btn-primary">
                    <i class="bi bi-house me-2"></i>Go to Dashboard
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Login
                </a>
            <?php endif; ?>
            
            <a href="javascript:history.back()" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-2"></i>Go Back
            </a>
        </div>
        
        <div class="mt-4">
            <small class="text-muted">
                If you believe this is an error, please contact your system administrator.
            </small>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>