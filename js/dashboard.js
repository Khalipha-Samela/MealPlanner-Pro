// Modern Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu functionality
    const menuToggle = document.getElementById('menuToggle');
    const closeSidebar = document.getElementById('closeSidebar');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    if (menuToggle && sidebar && overlay) {
        function openMenu() {
            sidebar.classList.add('mobile-visible');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
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
        
        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('mobile-visible')) {
                closeMenu();
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && sidebar.classList.contains('mobile-visible')) {
                closeMenu();
            }
        });
    }
    
    // Load recipe suggestions
    const suggestionsList = document.getElementById('suggestionsList');
    const refreshBtn = document.getElementById('refreshSuggestions');
    
    async function loadSuggestions() {
        if (!suggestionsList) return;
        
        // Show loading state
        suggestionsList.innerHTML = `
            <div class="suggestion-placeholder">
                <div class="placeholder-item"></div>
                <div class="placeholder-item"></div>
                <div class="placeholder-item"></div>
            </div>
        `;
        
        try {
            // Simulate API call
            await new Promise(resolve => setTimeout(resolve, 1500));
            
            // Mock suggestions based on common ingredients
            const suggestions = [
                { name: 'Garden Fresh Pasta', time: '25 mins', difficulty: 'Easy', uses: ['tomatoes', 'garlic'] },
                { name: 'Herb Roasted Chicken', time: '45 mins', difficulty: 'Medium', uses: ['chicken', 'rosemary'] },
                { name: 'Quick Veggie Stir Fry', time: '15 mins', difficulty: 'Easy', uses: ['bell peppers', 'onions'] }
            ];
            
            suggestionsList.innerHTML = suggestions.map(suggestion => `
                <div class="meal-item">
                    <div class="meal-time">
                        <i class="fas fa-clock" style="color: #10b981;"></i>
                        <span>${suggestion.time}</span>
                    </div>
                    <div class="meal-name">${suggestion.name}</div>
                    <span class="badge" style="background: #f0fdf4; color: #059669;">${suggestion.difficulty}</span>
                </div>
            `).join('');
            
        } catch (error) {
            suggestionsList.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-exclamation-triangle" style="color: #10b981;"></i>
                    </div>
                    <p>Failed to load suggestions</p>
                    <button class="btn btn-outline btn-sm" onclick="loadSuggestions()">
                        Try again
                    </button>
                </div>
            `;
        }
    }
    
    if (suggestionsList) {
        loadSuggestions();
    }
    
    if (refreshBtn) {
        refreshBtn.addEventListener('click', loadSuggestions);
    }
    
    // Animate progress bars on load
    const progressBars = document.querySelectorAll('.progress-fill');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        if (width) {
            bar.style.width = '0';
            setTimeout(() => {
                bar.style.width = width;
            }, 100);
        }
    });
    
    // Highlight active navigation item
    const currentPath = window.location.pathname.split('/').pop() || 'dashboard.php';
    const navLinks = document.querySelectorAll('.sidebar-nav a');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPath) {
            link.closest('li').classList.add('active');
        }
    });
    
    // Add smooth hover effects for stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            const icon = card.querySelector('.stat-icon');
            if (icon) {
                icon.style.transform = 'scale(1.1)';
            }
        });
        
        card.addEventListener('mouseleave', () => {
            const icon = card.querySelector('.stat-icon');
            if (icon) {
                icon.style.transform = 'scale(1)';
            }
        });
    });
});