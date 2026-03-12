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

// Get or create current list
$current_list_id = null;
$stmt = $pdo->prepare("SELECT id, name FROM grocery_lists WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$current_list = $stmt->fetch(PDO::FETCH_ASSOC);

if ($current_list) {
    $current_list_id = $current_list['id'];
    $current_list_name = $current_list['name'];
}

// Handle list switching
if (isset($_GET['list']) && is_numeric($_GET['list'])) {
    $list_id = $_GET['list'];
    // Verify list belongs to user
    $stmt = $pdo->prepare("SELECT id FROM grocery_lists WHERE id = ? AND user_id = ?");
    $stmt->execute([$list_id, $user_id]);
    if ($stmt->fetch()) {
        $current_list_id = $list_id;
    }
}

// Create new list
if (isset($_GET['new']) || !$current_list_id) {
    try {
        $pdo->beginTransaction();
        
        $list_name = date('M d, Y') . ' Grocery List';
        $stmt = $pdo->prepare("INSERT INTO grocery_lists (user_id, name) VALUES (?, ?)");
        $stmt->execute([$user_id, $list_name]);
        $current_list_id = $pdo->lastInsertId();
        
        // Optionally copy items from previous list
        if (isset($_GET['copy_from']) && is_numeric($_GET['copy_from'])) {
            $copy_from_id = $_GET['copy_from'];
            $stmt = $pdo->prepare("INSERT INTO grocery_items (list_id, item_name, quantity, category, purchased)
                                  SELECT ?, item_name, quantity, category, false 
                                  FROM grocery_items 
                                  WHERE list_id = ? AND purchased = false");
            $stmt->execute([$current_list_id, $copy_from_id]);
        }
        
        $pdo->commit();
        $message = 'New grocery list created!';
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = 'Failed to create list: ' . $e->getMessage();
    }
}

// Generate list from meal plan
if (isset($_GET['generate'])) {
    // Get ingredients from recent meal plans
    $week_start = isset($_GET['week']) ? $_GET['week'] : date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime($week_start . ' +6 days'));
    
    $stmt = $pdo->prepare("SELECT DISTINCT ri.ingredient_name, ri.quantity, 
                                  CASE 
                                      WHEN ri.ingredient_name LIKE '%milk%' OR ri.ingredient_name LIKE '%cheese%' OR ri.ingredient_name LIKE '%yogurt%' OR ri.ingredient_name LIKE '%egg%' THEN 'Dairy'
                                      WHEN ri.ingredient_name LIKE '%apple%' OR ri.ingredient_name LIKE '%banana%' OR ri.ingredient_name LIKE '%carrot%' OR ri.ingredient_name LIKE '%lettuce%' THEN 'Produce'
                                      WHEN ri.ingredient_name LIKE '%chicken%' OR ri.ingredient_name LIKE '%beef%' OR ri.ingredient_name LIKE '%pork%' OR ri.ingredient_name LIKE '%fish%' THEN 'Meat'
                                      WHEN ri.ingredient_name LIKE '%bread%' OR ri.ingredient_name LIKE '%roll%' THEN 'Bakery'
                                      WHEN ri.ingredient_name LIKE '%rice%' OR ri.ingredient_name LIKE '%pasta%' OR ri.ingredient_name LIKE '%canned%' THEN 'Pantry'
                                      ELSE 'Other'
                                  END as category
                          FROM meal_plans mp 
                          JOIN recipe_ingredients ri ON mp.recipe_id = ri.recipe_id 
                          WHERE mp.user_id = ? AND mp.date BETWEEN ? AND ?
                          ORDER BY ri.ingredient_name");
    $stmt->execute([$user_id, $week_start, $week_end]);
    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($ingredients && $current_list_id) {
        try {
            $pdo->beginTransaction();
            
            // Clear existing items
            $stmt = $pdo->prepare("DELETE FROM grocery_items WHERE list_id = ?");
            $stmt->execute([$current_list_id]);
            
            // Add new items
            $stmt = $pdo->prepare("INSERT INTO grocery_items (list_id, item_name, quantity, category) 
                                  VALUES (?, ?, ?, ?)");
            
            foreach ($ingredients as $ingredient) {
                $stmt->execute([
                    $current_list_id,
                    $ingredient['ingredient_name'],
                    $ingredient['quantity'] ?? '1',
                    $ingredient['category'] ?? 'Other'
                ]);
            }
            
            $pdo->commit();
            $message = 'Grocery list generated from this week\'s meal plan!';
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to generate list: ' . $e->getMessage();
        }
    } else {
        $error = 'No ingredients found in your meal plan for this week.';
    }
}

// Add item to list
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $item_name = trim($_POST['item_name']);
    $quantity = trim($_POST['quantity']);
    $category = $_POST['category'] ?: 'Other';
    
    if (empty($item_name)) {
        $error = 'Item name is required';
    } elseif (!$current_list_id) {
        $error = 'No active grocery list';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO grocery_items (list_id, item_name, quantity, category) 
                                  VALUES (?, ?, ?, ?)");
            $stmt->execute([$current_list_id, $item_name, $quantity, $category]);
            $message = 'Item added to list!';
        } catch(PDOException $e) {
            $error = 'Failed to add item: ' . $e->getMessage();
        }
    }
}

// Edit item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item'])) {
    $item_id = $_POST['item_id'];
    $item_name = trim($_POST['item_name']);
    $quantity = trim($_POST['quantity']);
    $category = $_POST['category'] ?: 'Other';
    
    try {
        $stmt = $pdo->prepare("UPDATE grocery_items 
                              SET item_name = ?, quantity = ?, category = ? 
                              WHERE id = ? AND list_id IN (SELECT id FROM grocery_lists WHERE user_id = ?)");
        $stmt->execute([$item_name, $quantity, $category, $item_id, $user_id]);
        $message = 'Item updated successfully!';
    } catch(PDOException $e) {
        $error = 'Failed to update item: ' . $e->getMessage();
    }
}

// Toggle item purchased
if (isset($_GET['toggle'])) {
    $item_id = $_GET['toggle'];
    try {
        $stmt = $pdo->prepare("UPDATE grocery_items SET purchased = NOT purchased 
                              WHERE id = ? AND list_id IN (SELECT id FROM grocery_lists WHERE user_id = ?)");
        $stmt->execute([$item_id, $user_id]);
        header('Location: grocery.php' . ($current_list_id ? '?list=' . $current_list_id : ''));
        exit();
    } catch(PDOException $e) {
        $error = 'Failed to update item: ' . $e->getMessage();
    }
}

// Delete item
if (isset($_GET['delete_item'])) {
    $item_id = $_GET['delete_item'];
    try {
        $stmt = $pdo->prepare("DELETE FROM grocery_items 
                              WHERE id = ? AND list_id IN (SELECT id FROM grocery_lists WHERE user_id = ?)");
        $stmt->execute([$item_id, $user_id]);
        $message = 'Item removed from list!';
    } catch(PDOException $e) {
        $error = 'Failed to delete item: ' . $e->getMessage();
    }
}

// Clear purchased items
if (isset($_GET['clear_purchased'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM grocery_items WHERE purchased = TRUE AND list_id = ?");
        $stmt->execute([$current_list_id]);
        $message = 'Cleared purchased items!';
    } catch(PDOException $e) {
        $error = 'Failed to clear items: ' . $e->getMessage();
    }
}

// Delete entire list
if (isset($_GET['delete_list'])) {
    $list_id = $_GET['delete_list'];
    try {
        $stmt = $pdo->prepare("DELETE FROM grocery_lists WHERE id = ? AND user_id = ?");
        $stmt->execute([$list_id, $user_id]);
        
        // Get another list
        $stmt = $pdo->prepare("SELECT id FROM grocery_lists WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $new_list = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_list_id = $new_list ? $new_list['id'] : null;
        
        $message = 'List deleted successfully!';
    } catch(PDOException $e) {
        $error = 'Failed to delete list: ' . $e->getMessage();
    }
}

// Duplicate list
if (isset($_GET['duplicate_list'])) {
    $list_id = $_GET['duplicate_list'];
    try {
        $pdo->beginTransaction();
        
        // Get list name
        $stmt = $pdo->prepare("SELECT name FROM grocery_lists WHERE id = ? AND user_id = ?");
        $stmt->execute([$list_id, $user_id]);
        $list = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($list) {
            // Create new list
            $new_name = $list['name'] . ' (Copy)';
            $stmt = $pdo->prepare("INSERT INTO grocery_lists (user_id, name) VALUES (?, ?)");
            $stmt->execute([$user_id, $new_name]);
            $new_list_id = $pdo->lastInsertId();
            
            // Copy items
            $stmt = $pdo->prepare("INSERT INTO grocery_items (list_id, item_name, quantity, category, purchased)
                                  SELECT ?, item_name, quantity, category, false 
                                  FROM grocery_items 
                                  WHERE list_id = ?");
            $stmt->execute([$new_list_id, $list_id]);
            
            $pdo->commit();
            $message = 'List duplicated successfully!';
        }
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = 'Failed to duplicate list: ' . $e->getMessage();
    }
}

// Get current list items
$list_items = [];
$list_stats = ['total' => 0, 'purchased' => 0, 'remaining' => 0];
$grouped_items = [];

if ($current_list_id) {
    $stmt = $pdo->prepare("SELECT * FROM grocery_items WHERE list_id = ? ORDER BY category, item_name");
    $stmt->execute([$current_list_id]);
    $list_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats
    $list_stats['total'] = count($list_items);
    $list_stats['purchased'] = count(array_filter($list_items, fn($item) => $item['purchased']));
    $list_stats['remaining'] = $list_stats['total'] - $list_stats['purchased'];
    
    // Group items by category
    foreach ($list_items as $item) {
        $category = $item['category'] ?: 'Other';
        if (!isset($grouped_items[$category])) {
            $grouped_items[$category] = [];
        }
        $grouped_items[$category][] = $item;
    }
    
    // Sort categories
    uksort($grouped_items, function($a, $b) {
        $order = ['Produce', 'Dairy', 'Meat', 'Bakery', 'Pantry', 'Frozen', 'Beverages', 'Household', 'Other'];
        $pos_a = array_search($a, $order);
        $pos_b = array_search($b, $order);
        if ($pos_a === false) $pos_a = 999;
        if ($pos_b === false) $pos_b = 999;
        return $pos_a - $pos_b;
    });
}

// Get all lists
$stmt = $pdo->prepare("SELECT * FROM grocery_lists WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$all_lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent lists for copying
$recent_lists = array_slice($all_lists, 1, 3); // Exclude current list
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grocery Lists - MealPlanner Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/grocery.css">
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
                    <li>
                        <a href="mealplan.php">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Meal Plan</span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="grocery.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Grocery List</span>
                            <?php if ($list_stats['remaining'] > 0): ?>
                                <span class="nav-badge"><?php echo $list_stats['remaining']; ?></span>
                            <?php endif; ?>
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
                        <h1 class="page-title">Grocery Lists</h1>
                        <p class="page-subtitle">Manage your shopping lists and never forget an ingredient</p>
                    </div>
                    <div class="header-actions">
                        <div class="list-selector">
                            <select id="listSelect" class="list-dropdown" onchange="switchList(this.value)">
                                <option value="">Select a list...</option>
                                <?php foreach ($all_lists as $list): ?>
                                    <option value="<?php echo $list['id']; ?>" 
                                            <?php echo $list['id'] == $current_list_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($list['name']); ?>
                                        (<?php echo date('M d', strtotime($list['created_at'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-primary" onclick="showAddItemModal()">
                            <i class="fas fa-plus"></i>
                            <span>Add Item</span>
                        </button>
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

                <?php if ($current_list_id): ?>
                    <!-- List Header -->
                    <div class="list-header-card">
                        <div class="list-info">
                            <h2>
                                <i class="fas fa-shopping-basket" style="color: #10b981;"></i>
                                <?php echo htmlspecialchars($current_list_name ?? 'Grocery List'); ?>
                            </h2>
                            <span class="list-date">Created <?php echo date('F j, Y', strtotime($all_lists[0]['created_at'] ?? 'now')); ?></span>
                        </div>
                        
                        <div class="list-progress">
                            <div class="progress-stats">
                                <span class="progress-completed"><?php echo $list_stats['purchased']; ?> completed</span>
                                <span class="progress-total"><?php echo $list_stats['total']; ?> total items</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $list_stats['total'] > 0 ? ($list_stats['purchased'] / $list_stats['total'] * 100) : 0; ?>%;"></div>
                            </div>
                        </div>
                        
                        <div class="list-actions">
                            <?php if ($list_stats['remaining'] > 0): ?>
                                <button class="btn btn-outline btn-sm" onclick="if(confirm('Clear all purchased items?')) window.location.href='grocery.php?clear_purchased=true<?php echo $current_list_id ? '&list=' . $current_list_id : ''; ?>'">
                                    <i class="fas fa-check-double"></i>
                                    Clear Purchased
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-outline btn-sm" onclick="printList()">
                                <i class="fas fa-print"></i>
                                Print
                            </button>
                            <button class="btn btn-outline btn-sm" onclick="shareList()">
                                <i class="fas fa-share-alt"></i>
                                Share
                            </button>
                            <div class="dropdown">
                                <button class="btn btn-outline btn-sm dropdown-toggle" onclick="toggleDropdown()">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div id="listDropdown" class="dropdown-menu" style="display: none;">
                                    <a href="grocery.php?duplicate_list=<?php echo $current_list_id; ?>" class="dropdown-item">
                                        <i class="fas fa-copy"></i> Duplicate List
                                    </a>
                                    <a href="grocery.php?new=true&copy_from=<?php echo $current_list_id; ?>" class="dropdown-item">
                                        <i class="fas fa-clone"></i> Create Copy
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a href="grocery.php?delete_list=<?php echo $current_list_id; ?>" class="dropdown-item text-danger" 
                                       onclick="return confirm('Are you sure you want to delete this entire list?')">
                                        <i class="fas fa-trash"></i> Delete List
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                <i class="fas fa-shopping-basket"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label">Total Items</span>
                                <span class="stat-value"><?php echo $list_stats['total']; ?></span>
                                <span class="stat-trend">In your list</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label">Purchased</span>
                                <span class="stat-value"><?php echo $list_stats['purchased']; ?></span>
                                <span class="stat-trend">Checked off</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label">Remaining</span>
                                <span class="stat-value"><?php echo $list_stats['remaining']; ?></span>
                                <span class="stat-trend">Still to buy</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                                <i class="fas fa-tags"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-label">Categories</span>
                                <span class="stat-value"><?php echo count($grouped_items); ?></span>
                                <span class="stat-trend">Different sections</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Bar -->
                    <div class="quick-actions-bar">
                        <div class="quick-actions-buttons">
                            <a href="grocery.php?generate=true<?php echo $current_list_id ? '&list=' . $current_list_id : ''; ?>" class="quick-action-chip">
                                <i class="fas fa-magic"></i>
                                <span>Generate from Meal Plan</span>
                            </a>
                            <a href="grocery.php?new=true" class="quick-action-chip">
                                <i class="fas fa-plus-circle"></i>
                                <span>New List</span>
                            </a>
                            <?php if (!empty($recent_lists)): ?>
                                <div class="quick-action-chip dropdown-hover">
                                    <i class="fas fa-copy"></i>
                                    <span>Copy from...</span>
                                    <div class="hover-dropdown">
                                        <?php foreach ($recent_lists as $recent): ?>
                                            <a href="grocery.php?new=true&copy_from=<?php echo $recent['id']; ?>">
                                                <i class="fas fa-history"></i>
                                                <?php echo htmlspecialchars($recent['name']); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="search-box small">
                            <i class="fas fa-search"></i>
                            <input type="text" id="itemSearch" placeholder="Filter items..." onkeyup="filterItems()">
                        </div>
                    </div>

                    <!-- Grocery List -->
                    <div class="grocery-container">
                        <?php if (count($list_items) > 0): ?>
                            <div class="grocery-sections" id="grocerySections">
                                <?php foreach ($grouped_items as $category => $items): ?>
                                    <div class="category-section" data-category="<?php echo htmlspecialchars($category); ?>">
                                        <div class="category-header">
                                            <div class="category-title">
                                                <i class="fas fa-<?php echo getCategoryIcon($category); ?>" style="color: #10b981;"></i>
                                                <h3><?php echo htmlspecialchars($category); ?></h3>
                                                <span class="category-badge"><?php echo count($items); ?></span>
                                            </div>
                                            <div class="category-progress">
                                                <?php 
                                                $purchased_in_cat = count(array_filter($items, fn($i) => $i['purchased']));
                                                $cat_percent = count($items) > 0 ? ($purchased_in_cat / count($items) * 100) : 0;
                                                ?>
                                                <span class="category-stats"><?php echo $purchased_in_cat; ?>/<?php echo count($items); ?></span>
                                                <div class="category-progress-bar">
                                                    <div class="category-progress-fill" style="width: <?php echo $cat_percent; ?>%;"></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="items-list sortable-items" data-category="<?php echo htmlspecialchars($category); ?>">
                                            <?php foreach ($items as $item): ?>
                                                <div class="grocery-item <?php echo $item['purchased'] ? 'purchased' : ''; ?>" data-item-id="<?php echo $item['id']; ?>">
                                                    <label class="item-checkbox">
                                                        <input type="checkbox" 
                                                               <?php echo $item['purchased'] ? 'checked' : ''; ?>
                                                               onchange="toggleItem(<?php echo $item['id']; ?>)">
                                                        <span class="checkbox-custom"></span>
                                                    </label>
                                                    
                                                    <div class="item-content">
                                                        <div class="item-name-wrapper">
                                                            <span class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                                            <?php if (!empty($item['quantity'])): ?>
                                                                <span class="item-quantity-badge"><?php echo htmlspecialchars($item['quantity']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="item-actions">
                                                        <button class="item-action-btn edit" onclick="editItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['item_name'])); ?>', '<?php echo htmlspecialchars(addslashes($item['quantity'])); ?>', '<?php echo htmlspecialchars(addslashes($item['category'])); ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <a href="grocery.php?delete_item=<?php echo $item['id']; ?><?php echo $current_list_id ? '&list=' . $current_list_id : ''; ?>" 
                                                           class="item-action-btn delete"
                                                           onclick="return confirm('Remove this item from list?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                        <span class="drag-handle">
                                                            <i class="fas fa-grip-vertical"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Quick Add Suggestions -->
                            <div class="quick-add-section">
                                <h4>Quick Add Common Items</h4>
                                <div class="suggested-items">
                                    <button class="suggested-item-btn" onclick="addQuickItem('Milk', '1L', 'Dairy')">
                                        <i class="fas fa-fw fa-cow"></i>
                                        <span>Milk</span>
                                    </button>
                                    <button class="suggested-item-btn" onclick="addQuickItem('Eggs', '12', 'Dairy')">
                                        <i class="fas fa-fw fa-egg"></i>
                                        <span>Eggs</span>
                                    </button>
                                    <button class="suggested-item-btn" onclick="addQuickItem('Bread', '1 loaf', 'Bakery')">
                                        <i class="fas fa-fw fa-bread-slice"></i>
                                        <span>Bread</span>
                                    </button>
                                    <button class="suggested-item-btn" onclick="addQuickItem('Bananas', '6', 'Produce')">
                                        <i class="fas fa-fw fa-apple-alt"></i>
                                        <span>Bananas</span>
                                    </button>
                                    <button class="suggested-item-btn" onclick="addQuickItem('Chicken Breast', '500g', 'Meat')">
                                        <i class="fas fa-fw fa-drumstick-bite"></i>
                                        <span>Chicken</span>
                                    </button>
                                    <button class="suggested-item-btn" onclick="addQuickItem('Rice', '1kg', 'Pantry')">
                                        <i class="fas fa-fw fa-bowl-rice"></i>
                                        <span>Rice</span>
                                    </button>
                                    <button class="suggested-item-btn" onclick="addQuickItem('Pasta', '500g', 'Pantry')">
                                        <i class="fas fa-fw fa-wheat-awn"></i>
                                        <span>Pasta</span>
                                    </button>
                                    <button class="suggested-item-btn" onclick="addQuickItem('Apples', '1kg', 'Produce')">
                                        <i class="fas fa-fw fa-apple-alt"></i>
                                        <span>Apples</span>
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-shopping-basket"></i>
                                </div>
                                <h3>Your list is empty</h3>
                                <p>Add items manually or generate a list from your meal plan</p>
                                <div class="empty-state-actions">
                                    <button class="btn btn-primary" onclick="showAddItemModal()">
                                        <i class="fas fa-plus"></i>
                                        Add First Item
                                    </button>
                                    <a href="grocery.php?generate=true<?php echo $current_list_id ? '&list=' . $current_list_id : ''; ?>" class="btn btn-outline">
                                        <i class="fas fa-magic"></i>
                                        Generate from Meal Plan
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state large">
                        <div class="empty-state-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3>No grocery lists yet</h3>
                        <p>Create your first grocery list to start organizing your shopping</p>
                        <div class="empty-state-actions">
                            <a href="grocery.php?new=true" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Create First List
                            </a>
                            <a href="mealplan.php" class="btn btn-outline">
                                <i class="fas fa-calendar-alt"></i>
                                Go to Meal Plan
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add Item Modal -->
    <div id="addItemModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle" style="color: #10b981;"></i> Add Item to List</h2>
                <button class="modal-close" onclick="hideAddItemModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="" id="addItemForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="item_name">Item Name <span class="required">*</span></label>
                        <input type="text" id="item_name" name="item_name" required 
                               placeholder="e.g., Milk, Bread, Apples" class="form-input"
                               autocomplete="off">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="text" id="quantity" name="quantity" 
                                   placeholder="e.g., 1L, 500g, 6 pieces" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" class="form-input">
                                <option value="">Select Category</option>
                                <option value="Produce">🥬 Produce</option>
                                <option value="Dairy">🥛 Dairy & Eggs</option>
                                <option value="Meat">🥩 Meat & Seafood</option>
                                <option value="Bakery">🥖 Bakery</option>
                                <option value="Pantry">🥫 Pantry</option>
                                <option value="Frozen">❄️ Frozen Foods</option>
                                <option value="Beverages">🥤 Beverages</option>
                                <option value="Household">🧹 Household</option>
                                <option value="Other">📦 Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Voice Input -->
                    <div class="voice-input-section">
                        <button type="button" class="btn btn-outline btn-block" id="voiceInputBtn">
                            <i class="fas fa-microphone"></i>
                            Use Voice Input
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="hideAddItemModal()">
                        Cancel
                    </button>
                    <button type="submit" name="add_item" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Add to List
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div id="editItemModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2><i class="fas fa-edit" style="color: #10b981;"></i> Edit Item</h2>
                <button class="modal-close" onclick="hideEditItemModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="" id="editItemForm">
                <input type="hidden" id="edit_item_id" name="item_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_item_name">Item Name <span class="required">*</span></label>
                        <input type="text" id="edit_item_name" name="item_name" required class="form-input">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_quantity">Quantity</label>
                            <input type="text" id="edit_quantity" name="quantity" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_category">Category</label>
                            <select id="edit_category" name="category" class="form-input">
                                <option value="">Select Category</option>
                                <option value="Produce">🥬 Produce</option>
                                <option value="Dairy">🥛 Dairy & Eggs</option>
                                <option value="Meat">🥩 Meat & Seafood</option>
                                <option value="Bakery">🥖 Bakery</option>
                                <option value="Pantry">🥫 Pantry</option>
                                <option value="Frozen">❄️ Frozen Foods</option>
                                <option value="Beverages">🥤 Beverages</option>
                                <option value="Household">🧹 Household</option>
                                <option value="Other">📦 Other</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="hideEditItemModal()">
                        Cancel
                    </button>
                    <button type="submit" name="edit_item" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="js/grocery.js"></script>
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

        // Dropdown toggle
        function toggleDropdown() {
            const dropdown = document.getElementById('listDropdown');
            if (dropdown) {
                dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
            }
        }

        // Close dropdown when clicking outside
        window.addEventListener('click', function(event) {
            const dropdown = document.getElementById('listDropdown');
            const toggle = document.querySelector('.dropdown-toggle');
            if (dropdown && !dropdown.contains(event.target) && !toggle?.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'a') {
                e.preventDefault();
                showAddItemModal();
            }
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('itemSearch')?.focus();
            }
        });
    </script>
</body>
</html>

<?php
// Helper functions
function getCategoryIcon($category) {
    $icons = [
        'Produce' => 'carrot',
        'Dairy' => 'cheese',
        'Meat' => 'drumstick-bite',
        'Bakery' => 'bread-slice',
        'Pantry' => 'jar',
        'Frozen' => 'snowflake',
        'Beverages' => 'wine-bottle',
        'Household' => 'broom',
        'Other' => 'shopping-basket'
    ];
    
    return $icons[$category] ?? 'shopping-basket';
}
?>