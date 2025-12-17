/**
 * Main JavaScript
 * نظام إدارة محل أجهزة منزلية
 */

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function () {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.animation = 'slideUp 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});

// Confirm delete actions
function confirmDelete(message = 'هل أنت متأكد من الحذف؟') {
    return confirm(message);
}

// Format number with commas
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Calculate totals in invoice
function calculateInvoiceTotal() {
    let subtotal = 0;
    document.querySelectorAll('.invoice-item').forEach(item => {
        const total = parseFloat(item.querySelector('.item-total').textContent || 0);
        subtotal += total;
    });

    const discount = parseFloat(document.getElementById('discount')?.value || 0);
    const total = subtotal - discount;

    if (document.getElementById('subtotal')) {
        document.getElementById('subtotal').textContent = formatNumber(subtotal.toFixed(2));
    }
    if (document.getElementById('total')) {
        document.getElementById('total').textContent = formatNumber(total.toFixed(2));
    }

    calculateRemaining();
}

// Calculate remaining amount
function calculateRemaining() {
    const total = parseFloat(document.getElementById('total')?.textContent.replace(/,/g, '') || 0);
    const paid = parseFloat(document.getElementById('paid')?.value || 0);
    const remaining = total - paid;

    if (document.getElementById('remaining')) {
        document.getElementById('remaining').textContent = formatNumber(remaining.toFixed(2));
    }
}

// Play notification sound
function playNotificationSound() {
    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIHmjE8+Oqa1UAAAClN5Hc8tN8Jw');
    audio.play().catch(() => { });
}

// Print function
function printInvoice() {
    window.print();
}

// Search with autocomplete
function searchProducts(input, resultsContainer) {
    const query = input.value.trim();

    if (query.length < 2) {
        resultsContainer.innerHTML = '';
        resultsContainer.style.display = 'none';
        return;
    }

    fetch(`${window.location.origin}/سيستم اجهزه منزليه/modules/products/ajax.php?action=search&q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.products.length > 0) {
                displaySearchResults(data.products, resultsContainer);
            } else {
                resultsContainer.innerHTML = '<div class="p-2">لا توجد نتائج</div>';
                resultsContainer.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Search error:', error);
        });
}

function displaySearchResults(products, container) {
    container.innerHTML = products.map(product => `
        <div class="search-result-item" onclick="selectProduct(${product.id})">
            <strong>${product.name}</strong> (${product.code})
            <br>
            <small>السعر: ${product.price} - المخزون: ${product.stock_quantity}</small>
        </div>
    `).join('');
    container.style.display = 'block';
}

// Live search for products table (filters without reload)
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('productSearch');
    const tableBody = document.getElementById('productsTableBody');

    if (searchInput && tableBody) {
        searchInput.addEventListener('input', function () {
            const searchTerm = this.value.toLowerCase().trim();
            const rows = tableBody.querySelectorAll('tr');

            rows.forEach(row => {
                // Skip "no results" row
                if (row.querySelector('td[colspan]')) {
                    row.style.display = searchTerm ? 'none' : '';
                    return;
                }

                const code = row.cells[0]?.textContent?.toLowerCase() || '';
                const name = row.cells[2]?.textContent?.toLowerCase() || '';

                if (code.includes(searchTerm) || name.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // Update visible count
            const visibleRows = tableBody.querySelectorAll('tr:not([style*="display: none"]):not(:has(td[colspan]))');
            const countElement = document.querySelector('.mt-2 strong');
            if (countElement && countElement.nextSibling) {
                countElement.nextSibling.textContent = ' ' + visibleRows.length;
            }
        });
    }
});
