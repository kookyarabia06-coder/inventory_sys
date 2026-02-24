/**
 * Main JavaScript File
 * Handles common functionality across the system
 */

// Document Ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    initTooltips();
    
    // Initialize modals
    initModals();
    
    // Initialize search
    initSearch();
    
    // Initialize delete confirmation
    initDeleteConfirmation();
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        });
    }, 5000);
});

/**
 * Initialize tooltips for pop icons
 */
function initTooltips() {
    document.querySelectorAll('.pop-icon').forEach(function(icon) {
        icon.addEventListener('mouseenter', function() {
            // Tooltip handled by CSS
        });
    });
}

/**
 * Initialize modal functionality
 */
function initModals() {
    // Open modal
    document.querySelectorAll('[data-modal]').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const modalId = this.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
            }
        });
    });
    
    // Close modal
    document.querySelectorAll('.modal-close, .modal .btn-secondary').forEach(function(element) {
        element.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
}

/**
 * Initialize search functionality
 */
function initSearch() {
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    
    if (searchInput && searchButton) {
        searchButton.addEventListener('click', function() {
            performSearch(searchInput.value);
        });
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch(this.value);
            }
        });
    }
}

/**
 * Perform search
 * @param {string} query
 */
function performSearch(query) {
    if (query.trim() === '') return;
    
    // Show loading indicator
    showLoading();
    
    // Redirect to search page
    window.location.href = `/search?q=${encodeURIComponent(query)}`;
}

/**
 * Show loading overlay
 */
function showLoading() {
    const loading = document.createElement('div');
    loading.className = 'loading-overlay';
    loading.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(loading);
}

/**
 * Initialize delete confirmation
 */
function initDeleteConfirmation() {
    document.querySelectorAll('.delete-btn').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const itemName = this.getAttribute('data-item') || 'this item';
            const deleteUrl = this.getAttribute('href') || '#';
            
            if (confirm(`Are you sure you want to delete ${itemName}? This action cannot be undone.`)) {
                window.location.href = deleteUrl;
            }
        });
    });
}

/**
 * Show modal with dynamic content
 * @param {string} title
 * @param {string} content
 */
