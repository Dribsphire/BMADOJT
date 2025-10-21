/**
 * Simple Modal Fix
 * Just fixes the basic modal backdrop issue
 */

// Clean up on page load
document.addEventListener('DOMContentLoaded', function() {
    // Remove any existing backdrops
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => backdrop.remove());
    
    // Clean body
    document.body.classList.remove('modal-open');
    document.body.style.paddingRight = '';
});

// Clean up when modal is hidden
document.addEventListener('hidden.bs.modal', function() {
    // Remove backdrops
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => backdrop.remove());
    
    // Clean body
    document.body.classList.remove('modal-open');
    document.body.style.paddingRight = '';
});

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => backdrop.remove());
    document.body.classList.remove('modal-open');
    document.body.style.paddingRight = '';
});
