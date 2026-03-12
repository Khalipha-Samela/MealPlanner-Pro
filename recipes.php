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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_recipe'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $instructions = trim($_POST['instructions']);
        $prep_time = $_POST['prep_time'] ?: null;
        $cook_time = $_POST['cook_time'] ?: null;
        $servings = $_POST['servings'] ?: null;
        $category = $_POST['category'] ?? 'Other';
        $difficulty = $_POST['difficulty'] ?? 'Medium';
        
        if (empty($title)) {
            $error = 'Recipe title is required';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Check if category column exists
                $stmt = $pdo->query("SHOW COLUMNS FROM recipes LIKE 'category'");
                $category_exists = $stmt->fetch();
                
                $stmt = $pdo->query("SHOW COLUMNS FROM recipes LIKE 'difficulty'");
                $difficulty_exists = $stmt->fetch();
                
                // Build query dynamically based on existing columns
                $columns = ['user_id', 'title', 'description', 'instructions', 'prep_time', 'cook_time', 'servings'];
                $placeholders = ['?', '?', '?', '?', '?', '?', '?'];
                $values = [$user_id, $title, $description, $instructions, $prep_time, $cook_time, $servings];
                
                if ($category_exists) {
                    $columns[] = 'category';
                    $placeholders[] = '?';
                    $values[] = $category;
                }
                
                if ($difficulty_exists) {
                    $columns[] = 'difficulty';
                    $placeholders[] = '?';
                    $values[] = $difficulty;
                }
                
                $sql = "INSERT INTO recipes (" . implode(', ', $columns) . ") 
                        VALUES (" . implode(', ', $placeholders) . ")";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                
                $recipe_id = $pdo->lastInsertId();
                
                // Insert ingredients
                if (isset($_POST['ingredient_name']) && is_array($_POST['ingredient_name'])) {
                    foreach ($_POST['ingredient_name'] as $index => $name) {
                        $name = trim($name);
                        $quantity = trim($_POST['ingredient_quantity'][$index] ?? '');
                        
                        if (!empty($name)) {
                            $stmt = $pdo->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity) 
                                                  VALUES (?, ?, ?)");
                            $stmt->execute([$recipe_id, $name, $quantity]);
                        }
                    }
                }
                
                $pdo->commit();
                $message = 'Recipe added successfully!';
            } catch(PDOException $e) {
                $pdo->rollBack();
                $error = 'Failed to add recipe: ' . $e->getMessage();
            }
        }
    }
}

// Delete recipe
if (isset($_GET['delete'])) {
    $recipe_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM recipes WHERE id = ? AND user_id = ?");
        $stmt->execute([$recipe_id, $user_id]);
        $message = 'Recipe deleted successfully!';
    } catch(PDOException $e) {
        $error = 'Failed to delete recipe: ' . $e->getMessage();
    }
}