function showModal(title, content) {
    const modalId = 'dynamic-modal-' + Date.now();
    const modalHtml = `
        <div id="${modalId}" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>${title}</h2>
                    <span class="modal-close">&times;</span>
                </div>
                <div class="modal-body">
                    ${content}
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('modalContainer').innerHTML = modalHtml;
    document.getElementById(modalId).style.display = 'block';
    
    // Re-initialize modal events
    initModals();
}

/**
 * AJAX request helper
 * @param {string} url
 * @param {string} method
 * @param {object} data
 * @param {function} callback
 */
function ajaxRequest(url, method = 'GET', data = null, callback = null) {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (callback) callback(null, response);
                } catch (e) {
                    if (callback) callback(e, null);
                }
            } else {
                if (callback) callback(new Error('Request failed'), null);
            }
        }
    };
    
    xhr.send(data ? JSON.stringify(data) : null);
}

/**
 * Format number as currency
 * @param {number} amount
 * @returns {string}
 */
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Format date
 * @param {string} dateString
 * @returns {string}
 */
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
}
// Notification dropdown toggle
document.addEventListener('click', function(e) {
    const notificationIcon = document.getElementById('notificationIcon');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    if (notificationIcon && !notificationIcon.contains(e.target)) {
        notificationDropdown.style.display = 'none';
    }
});

// Mark all notifications as read
function markAllRead() {
    // Remove notification badge
    const badges = document.querySelectorAll('.notification-badge');
    badges.forEach(badge => badge.remove());
    
    // Update notification list
    const notificationList = document.querySelector('.notification-list');
    if (notificationList) {
        notificationList.innerHTML = `
            <div class="notification-empty">
                <i class="fas fa-bell-slash"></i>
                <p>No new notifications</p>
            </div>
        `;
    }
    
    // You can also make an AJAX call to update the server
    console.log('All notifications marked as read');
}

// Show notification on new activity
function showNotification(message, type = 'info') {
    const notificationIcon = document.getElementById('notificationIcon');
    if (notificationIcon) {
        // Add badge if not exists
        let badge = notificationIcon.querySelector('.notification-badge');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'notification-badge';
            badge.textContent = '1';
            notificationIcon.appendChild(badge);
        } else {
            const count = parseInt(badge.textContent) + 1;
            badge.textContent = count;
        }
        
        // Add to dropdown
        const notificationList = document.querySelector('.notification-list');
        if (notificationList) {
            const emptyState = notificationList.querySelector('.notification-empty');
            if (emptyState) {
                emptyState.remove();
            }
            
            const newNotification = document.createElement('a');
            newNotification.href = '#';
            newNotification.className = `notification-item ${type}`;
            newNotification.innerHTML = `
                <div class="notification-icon">
                    <i class="fas fa-${type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                </div>
                <div class="notification-content">
                    <p>${message}</p>
                    <small>Just now</small>
                </div>
            `;
            
            notificationList.prepend(newNotification);
        }
    }
}

// Example: Check for low stock every 5 minutes
setInterval(function() {
    // Make AJAX call to check for new notifications
    fetch('/api/check_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.has_new) {
                showNotification(data.message, data.type);
            }
        })
        .catch(error => console.error('Error checking notifications:', error));
}, 300000); // 5 minutes

// Notification functionality
document.addEventListener('DOMContentLoaded', function() {
    initNotifications();
});

function initNotifications() {
    const notificationIcon = document.getElementById('notificationIcon');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    if (notificationIcon) {
        // Toggle dropdown on click
        notificationIcon.addEventListener('click', function(e) {
            e.stopPropagation();
            if (notificationDropdown.style.display === 'block') {
                notificationDropdown.style.display = 'none';
            } else {
                notificationDropdown.style.display = 'block';
            }
        });
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (notificationIcon && !notificationIcon.contains(e.target)) {
            if (notificationDropdown) {
                notificationDropdown.style.display = 'none';
            }
        }
    });
}

// Mark all notifications as read
function markAllRead() {
    // Remove notification badge
    const badges = document.querySelectorAll('.notification-badge');
    badges.forEach(badge => {
        badge.style.animation = 'fadeOut 0.3s';
        setTimeout(() => {
            badge.remove();
        }, 300);
    });
    
    // Clear notification list
    const notificationList = document.querySelector('.notification-list');
    if (notificationList) {
        notificationList.innerHTML = `
            <div class="notification-empty">
                <i class="fas fa-bell-slash"></i>
                <p>No new notifications</p>
            </div>
        `;
    }
    
    // Show success message
    showNotification('All notifications marked as read', 'success');
    
    // You can also make an AJAX call to update the server
    fetch('/api/mark_all_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    }).catch(error => console.error('Error:', error));
}

// Show notification message
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
        ${message}
    `;
    
    // Add to page
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.insertBefore(notification, mainContent.firstChild);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'fadeOut 0.3s';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }
}

// Add new notification dynamically
function addNotification(notification) {
    const notificationList = document.querySelector('.notification-list');
    const notificationBadge = document.querySelector('.notification-badge');
    
    if (notificationList) {
        // Remove empty state if exists
        const emptyState = notificationList.querySelector('.notification-empty');
        if (emptyState) {
            emptyState.remove();
        }
        
        // Create new notification item
        const newNotif = document.createElement('a');
        newNotif.href = notification.link || '#';
        newNotif.className = `notification-item ${notification.type || 'info'}`;
        newNotif.innerHTML = `
            <div class="notification-icon">
                <i class="fas fa-${notification.icon || 'bell'}"></i>
            </div>
            <div class="notification-content">
                <p>${notification.message}</p>
                <small>Just now</small>
            </div>
        `;
        
        // Add to top of list
        notificationList.insertBefore(newNotif, notificationList.firstChild);
        
        // Update badge count
        if (notificationBadge) {
            let count = parseInt(notificationBadge.textContent) || 0;
            count++;
            notificationBadge.textContent = count;
            notificationBadge.style.display = 'flex';
        }
    }
}

// Animation keyframes (add to your CSS)
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    
    .notification-item {
        animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);