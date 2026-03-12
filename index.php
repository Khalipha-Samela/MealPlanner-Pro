<?php
session_start();
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MealPlanner Pro - Intelligent Meal Planning</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <i class="fas fa-utensils"></i>
                <span>MealPlanner Pro</span>
            </div>
            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="#how-it-works">How It Works</a>
                <a href="login.php" class="btn btn-outline">Login</a>
                <a href="register.php" class="btn btn-primary">Get Started</a>
            </div>
        </div>
    </nav>

    <header class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Stop Wasting Food, Start Eating Smart</h1>
                <p class="hero-subtitle">AI-powered meal planning that uses what you have, suggests delicious meals, and creates perfect grocery lists.</p>
                <div class="hero-cta">
                    <a href="register.php" class="btn btn-primary btn-large">Start Free Trial</a>
                    <a href="#features" class="btn btn-outline btn-large">Learn More</a>
                </div>
            </div>
            <div class="hero-image">
                <div class="dashboard-preview">
                    <div class="preview-header">
                        <div class="preview-nav">
                            <span>My Kitchen</span>
                            <span>Meal Plan</span>
                            <span class="active">Grocery List</span>
                        </div>
                    </div>
                    <div class="preview-content">
                        <div class="preview-item">🥦 Broccoli - 2 pieces</div>
                        <div class="preview-item">🍗 Chicken Breast - 4 pieces</div>
                        <div class="preview-item">🍚 Rice - 1kg</div>
                        <div class="preview-item">🥛 Milk - 1L</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section id="features" class="features">
        <div class="container">
            <h2>Smart Features</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-camera"></i>
                    </div>
                    <h3>Smart Ingredient Scanner</h3>
                    <p>Take photos of your fridge contents or scan barcodes to automatically add items to your inventory.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h3>AI Meal Suggestions</h3>
                    <p>Get recipe recommendations based on what you already have and what's about to expire.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>Weekly Meal Plans</h3>
                    <p>Automatically generate balanced weekly meal plans tailored to your dietary preferences.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3>Smart Grocery Lists</h3>
                    <p>Generate optimized shopping lists with only what you need, organized by store sections.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <h2>How It Works</h2>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Add Your Ingredients</h3>
                    <p>Take photos of your fridge and pantry items or manually add them with expiration dates. Our smart system organizes everything for you.</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Get Smart Suggestions</h3>
                    <p>Our AI suggests meals you can cook now or with just a few additional items.</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Generate Meal Plan</h3>
                    <p>Create a weekly meal plan that balances nutrition, variety, and cooking time.</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Shop & Cook Smart</h3>
                    <p>Get optimized grocery lists and cook with step-by-step instructions.</p>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>MealPlanner Pro</h4>
                    <p>Intelligent meal planning to reduce waste and save time.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>
                    <a href="#features">Features</a>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p>support@mealplannerpro.com</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 MealPlanner Pro. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="js/script.js"></script>
</body>
</html>