// Import API recipe from TheMealDB
if (isset($_GET['import_api'])) {
    $api_recipe_id = $_GET['import_api'];
    
    try {
        // Using TheMealDB API (free, no API key required)
        $apiUrl = "https://www.themealdb.com/api/json/v1/1/lookup.php?i=" . urlencode($api_recipe_id);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if (isset($data['meals'][0])) {
                $api_recipe = $data['meals'][0];
                
                // Format recipe data from TheMealDB
                $title = $api_recipe['strMeal'] ?? 'Unknown Recipe';
                $description = $api_recipe['strInstructions'] ? 
                    substr(strip_tags($api_recipe['strInstructions']), 0, 200) . '...' : 
                    "A delicious {$api_recipe['strArea']} {$api_recipe['strCategory']} recipe.";
                $instructions = $api_recipe['strInstructions'];
                $category = $api_recipe['strCategory'] ?? 'Other';
                
                // Estimate difficulty based on cooking time or ingredients count
                $ingredients_count = 0;
                for ($i = 1; $i <= 20; $i++) {
                    if (!empty($api_recipe["strIngredient$i"])) $ingredients_count++;
                }
                $difficulty = $ingredients_count > 12 ? 'Hard' : ($ingredients_count > 6 ? 'Medium' : 'Easy');
                
                // Extract ingredients
                $ingredients = [];
                for ($i = 1; $i <= 20; $i++) {
                    $ingredient = $api_recipe["strIngredient$i"] ?? '';
                    $measure = $api_recipe["strMeasure$i"] ?? '';
                    
                    if (!empty($ingredient) && trim($ingredient) !== '') {
                        $ingredients[] = [
                            'name' => $ingredient,
                            'quantity' => $measure
                        ];
                    }
                }
                
                $pdo->beginTransaction();
                
                // Check if columns exist
                $stmt = $pdo->query("SHOW COLUMNS FROM recipes LIKE 'category'");
                $category_exists = $stmt->fetch();
                
                $stmt = $pdo->query("SHOW COLUMNS FROM recipes LIKE 'difficulty'");
                $difficulty_exists = $stmt->fetch();
                
                $stmt = $pdo->query("SHOW COLUMNS FROM recipes LIKE 'image_url'");
                $image_exists = $stmt->fetch();
                
                // Build query dynamically
                $columns = ['user_id', 'title', 'description', 'instructions', 'source', 'source_id'];
                $placeholders = ['?', '?', '?', '?', '?', '?'];
                $values = [$user_id, $title, $description, $instructions, 'api', $api_recipe_id];
                
                if ($category_exists) {
                    $columns[] = 'category';
                    $placeholders[] = '?';
                    $values[] = $category;
                }
                
                if ($difficulty_exists) {
                    $columns[] = 'difficulty';
                    $placeholders[] = '?';
                    $values[] = $difficulty;
                }
                
                if ($image_exists && !empty($api_recipe['strMealThumb'])) {
                    $columns[] = 'image_url';
                    $placeholders[] = '?';
                    $values[] = $api_recipe['strMealThumb'];
                }
                
                $sql = "INSERT INTO recipes (" . implode(', ', $columns) . ") 
                        VALUES (" . implode(', ', $placeholders) . ")";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                
                $new_recipe_id = $pdo->lastInsertId();
                
                // Insert ingredients
                foreach ($ingredients as $ingredient) {
                    if (!empty($ingredient['name'])) {
                        $stmt = $pdo->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity) 
                                              VALUES (?, ?, ?)");
                        $stmt->execute([$new_recipe_id, $ingredient['name'], $ingredient['quantity']]);
                    }
                }
                
                $pdo->commit();
                $message = 'Recipe imported successfully from TheMealDB!';
            } else {
                throw new Exception('Recipe not found');
            }
        } else {
            throw new Exception('API request failed');
        }
        
    } catch(Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Failed to import recipe: ' . $e->getMessage();
    }
    
    // Redirect to clear GET parameters
    header('Location: recipes.php?message=' . urlencode($message ?: $error));
    exit();
}

// Search and filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Get stats
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM recipes WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_recipes = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT category) as categories FROM recipes WHERE user_id = ? AND category IS NOT NULL");
$stmt->execute([$user_id]);
$total_categories = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(
    CASE 
        WHEN source = 'api' THEN 1 
        ELSE 0 
    END
) as api_recipes FROM recipes WHERE user_id = ?");
$stmt->execute([$user_id]);
$api_recipes = $stmt->fetchColumn();

// Get unique categories for filter
$stmt = $pdo->prepare("SELECT DISTINCT category FROM recipes WHERE user_id = ? AND category IS NOT NULL AND category != '' ORDER BY category");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Build main query
$sql = "SELECT r.*, 
        (SELECT COUNT(*) FROM recipe_ingredients ri WHERE ri.recipe_id = r.id) as ingredient_count,
        (SELECT COUNT(*) FROM meal_plans mp WHERE mp.recipe_id = r.id) as meal_plan_count
        FROM recipes r WHERE r.user_id = ?";
$params = [$user_id];

if (!empty($search)) {
    $sql .= " AND (r.title LIKE ? OR r.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $sql .= " AND r.category = ?";
    $params[] = $category_filter;
}

