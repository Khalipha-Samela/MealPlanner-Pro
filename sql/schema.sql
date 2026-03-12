-- Create database
CREATE DATABASE IF NOT EXISTS mealplanner;
USE mealplanner;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    dietary_preferences TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Remember me tokens table
CREATE TABLE IF NOT EXISTS remember_me_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ingredients table
CREATE TABLE ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    barcode VARCHAR(50),
    quantity VARCHAR(50),
    notes TEXT,
    expiration_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_barcode (barcode)
);

-- Recipes table (updated with all columns)
CREATE TABLE recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    instructions TEXT,
    prep_time INT,
    cook_time INT,
    servings INT,
    category VARCHAR(100),
    difficulty VARCHAR(50) DEFAULT 'Medium',
    source VARCHAR(50) DEFAULT 'manual',
    source_id VARCHAR(100),
    image_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_category (category),
    INDEX idx_source (source)
);

-- Recipe ingredients table
CREATE TABLE recipe_ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    ingredient_name VARCHAR(100) NOT NULL,
    quantity VARCHAR(50),
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
);

-- Meal plans table
CREATE TABLE meal_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    meal_type ENUM('breakfast', 'lunch', 'dinner', 'snack') NOT NULL,
    recipe_id INT,
    custom_meal VARCHAR(200),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE SET NULL
);

-- Grocery lists table
CREATE TABLE grocery_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Grocery list items table
CREATE TABLE grocery_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    list_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    quantity VARCHAR(50),
    category VARCHAR(50),
    purchased BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (list_id) REFERENCES grocery_lists(id) ON DELETE CASCADE
);

-- Scanned products (global product database)
CREATE TABLE scanned_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barcode VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    brand VARCHAR(100),
    default_quantity VARCHAR(50),
    image_url VARCHAR(500),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_barcode (barcode),
    INDEX idx_category (category)
);

-- User scan history (track what users have scanned)
CREATE TABLE user_scans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    barcode VARCHAR(50),
    product_id INT,
    scan_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    added_to_kitchen BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES scanned_products(id) ON DELETE SET NULL,
    INDEX idx_user_scan (user_id, scan_date)
);

-- Product categories for better organization
CREATE TABLE product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50)
);

-- Insert some common categories
INSERT INTO product_categories (name, icon) VALUES
('Vegetables', 'fa-carrot'),
('Fruits', 'fa-apple-alt'),
('Meat', 'fa-drumstick-bite'),
('Dairy', 'fa-cheese'),
('Grains', 'fa-bread-slice'),
('Spices', 'fa-mortar-pestle'),
('Canned Goods', 'fa-can'),
('Frozen Foods', 'fa-ice-cream'),
('Beverages', 'fa-wine-bottle'),
('Snacks', 'fa-cookie'),
('Baking', 'fa-egg'),
('Condiments', 'fa-jar'),
('Other', 'fa-box');