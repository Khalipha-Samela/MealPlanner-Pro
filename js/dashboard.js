// dashboard with personality
document.addEventListener('DOMContentLoaded', function() {
    // ====================================
    // MOBILE MENU - with style
    // ====================================
    const menuToggle = document.getElementById('menuToggle');
    const closeSidebar = document.getElementById('closeSidebar');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    if (menuToggle && sidebar && overlay) {
        function openMenu() {
            sidebar.classList.add('mobile-visible');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // little vibration effect on mobile (optional, won't break if not supported)
            if (window.navigator && window.navigator.vibrate) {
                window.navigator.vibrate(10);
            }
        }
        
        function closeMenu() {
            sidebar.classList.remove('mobile-visible');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        menuToggle.addEventListener('click', openMenu);
        
        if (closeSidebar) {
            closeSidebar.addEventListener('click', closeMenu);
        }
        
        overlay.addEventListener('click', closeMenu);
        
        // escape key closes menu
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('mobile-visible')) {
                closeMenu();
            }
        });
        
        // handle resize - close menu on desktop
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 768 && sidebar.classList.contains('mobile-visible')) {
                    closeMenu();
                }
            }, 250);
        });
    }
    
    // ====================================
    // RECIPE SUGGESTIONS - pretend AI with add to recipes
    // ====================================
    const suggestionsList = document.getElementById('suggestionsList');
    const refreshBtn = document.getElementById('refreshSuggestions');
    
    // real suggestions based on what people actually cook
    const recipeIdeas = [
        { 
            name: 'garlic butter pasta', 
            time: '20 mins', 
            difficulty: 'easy',
            icon: '🍝',
            ingredients: [
                '200g pasta',
                '4 cloves garlic, minced',
                '4 tbsp butter',
                '1/4 cup parsley, chopped',
                '1/4 cup parmesan cheese, grated',
                'Salt to taste',
                'Pepper to taste'
            ],
            instructions: "1. Bring a large pot of salted water to boil\n2. Cook pasta according to package directions\n3. While pasta cooks, melt butter in a large pan over medium heat\n4. Add minced garlic and cook for 1-2 minutes until fragrant\n5. Drain pasta, reserving 1/4 cup pasta water\n6. Add pasta to the pan with garlic butter and toss to coat\n7. Add pasta water if needed to create a light sauce\n8. Stir in parsley and parmesan\n9. Season with salt and pepper to taste\n10. Serve immediately with extra parmesan"
        },
        { 
            name: 'quick veggie stir fry', 
            time: '15 mins', 
            difficulty: 'easy',
            icon: '🥬',
            ingredients: [
                '2 cups mixed vegetables (broccoli, carrots, bell peppers)',
                '3 tbsp soy sauce',
                '2 cloves garlic, minced',
                '1 tbsp ginger, grated',
                '2 tbsp sesame oil',
                '1 tbsp sesame seeds',
                'Cooked rice for serving'
            ],
            instructions: "1. Heat sesame oil in a wok or large pan over high heat\n2. Add minced garlic and grated ginger, stir for 30 seconds\n3. Add vegetables and stir-fry for 5-7 minutes until tender-crisp\n4. Add soy sauce and toss to combine\n5. Cook for another 1-2 minutes\n6. Sprinkle with sesame seeds\n7. Serve hot over rice"
        },
        { 
            name: 'sheet pan chicken', 
            time: '35 mins', 
            difficulty: 'medium',
            icon: '🍗',
            ingredients: [
                '4 chicken thighs, bone-in, skin-on',
                '3 potatoes, cubed',
                '2 carrots, chopped',
                '1 onion, sliced',
                '4 cloves garlic, whole',
                '3 tbsp olive oil',
                '2 tsp dried rosemary',
                '1 tsp dried thyme',
                'Salt and pepper to taste'
            ],
            instructions: "1. Preheat oven to 400°F (200°C)\n2. In a large bowl, toss vegetables with 2 tbsp olive oil, rosemary, thyme, salt and pepper\n3. Arrange vegetables on a large sheet pan\n4. Pat chicken dry, rub with remaining olive oil and season generously with salt and pepper\n5. Place chicken on top of vegetables, skin side up\n6. Roast for 30-35 minutes until chicken is golden and cooked through (internal temp 165°F)\n7. Let rest for 5 minutes before serving"
        },
        { 
            name: '3-ingredient pancakes', 
            time: '10 mins', 
            difficulty: 'easy',
            icon: '🥞',
            ingredients: [
                '2 ripe bananas',
                '2 large eggs',
                '1/2 cup flour',
                'Butter or oil for cooking',
                'Maple syrup for serving'
            ],
            instructions: "1. Mash bananas in a bowl until smooth\n2. Whisk in eggs until well combined\n3. Add flour and mix until just combined (batter will be thick)\n4. Heat a non-stick pan over medium heat and add a little butter\n5. Pour 1/4 cup batter for each pancake\n6. Cook until bubbles form on surface, about 2-3 minutes\n7. Flip and cook another 1-2 minutes until golden\n8. Serve warm with maple syrup"
        },
        { 
            name: 'one-pot tomato pasta', 
            time: '25 mins', 
            difficulty: 'easy',
            icon: '🍅',
            ingredients: [
                '400g pasta',
                '1 can (400g) crushed tomatoes',
                '1 onion, finely chopped',
                '4 cloves garlic, minced',
                '1/4 cup olive oil',
                '3 cups water',
                '1 tsp dried oregano',
                'Fresh basil leaves',
                'Salt and pepper to taste',
                'Grated parmesan for serving'
            ],
            instructions: "1. Combine pasta, crushed tomatoes, onion, garlic, olive oil, oregano, and water in a large pot\n2. Season with salt and pepper\n3. Bring to a boil over high heat\n4. Reduce heat to medium and cook, stirring occasionally, until pasta is tender and liquid is mostly absorbed (about 15 minutes)\n5. Stir in fresh basil\n6. Serve immediately with grated parmesan"
        },
        { 
            name: '15-min tacos', 
            time: '15 mins', 
            difficulty: 'easy',
            icon: '🌮',
            ingredients: [
                '500g ground beef',
                '1 packet taco seasoning',
                '8-10 tortillas',
                '1 cup lettuce, shredded',
                '1 tomato, diced',
                '1/2 cup cheese, grated',
                '1/4 cup sour cream',
                'Salsa for serving'
            ],
            instructions: "1. Brown ground beef in a large pan over medium-high heat, breaking it up with a spoon\n2. Drain excess fat\n3. Add taco seasoning and 1/2 cup water, simmer for 5 minutes\n4. Warm tortillas in a dry pan or microwave\n5. Assemble tacos: add meat, then top with lettuce, tomato, cheese\n6. Add sour cream and salsa\n7. Serve immediately"
        }
    ];
    
    // Function to save recipe to database
    async function saveSuggestionToRecipe(suggestion) {
        try {
            // Convert ingredients array to string if it's an array
            const ingredientsText = Array.isArray(suggestion.ingredients) 
                ? suggestion.ingredients.join('\n')
                : suggestion.ingredients;
            
            const formData = new FormData();
            formData.append('title', suggestion.name);
            formData.append('description', `A delicious ${suggestion.difficulty} recipe that takes ${suggestion.time} to make.`);
            formData.append('ingredients', ingredientsText);
            formData.append('instructions', suggestion.instructions);
            formData.append('cooking_time', suggestion.time);
            formData.append('difficulty', suggestion.difficulty);
            formData.append('from_suggestion', 'true');
            
            const response = await fetch('ajax/save_suggestion_recipe.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success message
                showNotification('success', `✨ "${suggestion.name}" added to your recipes!`);
                
                // Update the button to show it's added
                const buttons = document.querySelectorAll('.add-recipe-btn');
                buttons.forEach(btn => {
                    if (btn.dataset.recipeName === suggestion.name) {
                        btn.innerHTML = '<i class="fas fa-check"></i> Added!';
                        btn.classList.add('btn-success');
                        btn.disabled = true;
                        setTimeout(() => {
                            btn.innerHTML = '<i class="fas fa-plus"></i> Add to Recipes';
                            btn.classList.remove('btn-success');
                            btn.disabled = false;
                        }, 2000);
                    }
                });
            } else {
                showNotification('error', result.message || 'Could not add recipe');
            }
        } catch (error) {
            console.error('Error saving recipe:', error);
            showNotification('error', 'Something went wrong');
        }
    }
    
    // Show notification function
    function showNotification(type, message) {
        // Remove any existing notification
        const existingNotif = document.querySelector('.dashboard-notification');
        if (existingNotif) {
            existingNotif.remove();
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `dashboard-notification ${type}`;
        notification.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        // Show notification
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Hide after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }
    
    async function loadSuggestions() {
        if (!suggestionsList) return;
        
        // show loading with personality
        suggestionsList.innerHTML = `
            <div class="suggestion-placeholder">
                <div class="placeholder-item"></div>
                <div class="placeholder-item"></div>
                <div class="placeholder-item"></div>
            </div>
        `;
        
        try {
            // wait a bit like real loading
            await new Promise(resolve => setTimeout(resolve, 1200));
            
            // pick 3 random suggestions
            const shuffled = [...recipeIdeas].sort(() => 0.5 - Math.random());
            const selected = shuffled.slice(0, 3);
            
            suggestionsList.innerHTML = selected.map(suggestion => `
                <div class="suggestion-card">
                    <div class="suggestion-header">
                        <div class="suggestion-icon">${suggestion.icon}</div>
                        <div class="suggestion-info">
                            <div class="suggestion-name">${suggestion.name}</div>
                            <div class="suggestion-meta">
                                <span><i class="far fa-clock"></i> ${suggestion.time}</span>
                                <span class="badge" style="background: #f0fdf4; color: #059669; border-color: #10b981;">${suggestion.difficulty}</span>
                            </div>
                        </div>
                    </div>
                    <div class="suggestion-actions">
                        <button class="btn btn-outline btn-sm view-recipe-btn" onclick='viewSuggestionDetails(${JSON.stringify(suggestion).replace(/'/g, "\\'")})'>
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn btn-primary btn-sm add-recipe-btn" data-recipe-name="${suggestion.name}" onclick='addSuggestionToRecipes(${JSON.stringify(suggestion).replace(/'/g, "\\'")})'>
                            <i class="fas fa-plus"></i> Add to Recipes
                        </button>
                    </div>
                </div>
            `).join('');
            
        } catch (error) {
            // friendly error message
            suggestionsList.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-exclamation"></i>
                    </div>
                    <p>couldn't get suggestions</p>
                    <button class="btn btn-outline btn-sm" onclick="loadSuggestions()">
                        try again
                    </button>
                </div>
            `;
        }
    }
    
    // Make functions globally available for onclick handlers
    window.viewSuggestionDetails = function(suggestion) {
        // Format ingredients as a list
        const ingredientsList = Array.isArray(suggestion.ingredients) 
            ? suggestion.ingredients.map(ing => `<li>${ing}</li>`).join('')
            : suggestion.ingredients.split('\n').map(ing => `<li>${ing}</li>`).join('');
        
        // Create and show modal with recipe details
        const modal = document.createElement('div');
        modal.className = 'recipe-modal';
        modal.innerHTML = `
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3>${suggestion.name} ${suggestion.icon}</h3>
                    <button class="modal-close"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <div class="recipe-details">
                        <div class="recipe-meta">
                            <span><i class="far fa-clock"></i> ${suggestion.time}</span>
                            <span class="badge" style="background: #f0fdf4; color: #059669; border-color: #10b981;">${suggestion.difficulty}</span>
                            <span><i class="fas fa-users"></i> 4 servings</span>
                        </div>
                        
                        <div class="recipe-section">
                            <h4>Ingredients</h4>
                            <ul>
                                ${ingredientsList}
                            </ul>
                        </div>
                        
                        <div class="recipe-section">
                            <h4>Instructions</h4>
                            <p style="white-space: pre-line;">${suggestion.instructions}</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="this.closest('.recipe-modal').remove()">Close</button>
                    <button class="btn btn-primary" onclick="addSuggestionToRecipes(${JSON.stringify(suggestion).replace(/'/g, "\\'")}); this.closest('.recipe-modal').remove()">
                        <i class="fas fa-plus"></i> Add to My Recipes
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Add close functionality
        modal.querySelector('.modal-close').addEventListener('click', () => modal.remove());
        modal.querySelector('.modal-overlay').addEventListener('click', () => modal.remove());
    };
    
    window.addSuggestionToRecipes = function(suggestion) {
        saveSuggestionToRecipe(suggestion);
    };
    
    // load suggestions on page load
    if (suggestionsList) {
        loadSuggestions();
    }
    
    // refresh button
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            // little spin animation
            const icon = refreshBtn.querySelector('i');
            if (icon) {
                icon.style.transition = 'transform 0.5s ease';
                icon.style.transform = 'rotate(180deg)';
                setTimeout(() => {
                    icon.style.transform = 'rotate(0deg)';
                }, 500);
            }
            loadSuggestions();
        });
    }
    
    // ====================================
    // PROGRESS BARS - animate on load
    // ====================================
    const progressBars = document.querySelectorAll('.progress-fill');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        if (width) {
            bar.style.width = '0';
            setTimeout(() => {
                bar.style.transition = 'width 0.8s cubic-bezier(0.34, 1.56, 0.64, 1)';
                bar.style.width = width;
            }, 200);
        }
    });
    
    // ====================================
    // HIGHLIGHT ACTIVE NAV ITEM
    // ====================================
    const currentPath = window.location.pathname.split('/').pop() || 'dashboard.php';
    const navLinks = document.querySelectorAll('.sidebar-nav a');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPath) {
            link.closest('li').classList.add('active');
        }
    });
    
    // ====================================
    // FUN HOVER EFFECTS
    // ====================================
    // stat cards - subtle movement
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            const icon = card.querySelector('.stat-icon');
            if (icon) {
                icon.style.transform = 'rotate(-2deg) scale(1.05)';
            }
        });
        
        card.addEventListener('mouseleave', () => {
            const icon = card.querySelector('.stat-icon');
            if (icon) {
                icon.style.transform = 'rotate(-2deg) scale(1)';
            }
        });
    });
    
    // meal items - wiggle on hover
    const mealItems = document.querySelectorAll('.meal-item');
    mealItems.forEach(item => {
        item.addEventListener('mouseenter', () => {
            const icon = item.querySelector('i');
            if (icon && icon.classList.contains('fa-ellipsis-v')) {
                icon.style.transform = 'rotate(90deg)';
            }
        });
        
        item.addEventListener('mouseleave', () => {
            const icon = item.querySelector('i');
            if (icon && icon.classList.contains('fa-ellipsis-v')) {
                icon.style.transform = 'rotate(0deg)';
            }
        });
    });
    
    // ====================================
    // QUICK ACTION TOUCH FEEDBACK
    // ====================================
    const quickActions = document.querySelectorAll('.quick-action-item');
    quickActions.forEach(action => {
        action.addEventListener('touchstart', function() {
            this.style.backgroundColor = 'var(--primary-soft)';
        });
        
        action.addEventListener('touchend', function() {
            setTimeout(() => {
                this.style.backgroundColor = '';
            }, 150);
        });
    });
    
    // ====================================
    // MAKE BADGES POP
    // ====================================
    const badges = document.querySelectorAll('.badge-warning');
    badges.forEach(badge => {
        badge.addEventListener('mouseenter', () => {
            badge.style.transform = 'scale(1.05) rotate(-1deg)';
        });
        badge.addEventListener('mouseleave', () => {
            badge.style.transform = 'scale(1) rotate(0deg)';
        });
    });
    
    // ====================================
    // ADD SOME RANDOM ROTATION (just for fun)
    // ====================================
    // only on desktop
    if (window.innerWidth > 768) {
        const cards = document.querySelectorAll('.card, .stat-card');
        cards.forEach(card => {
            // add tiny random rotation if not already set
            if (!card.style.transform) {
                const randomRotate = (Math.random() * 0.8 - 0.4).toFixed(1);
                card.style.transform = `rotate(${randomRotate}deg)`;
                
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'rotate(0deg) translateY(-4px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = `rotate(${randomRotate}deg)`;
                });
            }
        });
    }
    
    console.log('✨ dashboard loaded with love');
});