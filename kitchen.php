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

// Check for success message from redirect
if (isset($_GET['success'])) {
    $message = urldecode($_GET['success']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Edit ingredient
    if (isset($_POST['edit_ingredient'])) {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $category = $_POST['category'] ?? 'Other';
        $quantity = trim($_POST['quantity']);
        $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($name)) {
            $error = 'Ingredient name is required';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE ingredients SET name = ?, category = ?, quantity = ?, expiration_date = ?, notes = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$name, $category, $quantity, $expiration_date, $notes, $id, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    // Redirect to prevent form resubmission and show success message
                    header('Location: kitchen.php?success=' . urlencode('Ingredient updated successfully!'));
                    exit();
                } else {
                    $error = 'No changes made or ingredient not found';
                }
            } catch(PDOException $e) {
                $error = 'Failed to update ingredient: ' . $e->getMessage();
            }
        }
    }
    
    // Add new ingredient
    if (isset($_POST['add_ingredient'])) {
        $name = trim($_POST['name']);
        $category = $_POST['category'] ?? 'Other';
        $quantity = trim($_POST['quantity']);
        $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($name)) {
            $error = 'Ingredient name is required';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO ingredients (user_id, name, category, quantity, expiration_date, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $name, $category, $quantity, $expiration_date, $notes]);
                
                // Redirect to prevent form resubmission
                header('Location: kitchen.php?success=' . urlencode('Ingredient added successfully!'));
                exit();
            } catch(PDOException $e) {
                $error = 'Failed to add ingredient: ' . $e->getMessage();
            }
        }
    }
}

// Delete ingredient
if (isset($_GET['delete'])) {
    $ingredient_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM ingredients WHERE id = ? AND user_id = ?");
        $stmt->execute([$ingredient_id, $user_id]);
        header('Location: kitchen.php?success=' . urlencode('Ingredient deleted successfully!'));
        exit();
    } catch(PDOException $e) {
        $error = 'Failed to delete ingredient: ' . $e->getMessage();
    }
}

// Bulk delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_ids'])) {
    $selected_ids = $_POST['selected_ids'];
    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
    $params = array_merge($selected_ids, [$user_id]);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM ingredients WHERE id IN ($placeholders) AND user_id = ?");
        $stmt->execute($params);
        header('Location: kitchen.php?success=' . urlencode(count($selected_ids) . ' ingredients deleted successfully!'));
        exit();
    } catch(PDOException $e) {
        $error = 'Failed to delete ingredients: ' . $e->getMessage();
    }
}

// Filter ingredients
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

$sql = "SELECT * FROM ingredients WHERE user_id = ?";
$params = [$user_id];

if (!empty($category_filter)) {
    $sql .= " AND category = ?";
    $params[] = $category_filter;
}

switch ($filter) {
    case 'expiring':
        $sql .= " AND expiration_date IS NOT NULL AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                 AND expiration_date >= CURDATE()";
        break;
    case 'expired':
        $sql .= " AND expiration_date IS NOT NULL AND expiration_date < CURDATE()";
        break;
    case 'no_date':
        $sql .= " AND expiration_date IS NULL";
        break;
}

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR notes LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY 
    CASE 
        WHEN expiration_date IS NULL THEN 2 
        WHEN expiration_date < CURDATE() THEN 3
        ELSE 1 
    END,
    expiration_date ASC,
    name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ingredient counts and stats
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN expiration_date IS NOT NULL AND expiration_date < CURDATE() THEN 1 END) as expired,
    COUNT(CASE WHEN expiration_date IS NOT NULL AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
          AND expiration_date >= CURDATE() THEN 1 END) as expiring_soon,
    COUNT(CASE WHEN expiration_date IS NULL THEN 1 END) as no_date
    FROM ingredients WHERE user_id = ?");
$stmt->execute([$user_id]);
$counts = $stmt->fetch(PDO::FETCH_ASSOC);

