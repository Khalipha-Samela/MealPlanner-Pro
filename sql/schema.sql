-- Users
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    dietary_preferences TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Remember me tokens
CREATE TABLE remember_me_tokens (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_token ON remember_me_tokens(token);
CREATE INDEX idx_expires ON remember_me_tokens(expires_at);
CREATE INDEX idx_user_id ON remember_me_tokens(user_id);

-- Ingredients
CREATE TABLE ingredients (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    barcode VARCHAR(50),
    quantity VARCHAR(50),
    notes TEXT,
    expiration_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_barcode ON ingredients(barcode);

-- Recipes
CREATE TABLE recipes (
    id SERIAL PRIMARY KEY,
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
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_category ON recipes(category);
CREATE INDEX idx_source ON recipes(source);

-- Recipe ingredients
CREATE TABLE recipe_ingredients (
    id SERIAL PRIMARY KEY,
    recipe_id INT NOT NULL,
    ingredient_name VARCHAR(100) NOT NULL,
    quantity VARCHAR(50),
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
);

-- ENUM for meal types
CREATE TYPE meal_type_enum AS ENUM ('breakfast','lunch','dinner','snack');

-- Meal plans
CREATE TABLE meal_plans (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    meal_type meal_type_enum NOT NULL,
    recipe_id INT,
    custom_meal VARCHAR(200),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE SET NULL
);

-- Grocery lists
CREATE TABLE grocery_lists (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Grocery items
CREATE TABLE grocery_items (
    id SERIAL PRIMARY KEY,
    list_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    quantity VARCHAR(50),
    category VARCHAR(50),
    purchased BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (list_id) REFERENCES grocery_lists(id) ON DELETE CASCADE
);

-- Scanned products
CREATE TABLE scanned_products (
    id SERIAL PRIMARY KEY,
    barcode VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    brand VARCHAR(100),
    default_quantity VARCHAR(50),
    image_url VARCHAR(500),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_scanned_barcode ON scanned_products(barcode);
CREATE INDEX idx_scanned_category ON scanned_products(category);

-- User scans
CREATE TABLE user_scans (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    barcode VARCHAR(50),
    product_id INT,
    scan_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    added_to_kitchen BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES scanned_products(id) ON DELETE SET NULL
);

CREATE INDEX idx_user_scan ON user_scans(user_id, scan_date);

-- Product categories
CREATE TABLE product_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50)
);

INSERT INTO product_categories (name, icon) VALUES
('Vegetables','fa-carrot'),
('Fruits','fa-apple-alt'),
('Meat','fa-drumstick-bite'),
('Dairy','fa-cheese'),
('Grains','fa-bread-slice'),
('Spices','fa-mortar-pestle'),
('Canned Goods','fa-can'),
('Frozen Foods','fa-ice-cream'),
('Beverages','fa-wine-bottle'),
('Snacks','fa-cookie'),
('Baking','fa-egg'),
('Condiments','fa-jar'),
('Other','fa-box');

