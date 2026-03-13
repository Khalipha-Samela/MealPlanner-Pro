<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to save recipes']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$user_id = $_SESSION['user_id'];
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$ingredients = trim($_POST['ingredients'] ?? '');
$instructions = trim($_POST['instructions'] ?? '');
$cooking_time = trim($_POST['cooking_time'] ?? '');
$difficulty = trim($_POST['difficulty'] ?? 'easy');
$from_suggestion = $_POST['from_suggestion'] ?? 'false';

// Parse cooking time to get prep_time and cook_time
$prep_time = 0;
$cook_time = 0;

if (!empty($cooking_time)) {
    // Extract numbers from cooking time (e.g., "20 mins" -> 20)
    preg_match('/(\d+)/', $cooking_time, $matches);
    $total_minutes = isset($matches[1]) ? (int)$matches[1] : 0;
    
    // Split roughly half for prep and half for cook
    $prep_time = ceil($total_minutes / 2);
    $cook_time = floor($total_minutes / 2);
}

// Validate required fields
if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Recipe title is required']);
    exit();
}

if (empty($ingredients)) {
    echo json_encode(['success' => false, 'message' => 'Ingredients are required']);
    exit();
}

if (empty($instructions)) {
    echo json_encode(['success' => false, 'message' => 'Instructions are required']);
    exit();
}

try {
    // Check if recipe already exists for this user
    $stmt = $pdo->prepare("SELECT id FROM recipes WHERE user_id = ? AND title = ?");
    $stmt->execute([$user_id, $title]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You already have a recipe with this name']);
        exit();
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Insert the recipe into the recipes table (matching your schema)
    $stmt = $pdo->prepare("
        INSERT INTO recipes (
            user_id, 
            title, 
            description, 
            instructions, 
            prep_time, 
            cook_time, 
            servings, 
            category, 
            difficulty, 
            source,
            image_url
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    // Set default values
    $servings = 4; // Default servings
    $category = 'Uncategorized';
    $source = 'suggestion';
    $image_url = ''; // Empty for now
    
    $stmt->execute([
        $user_id,
        $title,
        $description,
        $instructions,
        $prep_time,
        $cook_time,
        $servings,
        $category,
        ucfirst($difficulty), // Capitalize first letter
        $source,
        $image_url
    ]);
    
    $recipe_id = $pdo->lastInsertId();
    
    // Now insert the ingredients into recipe_ingredients table
    // Split the ingredients string by new lines
    $ingredient_lines = explode("\n", $ingredients);
    
    $ingredientStmt = $pdo->prepare("
        INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity) 
        VALUES (?, ?, ?)
    ");
    
    foreach ($ingredient_lines as $line) {
        $line = trim($line);
        if (!empty($line)) {
            // Try to split ingredient name and quantity if possible
            $ingredient_name = $line;
            $quantity = '';
            
            // Simple parsing - if there's a number at the beginning, treat it as quantity
            if (preg_match('/^([\d\s\/\.]+)\s*(.+)$/', $line, $matches)) {
                $quantity = trim($matches[1]);
                $ingredient_name = trim($matches[2]);
            }
            
            $ingredientStmt->execute([$recipe_id, $ingredient_name, $quantity]);
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => '✨ Recipe added to your collection!',
        'recipe_id' => $recipe_id
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error saving recipe: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>