<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$message = '';
$error = '';

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);
    $dietary_preferences = trim($_POST['dietary_preferences']);
    $display_name = trim($_POST['display_name'] ?? $username);
    
    if (empty($email)) {
        $error = 'Email is required';
    } else {
        try {
            // Check if email already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'Email already in use';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET email = ?, dietary_preferences = ? WHERE id = ?");
                $stmt->execute([$email, $dietary_preferences, $user_id]);
                $message = 'Profile updated successfully!';
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch(PDOException $e) {
            $error = 'Failed to update profile: ' . $e->getMessage();
        }
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters';
    } elseif (!password_verify($current_password, $user['password_hash'])) {
        $error = 'Current password is incorrect';
    } else {
        try {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$new_password_hash, $user_id]);
            $message = 'Password changed successfully!';
        } catch(PDOException $e) {
            $error = 'Failed to change password: ' . $e->getMessage();
        }
    }
}

// Save preferences
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_preferences'])) {
    // In a real app, you would save these to a user_preferences table
    // For now, we'll just show a success message
    $message = 'Preferences saved successfully!';
}

// Get user statistics
$stats = [];
$stmt = $pdo->prepare("SELECT 
    (SELECT COUNT(*) FROM ingredients WHERE user_id = ?) as ingredient_count,
    (SELECT COUNT(*) FROM recipes WHERE user_id = ?) as recipe_count,
    (SELECT COUNT(*) FROM meal_plans WHERE user_id = ?) as total_meals,
    (SELECT COUNT(*) FROM meal_plans WHERE user_id = ? AND date >= CURRENT_DATE) as upcoming_meals,
    (SELECT COUNT(DISTINCT DATE(date)) FROM meal_plans WHERE user_id = ?) as days_planned,
    (SELECT COUNT(*) FROM grocery_lists WHERE user_id = ?) as grocery_lists");
$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent activity
$stmt = $pdo->prepare("SELECT 'ingredient' as type, name as title, created_at 
                       FROM ingredients WHERE user_id = ? 
                       UNION ALL 
                       SELECT 'recipe' as type, title, created_at 
                       FROM recipes WHERE user_id = ? 
                       ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id, $user_id]);
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - MealPlanner Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/profile.css">
</head>
<body>
    <div class="app">
        <!-- Mobile Header -->
        <header class="mobile-header">
            <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="mobile-logo">
                <i class="fas fa-utensils"></i>
                <span>MealPlanner Pro</span>
            </div>
            <button class="notifications-toggle" aria-label="Notifications">
                <i class="far fa-bell"></i>
            </button>
        </header>

        <!-- Overlay for mobile -->
        <div class="overlay" id="overlay"></div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-utensils"></i>
                    <span>MealPlanner Pro</span>
                </div>
                <button class="close-sidebar" id="closeSidebar" aria-label="Close menu">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="user-profile">
                <div class="user-avatar large">
                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
                    <span class="user-role">Free Member</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="kitchen.php">
                            <i class="fas fa-warehouse"></i>
                            <span>My Kitchen</span>
                        </a>
                    </li>
                    <li>
                        <a href="recipes.php">
                            <i class="fas fa-book"></i>
                            <span>Recipes</span>
                        </a>
                    </li>
                    <li>
                        <a href="mealplan.php">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Meal Plan</span>
                        </a>
                    </li>
                    <li>
                        <a href="grocery.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Grocery List</span>
                        </a>
                    </li>
                    <li class="nav-divider"></li>
                    <li class="active">
                        <a href="profile.php">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li>
                        <a href="logout.php" class="logout-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-wrapper">
                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Settings</h1>
                        <p class="page-subtitle">Manage your account preferences and profile information</p>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Profile Header -->
                <div class="profile-header-card">
                    <div class="profile-avatar-large">
                        <?php echo strtoupper(substr($username, 0, 2)); ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($username); ?></h2>
                        <p class="profile-email"><?php echo htmlspecialchars($user['email']); ?></p>
                        <div class="profile-meta">
                            <span><i class="fas fa-calendar-alt"></i> Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></span>
                            <span><i class="fas fa-clock"></i> Last active today</span>
                        </div>
                    </div>
                    <div class="profile-badge">
                        <span class="premium-badge">
                            <i class="fas fa-crown"></i> Free Member
                        </span>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                            <i class="fas fa-carrot"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Ingredients</span>
                            <span class="stat-value"><?php echo $stats['ingredient_count']; ?></span>
                            <span class="stat-trend">In your kitchen</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Recipes</span>
                            <span class="stat-value"><?php echo $stats['recipe_count']; ?></span>
                            <span class="stat-trend">Saved recipes</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Meals Planned</span>
                            <span class="stat-value"><?php echo $stats['total_meals']; ?></span>
                            <span class="stat-trend"><?php echo $stats['days_planned']; ?> days</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Grocery Lists</span>
                            <span class="stat-value"><?php echo $stats['grocery_lists']; ?></span>
                            <span class="stat-trend">Created</span>
                        </div>
                    </div>
                </div>

                <!-- Settings Grid -->
                <div class="settings-grid">
                    <!-- Profile Settings -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="settings-icon">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <h3>Profile Information</h3>
                            <p>Update your personal information</p>
                        </div>
                        <div class="settings-card-body">
                            <form method="POST" action="" class="settings-form">
                                <div class="form-group">
                                    <label for="username">Username</label>
                                    <div class="input-with-icon">
                                        <i class="fas fa-user"></i>
                                        <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled class="form-input">
                                    </div>
                                    <small class="form-hint">Username cannot be changed</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address <span class="required">*</span></label>
                                    <div class="input-with-icon">
                                        <i class="fas fa-envelope"></i>
                                        <input type="email" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required class="form-input">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="dietary_preferences">Dietary Preferences</label>
                                    <div class="input-with-icon">
                                        <i class="fas fa-leaf"></i>
                                        <textarea id="dietary_preferences" name="dietary_preferences" rows="3"
                                                  placeholder="e.g., Vegetarian, Gluten-Free, Nut Allergies, etc."
                                                  class="form-input"><?php echo htmlspecialchars($user['dietary_preferences'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary btn-block">
                                    <i class="fas fa-save"></i>
                                    Save Changes
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="settings-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h3>Security</h3>
                            <p>Change your password and security settings</p>
                        </div>
                        <div class="settings-card-body">
                            <form method="POST" action="" class="settings-form" id="passwordForm">
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <div class="password-input-wrapper">
                                        <div class="input-with-icon">
                                            <i class="fas fa-lock"></i>
                                            <input type="password" id="current_password" name="current_password" class="form-input" required>
                                        </div>
                                        <button type="button" class="password-toggle" onclick="togglePassword('current_password', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <div class="password-input-wrapper">
                                        <div class="input-with-icon">
                                            <i class="fas fa-key"></i>
                                            <input type="password" id="new_password" name="new_password" class="form-input" required>
                                        </div>
                                        <button type="button" class="password-toggle" onclick="togglePassword('new_password', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div id="passwordStrength" class="password-strength"></div>
                                    <small class="form-hint">Minimum 8 characters with mix of letters, numbers & symbols</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <div class="password-input-wrapper">
                                        <div class="input-with-icon">
                                            <i class="fas fa-check-circle"></i>
                                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                                        </div>
                                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div id="passwordMatch" class="password-match"></div>
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-primary btn-block">
                                    <i class="fas fa-key"></i>
                                    Change Password
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Preferences -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="settings-icon">
                                <i class="fas fa-sliders-h"></i>
                            </div>
                            <h3>Preferences</h3>
                            <p>Customize your app experience</p>
                        </div>
                        <div class="settings-card-body">
                            <form method="POST" action="" class="settings-form" id="preferencesForm">
                                <div class="preferences-group">
                                    <h4>Notifications</h4>
                                    <label class="toggle-item">
                                        <input type="checkbox" name="email_notifications" checked>
                                        <span class="toggle-label">Email notifications for expiring ingredients</span>
                                        <span class="toggle-slider"></span>
                                    </label>
                                    
                                    <label class="toggle-item">
                                        <input type="checkbox" name="weekly_meal_plan" checked>
                                        <span class="toggle-label">Weekly meal plan suggestions</span>
                                        <span class="toggle-slider"></span>
                                    </label>
                                    
                                    <label class="toggle-item">
                                        <input type="checkbox" name="shopping_reminders">
                                        <span class="toggle-label">Shopping day reminders</span>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <div class="preferences-group">
                                    <h4>Regional Settings</h4>
                                    <div class="form-group">
                                        <label for="week_start">Week starts on</label>
                                        <select id="week_start" name="week_start" class="form-input">
                                            <option value="monday">Monday</option>
                                            <option value="sunday">Sunday</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="measurement_system">Measurement system</label>
                                        <select id="measurement_system" name="measurement_system" class="form-input">
                                            <option value="metric">Metric (kg, g, L, mL)</option>
                                            <option value="imperial">Imperial (lb, oz, cups)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="currency">Currency</label>
                                        <select id="currency" name="currency" class="form-input">
                                            <option value="ZAR">ZAR (R)</option>
                                            <option value="USD">USD ($)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <button type="submit" name="save_preferences" class="btn btn-primary btn-block">
                                    <i class="fas fa-save"></i>
                                    Save Preferences
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Data Management -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="settings-icon">
                                <i class="fas fa-database"></i>
                            </div>
                            <h3>Data Management</h3>
                            <p>Export, import, or delete your data</p>
                        </div>
                        <div class="settings-card-body">
                            <div class="data-actions">
                                <button class="data-action-btn" onclick="exportData()">
                                    <i class="fas fa-download"></i>
                                    <div class="data-action-info">
                                        <span class="data-action-title">Export Data</span>
                                        <span class="data-action-desc">Download all your information</span>
                                    </div>
                                </button>
                                
                                <button class="data-action-btn" onclick="importData()">
                                    <i class="fas fa-upload"></i>
                                    <div class="data-action-info">
                                        <span class="data-action-title">Import Data</span>
                                        <span class="data-action-desc">Restore from backup</span>
                                    </div>
                                </button>
                            </div>
                            
                            <div class="danger-zone">
                                <div class="danger-zone-header">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <h4>Danger Zone</h4>
                                </div>
                                <p>Permanently delete your account and all associated data. This action cannot be undone.</p>
                                <button class="btn btn-danger btn-block" onclick="showDeleteModal()">
                                    <i class="fas fa-trash"></i>
                                    Delete Account
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="settings-card recent-activity-card">
                        <div class="settings-card-header">
                            <div class="settings-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <h3>Recent Activity</h3>
                            <p>Your latest actions</p>
                        </div>
                        <div class="settings-card-body">
                            <?php if (!empty($recent_activity)): ?>
                                <div class="activity-timeline">
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon" style="background: <?php 
                                                echo $activity['type'] == 'ingredient' ? '#f0fdf4' : '#fef3c7'; 
                                            ?>;">
                                                <i class="fas fa-<?php echo $activity['type'] == 'ingredient' ? 'carrot' : 'book'; ?>" 
                                                   style="color: <?php echo $activity['type'] == 'ingredient' ? '#10b981' : '#f59e0b'; ?>;"></i>
                                            </div>
                                            <div class="activity-details">
                                                <span class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></span>
                                                <span class="activity-time"><?php echo timeAgo($activity['created_at']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No recent activity</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Connected Accounts -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="settings-icon">
                                <i class="fas fa-link"></i>
                            </div>
                            <h3>Connected Accounts</h3>
                            <p>Link your accounts for easier access</p>
                        </div>
                        <div class="settings-card-body">
                            <div class="connected-accounts">
                                <button class="account-connect-btn google" onclick="connectAccount('google')">
                                    <i class="fab fa-google"></i>
                                    <span>Connect Google</span>
                                    <i class="fas fa-plus-circle"></i>
                                </button>
                                
                                <button class="account-connect-btn facebook" onclick="connectAccount('facebook')">
                                    <i class="fab fa-facebook-f"></i>
                                    <span>Connect Facebook</span>
                                    <i class="fas fa-plus-circle"></i>
                                </button>
                                
                                <button class="account-connect-btn apple" onclick="connectAccount('apple')">
                                    <i class="fab fa-apple"></i>
                                    <span>Connect Apple</span>
                                    <i class="fas fa-plus-circle"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Account Modal -->
    <div id="deleteModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Delete Account</h2>
                <button class="modal-close" onclick="hideDeleteModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="warning-message">
                    <div class="warning-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h3>Are you absolutely sure?</h3>
                    <p>This action cannot be undone. This will permanently delete:</p>
                    <ul>
                        <li><i class="fas fa-user"></i> Your account</li>
                        <li><i class="fas fa-carrot"></i> All your ingredients</li>
                        <li><i class="fas fa-book"></i> All your recipes</li>
                        <li><i class="fas fa-calendar-alt"></i> All your meal plans</li>
                        <li><i class="fas fa-shopping-cart"></i> All your grocery lists</li>
                    </ul>
                    <div class="confirm-box">
                        <p>Please type <strong>DELETE MY ACCOUNT</strong> to confirm:</p>
                        <input type="text" id="confirmDelete" placeholder="DELETE MY ACCOUNT" class="form-input">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="hideDeleteModal()">
                    Cancel
                </button>
                <button type="button" class="btn btn-danger" id="deleteAccountBtn" onclick="deleteAccount()" disabled>
                    <i class="fas fa-trash"></i>
                    Delete Account
                </button>
            </div>
        </div>
    </div>

    <script src="js/profile.js"></script>
    <script>
        // Mobile menu functionality
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.add('mobile-visible');
            document.getElementById('overlay').classList.add('active');
        });

        document.getElementById('closeSidebar')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('mobile-visible');
            document.getElementById('overlay').classList.remove('active');
        });

        document.getElementById('overlay')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('mobile-visible');
            this.classList.remove('active');
        });

        // Delete modal confirmation
        const confirmInput = document.getElementById('confirmDelete');
        const deleteBtn = document.getElementById('deleteAccountBtn');

        if (confirmInput && deleteBtn) {
            confirmInput.addEventListener('input', function() {
                deleteBtn.disabled = this.value !== 'DELETE MY ACCOUNT';
            });
        }

        // Password strength checker
        const passwordInput = document.getElementById('new_password');
        const confirmInput2 = document.getElementById('confirm_password');
        const passwordStrength = document.getElementById('passwordStrength');
        const passwordMatch = document.getElementById('passwordMatch');

        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const strength = checkPasswordStrength(this.value);
                showPasswordStrength(strength);
            });
        }

        if (confirmInput2 && passwordInput) {
            confirmInput2.addEventListener('input', function() {
                if (this.value !== passwordInput.value) {
                    passwordMatch.innerHTML = '<span class="match-error"><i class="fas fa-times"></i> Passwords do not match</span>';
                } else {
                    passwordMatch.innerHTML = '<span class="match-success"><i class="fas fa-check"></i> Passwords match</span>';
                }
            });
        }

        function checkPasswordStrength(password) {
            let score = 0;
            
            if (password.length >= 8) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            
            return score;
        }

        function showPasswordStrength(strength) {
            const levels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
            const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#10b981'];
            
            let html = `
                <div class="strength-bars">
                    ${[0,1,2,3,4].map(i => `
                        <div class="strength-bar" style="background-color: ${i < strength ? colors[strength] : '#e5e7eb'};"></div>
                    `).join('')}
                </div>
                <span class="strength-text" style="color: ${colors[strength]};">${levels[strength]}</span>
            `;
            
            passwordStrength.innerHTML = html;
        }

        // Password toggle function
        function togglePassword(inputId, button) {
            const passwordInput = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Modal functions
        function showDeleteModal() {
            document.getElementById('deleteModal').style.display = 'block';
            document.getElementById('confirmDelete').value = '';
            document.getElementById('deleteAccountBtn').disabled = true;
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function deleteAccount() {
            if (confirm('This action cannot be undone. Are you absolutely sure?')) {
                alert('Account deletion would be processed here. This is disabled in the demo.');
                hideDeleteModal();
            }
        }

        // Data management functions
        function exportData() {
            const data = {
                user: {
                    username: '<?php echo htmlspecialchars($_SESSION["username"]); ?>',
                    email: '<?php echo htmlspecialchars($user["email"]); ?>',
                    member_since: '<?php echo $user["created_at"]; ?>'
                },
                stats: {
                    ingredients: <?php echo $stats['ingredient_count']; ?>,
                    recipes: <?php echo $stats['recipe_count']; ?>,
                    meals: <?php echo $stats['total_meals']; ?>
                },
                export_date: new Date().toISOString()
            };
            
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `mealplanner_export_${new Date().toISOString().slice(0,10)}.json`;
            a.click();
            URL.revokeObjectURL(url);
            
            showNotification('Data exported successfully!', 'success');
        }

        function importData() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            
            input.onchange = function(e) {
                const file = e.target.files[0];
                const reader = new FileReader();
                
                reader.onload = function(event) {
                    try {
                        JSON.parse(event.target.result);
                        showNotification('Data imported successfully!', 'success');
                    } catch (error) {
                        showNotification('Invalid file format', 'error');
                    }
                };
                
                reader.readAsText(file);
            };
            
            input.click();
        }

        function connectAccount(provider) {
            showNotification(`Connecting to ${provider}...`, 'info');
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                ${message}
            `;
            
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.minWidth = '300px';
            notification.style.animation = 'slideInRight 0.3s ease';
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideDeleteModal();
            }
        });
    </script>
</body>
</html>

<?php
// Helper function for time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}
?>