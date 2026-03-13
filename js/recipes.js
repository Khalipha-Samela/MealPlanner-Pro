// recipes.js
let ingredientCount = 1;
let currentPreviewRecipeId = null;
const recipeAPI = new RecipeAPI();

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing...');
    initializeEventListeners();
    initializeKeyboardShortcuts();
    
    // Check for message from redirect
    const urlParams = new URLSearchParams(window.location.search);
    const message = urlParams.get('message');
    if (message) {
        showNotification(decodeURIComponent(message), 'success');
        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});

function initializeEventListeners() {
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    });

    // Add to meal plan from view modal
    const addToPlanBtn = document.getElementById('addToPlanFromView');
    if (addToPlanBtn) {
        addToPlanBtn.addEventListener('click', function() {
            const recipeId = this.dataset.recipeId;
            if (recipeId) {
                addToMealPlan(recipeId);
            }
        });
    }

    // Import from preview button
    const importBtn = document.getElementById('importFromPreviewBtn');
    if (importBtn) {
        importBtn.addEventListener('click', function() {
            if (currentPreviewRecipeId) {
                importAPIRecipe(currentPreviewRecipeId);
            }
        });
    }
}

function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl + N for new recipe
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            showAddRecipeModal();
        }
        
        // Ctrl + F for search
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            document.querySelector('.search-box input')?.focus();
        }
        
        // Ctrl + L for online search
        if (e.ctrlKey && e.key === 'l') {
            e.preventDefault();
            showAPISearchModal();
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
            document.body.style.overflow = '';
        }
    });
}

// Recipe CRUD Operations
function showAddRecipeModal() {
    console.log('Showing add recipe modal');
    const modal = document.getElementById('addRecipeModal');
    if (!modal) {
        console.error('Add recipe modal not found');
        return;
    }
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Focus on first input
    setTimeout(() => {
        const titleInput = document.getElementById('title');
        if (titleInput) titleInput.focus();
    }, 100);
}

function hideAddRecipeModal() {
    console.log('Hiding add recipe modal');
    const modal = document.getElementById('addRecipeModal');
    if (!modal) return;
    
    modal.style.display = 'none';
    document.body.style.overflow = '';
    
    // Reset form
    const form = document.getElementById('recipeForm');
    if (form) form.reset();
    
    // Reset ingredients to just one row
    resetIngredients();
}

function resetIngredients() {
    const container = document.getElementById('ingredients-container');
    if (!container) return;
    
    container.innerHTML = `
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
    `;
    ingredientCount = 1;
}

