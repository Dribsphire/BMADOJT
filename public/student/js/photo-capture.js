/**
 * Photo Capture Module for Time-In Attendance
 * Handles camera access, photo capture, compression, and preview
 */

class PhotoCapture {
    constructor() {
        this.stream = null;
        this.video = null;
        this.canvas = null;
        this.capturedPhoto = null;
        this.maxFileSize = 2 * 1024 * 1024; // 2MB
        this.compressionQuality = 0.8;
    }

    /**
     * Initialize camera access
     * @param {string} videoElementId - ID of video element
     * @param {string} canvasElementId - ID of canvas element
     * @param {string} preferredCamera - 'front' or 'rear'
     * @returns {Promise<boolean>} Success status
     */
    async initializeCamera(videoElementId, canvasElementId, preferredCamera = 'rear') {
        try {
            this.video = document.getElementById(videoElementId);
            this.canvas = document.getElementById(canvasElementId);
            
            if (!this.video || !this.canvas) {
                throw new Error('Video or canvas element not found');
            }

            // Get camera constraints
            const constraints = this.getCameraConstraints(preferredCamera);
            
            // Request camera access
            this.stream = await navigator.mediaDevices.getUserMedia(constraints);
            
            // Set video source
            this.video.srcObject = this.stream;
            this.video.play();
            
            return true;
        } catch (error) {
            console.error('Camera initialization failed:', error);
            this.handleCameraError(error);
            return false;
        }
    }

    /**
     * Get camera constraints based on preference
     * @param {string} preferredCamera - 'front' or 'rear'
     * @returns {object} Media constraints
     */
    getCameraConstraints(preferredCamera) {
        return {
            video: {
                facingMode: preferredCamera === 'front' ? 'user' : 'environment',
                width: { ideal: 1280 },
                height: { ideal: 720 }
            },
            audio: false
        };
    }

    /**
     * Capture photo from video stream
     * @returns {Promise<string>} Base64 encoded image data
     */
    async capturePhoto() {
        try {
            if (!this.video || !this.canvas) {
                throw new Error('Camera not initialized');
            }

            // Set canvas dimensions to match video
            this.canvas.width = this.video.videoWidth;
            this.canvas.height = this.video.videoHeight;

            // Draw video frame to canvas
            const context = this.canvas.getContext('2d');
            context.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height);

            // Convert to blob and compress
            const blob = await this.compressImage(this.canvas);
            
            // Convert to base64
            const base64 = await this.blobToBase64(blob);
            
            this.capturedPhoto = base64;
            return base64;
        } catch (error) {
            console.error('Photo capture failed:', error);
            throw error;
        }
    }

    /**
     * Compress image to meet size requirements
     * @param {HTMLCanvasElement} canvas - Canvas with image
     * @returns {Promise<Blob>} Compressed image blob
     */
    async compressImage(canvas) {
        return new Promise((resolve) => {
            let quality = this.compressionQuality;
            
            const compress = () => {
                canvas.toBlob((blob) => {
                    if (blob.size <= this.maxFileSize || quality <= 0.1) {
                        resolve(blob);
                    } else {
                        quality -= 0.1;
                        compress();
                    }
                }, 'image/jpeg', quality);
            };
            
            compress();
        });
    }

    /**
     * Convert blob to base64
     * @param {Blob} blob - Image blob
     * @returns {Promise<string>} Base64 string
     */
    blobToBase64(blob) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    }

    /**
     * Validate image format and size
     * @param {string} base64Data - Base64 image data
     * @returns {object} Validation result
     */
    validateImage(base64Data) {
        try {
            // Check if it's a valid base64 image
            if (!base64Data.startsWith('data:image/')) {
                return { valid: false, error: 'Invalid image format' };
            }

            // Check file size
            const sizeInBytes = (base64Data.length * 3) / 4;
            if (sizeInBytes > this.maxFileSize) {
                return { valid: false, error: 'Image too large. Please retake photo.' };
            }

            // Check format (JPEG/PNG)
            const format = base64Data.split(';')[0].split('/')[1];
            if (!['jpeg', 'jpg', 'png'].includes(format.toLowerCase())) {
                return { valid: false, error: 'Unsupported image format. Use JPEG or PNG.' };
            }

            return { valid: true, size: sizeInBytes, format: format };
        } catch (error) {
            return { valid: false, error: 'Invalid image data' };
        }
    }

    /**
     * Show photo preview
     * @param {string} previewElementId - ID of preview element
     * @param {string} base64Data - Base64 image data
     */
    showPreview(previewElementId, base64Data) {
        const previewElement = document.getElementById(previewElementId);
        if (previewElement) {
            previewElement.src = base64Data;
            previewElement.style.display = 'block';
        }
    }

    /**
     * Hide photo preview
     * @param {string} previewElementId - ID of preview element
     */
    hidePreview(previewElementId) {
        const previewElement = document.getElementById(previewElementId);
        if (previewElement) {
            previewElement.style.display = 'none';
        }
    }

    /**
     * Switch camera (front/rear)
     * @param {string} preferredCamera - 'front' or 'rear'
     * @returns {Promise<boolean>} Success status
     */
    async switchCamera(preferredCamera) {
        try {
            // Stop current stream
            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
            }

            // Initialize with new camera
            return await this.initializeCamera(
                this.video.id, 
                this.canvas.id, 
                preferredCamera
            );
        } catch (error) {
            console.error('Camera switch failed:', error);
            return false;
        }
    }

    /**
     * Stop camera stream
     */
    stopCamera() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
    }

    /**
     * Handle camera errors
     * @param {Error} error - Camera error
     */
    handleCameraError(error) {
        let message = 'Camera access failed. ';
        
        switch (error.name) {
            case 'NotAllowedError':
                message += 'Camera permission denied. Please allow camera access and try again.';
                break;
            case 'NotFoundError':
                message += 'No camera found on this device.';
                break;
            case 'NotReadableError':
                message += 'Camera is being used by another application.';
                break;
            case 'OverconstrainedError':
                message += 'Camera constraints cannot be satisfied.';
                break;
            default:
                message += error.message;
        }
        
        // Show error to user
        this.showError(message);
    }

    /**
     * Show error message
     * @param {string} message - Error message
     */
    showError(message) {
        // Create or update error display
        let errorDiv = document.getElementById('camera-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = 'camera-error';
            errorDiv.className = 'alert alert-danger';
            document.body.appendChild(errorDiv);
        }
        
        errorDiv.innerHTML = `
            <i class="bi bi-exclamation-triangle me-2"></i>
            ${message}
        `;
        errorDiv.style.display = 'block';
    }

    /**
     * Hide error message
     */
    hideError() {
        const errorDiv = document.getElementById('camera-error');
        if (errorDiv) {
            errorDiv.style.display = 'none';
        }
    }

    /**
     * Get captured photo data
     * @returns {string|null} Base64 image data
     */
    getCapturedPhoto() {
        return this.capturedPhoto;
    }

    /**
     * Clear captured photo
     */
    clearCapturedPhoto() {
        this.capturedPhoto = null;
    }
}

// Export for use in other modules
window.PhotoCapture = PhotoCapture;
