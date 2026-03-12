// Make sure all functions are available globally
let selectedDate = '';
let selectedMealType = '';

document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    initializeDragAndDrop();
    
    // Load recipe details when select changes
    const recipeSelect = document.getElementById('recipeSelect');
    if (recipeSelect) {
        recipeSelect.addEventListener('change', loadRecipeDetails);
    }
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
}

// Main function to open add meal modal
function openAddMealModal(date, mealType) {
    selectedDate = date;
    selectedMealType = mealType;
    
    const dateObj = new Date(date + 'T12:00:00');
    const formattedDate = dateObj.toLocaleDateString('en-US', { 
        weekday: 'long', 
        month: 'long', 
        day: 'numeric' 
    });
    
    const mealTypeStr = mealType.charAt(0).toUpperCase() + mealType.slice(1);
    
    const modalTitle = document.getElementById('modalTitle');
    if (modalTitle) {
        modalTitle.innerHTML = `
            <i class="fas fa-${getMealIcon(mealType)}" style="color: ${getMealColor(mealType)}; margin-right: 10px;"></i>
            Add ${mealTypeStr} for ${formattedDate}
        `;
    }
    
    const modalDate = document.getElementById('modalDate');
    const modalMealType = document.getElementById('modalMealType');
    
    if (modalDate) modalDate.value = date;
    if (modalMealType) modalMealType.value = mealType;
    
    // Reset form
    const recipeSelect = document.getElementById('recipeSelect');
    const customMeal = document.getElementById('custom_meal');
    const customMealGroup = document.getElementById('customMealGroup');
    const recipeInfo = document.getElementById('recipeInfo');
    
    if (recipeSelect) recipeSelect.value = '';
    if (customMeal) customMeal.value = '';
    if (customMealGroup) customMealGroup.style.display = 'none';
    if (recipeInfo) recipeInfo.style.display = 'none';
    
    const modal = document.getElementById('addMealModal');
    if (modal) modal.style.display = 'block';
}

// New function to open modal with a specific recipe pre-selected
function openAddMealModalWithRecipe(date, mealType, recipeId) {
    // First open the modal normally
    openAddMealModal(date, mealType);
    
    // Then select the recipe after a short delay to ensure modal is loaded
    setTimeout(() => {
        const recipeSelect = document.getElementById('recipeSelect');
        if (recipeSelect) {
            recipeSelect.value = recipeId;
            // Trigger the change event to load recipe details
            loadRecipeDetails();
            
            // Hide custom meal group if visible
            const customMealGroup = document.getElementById('customMealGroup');
            const recipeInfo = document.getElementById('recipeInfo');
            
            if (customMealGroup) customMealGroup.style.display = 'none';
            if (recipeInfo) recipeInfo.style.display = 'block';
        }
    }, 100);
}

function hideAddMealModal() {
    const modal = document.getElementById('addMealModal');
    if (modal) modal.style.display = 'none';
}

function getMealIcon(mealType) {
    const icons = {
        'breakfast': 'coffee',
        'lunch': 'hamburger',
        'dinner': 'moon',
        'snack': 'cookie'
    };
    return icons[mealType] || 'utensils';
}

function getMealColor(mealType) {
    const colors = {
        'breakfast': '#f59e0b',
        'lunch': '#10b981',
        'dinner': '#3b82f6',
        'snack': '#8b5cf6'
    };
    return colors[mealType] || '#10b981';
}

function toggleCustomMeal() {
    const recipeSelect = document.getElementById('recipeSelect');
    const customMealGroup = document.getElementById('customMealGroup');
    const recipeInfo = document.getElementById('recipeInfo');
    const customMealInput = document.getElementById('custom_meal');
    
    if (!recipeSelect || !customMealGroup || !recipeInfo) return;
    
    if (recipeSelect.value === 'custom') {
        // Custom meal selected
        customMealGroup.style.display = 'block';
        recipeInfo.style.display = 'none';
        recipeSelect.value = '';
        setTimeout(() => {
            if (customMealInput) customMealInput.focus();
        }, 100);
    } else if (recipeSelect.value) {
        // Regular recipe selected
        customMealGroup.style.display = 'none';
        if (customMealInput) customMealInput.value = '';
        recipeInfo.style.display = 'block';
    } else {
        // No selection
        customMealGroup.style.display = 'none';
        recipeInfo.style.display = 'none';
        if (customMealInput) customMealInput.value = '';
    }
}