function addIngredientField() {
    ingredientCount++;
    const container = document.getElementById('ingredients-container');
    if (!container) return;
    
    const newRow = document.createElement('div');
    newRow.className = 'ingredient-row';
    newRow.innerHTML = `
        <div class="form-group">
            <input type="text" name="ingredient_name[]" 
                   placeholder="Ingredient name" 
                   required
                   class="form-input">
        </div>
        <div class="form-group">
            <input type="text" name="ingredient_quantity[]" 
                   placeholder="Quantity"
                   class="form-input">
        </div>
        <div class="form-group action-group">
            <button type="button" class="btn-icon delete" 
                    onclick="removeIngredientField(this)" 
                    title="Remove ingredient">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(newRow);
    
    // Enable delete button on first row if there are multiple rows
    if (ingredientCount > 1) {
        const firstDeleteBtn = container.querySelector('.ingredient-row:first-child .btn-icon.delete');
        if (firstDeleteBtn) {
            firstDeleteBtn.disabled = false;
        }
    }
    
    // Scroll to the new field
    newRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function removeIngredientField(button) {
    const row = button.closest('.ingredient-row');
    if (!row) return;
    
    if (ingredientCount > 1) {
        row.remove();
        ingredientCount--;
        
        // Disable delete button on first row if only one row remains
        if (ingredientCount === 1) {
            const container = document.getElementById('ingredients-container');
            const firstDeleteBtn = container.querySelector('.ingredient-row:first-child .btn-icon.delete');
            if (firstDeleteBtn) {
                firstDeleteBtn.disabled = true;
            }
        }
    }
}

async function viewRecipe(recipeId) {
    console.log('Viewing recipe:', recipeId);
    const modal = document.getElementById('viewRecipeModal');
    const titleEl = document.getElementById('viewRecipeTitle');
    const contentEl = document.getElementById('viewRecipeContent');
    const addToPlanBtn = document.getElementById('addToPlanFromView');
    
    if (!modal || !titleEl || !contentEl) {
        console.error('View modal elements not found');
        return;
    }
    
    // Show loading state
    titleEl.textContent = 'Loading...';
    contentEl.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="loading-spinner"></div><p style="margin-top: 20px;">Loading recipe details...</p></div>';
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    try {
        // Fetch recipe details from server
        const response = await fetch(`api/get_recipe.php?id=${recipeId}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (!data || !data.recipe) {
            throw new Error('Recipe not found');
        }
        
        const recipe = data.recipe;
        const ingredients = data.ingredients || [];
        
        titleEl.textContent = recipe.title;
        if (addToPlanBtn) addToPlanBtn.dataset.recipeId = recipeId;
        
        // Calculate total time
        const totalTime = (parseInt(recipe.prep_time) || 0) + (parseInt(recipe.cook_time) || 0);
        
        // Format instructions
        const instructions = recipe.instructions ? 
            recipe.instructions.split('\n').filter(step => step.trim() !== '') : [];
        
        // Build content
        let content = `
            <div class="recipe-view">
                <div class="recipe-meta-view" style="display: flex; flex-wrap: wrap; gap: 10px; padding: 15px; background: var(--gray-50); border-radius: var(--radius-lg); margin-bottom: 20px;">
                    ${totalTime > 0 ? `
                        <span class="meta-item" style="display: flex; align-items: center; gap: 8px; background: white; padding: 8px 12px; border-radius: var(--radius); box-shadow: var(--shadow-sm);">
                            <i class="fas fa-clock" style="color: var(--primary);"></i> Total: ${totalTime} min
                            ${recipe.prep_time ? `<span style="font-size: 0.8rem; color: var(--gray-500); margin-left: 4px;">(Prep: ${recipe.prep_time}m</span>` : ''}
                            ${recipe.cook_time ? `<span style="font-size: 0.8rem; color: var(--gray-500);">${recipe.prep_time ? ', ' : '('}Cook: ${recipe.cook_time}m)</span>` : ''}
                        </span>
                    ` : ''}
                    ${recipe.servings ? `
                        <span class="meta-item" style="display: flex; align-items: center; gap: 8px; background: white; padding: 8px 12px; border-radius: var(--radius); box-shadow: var(--shadow-sm);">
                            <i class="fas fa-users" style="color: var(--primary);"></i> Serves: ${recipe.servings}
                        </span>
                    ` : ''}
                    ${recipe.category ? `
                        <span class="meta-item" style="display: flex; align-items: center; gap: 8px; background: white; padding: 8px 12px; border-radius: var(--radius); box-shadow: var(--shadow-sm);">
                            <i class="fas fa-tag" style="color: var(--primary);"></i> ${recipe.category}
                        </span>
                    ` : ''}
                    ${recipe.difficulty ? `
                        <span class="meta-item" style="display: flex; align-items: center; gap: 8px; background: white; padding: 8px 12px; border-radius: var(--radius); box-shadow: var(--shadow-sm);">
                            <i class="fas fa-signal" style="color: var(--primary);"></i> ${recipe.difficulty}
                        </span>
                    ` : ''}
                </div>
                
                ${recipe.image_url ? `
                    <img src="${escapeHtml(recipe.image_url)}" alt="${escapeHtml(recipe.title)}" 
                         style="width: 100%; max-height: 300px; object-fit: cover; border-radius: var(--radius-lg); margin-bottom: 20px;">
                ` : ''}
                
                ${recipe.description ? `
                    <div style="margin-bottom: 20px;">
                        <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 10px; color: var(--gray-800);">Description</h3>
                        <div style="background: var(--gray-50); padding: 15px; border-radius: var(--radius-lg); line-height: 1.6;">
                            ${nl2br(escapeHtml(recipe.description))}
                        </div>
                    </div>
                ` : ''}
                
                <div style="margin-bottom: 20px;">
                    <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 10px; color: var(--gray-800);">Ingredients</h3>
                    <ul style="list-style: none; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px;">
        `;
        
        // Add ingredients
        if (ingredients.length > 0) {
            ingredients.forEach(ing => {
                content += `
                    <li style="padding: 10px; background: var(--gray-50); border-radius: var(--radius); border: 1px solid var(--gray-200);">
                        ${ing.quantity ? `<strong style="color: var(--primary-dark); margin-right: 5px;">${escapeHtml(ing.quantity)}</strong> ` : ''}
                        ${escapeHtml(ing.ingredient_name)}
                    </li>
                `;
            });
        } else {
            content += '<li style="padding: 10px; background: var(--gray-50); border-radius: var(--radius);">No ingredients listed</li>';
        }
        
        content += `
                    </ul>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 10px; color: var(--gray-800);">Instructions</h3>
        `;
        
        // Add instructions
        if (instructions.length > 0) {
            content += '<ol style="list-style: none; padding: 0; counter-reset: instruction;">';
            instructions.forEach(step => {
                if (step.trim()) {
                    content += `
                        <li style="counter-increment: instruction; padding: 15px 15px 15px 45px; position: relative; background: var(--gray-50); border-radius: var(--radius-lg); margin-bottom: 10px; line-height: 1.6;">
                            <span style="position: absolute; left: 10px; top: 12px; width: 28px; height: 28px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 600;">${instructions.indexOf(step) + 1}</span>
                            ${escapeHtml(step)}
                        </li>
                    `;
                }
            });
            content += '</ol>';
        } else {
            content += '<p style="padding: 15px; background: var(--gray-50); border-radius: var(--radius-lg);">No instructions available.</p>';
        }
        
        content += `
                </div>
            </div>
        `;
        
        contentEl.innerHTML = content;
        
    } catch (error) {
        console.error('Error loading recipe:', error);
        contentEl.innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <div style="width: 80px; height: 80px; background: var(--danger-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: var(--danger);"></i>
                </div>
                <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 10px;">Failed to load recipe</h3>
                <p style="color: var(--gray-500); margin-bottom: 20px;">${error.message}</p>
                <button class="btn btn-primary" onclick="viewRecipe(${recipeId})">
                    Try Again
                </button>
            </div>
        `;
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Helper function for newlines
function nl2br(text) {
    if (!text) return '';
    return text.replace(/\n/g, '<br>');
}

function hideViewRecipeModal() {
    const modal = document.getElementById('viewRecipeModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function addToMealPlan(recipeId) {
    window.location.href = `mealplan.php?add_recipe=${recipeId}`;
}

function editRecipe(recipeId) {
    alert('Edit feature coming soon!');
}

// API Integration Functions
function showAPISearchModal() {
    console.log('Showing API search modal');
    const modal = document.getElementById('apiSearchModal');
    if (!modal) {
        console.error('API search modal not found');
        return;
    }
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    const searchInput = document.getElementById('apiSearchQuery');
    if (searchInput) {
        setTimeout(() => searchInput.focus(), 100);
    }
    
    // Clear previous results
    const apiResults = document.getElementById('apiResults');
    const randomRecipes = document.getElementById('randomRecipes');
    if (apiResults) apiResults.style.display = 'none';
    if (randomRecipes) randomRecipes.style.display = 'none';
}

function hideAPISearchModal() {
    const modal = document.getElementById('apiSearchModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

async function performAPISearch() {
    const query = document.getElementById('apiSearchQuery').value.trim();
    if (!query) {
        showNotification('Please enter a search term', 'warning');
        return;
    }

    const resultsDiv = document.getElementById('apiResultsList');
    const apiResults = document.getElementById('apiResults');
    const randomRecipes = document.getElementById('randomRecipes');
    
    if (!resultsDiv || !apiResults) return;
    
    resultsDiv.innerHTML = '<div style="text-align: center; padding: 20px;"><div class="loading-spinner"></div><p style="margin-top: 10px;">Searching...</p></div>';
    apiResults.style.display = 'block';
    if (randomRecipes) randomRecipes.style.display = 'none';
    
    try {
        const recipes = await recipeAPI.searchRecipes(query, { limit: 12 });
        
        if (recipes.length === 0) {
            resultsDiv.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--gray-500);">No recipes found. Try a different search term.</p>';
            return;
        }
        
        displayAPIResults(recipes, resultsDiv);
        
    } catch (error) {
        console.error('Search error:', error);
        resultsDiv.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--danger);">Error searching for recipes. Please try again.</p>';
    }
}

async function getRandomRecipes() {
    const randomDiv = document.getElementById('randomRecipesList');
    const randomRecipes = document.getElementById('randomRecipes');
    const apiResults = document.getElementById('apiResults');
    
    if (!randomDiv || !randomRecipes) return;
    
    randomDiv.innerHTML = '<div style="text-align: center; padding: 20px;"><div class="loading-spinner"></div><p style="margin-top: 10px;">Loading random recipes...</p></div>';
    randomRecipes.style.display = 'block';
    if (apiResults) apiResults.style.display = 'none';
    
    try {
        const recipes = await recipeAPI.getRandomRecipes(8);
        
        if (recipes.length === 0) {
            randomDiv.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--gray-500);">Could not load random recipes. Please try again.</p>';
            return;
        }
        
        displayAPIResults(recipes, randomDiv);
        
    } catch (error) {
        console.error('Random recipes error:', error);
        randomDiv.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--danger);">Error loading random recipes. Please try again.</p>';
    }
}

function displayAPIResults(recipes, container) {
    let content = '';
    recipes.forEach(recipe => {
        content += `
            <div style="background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-lg); padding: 15px; transition: all 0.2s ease; hover: transform: translateY(-2px); hover: box-shadow: var(--shadow-md);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h4 style="font-size: 1rem; font-weight: 600; margin: 0; flex: 1;">${escapeHtml(recipe.title)}</h4>
                    <span style="background: var(--primary-bg); color: var(--primary-dark); padding: 4px 8px; border-radius: 999px; font-size: 0.8rem; font-weight: 500; white-space: nowrap;">${recipe.readyInMinutes || '?'} min</span>
                </div>
                ${recipe.image ? `
                    <img src="${recipe.image}" alt="${escapeHtml(recipe.title)}" 
                         style="width: 100%; height: 140px; object-fit: cover; border-radius: var(--radius); margin: 10px 0;">
                ` : ''}
                <div style="display: flex; gap: 8px; margin-top: 10px;">
                    <button class="btn btn-outline btn-sm" onclick="previewAPIRecipe('${recipe.id}')" style="flex: 1;">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="importAPIRecipe('${recipe.id}')" style="flex: 1;">
                        <i class="fas fa-download"></i> Import
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = content;
}

