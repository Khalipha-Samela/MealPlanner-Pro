<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

// Create remember_me_tokens table if it doesn't exist (for login page)
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
    // Table might already exist, continue silently
    error_log('Remember me table creation: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $terms = isset($_POST['terms']) ? true : false;
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!$terms) {
        $error = 'You must agree to the Terms of Service';
    } else {
        try {
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'Username or email already exists';
            } else {
                // Create new user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$username, $email, $password_hash]);
                
                $user_id = $pdo->lastInsertId();
                
                // Log the user in immediately
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit();
            }
        } catch(PDOException $e) {
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MealPlanner Pro</title>
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
                <h2>Create an account</h2>
                <p>Get started with your meal planning journey</p>
            </div>
            
            <?php if ($error): ?>
                <div class="auth-alert auth-alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="auth-alert auth-alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <div class="auth-form-group">
                    <label for="username">Username</label>
                    <div class="auth-input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" placeholder="Choose a username" required 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                </div>
                
                <div class="auth-form-group">
                    <label for="email">Email Address</label>
                    <div class="auth-input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
                
                <div class="auth-form-group">
                    <label for="password">Password</label>
                    <div class="auth-input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Create a password" required>
                        <button type="button" class="auth-password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="auth-password-strength" id="passwordStrength"></div>
                </div>
                
                <div class="auth-form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="auth-input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                        <button type="button" class="auth-password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="auth-password-match" id="passwordMatch"></div>
                </div>
                
                <div class="auth-terms">
                    <label class="auth-checkbox">
                        <input type="checkbox" id="terms" name="terms" required <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
                        <span>I agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a></span>
                    </label>
                </div>
                
                <button type="submit" class="auth-btn auth-btn-primary auth-btn-block">
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Sign in</a></p>
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
        
        // Password strength indicator
        document.getElementById('password')?.addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthIndicator = document.getElementById('passwordStrength');
            
            let strength = 0;
            let message = '';
            let color = '#dc2626';
            
            if (password.length >= 6) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^A-Za-z0-9]/)) strength++;
            
            const levels = ['Very weak', 'Weak', 'Fair', 'Good', 'Strong'];
            const colors = ['#dc2626', '#dc2626', '#f59e0b', '#10b981', '#059669'];
            
            message = levels[strength];
            color = colors[strength];
            
            strengthIndicator.innerHTML = `<span style="color: ${color}">${message}</span>`;
        });
        
        // Password match checker
        document.getElementById('confirm_password')?.addEventListener('input', function(e) {
            const password = document.getElementById('password').value;
            const confirm = e.target.value;
            const matchIndicator = document.getElementById('passwordMatch');
            
            if (confirm.length === 0) {
                matchIndicator.innerHTML = '';
            } else if (password === confirm) {
                matchIndicator.innerHTML = '<span style="color: #10b981"><i class="fas fa-check"></i> Passwords match</span>';
            } else {
                matchIndicator.innerHTML = '<span style="color: #dc2626"><i class="fas fa-times"></i> Passwords do not match</span>';
            }
        });
    </script>
</body>
</html>