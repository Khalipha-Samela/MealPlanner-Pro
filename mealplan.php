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

// Get current week dates
$week_start = isset($_GET['week']) ? $_GET['week'] : date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($week_start)));

$week_dates = [];
for ($i = 0; $i < 7; $i++) {
    $week_dates[] = date('Y-m-d', strtotime($week_start . " +$i days"));
}

// Get meal plan for the week
$meal_plans = [];

$stmt = $pdo->prepare("SELECT mp.*, r.title as recipe_title, r.prep_time, r.cook_time, r.category, r.image_url
                      FROM meal_plans mp 
                      LEFT JOIN recipes r ON mp.recipe_id = r.id 
                      WHERE mp.user_id = ? AND mp.date BETWEEN ? AND ? 
                      ORDER BY mp.date,
                      CASE mp.meal_type
                          WHEN 'breakfast' THEN 1
                          WHEN 'lunch' THEN 2
                          WHEN 'dinner' THEN 3
                          WHEN 'snack' THEN 4
                          ELSE 5
                      END
                    ");

$stmt->execute([$user_id, $week_dates[0], $week_dates[6]]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $plan) {
    $meal_plans[$plan['date']][$plan['meal_type']] = $plan;
}

// Add meal to plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_meal'])) {

    $date = $_POST['date'];
    $meal_type = $_POST['meal_type'];
    $recipe_id = !empty($_POST['recipe_id']) ? $_POST['recipe_id'] : null;
    $custom_meal = trim($_POST['custom_meal']) ?: null;

    if (!$recipe_id && !$custom_meal) {
        $error = 'Please select a recipe or enter a custom meal';
    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT id FROM meal_plans 
                WHERE user_id = ? AND date = ? AND meal_type = ?
            ");

            $stmt->execute([$user_id, $date, $meal_type]);

            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {

                $stmt = $pdo->prepare("
                    UPDATE meal_plans 
                    SET recipe_id = ?, custom_meal = ?
                    WHERE user_id = ? AND date = ? AND meal_type = ?
                ");

                $stmt->execute([$recipe_id, $custom_meal, $user_id, $date, $meal_type]);

                $message = 'Meal updated successfully!';

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO meal_plans (user_id, date, meal_type, recipe_id, custom_meal)
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->execute([$user_id, $date, $meal_type, $recipe_id, $custom_meal]);

                $message = 'Meal added to plan!';
            }

        } catch(PDOException $e) {
            $error = 'Failed to add meal: ' . $e->getMessage();
        }
    }
}

