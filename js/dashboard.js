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
    // RECIPE SUGGESTIONS - pretend AI
    // ====================================
    const suggestionsList = document.getElementById('suggestionsList');
    const refreshBtn = document.getElementById('refreshSuggestions');
    
    // real suggestions based on what people actually cook
    const recipeIdeas = [
        { 
            name: 'garlic butter pasta', 
            time: '20 mins', 
            difficulty: 'easy',
            icon: '🍝'
        },
        { 
            name: 'quick veggie stir fry', 
            time: '15 mins', 
            difficulty: 'easy',
            icon: '🥬'
        },
        { 
            name: 'sheet pan chicken', 
            time: '35 mins', 
            difficulty: 'medium',
            icon: '🍗'
        },
        { 
            name: '3-ingredient pancakes', 
            time: '10 mins', 
            difficulty: 'easy',
            icon: '🥞'
        },
        { 
            name: 'one-pot tomato pasta', 
            time: '25 mins', 
            difficulty: 'easy',
            icon: '🍅'
        },
        { 
            name: '15-min tacos', 
            time: '15 mins', 
            difficulty: 'easy',
            icon: '🌮'
        }
    ];
    
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
                <div class="meal-item" style="cursor: pointer;" onclick="window.location.href='recipes.php?search=${encodeURIComponent(suggestion.name)}'">
                    <div class="meal-time">
                        <span style="font-size: 1.2rem;">${suggestion.icon}</span>
                        <span>${suggestion.time}</span>
                    </div>
                    <div class="meal-name">${suggestion.name}</div>
                    <span class="badge" style="background: #f0fdf4; color: #059669; border-color: #10b981;">${suggestion.difficulty}</span>
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
            if (icon && icon.classList.contains('fa-ellipsis-h')) {
                icon.style.transform = 'rotate(90deg)';
            }
        });
        
        item.addEventListener('mouseleave', () => {
            const icon = item.querySelector('i');
            if (icon && icon.classList.contains('fa-ellipsis-h')) {
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