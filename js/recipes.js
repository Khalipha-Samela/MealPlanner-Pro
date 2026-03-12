// recipes.js
let ingredientCount = 1;
let currentPreviewRecipeId = null;
const recipeAPI = new RecipeAPI();

document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    initializeKeyboardShortcuts();
});

function initializeEventListeners() {
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
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
        }
    });
}

// Recipe CRUD Operations
function showAddRecipeModal() {
    const modal = document.getElementById('addRecipeModal');
    modal.style.display = 'block';
    
    // Prevent body scrolling when modal is open
    document.body.style.overflow = 'hidden';
    
    // Focus on first input
    setTimeout(() => {
        document.getElementById('title').focus();
    }, 100);
}

function hideAddRecipeModal() {
    const modal = document.getElementById('addRecipeModal');
    modal.style.display = 'none';
    
    // Restore body scrolling
    document.body.style.overflow = '';
    
    // Reset form
    const form = document.getElementById('recipeForm');
    if (form) form.reset();
    
    // Reset ingredients to just one row
    const container = document.getElementById('ingredients-container');
    if (container) {
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
    }
    ingredientCount = 1;
}

function addIngredientField() {
    ingredientCount++;
    const container = document.getElementById('ingredients-container');
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
    if (row && ingredientCount > 1) {
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
    const modal = document.getElementById('viewRecipeModal');
    const titleEl = document.getElementById('viewRecipeTitle');
    const contentEl = document.getElementById('viewRecipeContent');
    const addToPlanBtn = document.getElementById('addToPlanFromView');
    
    if (!modal || !titleEl || !contentEl) return;
    
    // Show loading state
    titleEl.textContent = 'Loading...';
    contentEl.innerHTML = '<div class="loading-spinner"></div><p style="text-align: center;">Loading recipe details...</p>';
    modal.style.display = 'block';
    
    try {
        // Fetch recipe details from server
        const response = await fetch(`api/get_recipe.php?id=${recipeId}`);
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
        
        // Format instructions - split by newlines and filter empty lines
        const instructions = recipe.instructions ? 
            recipe.instructions.split('\n').filter(step => step.trim() !== '') : [];
        
        // Build content
        let content = `
            <div class="recipe-view">
                <div class="recipe-meta-view">
                    ${totalTime > 0 ? `
                        <span class="meta-item">
                            <i class="fas fa-clock"></i> Total: ${totalTime} min
                            ${recipe.prep_time ? `<span class="meta-detail">(Prep: ${recipe.prep_time}m</span>` : ''}
                            ${recipe.cook_time ? `<span class="meta-detail">${recipe.prep_time ? ', ' : '('}Cook: ${recipe.cook_time}m)</span>` : ''}
                        </span>
                    ` : ''}
                    ${recipe.servings ? `
                        <span class="meta-item">
                            <i class="fas fa-users"></i> Serves: ${recipe.servings}
                        </span>
                    ` : ''}
                    ${recipe.category ? `
                        <span class="meta-item">
                            <i class="fas fa-tag"></i> ${recipe.category}
                        </span>
                    ` : ''}
                    ${recipe.difficulty ? `
                        <span class="meta-item">
                            <i class="fas fa-signal"></i> ${recipe.difficulty}
                        </span>
                    ` : ''}
                </div>
                
                ${recipe.image_url ? `
                    <img src="${recipe.image_url}" alt="${escapeHtml(recipe.title)}" 
                         class="recipe-view-image">
                ` : ''}
                
                ${recipe.description ? `
                    <div class="recipe-section">
                        <h3>Description</h3>
                        <div class="recipe-description-full">
                            ${nl2br(escapeHtml(recipe.description))}
                        </div>
                    </div>
                ` : ''}
                
                <div class="recipe-section">
                    <h3>Ingredients</h3>
                    <ul class="ingredient-list">
        `;
        
        // Add ingredients
        if (ingredients.length > 0) {
            ingredients.forEach(ing => {
                content += `
                    <li>
                        ${ing.quantity ? `<strong>${escapeHtml(ing.quantity)}</strong> ` : ''}
                        ${escapeHtml(ing.ingredient_name)}
                    </li>
                `;
            });
        } else {
            content += '<li>No ingredients listed</li>';
        }
        
        content += `
                    </ul>
                </div>
                
                <div class="recipe-section">
                    <h3>Instructions</h3>
        `;
        
        // Add instructions
        if (instructions.length > 0) {
            content += '<ol class="instruction-list">';
            instructions.forEach(step => {
                if (step.trim()) {
                    content += `<li>${escapeHtml(step)}</li>`;
                }
            });
            content += '</ol>';
        } else {
            content += '<p>No instructions available.</p>';
        }
        
        content += `
                </div>
            </div>
        `;
        
        contentEl.innerHTML = content;
        
    } catch (error) {
        console.error('Error loading recipe:', error);
        contentEl.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>Failed to load recipe</h3>
                <p>${error.message}</p>
                <button class="btn btn-primary btn-sm" onclick="viewRecipe(${recipeId})">
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
    if (modal) modal.style.display = 'none';
}

function addToMealPlan(recipeId) {
    // Redirect to meal plan page with recipe pre-selected
    window.location.href = `mealplan.php?add_recipe=${recipeId}`;
}

function editRecipe(recipeId) {
    // Redirect to edit page (you'll need to create this)
    alert('Edit feature coming soon!');
    // window.location.href = `edit_recipe.php?id=${recipeId}`;
}

// API Integration Functions
function showAPISearchModal() {
    const modal = document.getElementById('apiSearchModal');
    const searchInput = document.getElementById('apiSearchQuery');
    if (modal) modal.style.display = 'block';
    if (searchInput) searchInput.focus();
    
    // Clear previous results
    const apiResults = document.getElementById('apiResults');
    const randomRecipes = document.getElementById('randomRecipes');
    if (apiResults) apiResults.style.display = 'none';
    if (randomRecipes) randomRecipes.style.display = 'none';
}

function hideAPISearchModal() {
    const modal = document.getElementById('apiSearchModal');
    if (modal) modal.style.display = 'none';
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
    
    resultsDiv.innerHTML = '<div class="loading-spinner"></div><p>Searching...</p>';
    apiResults.style.display = 'block';
    if (randomRecipes) randomRecipes.style.display = 'none';
    
    try {
        const recipes = await recipeAPI.searchRecipes(query, { limit: 12 });
        
        if (recipes.length === 0) {
            resultsDiv.innerHTML = '<p class="text-muted">No recipes found. Try a different search term.</p>';
            return;
        }
        
        displayAPIResults(recipes, resultsDiv);
        
    } catch (error) {
        console.error('Search error:', error);
        resultsDiv.innerHTML = '<p class="text-danger">Error searching for recipes. Please try again.</p>';
    }
}

async function getRandomRecipes() {
    const randomDiv = document.getElementById('randomRecipesList');
    const randomRecipes = document.getElementById('randomRecipes');
    const apiResults = document.getElementById('apiResults');
    
    if (!randomDiv || !randomRecipes) return;
    
    randomDiv.innerHTML = '<div class="loading-spinner"></div><p>Loading random recipes...</p>';
    randomRecipes.style.display = 'block';
    if (apiResults) apiResults.style.display = 'none';
    
    try {
        const recipes = await recipeAPI.getRandomRecipes(8);
        
        if (recipes.length === 0) {
            randomDiv.innerHTML = '<p class="text-muted">Could not load random recipes. Please try again.</p>';
            return;
        }
        
        displayAPIResults(recipes, randomDiv);
        
    } catch (error) {
        console.error('Random recipes error:', error);
        randomDiv.innerHTML = '<p class="text-danger">Error loading random recipes. Please try again.</p>';
    }
}

function displayAPIResults(recipes, container) {
    let content = '';
    recipes.forEach(recipe => {
        content += `
            <div class="api-recipe-card">
                <div class="api-recipe-header">
                    <h4>${escapeHtml(recipe.title)}</h4>
                    <span class="recipe-time">${recipe.readyInMinutes || '?'} min</span>
                </div>
                ${recipe.image ? `
                    <img src="${recipe.image}" alt="${escapeHtml(recipe.title)}" 
                         class="api-recipe-image">
                ` : ''}
                <div class="api-recipe-footer">
                    <button class="btn btn-outline btn-sm" onclick="previewAPIRecipe('${recipe.id}')">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="importAPIRecipe('${recipe.id}')">
                        <i class="fas fa-download"></i> Import
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = content;
}

async function previewAPIRecipe(recipeId) {
    currentPreviewRecipeId = recipeId;
    
    const previewContent = document.getElementById('apiPreviewContent');
    const previewTitle = document.getElementById('apiPreviewTitle');
    const previewModal = document.getElementById('apiPreviewModal');
    
    if (!previewContent || !previewTitle || !previewModal) return;
    
    previewTitle.textContent = 'Loading...';
    previewContent.innerHTML = '<div class="loading-spinner"></div><p style="text-align: center;">Loading recipe details...</p>';
    previewModal.style.display = 'block';
    
    try {
        const recipe = await recipeAPI.getRecipeDetails(recipeId);
        if (!recipe) {
            throw new Error('Recipe not found');
        }
        
        previewTitle.textContent = recipe.title;
        
        let ingredientsHtml = '';
        if (recipe.ingredients && recipe.ingredients.length > 0) {
            ingredientsHtml = '<ul class="ingredient-list">';
            recipe.ingredients.forEach(ing => {
                ingredientsHtml += `<li><strong>${escapeHtml(ing.quantity || '')}</strong> ${escapeHtml(ing.name)}</li>`;
            });
            ingredientsHtml += '</ul>';
        } else {
            ingredientsHtml = '<p>No ingredients listed</p>';
        }
        
        // Format instructions
        let instructionsHtml = '';
        if (recipe.instructions) {
            const instructions = recipe.instructions.split('\n').filter(step => step.trim());
            if (instructions.length > 0) {
                instructionsHtml = '<ol class="instruction-list">';
                instructions.forEach(step => {
                    if (step.trim()) {
                        instructionsHtml += `<li>${escapeHtml(step)}</li>`;
                    }
                });
                instructionsHtml += '</ol>';
            } else {
                instructionsHtml = '<p>No instructions available.</p>';
            }
        } else {
            instructionsHtml = '<p>No instructions available.</p>';
        }
        
        const totalTime = (recipe.prep_time || 0) + (recipe.cook_time || 0);
        
        const content = `
            <div class="recipe-view">
                <div class="recipe-meta-view">
                    ${totalTime > 0 ? `
                        <span class="meta-item">
                            <i class="fas fa-clock"></i> Total: ${totalTime} min
                        </span>
                    ` : ''}
                    ${recipe.servings ? `
                        <span class="meta-item">
                            <i class="fas fa-users"></i> Serves: ${recipe.servings}
                        </span>
                    ` : ''}
                    <span class="meta-item">
                        <i class="fas fa-globe"></i> TheMealDB
                    </span>
                </div>
                
                ${recipe.image ? `
                    <img src="${recipe.image}" alt="${escapeHtml(recipe.title)}" 
                         class="recipe-view-image">
                ` : ''}
                
                <div class="recipe-section">
                    <h3>Ingredients</h3>
                    ${ingredientsHtml}
                </div>
                
                <div class="recipe-section">
                    <h3>Instructions</h3>
                    ${instructionsHtml}
                </div>
                
                ${recipe.description ? `
                    <div class="recipe-section">
                        <h3>Description</h3>
                        <div class="recipe-description-full">
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
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>Failed to load recipe</h3>
                <p>${error.message}</p>
                <button class="btn btn-primary btn-sm" onclick="previewAPIRecipe('${recipeId}')">
                    Try Again
                </button>
            </div>
        `;
    }
}

function hideAPIPreviewModal() {
    const modal = document.getElementById('apiPreviewModal');
    if (modal) modal.style.display = 'none';
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