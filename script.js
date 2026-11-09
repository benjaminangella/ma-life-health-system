// script.js - Main JavaScript for MA-LIFE System

/**
 * ========================================
 * AUTHENTICATION FUNCTIONS
 * ========================================
 */

// Toggle between Login and Sign Up forms
function toggleForm(type) {
    const signupForm = document.getElementById('signupForm');
    const loginForm = document.getElementById('loginForm');
    const btnSignup = document.getElementById('btnSignup');
    const btnLogin = document.getElementById('btnLogin');
    const title = document.getElementById('formTitle');
    const subtitle = document.getElementById('formSubtitle');

    if (!signupForm || !loginForm) return;

    if (type === 'signup') {
        signupForm.classList.add('active-form');
        loginForm.classList.remove('active-form');
        btnSignup.classList.add('active');
        btnLogin.classList.remove('active');
        if (title) title.innerText = 'Sign Up for Ma-Life';
        if (subtitle) subtitle.innerText = 'Join Kawempe\'s digital healthcare network';
    } else {
        signupForm.classList.remove('active-form');
        loginForm.classList.add('active-form');
        btnSignup.classList.remove('active');
        btnLogin.classList.add('active');
        if (title) title.innerText = 'Welcome Back';
        if (subtitle) subtitle.innerText = 'Login to your dashboard';
    }
}

/**
 * ========================================
 * FORM HANDLING
 * ========================================
 */

// Handle Form Submissions
document.addEventListener('DOMContentLoaded', function() {
    // Signup Form
    const signupForm = document.getElementById('signupForm');
    if (signupForm) {
        signupForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const fullName = document.getElementById('full_name')?.value || '';
            const email = document.getElementById('email')?.value || '';
            const password = document.getElementById('password')?.value || '';
            const confirmPassword = document.getElementById('confirm_password')?.value || '';
            
            // Basic validation
            if (password !== confirmPassword) {
                showAlert('Passwords do not match!', 'error');
                return;
            }
            
            if (password.length < 8) {
                showAlert('Password must be at least 8 characters!', 'error');
                return;
            }
            
            // Simulate successful registration
            showAlert('Account created successfully! Redirecting to login...', 'success');
            
            // Redirect to login after 2 seconds
            setTimeout(function() {
                window.location.href = 'login.php';
            }, 2000);
        });
    }

    // Login Form
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email')?.value || '';
            const password = document.getElementById('password')?.value || '';
            
            if (!email || !password) {
                showAlert('Please fill in all fields!', 'error');
                return;
            }
            
            // Simulate successful login
            showAlert('Login successful! Redirecting to dashboard...', 'success');
            
            // Redirect to dashboard after 1.5 seconds
            setTimeout(function() {
                window.location.href = 'index.php';
            }, 1500);
        });
    }
});

/**
 * ========================================
 * ALERT/TOAST NOTIFICATIONS
 * ========================================
 */