async function previewAPIRecipe(recipeId) {
    console.log('Previewing API recipe:', recipeId);
    currentPreviewRecipeId = recipeId;
    
    const previewContent = document.getElementById('apiPreviewContent');
    const previewTitle = document.getElementById('apiPreviewTitle');
    const previewModal = document.getElementById('apiPreviewModal');
    
    if (!previewContent || !previewTitle || !previewModal) {
        console.error('Preview modal elements not found');
        return;
    }
    
    previewTitle.textContent = 'Loading...';
    previewContent.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="loading-spinner"></div><p style="margin-top: 20px;">Loading recipe details...</p></div>';
    previewModal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    try {
        const recipe = await recipeAPI.getRecipeDetails(recipeId);
        if (!recipe) {
            throw new Error('Recipe not found');
        }
        
        previewTitle.textContent = recipe.title;
        
        let ingredientsHtml = '';
        if (recipe.ingredients && recipe.ingredients.length > 0) {
            ingredientsHtml = '<ul style="list-style: none; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px;">';
            recipe.ingredients.forEach(ing => {
                ingredientsHtml += `<li style="padding: 10px; background: var(--gray-50); border-radius: var(--radius); border: 1px solid var(--gray-200);"><strong style="color: var(--primary-dark); margin-right: 5px;">${escapeHtml(ing.quantity || '')}</strong> ${escapeHtml(ing.name)}</li>`;
            });
            ingredientsHtml += '</ul>';
        } else {
            ingredientsHtml = '<p style="padding: 15px; background: var(--gray-50); border-radius: var(--radius);">No ingredients listed</p>';
        }
        
        // Format instructions
        let instructionsHtml = '';
        if (recipe.instructions) {
            const instructions = recipe.instructions.split('\n').filter(step => step.trim());
            if (instructions.length > 0) {
                instructionsHtml = '<ol style="list-style: none; padding: 0; counter-reset: instruction;">';
                instructions.forEach(step => {
                    if (step.trim()) {
                        instructionsHtml += `
                            <li style="counter-increment: instruction; padding: 15px 15px 15px 45px; position: relative; background: var(--gray-50); border-radius: var(--radius-lg); margin-bottom: 10px; line-height: 1.6;">
                                <span style="position: absolute; left: 10px; top: 12px; width: 28px; height: 28px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 600;">${instructions.indexOf(step) + 1}</span>
                                ${escapeHtml(step)}
                            </li>
                        `;
                    }
                });
                instructionsHtml += '</ol>';
            } else {
                instructionsHtml = '<p style="padding: 15px; background: var(--gray-50); border-radius: var(--radius);">No instructions available.</p>';
            }
        } else {
            instructionsHtml = '<p style="padding: 15px; background: var(--gray-50); border-radius: var(--radius);">No instructions available.</p>';
        }
        
        const totalTime = (recipe.prep_time || 0) + (recipe.cook_time || 0);
        
        const content = `
            <div class="recipe-view">
                <div style="display: flex; flex-wrap: wrap; gap: 10px; padding: 15px; background: var(--gray-50); border-radius: var(--radius-lg); margin-bottom: 20px;">
                    ${totalTime > 0 ? `
                        <span style="display: flex; align-items: center; gap: 8px; background: white; padding: 8px 12px; border-radius: var(--radius); box-shadow: var(--shadow-sm);">
                            <i class="fas fa-clock" style="color: var(--primary);"></i> Total: ${totalTime} min
                        </span>
                    ` : ''}
                    ${recipe.servings ? `
                        <span style="display: flex; align-items: center; gap: 8px; background: white; padding: 8px 12px; border-radius: var(--radius); box-shadow: var(--shadow-sm);">
                            <i class="fas fa-users" style="color: var(--primary);"></i> Serves: ${recipe.servings}
                        </span>
                    ` : ''}
                    <span style="display: flex; align-items: center; gap: 8px; background: white; padding: 8px 12px; border-radius: var(--radius); box-shadow: var(--shadow-sm);">
                        <i class="fas fa-globe" style="color: var(--primary);"></i> TheMealDB
                    </span>
                </div>
                
                ${recipe.image ? `
                    <img src="${recipe.image}" alt="${escapeHtml(recipe.title)}" 
                         style="width: 100%; max-height: 300px; object-fit: cover; border-radius: var(--radius-lg); margin-bottom: 20px;">
                ` : ''}
                
                <div style="margin-bottom: 20px;">
                    <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 10px;">Ingredients</h3>
                    ${ingredientsHtml}
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 10px;">Instructions</h3>
                    ${instructionsHtml}
                </div>
                
                ${recipe.description ? `
                    <div style="margin-bottom: 20px;">
                        <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 10px;">Description</h3>
                        <div style="background: var(--gray-50); padding: 15px; border-radius: var(--radius-lg);">
                            ${escapeHtml(recipe.description)}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
        
        previewContent.innerHTML = content;
        
    } catch (error) {
        console.error('Preview error:', error);
        previewContent.innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <div style="width: 80px; height: 80px; background: var(--danger-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: var(--danger);"></i>
                </div>
                <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 10px;">Failed to load recipe</h3>
                <p style="color: var(--gray-500); margin-bottom: 20px;">${error.message}</p>
                <button class="btn btn-primary" onclick="previewAPIRecipe('${recipeId}')">
                    Try Again
                </button>
            </div>
        `;
    }
}

function hideAPIPreviewModal() {
    const modal = document.getElementById('apiPreviewModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    currentPreviewRecipeId = null;
}

function importAPIRecipe(recipeId) {
    if (confirm('Import this recipe to your collection?')) {
        window.location.href = `recipes.php?import_api=${recipeId}`;
    }
}

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    
    let icon = 'info-circle';
    if (type === 'success') icon = 'check-circle';
    if (type === 'error') icon = 'exclamation-circle';
    if (type === 'warning') icon = 'exclamation-triangle';
    
    notification.innerHTML = `
        <i class="fas fa-${icon}"></i>
        ${message}
    `;
    
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    notification.style.animation = 'slideInRight 0.3s ease';
    notification.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Export functions for HTML onclick attributes
window.showAddRecipeModal = showAddRecipeModal;
window.hideAddRecipeModal = hideAddRecipeModal;
window.showAPISearchModal = showAPISearchModal;
window.hideAPISearchModal = hideAPISearchModal;
window.viewRecipe = viewRecipe;
window.hideViewRecipeModal = hideViewRecipeModal;
window.addToMealPlan = addToMealPlan;
window.editRecipe = editRecipe;
window.addIngredientField = addIngredientField;
window.removeIngredientField = removeIngredientField;
window.performAPISearch = performAPISearch;
window.getRandomRecipes = getRandomRecipes;
window.previewAPIRecipe = previewAPIRecipe;
window.hideAPIPreviewModal = hideAPIPreviewModal;
window.importAPIRecipe = importAPIRecipe;