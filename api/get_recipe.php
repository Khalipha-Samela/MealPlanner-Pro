<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$recipe_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$recipe_id) {
    echo json_encode(['error' => 'Invalid recipe ID']);
    exit();
}

try {
    // Get recipe details
    $stmt = $pdo->prepare("SELECT * FROM recipes WHERE id = ? AND user_id = ?");
    $stmt->execute([$recipe_id, $user_id]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recipe) {
        echo json_encode(['error' => 'Recipe not found']);
        exit();
    }
    
    // Get ingredients
    $stmt = $pdo->prepare("SELECT * FROM recipe_ingredients WHERE recipe_id = ?");
    $stmt->execute([$recipe_id]);
    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'recipe' => $recipe,
        'ingredients' => $ingredients
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>