// Profile page JavaScript - User settings and account management

document.addEventListener('DOMContentLoaded', function() {
    console.log('Profile.js loaded');
    initializeEventListeners();
    loadUserPreferences();
    initializePasswordStrength();
    initializePasswordMatch();
});

// Initialize all event listeners
function initializeEventListeners() {
    // Password toggle buttons
    document.querySelectorAll('.password-toggle').forEach(button => {
        button.addEventListener('click', function() {
            const inputId = this.getAttribute('data-target') || 'current_password';
            togglePasswordVisibility(inputId, this);
        });
    });
    
    // Delete account confirmation input
    const confirmInput = document.getElementById('confirmDelete');
    const deleteBtn = document.getElementById('deleteAccountBtn');
    
    if (confirmInput && deleteBtn) {
        confirmInput.addEventListener('input', function() {
            deleteBtn.disabled = this.value !== 'DELETE MY ACCOUNT';
        });
    }
    
    // Save preferences button
    const savePrefsBtn = document.getElementById('savePreferencesBtn');
    if (savePrefsBtn) {
        savePrefsBtn.addEventListener('click', savePreferences);
    }
    
    // Export data button
    const exportBtn = document.getElementById('exportDataBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', exportUserData);
    }
    
    // Import data button
    const importBtn = document.getElementById('importDataBtn');
    if (importBtn) {
        importBtn.addEventListener('click', importUserData);
    }
}

// Toggle password visibility
function togglePasswordVisibility(inputId, button) {
    const passwordInput = document.getElementById(inputId);
    const icon = button.querySelector('i');
    
    if (!passwordInput || !icon) return;
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Initialize password strength indicator
function initializePasswordStrength() {
    const passwordInput = document.getElementById('new_password');
    const strengthIndicator = document.getElementById('passwordStrength');
    
    if (!passwordInput || !strengthIndicator) return;
    
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        const strength = checkPasswordStrength(password);
        displayPasswordStrength(strength, strengthIndicator);
    });
}

// Check password strength
function checkPasswordStrength(password) {
    let score = 0;
    
    if (!password) return 0;
    
    // Length check
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    
    // Character variety checks
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    
    // Normalize score to 0-4 range
    return Math.min(Math.floor(score / 2), 4);
}

// Display password strength
function displayPasswordStrength(strength, indicator) {
    const levels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#10b981'];
    const percentages = ['25%', '25%', '50%', '75%', '100%'];
    
    let html = `
        <div class="strength-bar-container">
            <div class="strength-bar" style="width: ${percentages[strength]}; background-color: ${colors[strength]};"></div>
        </div>
        <span class="strength-text" style="color: ${colors[strength]};">${levels[strength]}</span>
    `;
    
    indicator.innerHTML = html;
}

// Initialize password match checker
function initializePasswordMatch() {
    const passwordInput = document.getElementById('new_password');
    const confirmInput = document.getElementById('confirm_password');
    const matchIndicator = document.getElementById('passwordMatch');
    
    if (!passwordInput || !confirmInput || !matchIndicator) return;
    
    function checkMatch() {
        const password = passwordInput.value;
        const confirm = confirmInput.value;
        
        if (confirm.length === 0) {
            matchIndicator.innerHTML = '';
            return;
        }
        
        if (password === confirm) {
            matchIndicator.innerHTML = '<span class="match-success"><i class="fas fa-check-circle"></i> Passwords match</span>';
        } else {
            matchIndicator.innerHTML = '<span class="match-error"><i class="fas fa-exclamation-circle"></i> Passwords do not match</span>';
        }
    }
    
    passwordInput.addEventListener('input', checkMatch);
    confirmInput.addEventListener('input', checkMatch);
}

// Load user preferences from localStorage
function loadUserPreferences() {
    const savedPrefs = localStorage.getItem('mealplanner_preferences');
    
    if (!savedPrefs) return;
    
    try {
        const preferences = JSON.parse(savedPrefs);
        const form = document.getElementById('preferencesForm');
        
        if (!form) return;
        
        // Set form values based on saved preferences
        Object.keys(preferences).forEach(key => {
            const element = form.elements[key];
            if (!element) return;
            
            if (element.type === 'checkbox') {
                element.checked = preferences[key] === true || preferences[key] === 'on';
            } else {
                element.value = preferences[key];
            }
        });
        
        console.log('Preferences loaded');
    } catch (error) {
        console.error('Error loading preferences:', error);
    }
}

// Save user preferences
function savePreferences() {
    const form = document.getElementById('preferencesForm');
    
    if (!form) {
        showNotification('Preferences form not found', 'error');
        return;
    }
    
    const formData = new FormData(form);
    const preferences = {};
    
    for (let [key, value] of formData.entries()) {
        if (form.elements[key]?.type === 'checkbox') {
            preferences[key] = true;
        } else {
            preferences[key] = value;
        }
    }
    
    // Handle unchecked checkboxes
    Array.from(form.elements).forEach(element => {
        if (element.type === 'checkbox' && !element.checked && element.name) {
            preferences[element.name] = false;
        }
    });
    
    try {
        localStorage.setItem('mealplanner_preferences', JSON.stringify(preferences));
        showNotification('Preferences saved successfully!', 'success');
    } catch (error) {
        console.error('Error saving preferences:', error);
        showNotification('Failed to save preferences', 'error');
    }
}

