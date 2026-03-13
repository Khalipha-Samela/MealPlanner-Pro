<?php
session_start();
require_once 'config/database.php';

$error   = '';
$success = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username         = trim($_POST['username']);
    $email            = trim($_POST['email']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $terms            = isset($_POST['terms']);

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!$terms) {
        $error = 'You must agree to the Terms of Service.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);

            if ($stmt->rowCount() > 0) {
                $error = 'Username or email already exists.';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$username, $email, $password_hash]);

                $_SESSION['user_id']  = $pdo->lastInsertId();
                $_SESSION['username'] = $username;
                header('Location: dashboard.php');
                exit();
            }
        } catch(PDOException $e) {
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — MealPlanner Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-background">
    <div class="auth-container">

        <div class="auth-header">
            <a href="index.php" class="auth-logo">
                <i class="fas fa-utensils"></i>
                MealPlanner Pro
            </a>
        </div>

        <div class="auth-card">
            <div class="auth-card-header">
                <h2>Create an account</h2>
                <p>Start your meal planning journey today</p>
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
                        <input type="text" id="username" name="username"
                               placeholder="Choose a username" required autocomplete="username"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                </div>

                <div class="auth-form-group">
                    <label for="email">Email Address</label>
                    <div class="auth-input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email"
                               placeholder="your@email.com" required autocomplete="email"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>

                <div class="auth-form-group">
                    <label for="password">Password</label>
                    <div class="auth-input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password"
                               placeholder="Create a password" required autocomplete="new-password">
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
                        <input type="password" id="confirm_password" name="confirm_password"
                               placeholder="Repeat your password" required autocomplete="new-password">
                        <button type="button" class="auth-password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="auth-password-match" id="passwordMatch"></div>
                </div>

                <div class="auth-terms">
                    <label class="auth-checkbox">
                        <input type="checkbox" id="terms" name="terms" required
                               <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
                        <span>I agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a></span>
                    </label>
                </div>

                <button type="submit" class="auth-btn auth-btn-primary auth-btn-block">
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
            const field = document.getElementById(fieldId);
            const icon  = field.parentElement.querySelector('.auth-password-toggle i');
            const show  = field.type === 'password';
            field.type  = show ? 'text' : 'password';
            icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        }

        document.getElementById('password')?.addEventListener('input', function () {
            const val = this.value;
            const el  = document.getElementById('passwordStrength');
            let score = 0;
            if (val.length >= 6)             score++;
            if (val.match(/[A-Z]/))          score++;
            if (val.match(/[0-9]/))          score++;
            if (val.match(/[^A-Za-z0-9]/))  score++;
            const labels = ['Very weak','Weak','Fair','Good','Strong'];
            const colors = ['#dc2626','#ef4444','#f59e0b','#10b981','#059669'];
            el.innerHTML = val ? `<span style="color:${colors[score]}">${labels[score]}</span>` : '';
        });

        document.getElementById('confirm_password')?.addEventListener('input', function () {
            const pass = document.getElementById('password').value;
            const el   = document.getElementById('passwordMatch');
            if (!this.value) { el.innerHTML = ''; return; }
            el.innerHTML = pass === this.value
                ? '<span style="color:#10b981"><i class="fas fa-check"></i> Passwords match</span>'
                : '<span style="color:#dc2626"><i class="fas fa-times"></i> Passwords do not match</span>';
        });
    </script>
</body>
</html>