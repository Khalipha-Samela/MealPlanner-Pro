<?php
session_start();

require_once 'config/database.php';

// Clear remember me token if it exists
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    try {
        // Delete token from database
        $stmt = $pdo->prepare("DELETE FROM remember_me_tokens WHERE token = ?");
        $stmt->execute([$token]);
    } catch(PDOException $e) {
        error_log('Logout error: ' . $e->getMessage());
    }
    
    // Clear cookie
    setcookie('remember_token', '', time() - 3600, '/');
}

// Clear session
$_SESSION = array();

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirect to login page
header('Location: login.php');
exit();