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

    <!-- ===================== NAVBAR ===================== -->
    <nav class="navbar" id="navbar">
        <div class="container">
            <a href="/" class="logo">
                <i class="fas fa-utensils"></i>
                <span>MealPlanner Pro</span>
            </a>

            <div class="hamburger" id="hamburger" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </div>

            <div class="nav-links" id="navLinks">
                <a href="#features">Features</a>
                <a href="#how-it-works">How It Works</a>
                <a href="login.php" class="btn btn-outline">Login</a>
                <a href="register.php" class="btn btn-primary">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- ===================== HERO ===================== -->
    <header class="hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-eyebrow">
                    <i class="fas fa-leaf"></i>
                    AI-Powered Kitchen Management
                </div>
                <h1>Stop Wasting Food,<br><em>Start Eating Smart</em></h1>
                <p class="hero-subtitle">AI-powered meal planning that uses what you have, suggests delicious meals, and creates perfect grocery lists — all in minutes.</p>
                <div class="hero-cta">
                    <a href="register.php" class="btn btn-primary btn-large">Start Free Trial</a>
                    <a href="#features" class="btn btn-outline btn-large">See Features</a>
                </div>
            </div>

            <div class="hero-image">
                <div class="dashboard-preview">
                    <div class="preview-header">
                        <div class="preview-title">My Kitchen</div>
                        <div class="preview-nav">
                            <span>Ingredients</span>
                            <span>Meal Plan</span>
                            <span class="active">Grocery List</span>
                        </div>
                    </div>
                    <div class="preview-content">
                        <div class="preview-item">🥦 Broccoli &mdash; 2 pieces</div>
                        <div class="preview-item">🍗 Chicken Breast &mdash; 4 pieces</div>
                        <div class="preview-item">🍚 Basmati Rice &mdash; 1 kg</div>
                        <div class="preview-item">🥛 Whole Milk &mdash; 1 L</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- ===================== FEATURES ===================== -->
    <section id="features" class="features">
        <div class="container">
            <div class="section-header">
                <span class="section-eyebrow">What's included</span>
                <h2>Everything Your Kitchen Needs</h2>
                <p>Four powerful tools working together so you spend less time planning and more time enjoying food.</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-camera"></i>
                    </div>
                    <h3>Smart Ingredient Scanner</h3>
                    <p>Photograph your fridge contents or scan barcodes to automatically add items to your inventory — no typing required.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h3>AI Meal Suggestions</h3>
                    <p>Get recipe recommendations based on what you already have and what's closest to expiring, minimising waste effortlessly.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>Weekly Meal Plans</h3>
                    <p>Automatically generate balanced weekly plans tailored to your dietary preferences, calorie goals, and cooking schedule.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3>Smart Grocery Lists</h3>
                    <p>Generate optimised shopping lists with only what you need, organised by store section so every trip is faster.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================== HOW IT WORKS ===================== -->
    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <div class="section-header">
                <span class="section-eyebrow">Getting started</span>
                <h2>Up and Running in Minutes</h2>
                <p>Four simple steps to a smarter, less wasteful kitchen.</p>
            </div>

            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Add Your Ingredients</h3>
                    <p>Photograph or manually add fridge and pantry items with expiration dates. Our system organises everything automatically.</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Get Smart Suggestions</h3>
                    <p>Our AI surfaces meals you can cook right now, or with just a handful of extra items from the shops.</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Generate a Meal Plan</h3>
                    <p>Create a full weekly plan that balances nutrition, variety, and how much time you actually have to cook.</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Shop & Cook Smart</h3>
                    <p>Get an optimised grocery list and cook with clear, step-by-step instructions built right into the app.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================== FOOTER ===================== -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <i class="fas fa-utensils"></i>
                        MealPlanner Pro
                    </div>
                    <p>Intelligent meal planning to reduce food waste, save money, and make every meal count.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>
                    <a href="#features">Features</a>
                    <a href="#how-it-works">How It Works</a>
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
    <script>
        // Navbar scroll shadow
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            navbar.classList.toggle('scrolled', window.scrollY > 20);
        });
    </script>
</body>
</html>