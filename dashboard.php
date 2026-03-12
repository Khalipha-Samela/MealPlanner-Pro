<?php
session_start();
require_once 'config/database.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get user's ingredient count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM ingredients WHERE user_id = ?");
$stmt->execute([$user_id]);
$ingredient_count = $stmt->fetchColumn();

// Get user's recipe count 
$stmt = $pdo->prepare("SELECT COUNT(*) FROM recipes WHERE user_id = ?");
$stmt->execute([$user_id]);
$recipe_count = $stmt->fetchColumn();

// Get upcoming expiring ingredients
$stmt = $pdo->prepare("SELECT name, expiration_date FROM ingredients 
                      WHERE user_id = ? AND expiration_date IS NOT NULL 
                      AND expiration_date >= CURDATE() 
                      ORDER BY expiration_date ASC LIMIT 5");
$stmt->execute([$user_id]);
$expiring_ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's meal plan
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT mp.*, r.title FROM meal_plans mp 
                      LEFT JOIN recipes r ON mp.recipe_id = r.id 
                      WHERE mp.user_id = ? AND mp.date = ? 
                      ORDER BY FIELD(mp.meal_type, 'breakfast', 'lunch', 'dinner', 'snack')");
$stmt->execute([$user_id, $today]);
$today_meals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MealPlanner Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
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
                <div class="user-avatar">
                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
                    <span class="user-role">Free Member</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li class="active">
                        <a href="dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="kitchen.php">
                            <i class="fas fa-warehouse"></i>
                            <span>My Kitchen</span>
                            <?php if ($ingredient_count > 0): ?>
                                <span class="nav-badge"><?php echo $ingredient_count; ?></span>
                            <?php endif; ?>
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
                    <li>
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
                        <h1 class="page-title">Dashboard</h1>
                        <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($username); ?>! Here's what's happening with your meal plan today.</p>
                    </div>
                    <a href="mealplan.php?generate=true" class="btn btn-primary">
                        <i class="fas fa-magic"></i>
                        <span>Generate Meal Plan</span>
                    </a>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                            <i class="fas fa-carrot"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Ingredients</span>
                            <span class="stat-value"><?php echo $ingredient_count; ?></span>
                            <a href="kitchen.php" class="stat-link">View all <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Recipes</span>
                            <span class="stat-value"><?php echo $recipe_count; ?></span>
                            <a href="recipes.php" class="stat-link">View all <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Today's Meals</span>
                            <span class="stat-value"><?php echo count($today_meals); ?></span>
                            <a href="mealplan.php" class="stat-link">View plan <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Expiring Soon</span>
                            <span class="stat-value"><?php echo count($expiring_ingredients); ?></span>
                            <a href="kitchen.php?filter=expiring" class="stat-link">View all <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Main Grid -->
                <div class="dashboard-grid">
                    <!-- Today's Meals -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-sun" style="color: #10b981;"></i>
                                <h2>Today's Meals</h2>
                            </div>
                            <a href="mealplan.php" class="btn btn-outline btn-sm">
                                Full Week
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (count($today_meals) > 0): ?>
                                <div class="meals-list">
                                    <?php 
                                    $meal_icons = [
                                        'breakfast' => 'fa-coffee',
                                        'lunch' => 'fa-hamburger',
                                        'dinner' => 'fa-moon',
                                        'snack' => 'fa-cookie'
                                    ];
                                    ?>
                                    <?php foreach ($today_meals as $meal): ?>
                                        <div class="meal-item">
                                            <div class="meal-time">
                                                <i class="fas <?php echo $meal_icons[$meal['meal_type']] ?? 'fa-utensils'; ?>" style="color: #10b981;"></i>
                                                <span><?php echo ucfirst($meal['meal_type']); ?></span>
                                            </div>
                                            <div class="meal-name">
                                                <?php echo $meal['recipe_id'] ? htmlspecialchars($meal['title']) : htmlspecialchars($meal['custom_meal']); ?>
                                            </div>
                                            <button class="meal-action" title="More options">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-utensils" style="color: #10b981;"></i>
                                    </div>
                                    <h3>No meals planned</h3>
                                    <p>Start planning your meals for today</p>
                                    <a href="mealplan.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus"></i>
                                        Add meals
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Expiring Soon -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-clock" style="color: #10b981;"></i>
                                <h2>Expiring Soon</h2>
                            </div>
                            <a href="kitchen.php?filter=expiring" class="btn btn-outline btn-sm">
                                View All
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (count($expiring_ingredients) > 0): ?>
                                <div class="expiring-list">
                                    <?php foreach ($expiring_ingredients as $ingredient): 
                                        $exp_date = strtotime($ingredient['expiration_date']);
                                        $days_left = ceil(($exp_date - time()) / 86400);
                                        $progress = max(0, min(100, (7 - $days_left) * 14));
                                    ?>
                                        <div class="expiring-item">
                                            <div class="expiring-info">
                                                <span class="ingredient-name"><?php echo htmlspecialchars($ingredient['name']); ?></span>
                                                <span class="expiring-date">
                                                    <?php if ($days_left <= 3): ?>
                                                        <span class="badge badge-warning"><?php echo $days_left; ?> days left</span>
                                                    <?php else: ?>
                                                        <?php echo date('M d', $exp_date); ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div class="expiring-progress">
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $progress; ?>%; background: linear-gradient(90deg, #f59e0b, #ef4444);"></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-check-circle" style="color: #10b981;"></i>
                                    </div>
                                    <h3>All fresh!</h3>
                                    <p>No ingredients expiring soon</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-bolt" style="color: #10b981;"></i>
                                <h2>Quick Actions</h2>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions-grid">
                                <a href="kitchen.php?add=true" class="quick-action-item">
                                    <div class="quick-action-icon" style="background: #f0fdf4; color: #10b981;">
                                        <i class="fas fa-carrot"></i>
                                    </div>
                                    <div class="quick-action-content">
                                        <span class="quick-action-title">Add Ingredient</span>
                                        <span class="quick-action-desc">Track what's in your kitchen</span>
                                    </div>
                                    <i class="fas fa-chevron-right" style="color: #10b981;"></i>
                                </a>
                                
                                <a href="recipes.php?add=true" class="quick-action-item">
                                    <div class="quick-action-icon" style="background: #f0fdf4; color: #10b981;">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <div class="quick-action-content">
                                        <span class="quick-action-title">Add Recipe</span>
                                        <span class="quick-action-desc">Save a new recipe</span>
                                    </div>
                                    <i class="fas fa-chevron-right" style="color: #10b981;"></i>
                                </a>
                                
                                <a href="grocery.php?new=true" class="quick-action-item">
                                    <div class="quick-action-icon" style="background: #f0fdf4; color: #10b981;">
                                        <i class="fas fa-shopping-bag"></i>
                                    </div>
                                    <div class="quick-action-content">
                                        <span class="quick-action-title">Grocery List</span>
                                        <span class="quick-action-desc">Create a shopping list</span>
                                    </div>
                                    <i class="fas fa-chevron-right" style="color: #10b981;"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Recipe Suggestions -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-lightbulb" style="color: #10b981;"></i>
                                <h2>Recipe Suggestions</h2>
                            </div>
                            <button class="btn btn-outline btn-sm" id="refreshSuggestions">
                                <i class="fas fa-sync-alt"></i>
                                Refresh
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="suggestions-list" id="suggestionsList">
                                <!-- Will be populated by JS -->
                                <div class="suggestion-placeholder">
                                    <div class="placeholder-item"></div>
                                    <div class="placeholder-item"></div>
                                    <div class="placeholder-item"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="js/dashboard.js"></script>
</body>
</html>