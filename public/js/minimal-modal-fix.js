/**
 * Minimal Modal Fix
 * Only fixes the z-index issue without interfering with Bootstrap
 */

// Just fix z-index when modal is shown
document.addEventListener('shown.bs.modal', function() {
    // Ensure backdrop is behind modal
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => {
        backdrop.style.zIndex = '1040';
    });
    
    // Ensure modal is above backdrop
    const modals = document.querySelectorAll('.modal.show');
    modals.forEach(modal => {
        modal.style.zIndex = '1055';
    });
});
