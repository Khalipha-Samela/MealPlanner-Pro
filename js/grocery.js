// Grocery page JavaScript
let currentEditId = null;

document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    initializeSortable();
    initializeVoiceInput();
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
    
    // Filter items on search
    const searchInput = document.getElementById('itemSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', filterItems);
    }
}

function initializeSortable() {
    // Initialize sortable for each category
    const sortableLists = document.querySelectorAll('.sortable-items');
    
    sortableLists.forEach(list => {
        new Sortable(list, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
                // In a real app, you would save the new order
                console.log('Items reordered');
            }
        });
    });
}

function initializeVoiceInput() {
    const voiceBtn = document.getElementById('voiceInputBtn');
    if (!voiceBtn) return;
    
    voiceBtn.addEventListener('click', function() {
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const recognition = new SpeechRecognition();
            
            recognition.lang = 'en-US';
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;
            
            recognition.start();
            
            recognition.onresult = function(event) {
                const transcript = event.results[0][0].transcript;
                parseVoiceInput(transcript);
            };
            
            recognition.onspeechend = function() {
                recognition.stop();
            };
            
            recognition.onerror = function(event) {
                console.error('Speech recognition error:', event.error);
                showNotification('Voice recognition failed. Please try again.', 'error');
            };
        } else {
            showNotification('Voice recognition is not supported in your browser. Try Chrome or Edge.', 'warning');
        }
    });
}

function parseVoiceInput(transcript) {
    // Try to parse quantity and item name
    const quantityMatch = transcript.match(/(\d+)\s*(kg|g|lb|oz|l|ml|pieces?|cans?|bottles?)?\s*(.+)/i);
    
    if (quantityMatch) {
        const quantity = quantityMatch[1] + (quantityMatch[2] || '');
        const itemName = quantityMatch[3].trim();
        
        document.getElementById('item_name').value = itemName;
        document.getElementById('quantity').value = quantity;
        
        showNotification(`Added: ${quantity} of ${itemName}`, 'success');
    } else {
        document.getElementById('item_name').value = transcript;
        showNotification(`Added: ${transcript}`, 'success');
    }
    
    showAddItemModal();
}

function showAddItemModal() {
    const modal = document.getElementById('addItemModal');
    if (modal) {
        modal.style.display = 'block';
        document.getElementById('item_name').focus();
    }
}

function hideAddItemModal() {
    const modal = document.getElementById('addItemModal');
    if (modal) {
        modal.style.display = 'none';
        // Reset form
        document.getElementById('addItemForm')?.reset();
    }
}

function showEditItemModal() {
    const modal = document.getElementById('editItemModal');
    if (modal) {
        modal.style.display = 'block';
    }
}

function hideEditItemModal() {
    const modal = document.getElementById('editItemModal');
    if (modal) {
        modal.style.display = 'none';
        // Reset form
        document.getElementById('editItemForm')?.reset();
        currentEditId = null;
    }
}

function addQuickItem(name, quantity, category) {
    document.getElementById('item_name').value = name;
    document.getElementById('quantity').value = quantity;
    document.getElementById('category').value = category;
    showAddItemModal();
}

function editItem(id, name, quantity, category) {
    currentEditId = id;
    document.getElementById('edit_item_id').value = id;
    document.getElementById('edit_item_name').value = name;
    document.getElementById('edit_quantity').value = quantity;
    document.getElementById('edit_category').value = category;
    showEditItemModal();
}

function toggleItem(itemId) {
    const url = new URL(window.location.href);
    const listId = url.searchParams.get('list');
    window.location.href = `grocery.php?toggle=${itemId}` + (listId ? `&list=${listId}` : '');
}

function switchList(listId) {
    if (listId) {
        window.location.href = `grocery.php?list=${listId}`;
    }
}