// Export user data
function exportUserData() {
    // Show loading state
    const exportBtn = document.getElementById('exportDataBtn');
    const originalText = exportBtn ? exportBtn.innerHTML : 'Export';
    
    if (exportBtn) {
        exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
        exportBtn.disabled = true;
    }
    
    // Collect user data from the page
    const userData = {
        exportDate: new Date().toISOString(),
        version: '1.0',
        user: {
            username: document.querySelector('.user-name')?.textContent || '',
            email: document.querySelector('.profile-email')?.textContent || '',
            memberSince: document.querySelector('.profile-meta span:first-child')?.textContent || ''
        },
        stats: {
            ingredients: document.querySelector('.stat-card:first-child .stat-value')?.textContent || '0',
            recipes: document.querySelector('.stat-card:nth-child(2) .stat-value')?.textContent || '0',
            meals: document.querySelector('.stat-card:nth-child(3) .stat-value')?.textContent || '0',
            groceryLists: document.querySelector('.stat-card:last-child .stat-value')?.textContent || '0'
        },
        preferences: JSON.parse(localStorage.getItem('mealplanner_preferences') || '{}')
    };
    
    // Simulate API call delay
    setTimeout(() => {
        try {
            // Create and download JSON file
            const dataStr = JSON.stringify(userData, null, 2);
            const blob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = `mealplanner_export_${new Date().toISOString().slice(0, 10)}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            showNotification('Data exported successfully!', 'success');
        } catch (error) {
            console.error('Export error:', error);
            showNotification('Failed to export data', 'error');
        } finally {
            // Restore button
            if (exportBtn) {
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }
        }
    }, 1000);
}

// Import user data
function importUserData() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json,application/json';
    
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const reader = new FileReader();
        
        reader.onload = function(event) {
            try {
                const data = JSON.parse(event.target.result);
                
                // Validate file structure
                if (!data.version || !data.user) {
                    throw new Error('Invalid file format');
                }
                
                // Confirm import
                if (confirm(`Import data from ${file.name}? This will merge with your existing preferences.`)) {
                    processImportData(data);
                }
            } catch (error) {
                console.error('Import error:', error);
                showNotification('Invalid file format: ' + error.message, 'error');
            }
        };
        
        reader.readAsText(file);
    };
    
    input.click();
}

// Process imported data
function processImportData(data) {
    const importBtn = document.getElementById('importDataBtn');
    const originalText = importBtn ? importBtn.innerHTML : 'Import';
    
    if (importBtn) {
        importBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';
        importBtn.disabled = true;
    }
    
    setTimeout(() => {
        try {
            // Import preferences if they exist
            if (data.preferences) {
                localStorage.setItem('mealplanner_preferences', JSON.stringify(data.preferences));
            }
            
            showNotification('Data imported successfully! Page will reload to apply changes.', 'success');
            
            // Reload page after 2 seconds
            setTimeout(() => {
                window.location.reload();
            }, 2000);
            
        } catch (error) {
            console.error('Import processing error:', error);
            showNotification('Failed to import data', 'error');
            
            if (importBtn) {
                importBtn.innerHTML = originalText;
                importBtn.disabled = false;
            }
        }
    }, 1500);
}

// Show delete account modal
function showDeleteModal() {
    const modal = document.getElementById('deleteModal');
    const confirmInput = document.getElementById('confirmDelete');
    const deleteBtn = document.getElementById('deleteAccountBtn');
    
    if (modal) {
        modal.style.display = 'block';
        
        if (confirmInput) confirmInput.value = '';
        if (deleteBtn) deleteBtn.disabled = true;
    }
}

// Hide delete account modal
function hideDeleteModal() {
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Delete account (with confirmation)
function deleteAccount() {
    const confirmInput = document.getElementById('confirmDelete');
    
    if (!confirmInput || confirmInput.value !== 'DELETE MY ACCOUNT') {
        showNotification('Please type DELETE MY ACCOUNT to confirm', 'warning');
        return;
    }
    
    if (!confirm('This action is permanent and cannot be undone. Are you absolutely sure?')) {
        return;
    }
    
    showNotification('Account deletion request submitted. You will be logged out.', 'info');
    
    // Simulate account deletion (in production, this would call an API)
    setTimeout(() => {
        window.location.href = 'logout.php';
    }, 2000);
}

// Connect social account
function connectAccount(provider) {
    showNotification(`Connecting to ${provider}... This feature is coming soon!`, 'info');
}

// Show notification
function showNotification(message, type = 'info') {
    // Remove any existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    let icon = 'info-circle';
    if (type === 'success') icon = 'check-circle';
    if (type === 'error') icon = 'exclamation-circle';
    if (type === 'warning') icon = 'exclamation-triangle';
    
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${icon}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }
    }, 5000);
}

// Make functions globally available
window.showDeleteModal = showDeleteModal;
window.hideDeleteModal = hideDeleteModal;
window.deleteAccount = deleteAccount;
window.savePreferences = savePreferences;
window.exportUserData = exportUserData;
window.importUserData = importUserData;
window.connectAccount = connectAccount;
window.togglePasswordVisibility = togglePasswordVisibility;

console.log('Profile.js functions loaded');