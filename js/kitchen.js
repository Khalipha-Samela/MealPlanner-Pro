// Kitchen page JavaScript - Modern functionality
document.addEventListener('DOMContentLoaded', function() {
    initializeBulkActions();
    initializeScanner();
    initializeKeyboardShortcuts();
    initializeFormHandlers(); // Add this
});

// Bulk actions functionality
function initializeBulkActions() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const bulkDeleteForm = document.getElementById('bulkDeleteForm');

    // Select all checkbox in header
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            rowCheckboxes.forEach(cb => cb.checked = this.checked);
            updateBulkActionsVisibility();
        });
    }

    // Individual checkboxes
    rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = Array.from(rowCheckboxes).every(c => c.checked);
            }
            updateBulkActionsVisibility();
        });
    });

    // Select all button
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            rowCheckboxes.forEach(cb => cb.checked = true);
            if (selectAllCheckbox) selectAllCheckbox.checked = true;
            updateBulkActionsVisibility();
        });
    }

    // Bulk delete button
    if (bulkDeleteBtn && bulkDeleteForm) {
        bulkDeleteBtn.addEventListener('click', function() {
            const selectedCount = document.querySelectorAll('.row-checkbox:checked').length;
            if (selectedCount === 0) {
                showNotification('Please select at least one ingredient to delete.', 'warning');
                return;
            }
            
            if (confirm(`Are you sure you want to delete ${selectedCount} ingredients?`)) {
                bulkDeleteForm.submit();
            }
        });
    }
}

// Update bulk actions visibility
function updateBulkActionsVisibility() {
    const selectedCount = document.querySelectorAll('.row-checkbox:checked').length;
    const bulkActions = document.querySelector('.bulk-actions');
    
    if (bulkActions) {
        if (selectedCount > 0) {
            bulkActions.classList.add('has-selection');
        } else {
            bulkActions.classList.remove('has-selection');
        }
    }
}