function filterItems() {
    const searchTerm = document.getElementById('itemSearch')?.value.toLowerCase() || '';
    const items = document.querySelectorAll('.grocery-item');
    
    items.forEach(item => {
        const itemName = item.querySelector('.item-name')?.textContent.toLowerCase() || '';
        if (itemName.includes(searchTerm)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
    
    // Hide empty categories
    const categories = document.querySelectorAll('.category-section');
    categories.forEach(category => {
        const visibleItems = category.querySelectorAll('.grocery-item[style="display: flex;"]').length;
        const totalItems = category.querySelectorAll('.grocery-item').length;
        
        if (visibleItems === 0 && totalItems > 0) {
            category.style.display = 'none';
        } else {
            category.style.display = 'block';
        }
    });
}

function printList() {
    const printWindow = window.open('', '_blank');
    const listName = document.querySelector('.list-info h2')?.textContent || 'Grocery List';
    const date = new Date().toLocaleDateString();
    
    // Clone the grocery sections
    const grocerySections = document.getElementById('grocerySections')?.cloneNode(true);
    
    // Remove interactive elements
    if (grocerySections) {
        grocerySections.querySelectorAll('.item-actions, .drag-handle, .category-progress').forEach(el => el.remove());
    }
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${listName} - MealPlanner Pro</title>
            <style>
                body { 
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; 
                    padding: 30px; 
                    max-width: 800px; 
                    margin: 0 auto;
                }
                h1 { 
                    color: #10b981; 
                    font-size: 28px;
                    margin-bottom: 5px;
                }
                .date {
                    color: #6b7280;
                    font-size: 14px;
                    margin-bottom: 30px;
                    padding-bottom: 20px;
                    border-bottom: 1px solid #e5e7eb;
                }
                .category-section { 
                    margin-bottom: 30px; 
                }
                .category-title { 
                    font-size: 18px;
                    font-weight: 600;
                    color: #1f2937;
                    padding-bottom: 8px;
                    border-bottom: 1px solid #e5e7eb;
                    margin-bottom: 15px;
                }
                .grocery-item { 
                    display: flex;
                    align-items: center;
                    padding: 10px 0;
                    border-bottom: 1px solid #f3f4f6;
                }
                .item-name {
                    font-size: 16px;
                    color: #1f2937;
                }
                .item-quantity-badge {
                    margin-left: 10px;
                    background: #f3f4f6;
                    padding: 2px 8px;
                    border-radius: 12px;
                    font-size: 12px;
                    color: #6b7280;
                }
                .purchased .item-name {
                    text-decoration: line-through;
                    color: #9ca3af;
                }
                .checkbox-custom {
                    display: inline-block;
                    width: 20px;
                    height: 20px;
                    border: 2px solid #d1d5db;
                    border-radius: 4px;
                    margin-right: 12px;
                }
                .purchased .checkbox-custom {
                    background-color: #10b981;
                    border-color: #10b981;
                }
                @media print {
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <h1>${listName}</h1>
            <div class="date">Generated on ${date}</div>
            ${grocerySections ? grocerySections.outerHTML : '<p>No items in list</p>'}
            <div class="no-print" style="margin-top: 40px;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer; margin-right: 10px;">Print</button>
                <button onclick="window.close()" style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 8px; cursor: pointer;">Close</button>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}

function shareList() {
    if (navigator.share) {
        navigator.share({
            title: 'My Grocery List',
            text: 'Check out my grocery list from MealPlanner Pro!',
            url: window.location.href
        })
        .then(() => showNotification('List shared successfully!', 'success'))
        .catch(error => console.log('Error sharing:', error));
    } else {
        // Fallback - copy to clipboard
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            showNotification('Link copied to clipboard!', 'success');
        }).catch(() => {
            showNotification('Copy this link: ' + url, 'info');
        });
    }
}

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

// Export functions for HTML onclick
window.showAddItemModal = showAddItemModal;
window.hideAddItemModal = hideAddItemModal;
window.showEditItemModal = showEditItemModal;
window.hideEditItemModal = hideEditItemModal;
window.addQuickItem = addQuickItem;
window.editItem = editItem;
window.toggleItem = toggleItem;
window.switchList = switchList;
window.filterItems = filterItems;
window.printList = printList;
window.shareList = shareList;
window.organizeByStore = organizeByStore;