// Get unique categories for filter
$stmt = $pdo->prepare("SELECT DISTINCT category FROM ingredients WHERE user_id = ? AND category IS NOT NULL AND category != '' ORDER BY category");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get recently added ingredients
$stmt = $pdo->prepare("SELECT * FROM ingredients WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent_ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Kitchen - MealPlanner Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/kitchen.css">
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
                    <li class="active">
                        <a href="kitchen.php">
                            <i class="fas fa-warehouse"></i>
                            <span>My Kitchen</span>
                            <?php if ($counts['total'] > 0): ?>
                                <span class="nav-badge"><?php echo $counts['total']; ?></span>
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
                        <h1 class="page-title">My Kitchen</h1>
                        <p class="page-subtitle">Manage your ingredients and track what's in your kitchen</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline" id="scanBarcodeBtn">
                            <i class="fas fa-barcode"></i>
                            <span>Scan Barcode</span>
                        </button>
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i>
                            <span>Add Ingredient</span>
                        </button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                            <i class="fas fa-carrot"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Total Ingredients</span>
                            <span class="stat-value"><?php echo $counts['total']; ?></span>
                            <span class="stat-trend">+<?php echo count($recent_ingredients); ?> this week</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Expiring Soon</span>
                            <span class="stat-value"><?php echo $counts['expiring_soon']; ?></span>
                            <a href="?filter=expiring" class="stat-link">View all →</a>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Expired</span>
                            <span class="stat-value"><?php echo $counts['expired']; ?></span>
                            <a href="?filter=expired" class="stat-link">View all →</a>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Categories</span>
                            <span class="stat-value"><?php echo count($categories); ?></span>
                            <span class="stat-trend">Unique types</span>
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
                                <input type="text" name="search" placeholder="Search ingredients..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <div class="filter-dropdown">
                                <select name="filter" onchange="this.form.submit()">
                                    <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All Ingredients</option>
                                    <option value="expiring" <?php echo $filter == 'expiring' ? 'selected' : ''; ?>>Expiring Soon</option>
                                    <option value="expired" <?php echo $filter == 'expired' ? 'selected' : ''; ?>>Expired</option>
                                    <option value="no_date" <?php echo $filter == 'no_date' ? 'selected' : ''; ?>>No Expiration Date</option>
                                </select>
                            </div>
                            
                            <?php if (!empty($categories)): ?>
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
                            <?php endif; ?>
                            
                            <?php if (!empty($search) || $filter != 'all' || !empty($category_filter)): ?>
                                <a href="kitchen.php" class="btn btn-ghost">
                                    <i class="fas fa-times"></i>
                                    Clear Filters
                                </a>
                            <?php endif; ?>
                        </form>
                        
                        <div class="bulk-actions">
                            <button class="btn btn-outline btn-sm" id="selectAllBtn">
                                <i class="fas fa-check-double"></i>
                                Select All
                            </button>
                            <button class="btn btn-outline btn-sm" id="bulkDeleteBtn">
                                <i class="fas fa-trash"></i>
                                Delete Selected
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Ingredients Table -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-carrot" style="color: #10b981;"></i>
                            <h2>Your Ingredients</h2>
                        </div>
                        <span class="ingredient-count"><?php echo count($ingredients); ?> items</span>
                    </div>
                    
                    <div class="card-body">
                        <?php if (count($ingredients) > 0): ?>
                            <form id="bulkDeleteForm" method="POST" action="">
                                <input type="hidden" name="bulk_delete" value="1">
                                <div class="table-responsive">
                                    <table class="ingredients-table">
                                        <thead>
                                            <tr>
                                                <th width="40">
                                                    <input type="checkbox" id="selectAllCheckbox">
                                                </th>
                                                <th>Ingredient</th>
                                                <th>Category</th>
                                                <th>Quantity</th>
                                                <th>Expiration</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ingredients as $ingredient): 
                                                $exp_date = $ingredient['expiration_date'] ? new DateTime($ingredient['expiration_date']) : null;
                                                $today = new DateTime();
                                                $days_left = $exp_date ? $today->diff($exp_date)->days : null;
                                                $is_expired = $exp_date && $exp_date < $today;
                                                $is_expiring_soon = $exp_date && !$is_expired && $days_left <= 7;
                                            ?>
                                            <tr class="<?php echo $is_expired ? 'expired-row' : ($is_expiring_soon ? 'expiring-row' : ''); ?>">
                                                <td>
                                                    <input type="checkbox" name="selected_ids[]" value="<?php echo $ingredient['id']; ?>" class="row-checkbox">
                                                </td>
                                                <td>
                                                    <div class="ingredient-info">
                                                        <span class="ingredient-name"><?php echo htmlspecialchars($ingredient['name']); ?></span>
                                                        <?php if (!empty($ingredient['notes'])): ?>
                                                            <span class="ingredient-notes"><?php echo htmlspecialchars($ingredient['notes']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="category-badge">
                                                        <?php echo htmlspecialchars($ingredient['category'] ?: 'Uncategorized'); ?>
                                                    </span>
                                                </td>
                                                <td class="quantity-cell">
                                                    <?php echo htmlspecialchars($ingredient['quantity'] ?: '-'); ?>
                                                </td>
                                                <td>
                                                    <?php if ($ingredient['expiration_date']): ?>
                                                        <div class="expiration-info">
                                                            <span><?php echo date('M d, Y', strtotime($ingredient['expiration_date'])); ?></span>
                                                            <span class="days-left <?php echo $is_expired ? 'expired' : ($is_expiring_soon ? 'warning' : ''); ?>">
                                                                <?php 
                                                                if ($is_expired) {
                                                                    echo 'Expired';
                                                                } elseif ($days_left == 0) {
                                                                    echo 'Today';
                                                                } elseif ($days_left == 1) {
                                                                    echo 'Tomorrow';
                                                                } else {
                                                                    echo $days_left . ' days left';
                                                                }
                                                                ?>
                                                            </span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="no-date">No expiration</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($ingredient['expiration_date']): ?>
                                                        <?php if ($is_expired): ?>
                                                            <span class="status-badge expired">Expired</span>
                                                        <?php elseif ($is_expiring_soon): ?>
                                                            <span class="status-badge expiring-soon">Expiring Soon</span>
                                                        <?php else: ?>
                                                            <span class="status-badge good">Fresh</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="status-badge no-date">No Date</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn-icon edit" 
                                                                onclick="return editIngredient(<?php echo htmlspecialchars(json_encode($ingredient), ENT_QUOTES, 'UTF-8'); ?>)" 
                                                                title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <a href="?delete=<?php echo $ingredient['id']; ?>" 
                                                           class="btn-icon delete" 
                                                           onclick="return confirm('Are you sure you want to delete this ingredient?')"
                                                           title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-warehouse"></i>
                                </div>
                                <h3>Your kitchen is empty</h3>
                                <p>Add your first ingredient to start tracking what's in your kitchen</p>
                                <button class="btn btn-primary" onclick="openAddModal()">
                                    <i class="fas fa-plus"></i>
                                    Add Your First Ingredient
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <?php if (!empty($recent_ingredients)): ?>
                <div class="recent-activity">
                    <h3>Recently Added</h3>
                    <div class="recent-grid">
                        <?php foreach ($recent_ingredients as $recent): ?>
                            <div class="recent-item">
                                <div class="recent-icon" style="background: <?php 
                                    $colors = ['#f0fdf4', '#fef3c7', '#fee2e2', '#dbeafe'];
                                    echo $colors[array_rand($colors)]; 
                                ?>;">
                                    <i class="fas fa-carrot" style="color: #10b981;"></i>
                                </div>
                                <div class="recent-info">
                                    <span class="recent-name"><?php echo htmlspecialchars($recent['name']); ?></span>
                                    <span class="recent-category"><?php echo htmlspecialchars($recent['category'] ?: 'Uncategorized'); ?></span>
                                </div>
                                <span class="recent-time">Just now</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add Ingredient Modal -->
    <div id="addIngredientModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Ingredient</h2>
                <button class="modal-close" onclick="closeModal('addIngredientModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="" id="addIngredientForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Ingredient Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" required placeholder="e.g., Organic Tomatoes">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category">
                                <option value="">Select Category</option>
                                <option value="Vegetables">🥬 Vegetables</option>
                                <option value="Fruits">🍎 Fruits</option>
                                <option value="Meat">🥩 Meat</option>
                                <option value="Dairy">🥛 Dairy</option>
                                <option value="Grains">🌾 Grains</option>
                                <option value="Spices">🌶️ Spices</option>
                                <option value="Canned">🥫 Canned Goods</option>
                                <option value="Frozen">❄️ Frozen Foods</option>
                                <option value="Beverages">🥤 Beverages</option>
                                <option value="Other">📦 Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="text" id="quantity" name="quantity" placeholder="e.g., 500g, 2 pieces, 1L">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="expiration_date">Expiration Date</label>
                            <input type="date" id="expiration_date" name="expiration_date" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes (Optional)</label>
                            <input type="text" id="notes" name="notes" placeholder="e.g., Organic, Brand name">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addIngredientModal')">
                        Cancel
                    </button>
                    <button type="submit" name="add_ingredient" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Add Ingredient
                    </button>
                </div>
            </form>
        </div>
    </div>

   <!-- Edit Ingredient Modal -->
    <div id="editIngredientModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Ingredient</h2>
                <button type="button" class="modal-close" onclick="closeModal('editIngredientModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="kitchen.php" id="editIngredientForm">
                <input type="hidden" id="edit_id" name="id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_name">Ingredient Name <span class="required">*</span></label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_category">Category</label>
                            <select id="edit_category" name="category">
                                <option value="">Select Category</option>
                                <option value="Vegetables">🥬 Vegetables</option>
                                <option value="Fruits">🍎 Fruits</option>
                                <option value="Meat">🥩 Meat</option>
                                <option value="Dairy">🥛 Dairy</option>
                                <option value="Grains">🌾 Grains</option>
                                <option value="Spices">🌶️ Spices</option>
                                <option value="Canned">🥫 Canned Goods</option>
                                <option value="Frozen">❄️ Frozen Foods</option>
                                <option value="Beverages">🥤 Beverages</option>
                                <option value="Other">📦 Other</option>
                            </select>
                        </div>
                    
                        <div class="form-group">
                            <label for="edit_quantity">Quantity</label>
                            <input type="text" id="edit_quantity" name="quantity">
                        </div>
                    </div>
                
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_expiration_date">Expiration Date</label>
                            <input type="date" id="edit_expiration_date" name="expiration_date">
                        </div>
                    
                        <div class="form-group">
                            <label for="edit_notes">Notes (Optional)</label>
                            <input type="text" id="edit_notes" name="notes" placeholder="e.g., Organic, Brand name">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editIngredientModal')">
                        Cancel
                    </button>
                    <button type="submit" name="edit_ingredient" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
        
    <!-- Barcode Scanner Modal -->
    <div id="scannerModal" class="modal" style="display: none;">
        <div class="modal-content modal-lg" style="max-width: 900px; max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header">
                <h2><i class="fas fa-barcode" style="color: #10b981;"></i> Scan Barcode</h2>
                <button class="modal-close" onclick="closeScanner()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body" style="overflow-y: auto; flex: 1; padding: 0;">
                <!-- Scanner View -->
                <div id="scannerView" style="display: block;">
                    <div class="scanner-container" style="height: 350px; background: #1a1a1a; position: relative;">
                        <div id="scanner" style="width: 100%; height: 100%;"></div>
                        <div class="scanner-overlay">
                            <div class="scanner-frame"></div>
                        </div>
                        <div class="scanner-instructions">
                            <i class="fas fa-camera"></i> Position barcode within the frame
                        </div>
                    </div>
                    
                    <div class="scanner-controls">
                        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                            <button class="btn btn-outline" id="toggleCamera">
                                <i class="fas fa-sync-alt"></i> Switch Camera
                            </button>
                            <button class="btn btn-outline" id="manualEntry">
                                <i class="fas fa-keyboard"></i> Manual Entry
                            </button>
                            <button class="btn btn-outline" id="uploadImage">
                                <i class="fas fa-image"></i> Upload Image
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Scan Result View -->
                <div id="scanResultView" style="display: none; padding: 20px;">
                    <div class="scan-result-header">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>Product Found!</h3>
                        <p>Review the details below and add to your kitchen</p>
                    </div>
                    
                    <div id="productInfoContainer" class="product-info" style="margin: 0 0 20px 0;">
                        <!-- Product info will be loaded here -->
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading product information...</p>
                        </div>
                    </div>
                    
                    <div class="scanned-product-actions">
                        <button class="btn btn-outline" onclick="rescanProduct()">
                            <i class="fas fa-redo"></i> Rescan
                        </button>
                        <button class="btn btn-primary" id="addScannedProduct">
                            <i class="fas fa-plus"></i> Add to Kitchen
                        </button>
                    </div>
                </div>
                
                <!-- Manual Entry View -->
                <div id="manualEntryView" style="display: none; padding: 20px;">
                    <div class="manual-entry-header">
                        <div class="manual-icon">
                            <i class="fas fa-keyboard"></i>
                        </div>
                        <h3>Manual Entry</h3>
                        <p>Enter the barcode number manually</p>
                    </div>
                    
                    <div class="manual-entry-form">
                        <div class="form-group">
                            <label for="manualBarcode">Barcode Number</label>
                            <input type="text" id="manualBarcode" class="form-input" placeholder="e.g., 5901234123457" style="text-align: center; font-size: 18px; letter-spacing: 2px;">
                        </div>
                        
                        <div class="manual-entry-actions">
                            <button class="btn btn-outline" onclick="cancelManualEntry()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button class="btn btn-primary" onclick="submitManualBarcode()">
                                <i class="fas fa-search"></i> Lookup Product
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QuaggaJS for barcode scanning -->
    <script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
    <script src="js/kitchen.js"></script>
    <script>
        // Mobile menu functionality ONLY
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

        // Simple modal functions (these are NOT in kitchen.js)
        function openAddModal() {
            document.getElementById('addIngredientModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        });
    </script>
</body>
</html>