// Remove meal
if (isset($_GET['remove'])) {

    $meal_id = $_GET['remove'];

    try {

        $stmt = $pdo->prepare("
            DELETE FROM meal_plans 
            WHERE id = ? AND user_id = ?
        ");

        $stmt->execute([$meal_id, $user_id]);

        $message = 'Meal removed from plan!';

    } catch(PDOException $e) {
        $error = 'Failed to remove meal: ' . $e->getMessage();
    }
}

// Generate meal plan from kitchen ingredients
if (isset($_GET['generate'])) {

    $stmt = $pdo->prepare("
        SELECT name FROM ingredients 
        WHERE user_id = ?
    ");

    $stmt->execute([$user_id]);

    $user_ingredients = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($user_ingredients)) {

        $placeholders = implode(',', array_fill(0, count($user_ingredients), '?'));

        $stmt = $pdo->prepare("
            SELECT DISTINCT r.*
            FROM recipes r
            JOIN recipe_ingredients ri ON r.id = ri.recipe_id
            WHERE r.user_id = ?
            AND ri.ingredient_name IN ($placeholders)
            LIMIT 10
        ");

        $params = array_merge([$user_id], $user_ingredients);

        $stmt->execute($params);

        $suggested_recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $meal_types = ['breakfast','lunch','dinner','snack'];

        $count = 0;

        foreach ($week_dates as $date) {

            foreach ($meal_types as $meal_type) {

                if (!isset($meal_plans[$date][$meal_type]) && $count < count($suggested_recipes)) {

                    $recipe = $suggested_recipes[$count];

                    $stmt = $pdo->prepare("
                        INSERT INTO meal_plans (user_id, date, meal_type, recipe_id)
                        VALUES (?, ?, ?, ?)
                    ");

                    $stmt->execute([$user_id, $date, $meal_type, $recipe['id']]);

                    $count++;

                    if ($count >= 7) break;
                }
            }
        }

        $message = "Generated meal plan with $count recipes based on your kitchen!";

        header('Location: mealplan.php?week=' . $week_start);
        exit();

    } else {

        $error = 'Add ingredients to your kitchen first!';
    }
}

// Get recipes for dropdown
$stmt = $pdo->prepare("
    SELECT id, title, category, prep_time, cook_time
    FROM recipes
    WHERE user_id = ?
    ORDER BY title
");

$stmt->execute([$user_id]);

$recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Quick stats
$total_meals = count($results);
$total_time = calculateTotalTime($results, $pdo);
$unique_ingredients = count(getWeeklyIngredients($results, $pdo));
$planned_days = count(array_unique(array_column($results, 'date')));

// Recipes used
$used_recipe_ids = array_filter(array_column($results, 'recipe_id'));
$cooked_count = count($used_recipe_ids);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Plan - MealPlanner Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/mealplan.css">
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
                    <li class="active">
                        <a href="mealplan.php">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Meal Plan</span>
                            <?php if ($total_meals > 0): ?>
                                <span class="nav-badge"><?php echo $total_meals; ?></span>
                            <?php endif; ?>
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
                        <h1 class="page-title">Meal Plan</h1>
                        <p class="page-subtitle">Plan your weekly meals and generate grocery lists</p>
                    </div>
                    <div class="header-actions">
                        <a href="grocery.php?generate=true&week=<?php echo $week_start; ?>" class="btn btn-outline">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Generate List</span>
                        </a>
                        <button class="btn btn-primary" onclick="generateMealPlan()">
                            <i class="fas fa-magic"></i>
                            <span>Auto-Generate</span>
                        </button>
                    </div>
                </div>

                <!-- Week Navigation -->
                <div class="week-navigation-card">
                    <div class="week-nav-content">
                        <a href="mealplan.php?week=<?php echo date('Y-m-d', strtotime($week_start . ' -7 days')); ?>" 
                           class="nav-arrow">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        
                        <div class="week-display">
                            <span class="week-range">
                                <?php echo date('M d', strtotime($week_dates[0])); ?> - 
                                <?php echo date('M d, Y', strtotime($week_dates[6])); ?>
                            </span>
                            <span class="week-badge">Week <?php echo date('W', strtotime($week_start)); ?></span>
                        </div>
                        
                        <a href="mealplan.php?week=<?php echo date('Y-m-d', strtotime($week_start . ' +7 days')); ?>" 
                           class="nav-arrow">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        
                        <a href="mealplan.php" class="btn-today">
                            <i class="fas fa-calendar-day"></i>
                            Today
                        </a>
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

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Meals Planned</span>
                            <span class="stat-value"><?php echo $total_meals; ?></span>
                            <span class="stat-trend"><?php echo $planned_days; ?> days this week</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Total Time</span>
                            <span class="stat-value"><?php echo $total_time; ?> min</span>
                            <span class="stat-trend">≈ <?php echo round($total_time / 60, 1); ?> hours</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                            <i class="fas fa-carrot"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Unique Ingredients</span>
                            <span class="stat-value"><?php echo $unique_ingredients; ?></span>
                            <span class="stat-trend">For this week's meals</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                            <i class="fas fa-fire"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Recipes Used</span>
                            <span class="stat-value"><?php echo $cooked_count; ?></span>
                            <span class="stat-trend">Different recipes</span>
                        </div>
                    </div>
                </div>

                <!-- Meal Plan Calendar -->
                <div class="meal-plan-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-calendar-week" style="color: #10b981;"></i>
                            <h2>Weekly Meal Plan</h2>
                        </div>
                        <div class="meal-plan-actions">
                            <button class="btn btn-outline btn-sm" onclick="copyMealPlan()">
                                <i class="fas fa-copy"></i>
                                Copy
                            </button>
                            <button class="btn btn-outline btn-sm" onclick="printMealPlan()">
                                <i class="fas fa-print"></i>
                                Print
                            </button>
                        </div>
                    </div>
                    
                    <div class="meal-plan-calendar">
                        <!-- Calendar Header -->
                        <div class="calendar-header">
                            <div class="time-header"></div>
                            <?php foreach ($week_dates as $date): 
                                $is_today = date('Y-m-d') == $date;
                                $day_name = date('D', strtotime($date));
                                $day_date = date('d', strtotime($date));
                                $month = date('M', strtotime($date));
                            ?>
                                <div class="day-header <?php echo $is_today ? 'today' : ''; ?>">
                                    <div class="day-name"><?php echo $day_name; ?></div>
                                    <div class="day-number"><?php echo $day_date; ?></div>
                                    <div class="day-month"><?php echo $month; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Meal Rows -->
                        <?php 
                        $meal_types = ['breakfast', 'lunch', 'dinner', 'snack'];
                        $meal_icons = [
                            'breakfast' => 'fa-coffee',
                            'lunch' => 'fa-hamburger',
                            'dinner' => 'fa-moon',
                            'snack' => 'fa-cookie'
                        ];
                        $meal_labels = ['Breakfast', 'Lunch', 'Dinner', 'Snack'];
                        $meal_colors = [
                            'breakfast' => '#f59e0b',
                            'lunch' => '#10b981',
                            'dinner' => '#3b82f6',
                            'snack' => '#8b5cf6'
                        ];
                        ?>
                        
                        <?php foreach ($meal_types as $index => $meal_type): ?>
                            <div class="calendar-row">
                                <div class="time-slot">
                                    <i class="fas <?php echo $meal_icons[$meal_type]; ?>" style="color: <?php echo $meal_colors[$meal_type]; ?>;"></i>
                                    <span class="meal-label"><?php echo $meal_labels[$index]; ?></span>
                                </div>
                                
                                <?php foreach ($week_dates as $date): 
                                    $is_today = date('Y-m-d') == $date;
                                    $has_meal = isset($meal_plans[$date][$meal_type]);
                                    $meal = $has_meal ? $meal_plans[$date][$meal_type] : null;
                                ?>
                                    <div class="meal-slot <?php echo $is_today ? 'today' : ''; ?> <?php echo $has_meal ? 'has-meal' : 'empty'; ?>"
                                         data-date="<?php echo $date; ?>"
                                         data-meal-type="<?php echo $meal_type; ?>"
                                         onclick="openAddMealModal('<?php echo $date; ?>', '<?php echo $meal_type; ?>')">
                                        
                                        <?php if ($has_meal): ?>
                                            <div class="meal-card" data-meal-id="<?php echo $meal['id']; ?>">
                                                <div class="meal-card-content">
                                                    <?php if ($meal['recipe_title']): ?>
                                                        <div class="meal-title">
                                                            <?php echo htmlspecialchars($meal['recipe_title']); ?>
                                                        </div>
                                                        <?php if ($meal['prep_time'] || $meal['cook_time']): ?>
                                                            <div class="meal-time-badge">
                                                                <i class="fas fa-clock"></i>
                                                                <?php echo ($meal['prep_time'] ?? 0) + ($meal['cook_time'] ?? 0); ?> min
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div class="meal-title custom">
                                                            <?php echo htmlspecialchars($meal['custom_meal']); ?>
                                                        </div>
                                                        <div class="meal-time-badge custom">
                                                            <i class="fas fa-star"></i>
                                                            Custom
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="meal-card-actions">
                                                    <?php if ($meal['recipe_id']): ?>
                                                        <a href="recipes.php?view=<?php echo $meal['recipe_id']; ?>" 
                                                           class="meal-action-btn" 
                                                           title="View Recipe"
                                                           onclick="event.stopPropagation()">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="mealplan.php?remove=<?php echo $meal['id']; ?>" 
                                                       class="meal-action-btn remove" 
                                                       title="Remove"
                                                       onclick="event.stopPropagation(); return confirm('Remove this meal from plan?')">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="empty-slot">
                                                <i class="fas fa-plus-circle"></i>
                                                <span>Add meal</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Two Column Layout -->
                <div class="dashboard-grid">
                    <!-- Grocery List Preview -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-shopping-bag" style="color: #10b981;"></i>
                                <h2>Grocery List Preview</h2>
                            </div>
                            <a href="grocery.php?generate=true&week=<?php echo $week_start; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-arrow-right"></i>
                                Full List
                            </a>
                        </div>
                        <div class="card-body">
                            <?php 
                            $ingredients = getWeeklyIngredients($results, $pdo);
                            $grouped_ingredients = [];
                            foreach ($ingredients as $ingredient) {
                                $category = $ingredient['category'] ?? 'Other';
                                if (!isset($grouped_ingredients[$category])) {
                                    $grouped_ingredients[$category] = [];
                                }
                                $display = $ingredient['ingredient_name'];
                                if (!empty($ingredient['quantity'])) {
                                    $display .= ' - ' . $ingredient['quantity'];
                                }
                                $grouped_ingredients[$category][] = $display;
                            }
                            ?>
                            
                            <?php if (count($ingredients) > 0): ?>
                                <div class="grocery-preview">
                                    <?php foreach ($grouped_ingredients as $category => $items): ?>
                                        <div class="grocery-category">
                                            <h4><?php echo htmlspecialchars($category); ?></h4>
                                            <ul class="grocery-items">
                                                <?php foreach (array_slice($items, 0, 3) as $item): ?>
                                                    <li>
                                                        <i class="fas fa-check-circle" style="color: #10b981;"></i>
                                                        <?php echo htmlspecialchars($item); ?>
                                                    </li>
                                                <?php endforeach; ?>
                                                <?php if (count($items) > 3): ?>
                                                    <li class="more-items">+<?php echo count($items) - 3; ?> more</li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="grocery-summary">
                                    <span class="total-items"><?php echo count($ingredients); ?> total items</span>
                                    <a href="grocery.php?generate=true&week=<?php echo $week_start; ?>" class="btn-link">
                                        View full list <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="empty-state small">
                                    <div class="empty-state-icon small">
                                        <i class="fas fa-shopping-bag"></i>
                                    </div>
                                    <h3>No ingredients yet</h3>
                                    <p>Add meals to your plan to generate a grocery list</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recipe Suggestions -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-lightbulb" style="color: #10b981;"></i>
                                <h2>Suggested Recipes</h2>
                            </div>
                            <button class="btn btn-outline btn-sm" onclick="refreshSuggestions()">
                                <i class="fas fa-sync-alt"></i>
                                Refresh
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="suggestions-list" id="suggestionsList">
                                <?php
                                // Get random recipes for suggestions
                                $stmt = $pdo->prepare("
                                        SELECT r.*, COUNT(ri.id) AS ingredient_count
                                        FROM recipes r
                                        LEFT JOIN recipe_ingredients ri ON ri.recipe_id = r.id
                                        WHERE r.user_id = ?
                                        GROUP BY r.id
                                        ORDER BY RANDOM()
                                        LIMIT 3
                                ");
                                $stmt->execute([$user_id]);
                                $suggested = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                
                                <?php if (count($suggested) > 0): ?>
                                    <?php foreach ($suggested as $suggestion): ?>
                                        <div class="suggestion-item" onclick="openAddMealModalWithRecipe('<?php echo $week_dates[0]; ?>', 'dinner', <?php echo $suggestion['id']; ?>)">
                                            <div class="suggestion-icon" style="background: <?php 
                                                $colors = ['#f0fdf4', '#fef3c7', '#fee2e2', '#dbeafe'];
                                                echo $colors[array_rand($colors)]; 
                                            ?>;">
                                                <i class="fas fa-utensils" style="color: #10b981;"></i>
                                            </div>
                                            <div class="suggestion-info">
                                                <span class="suggestion-title"><?php echo htmlspecialchars($suggestion['title']); ?></span>
                                                <span class="suggestion-meta">
                                                    <?php echo $suggestion['ingredient_count']; ?> ingredients
                                                    <?php if ($suggestion['prep_time'] || $suggestion['cook_time']): ?>
                                                        • <?php echo ($suggestion['prep_time'] ?? 0) + ($suggestion['cook_time'] ?? 0); ?> min
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <button class="btn-icon add" title="Add to plan">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state small">
                                        <p>Add some recipes first to get suggestions</p>
                                        <a href="recipes.php" class="btn btn-primary btn-sm">Add Recipes</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Meal Modal -->
    <div id="addMealModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2 id="modalTitle">Add Meal to Plan</h2>
                <button class="modal-close" onclick="hideAddMealModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="" id="addMealForm">
                <input type="hidden" id="modalDate" name="date">
                <input type="hidden" id="modalMealType" name="meal_type">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="recipeSelect">Choose a Recipe</label>
                        <select id="recipeSelect" name="recipe_id" class="form-input" onchange="toggleCustomMeal()">
                            <option value="">-- Select a recipe --</option>
                            <?php foreach ($recipes as $recipe): ?>
                                <option value="<?php echo $recipe['id']; ?>" 
                                        data-prep="<?php echo $recipe['prep_time']; ?>"
                                        data-cook="<?php echo $recipe['cook_time']; ?>"
                                        data-category="<?php echo $recipe['category']; ?>">
                                    <?php echo htmlspecialchars($recipe['title']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="custom">✨ Custom Meal (not a recipe)</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="customMealGroup" style="display: none;">
                        <label for="custom_meal">Custom Meal Description</label>
                        <input type="text" id="custom_meal" name="custom_meal" 
                               class="form-input"
                               placeholder="e.g., Pizza night, Leftovers, Restaurant">
                        <small class="form-text">Describe what you'll be eating</small>
                    </div>
                    
                    <div class="recipe-preview" id="recipeInfo" style="display: none;">
                        <div class="recipe-preview-header">
                            <i class="fas fa-info-circle" style="color: #10b981;"></i>
                            <h4>Recipe Details</h4>
                        </div>
                        <div id="recipeDetails" class="recipe-details"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="hideAddMealModal()">
                        Cancel
                    </button>
                    <button type="submit" name="add_meal" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Add to Plan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/mealplan.js"></script>
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

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                const prevLink = document.querySelector('.nav-arrow:first-child');
                if (prevLink) window.location.href = prevLink.href;
            } else if (e.key === 'ArrowRight') {
                const nextLink = document.querySelector('.nav-arrow:last-child');
                if (nextLink) window.location.href = nextLink.href;
            }
        });
    </script>
</body>
</html>

<?php
// Helper functions
function calculateTotalTime($meals, $pdo) {
    $total = 0;
    $recipe_ids = array_filter(array_column($meals, 'recipe_id'));
    
    if (!empty($recipe_ids)) {
        $placeholders = str_repeat('?,', count($recipe_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT SUM(COALESCE(prep_time, 0) + COALESCE(cook_time, 0)) as total 
                              FROM recipes WHERE id IN ($placeholders)");
        $stmt->execute($recipe_ids);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = $result['total'] ?: 0;
    }
    
    return $total;
}

function getWeeklyIngredients($meals, $pdo) {
    $ingredients = [];
    $recipe_ids = array_filter(array_column($meals, 'recipe_id'));
    
    if (!empty($recipe_ids)) {
        $placeholders = str_repeat('?,', count($recipe_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT ri.ingredient_name, ri.quantity, r.category 
                              FROM recipe_ingredients ri
                              JOIN recipes r ON ri.recipe_id = r.id
                              WHERE ri.recipe_id IN ($placeholders)");
        $stmt->execute($recipe_ids);
        $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return $ingredients;
}
?>