function showAlert(message, type = 'info') {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert-message');
    existingAlerts.forEach(el => el.remove());
    
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert-message alert-${type}`;
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle',
        warning: 'fa-exclamation-triangle'
    };
    
    alertDiv.innerHTML = `
        <i class="fas ${icons[type] || icons.info}"></i>
        <span>${message}</span>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    // Style the alert
    Object.assign(alertDiv.style, {
        position: 'fixed',
        top: '20px',
        right: '20px',
        padding: '15px 20px',
        borderRadius: '8px',
        backgroundColor: type === 'success' ? '#e8f5e9' : type === 'error' ? '#ffebee' : '#e3f2fd',
        color: type === 'success' ? '#2e7d32' : type === 'error' ? '#c62828' : '#0d47a1',
        borderLeft: `4px solid ${type === 'success' ? '#2e7d32' : type === 'error' ? '#c62828' : '#0d47a1'}`,
        boxShadow: '0 4px 20px rgba(0,0,0,0.15)',
        zIndex: '9999',
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
        maxWidth: '400px',
        animation: 'slideInRight 0.4s ease',
        fontFamily: 'Inter, Arial, sans-serif',
        fontSize: '0.95rem'
    });
    
    // Close button style
    const closeBtn = alertDiv.querySelector('.alert-close');
    if (closeBtn) {
        Object.assign(closeBtn.style, {
            background: 'none',
            border: 'none',
            fontSize: '1.5rem',
            cursor: 'pointer',
            color: 'inherit',
            marginLeft: 'auto',
            opacity: '0.7'
        });
        closeBtn.addEventListener('mouseenter', function() {
            this.style.opacity = '1';
        });
        closeBtn.addEventListener('mouseleave', function() {
            this.style.opacity = '0.7';
        });
    }
    
    // Add to document
    document.body.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(function() {
        if (alertDiv.parentElement) {
            alertDiv.style.opacity = '0';
            alertDiv.style.transform = 'translateX(100px)';
            alertDiv.style.transition = 'all 0.4s ease';
            setTimeout(function() {
                alertDiv.remove();
            }, 400);
        }
    }, 5000);
}

// Add animation styles
const alertStyles = document.createElement('style');
alertStyles.textContent = `
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
`;
document.head.appendChild(alertStyles);

/**
 * ========================================
 * PASSWORD STRENGTH INDICATOR
 * ========================================
 */

function initPasswordStrength() {
    const passwordInput = document.getElementById('password');
    if (!passwordInput) return;
    
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strengthText = '';
        let color = '#999';
        let strength = 0;
        
        // Check length
        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        
        // Check for uppercase
        if (/[A-Z]/.test(password)) strength++;
        
        // Check for numbers
        if (/\d/.test(password)) strength++;
        
        // Check for special characters
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        // Determine strength
        if (password.length === 0) {
            strengthText = '';
        } else if (strength <= 2) {
            strengthText = '⚠️ Weak password';
            color = '#c62828';
        } else if (strength <= 4) {
            strengthText = '⚠️ Medium password';
            color = '#f57c00';
        } else {
            strengthText = '✅ Strong password';
            color = '#2e7d32';
        }
        
        // Find or create strength indicator
        let indicator = document.getElementById('passwordStrength');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'passwordStrength';
            indicator.className = 'password-hint';
            this.parentNode.appendChild(indicator);
        }
        
        indicator.textContent = strengthText;
        indicator.style.color = color;
    });
}

/**
 * ========================================
 * CONFIRM PASSWORD VALIDATION
 * ========================================
 */

function initConfirmPassword() {
    const confirmInput = document.getElementById('confirm_password');
    const passwordInput = document.getElementById('password');
    
    if (!confirmInput || !passwordInput) return;
    
    confirmInput.addEventListener('input', function() {
        const password = passwordInput.value;
        const confirm = this.value;
        
        if (confirm.length > 0 && password !== confirm) {
            this.style.borderColor = '#c62828';
            this.style.boxShadow = '0 0 0 4px rgba(198, 40, 40, 0.1)';
            
            // Show error message
            let errorMsg = document.getElementById('confirmError');
            if (!errorMsg) {
                errorMsg = document.createElement('div');
                errorMsg.id = 'confirmError';
                errorMsg.className = 'password-hint';
                errorMsg.style.color = '#c62828';
                this.parentNode.appendChild(errorMsg);
            }
            errorMsg.textContent = '❌ Passwords do not match';
        } else {
            this.style.borderColor = '#e0e0e0';
            this.style.boxShadow = 'none';
            
            const errorMsg = document.getElementById('confirmError');
            if (errorMsg) {
                errorMsg.textContent = '✅ Passwords match';
                errorMsg.style.color = '#2e7d32';
            }
        }
    });
}

/**
 * ========================================
 * FORM VALIDATION
 * ========================================
 */