// Form handlers
function initializeFormHandlers() {
    // Handle Add Ingredient Form
    const addForm = document.getElementById('addIngredientForm');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            submitBtn.disabled = true;
            
            fetch('kitchen.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                showNotification('Ingredient added successfully!', 'success');
                closeModal('addIngredientModal');
                this.reset();
                setTimeout(() => window.location.reload(), 1500);
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error adding ingredient', 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
    
    // Handle Edit Ingredient Form
    const editForm = document.getElementById('editIngredientForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;
            
            fetch('kitchen.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                showNotification('Ingredient updated successfully!', 'success');
                closeModal('editIngredientModal');
                setTimeout(() => window.location.reload(), 1500);
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error updating ingredient', 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
}

function editIngredient(ingredient) {
    console.log('Edit called');
    
    // Prevent any default behavior
    event?.preventDefault();
    event?.stopPropagation();
    
    // Set values
    document.getElementById('edit_id').value = ingredient.id;
    document.getElementById('edit_name').value = ingredient.name;
    document.getElementById('edit_category').value = ingredient.category || '';
    document.getElementById('edit_quantity').value = ingredient.quantity || '';
    document.getElementById('edit_notes').value = ingredient.notes || '';
    
    if (ingredient.expiration_date) {
        const date = new Date(ingredient.expiration_date);
        const formattedDate = date.toISOString().split('T')[0];
        document.getElementById('edit_expiration_date').value = formattedDate;
    } else {
        document.getElementById('edit_expiration_date').value = '';
    }
    
    // Show modal with important flag
    const modal = document.getElementById('editIngredientModal');
    modal.style.setProperty('display', 'block', 'important');
    
    // Return false to prevent any default action
    return false;
}

// Close modal function
window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
};

// Scanner functionality
let scannerInitialized = false;
let currentScanBarcode = null;
let currentProductData = null;

function initializeScanner() {
    const scanBtn = document.getElementById('scanBarcodeBtn');
    
    if (scanBtn) {
        scanBtn.addEventListener('click', openScanner);
    }
    
    // Camera toggle
    document.getElementById('toggleCamera')?.addEventListener('click', toggleCamera);
    
    // Manual entry
    document.getElementById('manualEntry')?.addEventListener('click', function() {
        if (Quagga) Quagga.stop();
        showScannerView('manual');
    });
    
    // Upload button
    document.getElementById('uploadImage')?.addEventListener('click', function() {
        showNotification('Image upload feature coming soon!', 'info');
    });
}

function openScanner() {
    const scannerModal = document.getElementById('scannerModal');
    if (!scannerModal) return;
    
    // Reset views
    document.getElementById('scannerView').style.display = 'block';
    document.getElementById('scanResultView').style.display = 'none';
    document.getElementById('manualEntryView').style.display = 'none';
    
    scannerModal.style.display = 'block';
    
    if (!scannerInitialized) {
        initQuagga();
    }
}

function initQuagga() {
    if (typeof Quagga === 'undefined') {
        console.error('Quagga library not loaded');
        showNotification('Scanner library failed to load', 'error');
        return;
    }
    
    Quagga.init({
        inputStream: {
            name: "Live",
            type: "LiveStream",
            target: document.querySelector('#scanner'),
            constraints: {
                facingMode: "environment",
                width: { min: 640, ideal: 1280, max: 1920 },
                height: { min: 480, ideal: 720, max: 1080 }
            },
        },
        decoder: {
            readers: [
                "ean_reader",
                "ean_8_reader",
                "upc_reader",
                "upc_e_reader",
                "code_128_reader",
                "code_39_reader"
            ]
        },
        locator: {
            patchSize: "medium",
            halfSample: true
        }
    }, function(err) {
        if (err) {
            console.error('Quagga init error:', err);
            showNotification('Failed to initialize camera. Please check permissions.', 'error');
            return;
        }
        
        Quagga.start();
        scannerInitialized = true;
        
        // Listen for scans
        Quagga.onDetected(handleScan);
    });
}

function toggleCamera() {
    if (!Quagga) return;
    
    Quagga.stop();
    
    const currentConstraints = Quagga.Config.inputStream.constraints;
    const newFacingMode = currentConstraints.facingMode === "user" ? "environment" : "user";
    
    setTimeout(() => {
        Quagga.init({
            ...Quagga.Config,
            inputStream: {
                ...Quagga.Config.inputStream,
                constraints: {
                    ...Quagga.Config.inputStream.constraints,
                    facingMode: newFacingMode
                }
            }
        }, function(err) {
            if (err) {
                console.error('Camera toggle error:', err);
                return;
            }
            Quagga.start();
        });
    }, 500);
}

function showScannerView(view) {
    document.getElementById('scannerView').style.display = view === 'scanner' ? 'block' : 'none';
    document.getElementById('scanResultView').style.display = view === 'result' ? 'block' : 'none';
    document.getElementById('manualEntryView').style.display = view === 'manual' ? 'block' : 'none';
}

function handleScan(result) {
    if (result && result.codeResult) {
        const barcode = result.codeResult.code;
        
        if (navigator.vibrate) {
            navigator.vibrate(200);
        }
        
        Quagga.stop();
        
        showScannerView('result');
        
        const productInfo = document.getElementById('productInfoContainer');
        productInfo.innerHTML = `
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Looking up product ${barcode}...</p>
            </div>
        `;
        
        fetchProductInfo(barcode);
    }
}

function manualEntry() {
    showScannerView('manual');
    setTimeout(() => {
        document.getElementById('manualBarcode')?.focus();
    }, 100);
}

function cancelManualEntry() {
    showScannerView('scanner');
    if (!scannerInitialized) {
        initQuagga();
    } else {
        Quagga.start();
    }
}

function submitManualBarcode() {
    const barcode = document.getElementById('manualBarcode').value.trim();
    
    if (!barcode) {
        showNotification('Please enter a barcode number', 'warning');
        return;
    }
    
    showScannerView('result');
    
    const productInfo = document.getElementById('productInfoContainer');
    productInfo.innerHTML = `
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Looking up product ${barcode}...</p>
        </div>
    `;
    
    fetchProductInfo(barcode);
}

function rescanProduct() {
    showScannerView('scanner');
    document.getElementById('scanner').style.display = 'block';
    document.querySelector('.scanner-overlay').style.display = 'flex';
    
    if (!scannerInitialized) {
        initQuagga();
    } else {
        Quagga.start();
    }
}

async function fetchProductInfo(barcode) {
    const productInfo = document.getElementById('productInfoContainer');
    currentScanBarcode = barcode;
    
    try {
        const response = await fetch(`https://world.openfoodfacts.org/api/v0/product/${barcode}.json`);
        const data = await response.json();
        
        if (data.status === 1) {
            const product = {
                name: data.product.product_name || `Product ${barcode}`,
                brand: data.product.brands || 'Unknown',
                category: data.product.categories ? data.product.categories.split(',')[0].trim() : 'Other',
                quantity: data.product.quantity || '',
                image: data.product.image_url,
                ingredients: data.product.ingredients_text || 'No ingredients information available'
            };
            
            currentProductData = product;
            displayProductInfo(product, barcode);
        } else {
            displayManualEntryForm(barcode);
        }
    } catch (error) {
        console.error('Error fetching product:', error);
        displayManualEntryForm(barcode);
    }
}

function displayProductInfo(product, barcode) {
    const productInfo = document.getElementById('productInfoContainer');
    
    const categoryIcons = {
        'Vegetables': 'fa-carrot',
        'Fruits': 'fa-apple-alt',
        'Dairy': 'fa-cheese',
        'Meat': 'fa-drumstick-bite',
        'Bakery': 'fa-bread-slice',
        'Beverages': 'fa-wine-bottle',
        'Snacks': 'fa-cookie',
        'Frozen': 'fa-snowflake',
        'Canned': 'fa-can'
    };
    
    const categoryIcon = categoryIcons[product.category] || 'fa-box';
    
    productInfo.innerHTML = `
        <div class="product-details-grid">
            <div>
                ${product.image ? 
                    `<img src="${product.image}" alt="${product.name}" class="product-image">` : 
                    `<div style="width: 120px; height: 120px; background: var(--gray-200); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-box-open" style="font-size: 40px; color: var(--gray-400);"></i>
                    </div>`
                }
            </div>
            
            <div class="product-info-list">
                <div class="product-info-item">
                    <i class="fas fa-tag"></i>
                    <div class="info-content">
                        <span class="info-label">Product Name</span>
                        <span class="info-value">${escapeHtml(product.name)}</span>
                    </div>
                </div>
                
                <div class="product-info-item">
                    <i class="fas fa-copyright"></i>
                    <div class="info-content">
                        <span class="info-label">Brand</span>
                        <span class="info-value">${escapeHtml(product.brand)}</span>
                    </div>
                </div>
                
                <div class="product-info-item">
                    <i class="fas ${categoryIcon}"></i>
                    <div class="info-content">
                        <span class="info-label">Category</span>
                        <span class="info-value">${escapeHtml(product.category)}</span>
                    </div>
                </div>
                
                <div class="product-info-item">
                    <i class="fas fa-weight"></i>
                    <div class="info-content">
                        <span class="info-label">Quantity</span>
                        <span class="info-value">${escapeHtml(product.quantity) || 'Not specified'}</span>
                    </div>
                </div>
                
                <div class="product-info-item">
                    <i class="fas fa-barcode"></i>
                    <div class="info-content">
                        <span class="info-label">Barcode</span>
                        <span class="info-value">${barcode}</span>
                    </div>
                </div>
                
                ${product.ingredients ? `
                <div class="product-info-item" style="flex-direction: column; align-items: flex-start;">
                    <div style="display: flex; gap: 10px; width: 100%;">
                        <i class="fas fa-flask"></i>
                        <div class="info-content">
                            <span class="info-label">Ingredients</span>
                            <span class="info-value" style="font-size: 0.9rem; line-height: 1.5;">${escapeHtml(product.ingredients.substring(0, 200))}${product.ingredients.length > 200 ? '...' : ''}</span>
                        </div>
                    </div>
                </div>
                ` : ''}
            </div>
        </div>
    `;
    
    document.getElementById('addScannedProduct').onclick = function() {
        addScannedProduct(product, barcode);
    };
}

function displayManualEntryForm(barcode) {
    const productInfo = document.getElementById('productInfoContainer');
    
    productInfo.innerHTML = `
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="width: 60px; height: 60px; background: var(--warning-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                <i class="fas fa-exclamation-triangle" style="font-size: 30px; color: var(--warning);"></i>
            </div>
            <h4 style="font-size: 1.2rem; color: var(--gray-900); margin-bottom: 5px;">Product Not Found</h4>
            <p style="color: var(--gray-500); margin-bottom: 20px;">No information found for barcode: <strong>${barcode}</strong></p>
        </div>
        
        <div style="background: white; border-radius: var(--radius-lg); padding: 20px;">
            <h5 style="font-size: 1rem; color: var(--gray-700); margin-bottom: 15px;">Enter Product Details</h5>
            
            <div class="form-group">
                <label for="manualProductName">Product Name <span class="required">*</span></label>
                <input type="text" id="manualProductName" class="form-input" placeholder="Enter product name" value="Product ${barcode}">
            </div>
            
            <div class="form-group">
                <label for="manualBrand">Brand (Optional)</label>
                <input type="text" id="manualBrand" class="form-input" placeholder="Enter brand name">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="manualCategory">Category</label>
                    <select id="manualCategory" class="form-input">
                        <option value="Vegetables">🥬 Vegetables</option>
                        <option value="Fruits">🍎 Fruits</option>
                        <option value="Meat">🥩 Meat</option>
                        <option value="Dairy">🥛 Dairy</option>
                        <option value="Grains">🌾 Grains</option>
                        <option value="Spices">🌶️ Spices</option>
                        <option value="Canned">🥫 Canned Goods</option>
                        <option value="Frozen">❄️ Frozen Foods</option>
                        <option value="Beverages">🥤 Beverages</option>
                        <option value="Other" selected>📦 Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="manualQuantity">Quantity</label>
                    <input type="text" id="manualQuantity" class="form-input" placeholder="e.g., 500g, 1L">
                </div>
            </div>
            
            <div class="form-group">
                <label for="manualNotes">Notes (Optional)</label>
                <input type="text" id="manualNotes" class="form-input" placeholder="Additional notes">
            </div>
        </div>
    `;
    
    document.getElementById('addScannedProduct').onclick = function() {
        const name = document.getElementById('manualProductName').value;
        const brand = document.getElementById('manualBrand')?.value || '';
        const category = document.getElementById('manualCategory').value;
        const quantity = document.getElementById('manualQuantity')?.value || '1 unit';
        const notes = document.getElementById('manualNotes')?.value || '';
        
        if (!name) {
            showNotification('Please enter a product name', 'warning');
            return;
        }
        
        const product = {
            name: name,
            brand: brand,
            category: category,
            quantity: quantity,
            notes: notes
        };
        
        addScannedProduct(product, barcode);
    };
}

function addScannedProduct(product, barcode) {
    document.getElementById('name').value = product.name;
    document.getElementById('category').value = product.category || 'Other';
    document.getElementById('quantity').value = product.quantity || '1 unit';
    
    let notes = product.notes || '';
    if (product.brand && product.brand !== 'Unknown') {
        notes += (notes ? ' | ' : '') + `Brand: ${product.brand}`;
    }
    notes += (notes ? ' | ' : '') + `Barcode: ${barcode}`;
    document.getElementById('notes').value = notes;
    
    closeScanner();
    document.getElementById('addIngredientModal').style.display = 'block';
    showNotification('Product information added to form!', 'success');
}

function closeScanner() {
    if (Quagga) {
        Quagga.stop();
    }
    
    const scannerModal = document.getElementById('scannerModal');
    if (scannerModal) {
        scannerModal.style.display = 'none';
    }
    
    // Reset views
    document.getElementById('scannerView').style.display = 'block';
    document.getElementById('scanResultView').style.display = 'none';
    document.getElementById('manualEntryView').style.display = 'none';
    
    // Reset scanner UI
    document.getElementById('scanner').style.display = 'block';
    document.querySelector('.scanner-overlay').style.display = 'flex';
    
    // Clear inputs
    document.getElementById('manualBarcode').value = '';
}

// Keyboard shortcuts
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            document.getElementById('addIngredientModal').style.display = 'block';
            document.getElementById('name').focus();
        }
        
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
            
            if (Quagga) {
                Quagga.stop();
            }
        }
        
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            document.querySelector('.search-box input')?.focus();
        }
    });
}

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : 'info-circle')}"></i>
        ${message}
    `;
    
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    notification.style.animation = 'slideInRight 0.3s ease';
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Escape HTML helper
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Export functions
window.manualEntry = manualEntry;
window.cancelManualEntry = cancelManualEntry;
window.submitManualBarcode = submitManualBarcode;
window.rescanProduct = rescanProduct;
window.editIngredient = editIngredient;
window.closeScanner = closeScanner;
window.closeModal = closeModal;