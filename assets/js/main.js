// Main JavaScript file for the PHPBaaS Platform

// Wait for the document to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Handle form validation
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Handle password confirmation match
    const passwordField = document.getElementById('register-password');
    const confirmPasswordField = document.getElementById('confirm-password');
    
    if (passwordField && confirmPasswordField) {
        confirmPasswordField.addEventListener('input', function() {
            if (passwordField.value !== confirmPasswordField.value) {
                confirmPasswordField.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordField.setCustomValidity('');
            }
        });
        
        passwordField.addEventListener('input', function() {
            if (passwordField.value !== confirmPasswordField.value) {
                confirmPasswordField.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordField.setCustomValidity('');
            }
        });
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // API key copy functionality
    const copyButtons = document.querySelectorAll('.copy-api-key');
    
    copyButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const apiKey = this.getAttribute('data-api-key');
            
            if (apiKey) {
                navigator.clipboard.writeText(apiKey).then(() => {
                    // Change button text temporarily
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                    }, 2000);
                });
            }
        });
    });
    
    // JSON editor formatting
    const jsonEditors = document.querySelectorAll('.json-editor');
    
    jsonEditors.forEach(function(editor) {
        editor.addEventListener('blur', function() {
            try {
                const json = JSON.parse(this.value);
                this.value = JSON.stringify(json, null, 2);
                this.classList.remove('is-invalid');
            } catch (e) {
                this.classList.add('is-invalid');
            }
        });
    });
});