<?php
session_start();
require_once 'config/database.php';

$error = '';

// Create remember_me_tokens table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS remember_me_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_token (token),
            INDEX idx_expires (expires_at),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch(PDOException $e) {
    error_log('Remember me table creation: ' . $e->getMessage());
}

// Check for remember me cookie FIRST (before any POST handling)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token']) && !empty($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    try {
        // Verify the token
        $stmt = $pdo->prepare("
            SELECT rt.user_id, u.username 
            FROM remember_me_tokens rt
            JOIN users u ON rt.user_id = u.id
            WHERE rt.token = ? AND rt.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Set session
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['username'] = $result['username'];
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit();
        } else {
            // Invalid or expired token, clear the cookie
            setcookie('remember_token', '', time() - 3600, '/');
        }
    } catch(PDOException $e) {
        error_log('Remember me error: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // Handle remember me
                if ($remember) {
                    // Generate a secure random token
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    // Delete any existing tokens for this user
                    $stmt = $pdo->prepare("DELETE FROM remember_me_tokens WHERE user_id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // Insert new token
                    $stmt = $pdo->prepare("INSERT INTO remember_me_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                    $result = $stmt->execute([$user['id'], $token, $expires]);
                    
                    if ($result) {
                        // Set cookie for 30 days - SIMPLE approach that definitely works
                        setcookie('remember_token', $token, time() + (86400 * 30), '/');
                        
                        // Debug - you can check if cookie is set
                        error_log('Remember me cookie set for user: ' . $user['username'] . ' with token: ' . substr($token, 0, 10) . '...');
                    }
                }
                
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Invalid username/email or password';
            }
        } catch(PDOException $e) {
            $error = 'Login failed: ' . $e->getMessage();
            error_log('Login error: ' . $e->getMessage());
        }
    }
}

// Clean up expired tokens occasionally (10% chance)
if (rand(1, 10) === 1) {
    try {
        $pdo->exec("DELETE FROM remember_me_tokens WHERE expires_at < NOW()");
    } catch(PDOException $e) {
        error_log('Token cleanup error: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MealPlanner Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-background">
    <div class="auth-container">
        <div class="auth-header">
            <a href="index.php" class="auth-logo">
                <i class="fas fa-utensils"></i>
                <span>MealPlanner Pro</span>
            </a>
        </div>
        
        <div class="auth-card">
            <div class="auth-card-header">
                <h2>Welcome back</h2>
                <p>Sign in to your account to continue</p>
            </div>
            
            <?php if ($error): ?>
                <div class="auth-alert auth-alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <div class="auth-form-group">
                    <label for="username">Username or Email</label>
                    <div class="auth-input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" placeholder="Enter your username or email" required
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                </div>
                
                <div class="auth-form-group">
                    <label for="password">Password</label>
                    <div class="auth-input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" class="auth-password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="auth-form-options">
                    <label class="auth-checkbox">
                        <input type="checkbox" name="remember" value="1" <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                        <span>Remember me for 30 days</span>
                    </label>
                    <a href="forgot-password.php" class="auth-forgot-link">Forgot password?</a>
                </div>
                
                <button type="submit" class="auth-btn auth-btn-primary auth-btn-block">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php">Create an account</a></p>
                <p class="auth-back-link"><a href="index.php"><i class="fas fa-arrow-left"></i> Back to home</a></p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleIcon = passwordField.parentElement.querySelector('.auth-password-toggle i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>