function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const inputs = form.querySelectorAll('input[required], select[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = '#c62828';
            input.style.boxShadow = '0 0 0 4px rgba(198, 40, 40, 0.1)';
            isValid = false;
        } else {
            input.style.borderColor = '#e0e0e0';
            input.style.boxShadow = 'none';
        }
    });
    
    if (!isValid) {
        showAlert('Please fill in all required fields!', 'error');
    }
    
    return isValid;
}

/**
 * ========================================
 * MOBILE MENU TOGGLE
 * ========================================
 */

function toggleMobileMenu() {
    const nav = document.querySelector('.nav-links');
    if (nav) {
        nav.classList.toggle('show');
    }
}

/**
 * ========================================
 * SIDEBAR TOGGLE (For Mobile)
 * ========================================
 */

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
}

/**
 * ========================================
 * DASHBOARD STATS LOADING
 * ========================================
 */

async function loadDashboardStats() {
    try {
        const response = await fetch('api/stats.php');
        if (response.ok) {
            const data = await response.json();
            
            // Update stat cards
            const statCards = document.querySelectorAll('.stat-card .number');
            if (statCards.length >= 4) {
                statCards[0].textContent = data.mothers || 0;
                statCards[1].textContent = data.children || 0;
                statCards[2].textContent = data.workers || 0;
                statCards[3].textContent = data.appointments || 0;
            }
        }
    } catch(e) {
        console.error('Error loading stats:', e);
    }
}

/**
 * ========================================
 * INITIALIZATION
 * ========================================
 */

// Initialize all features when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Password strength indicator
    initPasswordStrength();
    
    // Confirm password validation
    initConfirmPassword();
    
    // Auto-load dashboard stats if on dashboard page
    if (document.querySelector('.dashboard-stats')) {
        loadDashboardStats();
        
        // Refresh stats every 30 seconds
        setInterval(loadDashboardStats, 30000);
    }
});

/**
 * ========================================
 * UTILITY FUNCTIONS
 * ========================================
 */

// Format date
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'UGX',
        minimumFractionDigits: 0
    }).format(amount);
}

// Truncate text
function truncateText(text, maxLength = 50) {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

// Generate random ID
function generateId(prefix = '') {
    return prefix + '_' + Math.random().toString(36).substr(2, 9);
}

// Debounce function for performance
function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * ========================================
 * PRINT FUNCTIONS
 * ========================================
 */

function printPage() {
    window.print();
}

function printReport(reportId) {
    const reportContent = document.getElementById(reportId);
    if (!reportContent) return;
    
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    printWindow.document.write(`
        <html>
            <head>
                <title>Print Report</title>
                <link rel="stylesheet" href="style.css">
                <style>
                    body { padding: 40px; }
                    .no-print { display: none; }
                </style>
            </head>
            <body>
                ${reportContent.innerHTML}
                <script>
                    window.onload = function() {
                        window.print();
                        window.close();
                    }
                <\/script>
            </body>
        </html>
    `);
    printWindow.document.close();
}

/**
 * ========================================
 * EXPORT FUNCTIONS
 * ========================================
 */

function exportToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => {
            rowData.push('"' + col.textContent.replace(/"/g, '""') + '"');
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Export to PDF (requires html2pdf library)
function exportToPDF(elementId, filename = 'report.pdf') {
    const element = document.getElementById(elementId);
    if (!element) {
        showAlert('Element not found!', 'error');
        return;
    }
    
    // Check if html2pdf is available
    if (typeof html2pdf === 'undefined') {
        // Load html2pdf library
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
        script.onload = function() {
            html2pdf()
                .set({
                    margin: 10,
                    filename: filename,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, letterRendering: true },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                })
                .from(element)
                .save();
        };
        document.head.appendChild(script);
    } else {
        html2pdf()
            .set({
                margin: 10,
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, letterRendering: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            })
            .from(element)
            .save();
    }
}