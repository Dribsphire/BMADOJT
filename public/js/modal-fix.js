/**
 * Modal Backdrop Fix for Admin Pages
 * Fixes the issue where .modal-backdrop.show overlays all modals
 */

document.addEventListener('DOMContentLoaded', function() {
    // Clean up any existing modal backdrops on page load
    const existingBackdrops = document.querySelectorAll('.modal-backdrop');
    existingBackdrops.forEach(backdrop => backdrop.remove());
    
    // Ensure body doesn't have modal-open class
    document.body.classList.remove('modal-open');
    
    // Reset body padding
    document.body.style.paddingRight = '';
});

// Clean up modal backdrops when modals are hidden
document.addEventListener('hidden.bs.modal', function (event) {
    // Remove any lingering modal backdrops
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => backdrop.remove());
    
    // Remove modal-open class from body
    document.body.classList.remove('modal-open');
    
    // Reset body padding if it was modified
    document.body.style.paddingRight = '';
});

// Additional cleanup when modals are shown
document.addEventListener('show.bs.modal', function (event) {
    // Remove any existing backdrops before showing new modal
    const existingBackdrops = document.querySelectorAll('.modal-backdrop');
    existingBackdrops.forEach(backdrop => backdrop.remove());
});

// Force cleanup on page unload
window.addEventListener('beforeunload', function() {
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => backdrop.remove());
    document.body.classList.remove('modal-open');
    document.body.style.paddingRight = '';
});
