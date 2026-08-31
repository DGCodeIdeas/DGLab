/**
 * DGLab PWA - Main Application JavaScript
 *
 * Core application functionality including:
 * - Service Worker management
 * - UI interactions
 * - API communication
 * - Upload handling
 */

// DGLab Global Namespace
const DGLab = window.DGLab = {
    version: '1.0.0',
    config: {
        apiBase: '/api',
        chunkSize: 1024 * 1024, // 1MB
        maxRetries: 3,
    },

    /**
     * Initialize the application
     */
    init: function() {
        console.log('[DGLab] Initializing v' + this.version);

        this.initServiceWorker();
        this.initUI();
        this.initEventListeners();
    },

    /**
     * Initialize Service Worker
     */
    initServiceWorker: function() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then((registration) => {
                    console.log('[DGLab] Service Worker registered:', registration.scope);

                    // Check for updates
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // New version available
                                this.showUpdateNotification();
                            }
                        });
                    });
                })
                .catch((error) => {
                    console.log('[DGLab] Service Worker registration failed:', error);
                });

            // Listen for messages from SW
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data === 'updateAvailable') {
                    this.showUpdateNotification();
                }
            });
        }
    },

    /**
     * Initialize UI components
     */
    initUI: function() {
        // Initialize tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach((el) => {
            new bootstrap.Tooltip(el);
        });

        // Initialize popovers
        const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
        popoverTriggerList.forEach((el) => {
            new bootstrap.Popover(el);
        });

        // Check online status
        this.updateOnlineStatus();
        window.addEventListener('online', () => this.updateOnlineStatus());
        window.addEventListener('offline', () => this.updateOnlineStatus());
    },

    /**
     * Update online status indicator
     */
    updateOnlineStatus: function() {
        const isOnline = navigator.onLine;

        if (isOnline) {
            document.body.classList.remove('offline');
            this.hideToast('offline');
        } else {
            document.body.classList.add('offline');
            this.showToast('You are offline. Some features may be unavailable.', 'warning', 'offline');
        }
    },

    /**
     * Initialize event listeners
     */
    initEventListeners: function() {
        // PWA Install button
        let deferredPrompt;

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;

            const installBtn = document.getElementById('install-pwa');
            if (installBtn) {
                installBtn.classList.remove('d-none');
                installBtn.addEventListener('click', () => {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        if (choiceResult.outcome === 'accepted') {
                            console.log('[DGLab] PWA installed');
                        }
                        deferredPrompt = null;
                        installBtn.classList.add('d-none');
                    });
                });
            }
        });

        // File upload drag and drop
        this.initDragAndDrop();
    },

    /**
     * Initialize drag and drop for file uploads
     */
    initDragAndDrop: function() {
        const dropZones = document.querySelectorAll('.upload-zone');

        dropZones.forEach((zone) => {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
                zone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                });
            });

            ['dragenter', 'dragover'].forEach((eventName) => {
                zone.addEventListener(eventName, () => {
                    zone.classList.add('dragover');
                });
            });

            ['dragleave', 'drop'].forEach((eventName) => {
                zone.addEventListener(eventName, () => {
                    zone.classList.remove('dragover');
                });
            });
        });
    },

    /**
     * Show toast notification
     */
    showToast: function(message, type = 'info', id = null) {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toastId = id || 'toast-' + Date.now();

        // Remove existing toast with same ID
        const existing = document.getElementById(toastId);
        if (existing) {
            existing.remove();
        }

        const icons = {
            success: 'check-circle-fill',
            error: 'exclamation-circle-fill',
            warning: 'exclamation-triangle-fill',
            info: 'info-circle-fill'
        };

        const toast = document.createElement('div');
        toast.id = toastId;
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${icons[type]} me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;

        container.appendChild(toast);

        const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
        bsToast.show();

        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    },

    /**
     * Hide toast notification
     */
    hideToast: function(id) {
        const toast = document.getElementById(id);
        if (toast) {
            const bsToast = bootstrap.Toast.getInstance(toast);
            if (bsToast) {
                bsToast.hide();
            }
        }
    },

    /**
     * Show loading overlay
     */
    showLoading: function(message = 'Processing...') {
        const overlay = document.getElementById('loading-overlay');
        const msgEl = document.getElementById('loading-message');

        if (overlay && msgEl) {
            msgEl.textContent = message;
            overlay.classList.remove('d-none');
        }
    },

    /**
     * Hide loading overlay
     */
    hideLoading: function() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.classList.add('d-none');
        }
    },

    /**
     * Show update notification
     */
    showUpdateNotification: function() {
        this.showToast('A new version is available! <button class="btn btn-sm btn-light ms-2" onclick="location.reload()">Refresh</button>', 'info', 'update');
    },

    /**
     * API Request helper
     */
    api: async function(endpoint, options = {}) {
        const url = this.config.apiBase + endpoint;

        const defaultOptions = {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        const fetchOptions = { ...defaultOptions, ...options };

        // Add CSRF token for non-GET requests
        if (fetchOptions.method && fetchOptions.method !== 'GET') {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) {
                fetchOptions.headers['X-CSRF-Token'] = csrfToken;
            }
        }

        try {
            const response = await fetch(url, fetchOptions);

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(error.error || `HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('[DGLab] API Error:', error);
            throw error;
        }
    },

    /**
     * Upload file with chunked support
     */
    uploadWithChunking: async function(file, serviceId, options = {}, onProgress = null) {
        const fileSize = file.size;
        const chunkSize = options.chunkSize || this.config.chunkSize;
        const totalChunks = Math.ceil(fileSize / chunkSize);

        // Initialize chunked upload
        const initResponse = await this.api('/chunk/init', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                service_id: serviceId,
                filename: file.name,
                file_size: fileSize,
                ...options.metadata
            })
        });

        const sessionId = initResponse.session_id;

        // Upload chunks
        for (let i = 0; i < totalChunks; i++) {
            const start = i * chunkSize;
            const end = Math.min(start + chunkSize, fileSize);
            const chunk = file.slice(start, end);

            const chunkFormData = new FormData();
            chunkFormData.append('session_id', sessionId);
            chunkFormData.append('chunk_index', i);
            chunkFormData.append('chunk_data', chunk);

            await this.api('/chunk/upload', {
                method: 'POST',
                body: chunkFormData
            });

            if (onProgress) {
                onProgress(Math.round(((i + 1) / totalChunks) * 100));
            }
        }

        // Finalize
        return await this.api('/chunk/finalize', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: sessionId })
        });
    },

    /**
     * Navigate to a route
     */
    navigate: function(url) {
        window.location.href = url;
    },

    /**
     * Format bytes to human readable
     */
    formatBytes: function(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(decimals)) + ' ' + sizes[i];
    },

    /**
     * Debounce function
     */
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Throttle function
     */
    throttle: function(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    DGLab.init();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DGLab;
}