function loadRecipeDetails() {
    const recipeSelect = document.getElementById('recipeSelect');
    const recipeDetails = document.getElementById('recipeDetails');
    
    if (!recipeSelect || !recipeDetails) return;
    
    const selectedOption = recipeSelect.options[recipeSelect.selectedIndex];
    
    if (!recipeSelect.value || recipeSelect.value === 'custom') {
        recipeDetails.innerHTML = '';
        return;
    }
    
    // Get data from option attributes
    const prepTime = selectedOption.dataset.prep || '0';
    const cookTime = selectedOption.dataset.cook || '0';
    const category = selectedOption.dataset.category || 'Uncategorized';
    const totalTime = parseInt(prepTime) + parseInt(cookTime);
    
    let html = `
        <p><strong>${selectedOption.text}</strong></p>
        <p>
            <i class="fas fa-clock" style="color: #10b981;"></i> 
            ${totalTime > 0 ? totalTime + ' min total' : 'Time not specified'}
    `;
    
    if (prepTime > 0 || cookTime > 0) {
        html += ` <span style="color: var(--gray-500); font-size: 0.85rem;">`;
        if (prepTime > 0) html += `(Prep: ${prepTime}m`;
        if (cookTime > 0) html += `${prepTime > 0 ? ', ' : '('}Cook: ${cookTime}m)`;
        html += `</span>`;
    }
    
    html += `
        </p>
        <p><i class="fas fa-tag" style="color: #10b981;"></i> ${category}</p>
        <p><small><i class="fas fa-info-circle"></i> View full recipe for complete ingredients</small></p>
    `;
    
    recipeDetails.innerHTML = html;
}

function generateMealPlan() {
    if (confirm('Generate a meal plan based on your available ingredients?')) {
        const weekStart = getCurrentWeekStart();
        window.location.href = 'mealplan.php?generate=true&week=' + weekStart;
    }
}

function getCurrentWeekStart() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('week') || new Date().toISOString().split('T')[0];
}

function copyMealPlan() {
    // Create a text representation of the meal plan
    let text = 'Weekly Meal Plan\n\n';
    
    const dayHeaders = document.querySelectorAll('.day-header .day-name');
    const mealRows = document.querySelectorAll('.calendar-row');
    
    if (dayHeaders.length === 0 || mealRows.length === 0) {
        alert('No meal plan to copy');
        return;
    }
    
    // Create header row
    text += 'Day\tBreakfast\tLunch\tDinner\tSnack\n';
    
    for (let i = 0; i < 7; i++) {
        const day = dayHeaders[i]?.textContent || `Day ${i+1}`;
        const meals = [];
        
        mealRows.forEach(row => {
            const mealSlot = row.querySelectorAll('.meal-slot')[i];
            const mealTitle = mealSlot?.querySelector('.meal-title')?.textContent || '—';
            meals.push(mealTitle);
        });
        
        text += `${day}\t${meals.join('\t')}\n`;
    }
    
    // Copy to clipboard
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Meal plan copied to clipboard!', 'success');
    }).catch(() => {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showNotification('Meal plan copied to clipboard!', 'success');
    });
}

function printMealPlan() {
    window.print();
}

function refreshSuggestions() {
    const suggestionsList = document.getElementById('suggestionsList');
    if (!suggestionsList) return;
    
    suggestionsList.innerHTML = '<div class="loading-spinner"></div><p style="text-align: center;">Loading suggestions...</p>';
    
    // Reload the page to get fresh suggestions
    setTimeout(() => {
        location.reload();
    }, 1000);
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    
    let icon = 'info-circle';
    if (type === 'success') icon = 'check-circle';
    if (type === 'error') icon = 'exclamation-circle';
    
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
    notification.style.boxShadow = 'var(--shadow-lg)';
    
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

// Drag and drop functionality
function initializeDragAndDrop() {
    const mealCards = document.querySelectorAll('.meal-card');
    const emptySlots = document.querySelectorAll('.meal-slot.empty');
    
    mealCards.forEach(card => {
        card.setAttribute('draggable', 'true');
        
        card.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/plain', this.dataset.mealId || '');
            this.classList.add('dragging');
        });
        
        card.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            document.querySelectorAll('.meal-slot').forEach(slot => {
                slot.classList.remove('drag-over');
            });
        });
    });
    
    emptySlots.forEach(slot => {
        slot.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });
        
        slot.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });
        
        slot.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            const mealId = e.dataTransfer.getData('text/plain');
            if (mealId) {
                const date = this.closest('.meal-slot')?.dataset.date;
                const mealType = this.closest('.meal-slot')?.dataset.mealType;
                
                if (date && mealType && confirm('Move this meal to ' + date + ' ' + mealType + '?')) {
                    // Redirect to move meal
                    window.location.href = `mealplan.php?move=${mealId}&date=${date}&type=${mealType}`;
                }
            }
        });
    });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + N for new meal
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        const today = new Date().toISOString().split('T')[0];
        openAddMealModal(today, 'dinner');
    }
    
    // Ctrl + G to generate
    if (e.ctrlKey && e.key === 'g') {
        e.preventDefault();
        generateMealPlan();
    }
    
    // Escape key to close modal
    if (e.key === 'Escape') {
        const addModal = document.getElementById('addMealModal');
        if (addModal && addModal.style.display === 'block') {
            hideAddMealModal();
        }
    }
});

// Make functions globally available
window.openAddMealModal = openAddMealModal;
window.openAddMealModalWithRecipe = openAddMealModalWithRecipe; // This was missing!
window.hideAddMealModal = hideAddMealModal;
window.toggleCustomMeal = toggleCustomMeal;
window.generateMealPlan = generateMealPlan;
window.copyMealPlan = copyMealPlan;
window.printMealPlan = printMealPlan;
window.refreshSuggestions = refreshSuggestions;
window.loadRecipeDetails = loadRecipeDetails;
window.getMealIcon = getMealIcon;
window.getMealColor = getMealColor;