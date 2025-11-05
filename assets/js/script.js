// Custom JavaScript for nutrition clinic login page
// Bootstrap 5 handles most interactions now, but we can add custom enhancements here

// Optional: Add smooth scrolling or other effects if needed
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any custom functionality

    // Example: Auto-focus on the first input of the active tab
    const loginTab = document.getElementById('login-tab');
    const registerTab = document.getElementById('register-tab');

    if (loginTab && registerTab) {
        loginTab.addEventListener('shown.bs.tab', function() {
            document.getElementById('loginEmail').focus();
        });

        registerTab.addEventListener('shown.bs.tab', function() {
            document.getElementById('registerName').focus();
        });
    }

    // Optional: Add loading state to buttons on form submit
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
                submitBtn.disabled = true;
            }
        });
    });
});