if (!empty($difficulty_filter)) {
    $sql .= " AND r.difficulty = ?";
    $params[] = $difficulty_filter;
}

// Sorting
switch ($sort) {
    case 'oldest':
        $sql .= " ORDER BY r.created_at ASC";
        break;
    case 'name_asc':
        $sql .= " ORDER BY r.title ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY r.title DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY meal_plan_count DESC, r.created_at DESC";
        break;
    default:
        $sql .= " ORDER BY r.created_at DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent recipes for suggestions
$stmt = $pdo->prepare("SELECT * FROM recipes WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->execute([$user_id]);
$recent_recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for message from redirect
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipes - MealPlanner Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/recipes.css">
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
                    <li class="active">
                        <a href="recipes.php">
                            <i class="fas fa-book"></i>
                            <span>Recipes</span>
                            <?php if ($total_recipes > 0): ?>
                                <span class="nav-badge"><?php echo $total_recipes; ?></span>
                            <?php endif; ?>
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
                        <h1 class="page-title">Recipes</h1>
                        <p class="page-subtitle">Manage your recipe collection and discover new dishes</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline" onclick="showAPISearchModal()">
                            <i class="fas fa-search"></i>
                            <span>Find Online</span>
                        </button>
                        <button class="btn btn-primary" onclick="showAddRecipeModal()">
                            <i class="fas fa-plus"></i>
                            <span>Add Recipe</span>
                        </button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Total Recipes</span>
                            <span class="stat-value"><?php echo $total_recipes; ?></span>
                            <span class="stat-trend">In your collection</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Categories</span>
                            <span class="stat-value"><?php echo $total_categories; ?></span>
                            <span class="stat-trend">Different types</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                            <i class="fas fa-cloud-download-alt"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Online Recipes</span>
                            <span class="stat-value"><?php echo $api_recipes ?: 0; ?></span>
                            <span class="stat-trend">Imported</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">In Meal Plans</span>
                            <span class="stat-value"><?php echo array_sum(array_column($recipes, 'meal_plan_count')); ?></span>
                            <span class="stat-trend">Scheduled</span>
                        </div>
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

                <!-- Filters and Search -->
                <div class="filters-section">
                    <div class="filters-wrapper">
                        <form method="GET" action="" class="filters-form">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" placeholder="Search recipes..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <div class="filter-dropdown">
                                <select name="category" onchange="this.form.submit()">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" 
                                            <?php echo $category_filter == $cat ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-dropdown">
                                <select name="difficulty" onchange="this.form.submit()">
                                    <option value="">All Difficulties</option>
                                    <option value="Easy" <?php echo $difficulty_filter == 'Easy' ? 'selected' : ''; ?>>Easy</option>
                                    <option value="Medium" <?php echo $difficulty_filter == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="Hard" <?php echo $difficulty_filter == 'Hard' ? 'selected' : ''; ?>>Hard</option>
                                </select>
                            </div>
                            
                            <div class="filter-dropdown">
                                <select name="sort" onchange="this.form.submit()">
                                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                    <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                                    <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                                    <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                                </select>
                            </div>
                            
                            <?php if (!empty($search) || !empty($category_filter) || !empty($difficulty_filter)): ?>
                                <a href="recipes.php" class="btn btn-ghost">
                                    <i class="fas fa-times"></i>
                                    Clear Filters
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Recipes Grid -->
                <?php if (count($recipes) > 0): ?>
                    <div class="recipes-grid">
                        <?php foreach ($recipes as $recipe): 
                            $total_time = ($recipe['prep_time'] ?? 0) + ($recipe['cook_time'] ?? 0);
                            $difficulty_color = $recipe['difficulty'] == 'Easy' ? '#10b981' : 
                                               ($recipe['difficulty'] == 'Medium' ? '#f59e0b' : '#ef4444');

                            // Get ingredients for this recipe
                            $stmt = $pdo->prepare("SELECT * FROM recipe_ingredients WHERE recipe_id = ?");
                            $stmt->execute([$recipe['id']]);
                            $recipe_ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);                  
                        ?>
                            <div class="recipe-card">
                                <?php if (!empty($recipe['image_url'])): ?>
                                    <div class="recipe-image">
                                        <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($recipe['title']); ?>">
                                        <?php if (isset($recipe['source']) && $recipe['source'] === 'api'): ?>
                                            <span class="recipe-source">
                                                <i class="fas fa-cloud"></i> Online
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="recipe-image-placeholder">
                                        <i class="fas fa-utensils"></i>
                                        <?php if (isset($recipe['source']) && $recipe['source'] === 'api'): ?>
                                            <span class="recipe-source">
                                                <i class="fas fa-cloud"></i> Online
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="recipe-content">
                                    <div class="recipe-header">
                                        <h3 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h3>
                                        <div class="recipe-actions">
                                            <button class="btn-icon edit" onclick="editRecipe(<?php echo $recipe['id']; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="recipes.php?delete=<?php echo $recipe['id']; ?>" 
                                               class="btn-icon delete" 
                                               onclick="return confirm('Are you sure you want to delete this recipe?')"
                                               title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="recipe-meta">
                                        <?php if ($total_time > 0): ?>
                                            <span class="meta-item">
                                                <i class="fas fa-clock"></i>
                                                <?php echo $total_time; ?> min
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($recipe['difficulty']) && !empty($recipe['difficulty'])): ?>
                                            <span class="meta-item" style="color: <?php echo $difficulty_color; ?>">
                                                <i class="fas fa-signal"></i>
                                                <?php echo $recipe['difficulty']; ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($recipe['servings'])): ?>
                                            <span class="meta-item">
                                                <i class="fas fa-users"></i>
                                                <?php echo $recipe['servings']; ?> servings
                                            </span>
                                        <?php endif; ?>
                                        
                                        <span class="meta-item">
                                            <i class="fas fa-carrot"></i>
                                            <?php echo count($recipe_ingredients); ?> ingredients
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($recipe['description'])): ?>
                                        <div class="recipe-description-wrapper">
                                            <p class="recipe-description">
                                                <?php echo nl2br(htmlspecialchars(substr($recipe['description'], 0, 120))); ?>
                                                <?php if (strlen($recipe['description']) > 120): ?>...<?php endif; ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($recipe['category']) && !empty($recipe['category'])): ?>
                                        <div class="recipe-category">
                                            <span class="category-badge">
                                                <?php echo htmlspecialchars($recipe['category']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="recipe-footer">
                                        <button class="btn btn-outline btn-sm" onclick="viewRecipe(<?php echo $recipe['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                            View
                                        </button>
                                        <button class="btn btn-primary btn-sm" onclick="addToMealPlan(<?php echo $recipe['id']; ?>)">
                                            <i class="fas fa-calendar-plus"></i>
                                            Plan
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <h3>No recipes yet</h3>
                        <p>Start building your recipe collection by adding your own or discovering new ones online</p>
                        <div class="empty-state-actions">
                            <button class="btn btn-outline" onclick="showAPISearchModal()">
                                <i class="fas fa-search"></i>
                                Find Recipes Online
                            </button>
                            <button class="btn btn-primary" onclick="showAddRecipeModal()">
                                <i class="fas fa-plus"></i>
                                Add Your First Recipe
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Recent Activity -->
                <?php if (!empty($recent_recipes)): ?>
                <div class="recent-activity">
                    <h3>Recently Added</h3>
                    <div class="recent-grid">
                        <?php foreach ($recent_recipes as $recent): ?>
                            <div class="recent-item" onclick="viewRecipe(<?php echo $recent['id']; ?>)">
                                <div class="recent-icon" style="background: <?php 
                                    $colors = ['#f0fdf4', '#fef3c7', '#fee2e2', '#dbeafe'];
                                    echo $colors[array_rand($colors)]; 
                                ?>;">
                                    <i class="fas fa-utensils" style="color: #10b981;"></i>
                                </div>
                                <div class="recent-info">
                                    <span class="recent-name"><?php echo htmlspecialchars($recent['title']); ?></span>
                                    <span class="recent-category"><?php echo htmlspecialchars($recent['category'] ?: 'Uncategorized'); ?></span>
                                </div>
                                <span class="recent-time">
                                    <?php 
                                    $created = new DateTime($recent['created_at']);
                                    $now = new DateTime();
                                    $diff = $now->diff($created);
                                    
                                    if ($diff->d == 0) {
                                        echo 'Today';
                                    } elseif ($diff->d == 1) {
                                        echo 'Yesterday';
                                    } else {
                                        echo $diff->d . ' days ago';
                                    }
                                    ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add Recipe Modal -->
    <div id="addRecipeModal" class="modal" style="display: none;">
        <div class="modal-content modal-lg" style="max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle" style="color: #10b981; margin-right: 10px;"></i> Add New Recipe</h2>
                <button class="modal-close" onclick="hideAddRecipeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" action="" id="recipeForm" style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
                <div class="modal-body" style="overflow-y: auto; padding: 20px; flex: 1;">
                    <!-- Basic Info Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle" style="color: #10b981;"></i>
                            Basic Information
                        </h3>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="title">Recipe Title <span class="required">*</span></label>
                                <input type="text" id="title" name="title" required 
                                       placeholder="e.g., Creamy Mushroom Pasta"
                                       class="form-input">
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="2" 
                                          placeholder="Brief description of the recipe"
                                          class="form-input"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select id="category" name="category" class="form-input">
                                    <option value="">Select Category</option>
                                    <option value="Breakfast">🍳 Breakfast</option>
                                    <option value="Lunch">🥪 Lunch</option>
                                    <option value="Dinner">🍽️ Dinner</option>
                                    <option value="Dessert">🍰 Dessert</option>
                                    <option value="Appetizer">🥗 Appetizer</option>
                                    <option value="Soup">🍲 Soup</option>
                                    <option value="Salad">🥗 Salad</option>
                                    <option value="Main Course">🍖 Main Course</option>
                                    <option value="Side Dish">🥔 Side Dish</option>
                                    <option value="Baking">🥖 Baking</option>
                                    <option value="Vegetarian">🥬 Vegetarian</option>
                                    <option value="Vegan">🌱 Vegan</option>
                                    <option value="Other">📦 Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="difficulty">Difficulty</label>
                                <select id="difficulty" name="difficulty" class="form-input">
                                    <option value="Easy">Easy</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="Hard">Hard</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Time & Servings Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-clock" style="color: #10b981;"></i>
                            Time & Servings
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="prep_time">Prep Time (minutes)</label>
                                <input type="number" id="prep_time" name="prep_time" min="0" 
                                       placeholder="e.g., 15"
                                       class="form-input">
                            </div>
                            
                            <div class="form-group">
                                <label for="cook_time">Cook Time (minutes)</label>
                                <input type="number" id="cook_time" name="cook_time" min="0" 
                                       placeholder="e.g., 30"
                                       class="form-input">
                            </div>
                            
                            <div class="form-group">
                                <label for="servings">Servings</label>
                                <input type="number" id="servings" name="servings" min="1" 
                                       placeholder="e.g., 4"
                                       class="form-input">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ingredients Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-carrot" style="color: #10b981;"></i>
                                Ingredients
                            </h3>
                            <button type="button" class="btn btn-outline btn-sm" onclick="addIngredientField()">
                                <i class="fas fa-plus"></i> Add Ingredient
                            </button>
                        </div>
                        
                        <div id="ingredients-container" class="ingredients-container">
                            <div class="ingredient-row">
                                <div class="form-group">
                                    <input type="text" name="ingredient_name[]" 
                                           placeholder="Ingredient name (e.g., Chicken breast)" 
                                           required
                                           class="form-input">
                                </div>
                                <div class="form-group">
                                    <input type="text" name="ingredient_quantity[]" 
                                           placeholder="Quantity (e.g., 500g)"
                                           class="form-input">
                                </div>
                                <div class="form-group action-group">
                                    <button type="button" class="btn-icon delete" 
                                            onclick="removeIngredientField(this)" 
                                            disabled
                                            title="Remove ingredient">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ingredient-tip">
                            <i class="fas fa-lightbulb" style="color: #f59e0b;"></i>
                            <small>Tip: Be specific with quantities for better meal planning</small>
                        </div>
                    </div>
                    
                    <!-- Instructions Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-list-ol" style="color: #10b981;"></i>
                            Instructions
                        </h3>
                        
                        <div class="form-group">
                            <textarea id="instructions" name="instructions" rows="6" 
                                      placeholder="Step-by-step instructions&#10;&#10;1. First step...&#10;2. Second step...&#10;3. Third step..." 
                                      required
                                      class="form-input"></textarea>
                        </div>
                        
                        <div class="instruction-tip">
                            <i class="fas fa-lightbulb" style="color: #f59e0b;"></i>
                            <small>Tip: Number your steps for better readability</small>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <div class="footer-actions">
                        <button type="button" class="btn btn-outline" onclick="hideAddRecipeModal()">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                        <button type="submit" name="add_recipe" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Recipe
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- View Recipe Modal -->
    <div id="viewRecipeModal" class="modal" style="display: none;">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2 id="viewRecipeTitle"></h2>
                <button class="modal-close" onclick="hideViewRecipeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body recipe-view-modal" id="viewRecipeContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="hideViewRecipeModal()">
                    Close
                </button>
                <button class="btn btn-primary" id="addToPlanFromView">
                    <i class="fas fa-calendar-plus"></i>
                    Add to Meal Plan
                </button>
            </div>
        </div>
    </div>

    <!-- API Search Modal -->
    <div id="apiSearchModal" class="modal" style="display: none;">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2><i class="fas fa-search" style="color: #10b981;"></i> Find Recipes Online</h2>
                <button class="modal-close" onclick="hideAPISearchModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="api-search-container">
                    <div class="api-search-form">
                        <div class="search-box large">
                            <i class="fas fa-search"></i>
                            <input type="text" id="apiSearchQuery" placeholder="Search for recipes... (e.g., pasta, chicken, vegetarian)">
                        </div>
                        <button class="btn btn-primary" onclick="performAPISearch()">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                        <button class="btn btn-outline" onclick="getRandomRecipes()">
                            <i class="fas fa-random"></i>
                            Random
                        </button>
                    </div>
                    
                    <div class="api-results" id="apiResults" style="display: none;">
                        <h3>Search Results</h3>
                        <div id="apiResultsList" class="api-results-grid"></div>
                    </div>
                    
                    <div class="api-results" id="randomRecipes" style="display: none;">
                        <h3>Suggested Recipes</h3>
                        <div id="randomRecipesList" class="api-results-grid"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- API Recipe Preview Modal -->
    <div id="apiPreviewModal" class="modal" style="display: none;">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2 id="apiPreviewTitle"></h2>
                <button class="modal-close" onclick="hideAPIPreviewModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body recipe-view-modal" id="apiPreviewContent">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="hideAPIPreviewModal()">
                    Cancel
                </button>
                <button class="btn btn-primary" id="importFromPreviewBtn">
                    <i class="fas fa-download"></i>
                    Import to My Recipes
                </button>
            </div>
        </div>
    </div>

    <script src="js/api-recipes.js"></script>
    <script src="js/recipes.js"></script>
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

        // Modal functions
        function showAddRecipeModal() {
            document.getElementById('addRecipeModal').style.display = 'block';
            document.getElementById('title').focus();
        }

        function hideAddRecipeModal() {
            document.getElementById('addRecipeModal').style.display = 'none';
        }

        function hideViewRecipeModal() {
            document.getElementById('viewRecipeModal').style.display = 'none';
        }

        function showAPISearchModal() {
            document.getElementById('apiSearchModal').style.display = 'block';
            document.getElementById('apiSearchQuery').focus();
        }

        function hideAPISearchModal() {
            document.getElementById('apiSearchModal').style.display = 'none';
        }

        function hideAPIPreviewModal() {
            document.getElementById('apiPreviewModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                showAddRecipeModal();
            }
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.querySelector('.search-box input')?.focus();
            }
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>