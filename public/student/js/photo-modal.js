/**
 * Photo Capture Modal for Time-In Attendance
 * Provides UI for camera access, photo capture, and preview
 */

class PhotoModal {
    constructor() {
        this.photoCapture = new PhotoCapture();
        this.modal = null;
        this.currentBlockType = null;
        this.onPhotoCaptured = null;
        this.onCancel = null;
    }

    /**
     * Show photo capture modal
     * @param {string} blockType - Attendance block type
     * @param {function} onPhotoCaptured - Callback when photo is captured
     * @param {function} onCancel - Callback when modal is cancelled
     */
    show(blockType, onPhotoCaptured, onCancel) {
        this.currentBlockType = blockType;
        this.onPhotoCaptured = onPhotoCaptured;
        this.onCancel = onCancel;

        this.createModal();
        this.initializeCamera();
    }

    /**
     * Create photo capture modal HTML
     */
    createModal() {
        // Remove existing modal if any
        const existingModal = document.getElementById('photo-capture-modal');
        if (existingModal) {
            existingModal.remove();
        }

        const modalHTML = `
            <div class="modal fade" id="photo-capture-modal" tabindex="-1" aria-labelledby="photo-capture-modal-label" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="photo-capture-modal-label">
                                <i class="bi bi-camera me-2"></i>Capture Photo for ${this.currentBlockType} Time-In
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="camera-container">
                                        <video id="camera-video" autoplay muted playsinline style="width: 100%; max-height: 400px; background: #000;"></video>
                                        <canvas id="camera-canvas" style="display: none;"></canvas>
                                        <div id="photo-preview" class="text-center" style="display: none;">
                                            <img id="preview-image" style="max-width: 100%; max-height: 400px;" />
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="camera-controls">
                                        <div class="mb-3">
                                            <label class="form-label">Camera:</label>
                                            <div class="btn-group w-100" role="group">
                                                <input type="radio" class="btn-check" name="camera-type" id="rear-camera" value="rear" checked>
                                                <label class="btn btn-outline-primary" for="rear-camera">
                                                    <i class="bi bi-camera-rear me-1"></i>Rear
                                                </label>
                                                <input type="radio" class="btn-check" name="camera-type" id="front-camera" value="front">
                                                <label class="btn btn-outline-primary" for="front-camera">
                                                    <i class="bi bi-camera-front me-1"></i>Front
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <button id="capture-btn" class="btn btn-success w-100">
                                                <i class="bi bi-camera-fill me-2"></i>Capture Photo
                                            </button>
                                        </div>
                                        
                                        <div class="mb-3" id="preview-controls" style="display: none;">
                                            <button id="retake-btn" class="btn btn-warning w-100 mb-2">
                                                <i class="bi bi-arrow-clockwise me-2"></i>Retake
                                            </button>
                                            <button id="use-photo-btn" class="btn btn-primary w-100">
                                                <i class="bi bi-check-circle me-2"></i>Use This Photo
                                            </button>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="bi bi-info-circle me-1"></i>
                                                Photo will be compressed to max 2MB
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle me-2"></i>Cancel
                            </button>
                            <button type="button" class="btn btn-primary" id="proceed-without-photo">
                                <i class="bi bi-clock me-2"></i>Proceed Without Photo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = new bootstrap.Modal(document.getElementById('photo-capture-modal'));
        this.setupEventListeners();
        this.modal.show();
    }

    /**
     * Setup event listeners for modal interactions
     */
    setupEventListeners() {
        // Camera type change
        document.querySelectorAll('input[name="camera-type"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.switchCamera(e.target.value);
            });
        });

        // Capture photo
        document.getElementById('capture-btn').addEventListener('click', () => {
            this.capturePhoto();
        });

        // Retake photo
        document.getElementById('retake-btn').addEventListener('click', () => {
            this.retakePhoto();
        });

        // Use captured photo
        document.getElementById('use-photo-btn').addEventListener('click', () => {
            this.usePhoto();
        });

        // Proceed without photo
        document.getElementById('proceed-without-photo').addEventListener('click', () => {
            this.proceedWithoutPhoto();
        });

        // Modal close events
        document.getElementById('photo-capture-modal').addEventListener('hidden.bs.modal', () => {
            this.cleanup();
        });
    }

    /**
     * Initialize camera
     */
    async initializeCamera() {
        const success = await this.photoCapture.initializeCamera('camera-video', 'camera-canvas', 'rear');
        if (!success) {
            this.showCameraError();
        }
    }

    /**
     * Switch camera (front/rear)
     * @param {string} cameraType - 'front' or 'rear'
     */
    async switchCamera(cameraType) {
        const success = await this.photoCapture.switchCamera(cameraType);
        if (!success) {
            this.showCameraError();
        }
    }

    /**
     * Capture photo
     */
    async capturePhoto() {
        try {
            const captureBtn = document.getElementById('capture-btn');
            captureBtn.disabled = true;
            captureBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Capturing...';

            const base64Data = await this.photoCapture.capturePhoto();
            
            // Validate image
            const validation = this.photoCapture.validateImage(base64Data);
            if (!validation.valid) {
                this.showError(validation.error);
                captureBtn.disabled = false;
                captureBtn.innerHTML = '<i class="bi bi-camera-fill me-2"></i>Capture Photo';
                return;
            }

            // Show preview
            this.photoCapture.showPreview('preview-image', base64Data);
            document.getElementById('photo-preview').style.display = 'block';
            document.getElementById('camera-video').style.display = 'none';
            document.getElementById('preview-controls').style.display = 'block';
            document.getElementById('capture-btn').style.display = 'none';

        } catch (error) {
            console.error('Photo capture failed:', error);
            this.showError('Failed to capture photo. Please try again.');
            const captureBtn = document.getElementById('capture-btn');
            captureBtn.disabled = false;
            captureBtn.innerHTML = '<i class="bi bi-camera-fill me-2"></i>Capture Photo';
        }
    }

    /**
     * Retake photo
     */
    retakePhoto() {
        document.getElementById('photo-preview').style.display = 'none';
        document.getElementById('camera-video').style.display = 'block';
        document.getElementById('preview-controls').style.display = 'none';
        document.getElementById('capture-btn').style.display = 'block';
        
        this.photoCapture.clearCapturedPhoto();
    }

    /**
     * Use captured photo
     */
    usePhoto() {
        const photoData = this.photoCapture.getCapturedPhoto();
        if (photoData && this.onPhotoCaptured) {
            this.onPhotoCaptured(photoData);
        }
        this.close();
    }

    /**
     * Proceed without photo
     */
    proceedWithoutPhoto() {
        if (this.onPhotoCaptured) {
            this.onPhotoCaptured(null); // null indicates no photo
        }
        this.close();
    }

    /**
     * Show camera error
     */
    showCameraError() {
        const videoElement = document.getElementById('camera-video');
        videoElement.style.display = 'none';
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-warning text-center';
        errorDiv.innerHTML = `
            <i class="bi bi-exclamation-triangle me-2"></i>
            Camera access failed. You can still proceed without a photo.
        `;
        
        document.querySelector('.camera-container').appendChild(errorDiv);
    }

    /**
     * Show error message
     * @param {string} message - Error message
     */
    showError(message) {
        // Remove existing error
        const existingError = document.querySelector('.photo-error');
        if (existingError) {
            existingError.remove();
        }

        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger photo-error';
        errorDiv.innerHTML = `
            <i class="bi bi-exclamation-triangle me-2"></i>
            ${message}
        `;
        
        document.querySelector('.camera-container').insertBefore(errorDiv, document.querySelector('.camera-container').firstChild);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
        }, 5000);
    }

    /**
     * Close modal
     */
    close() {
        if (this.modal) {
            this.modal.hide();
        }
    }

    /**
     * Cleanup resources
     */
    cleanup() {
        this.photoCapture.stopCamera();
        this.photoCapture.clearCapturedPhoto();
        
        // Remove modal from DOM
        const modalElement = document.getElementById('photo-capture-modal');
        if (modalElement) {
            modalElement.remove();
        }
    }
}

// Export for use in other modules
window.PhotoModal = PhotoModal;
