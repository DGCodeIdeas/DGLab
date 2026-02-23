/**
 * DGLab PWA - Main JavaScript
 * 
 * This is the main JavaScript file for the DGLab PWA.
 * It handles:
 * - UI interactions
 * - File uploads (including chunked uploads)
 * - Tool processing
 * - Toast notifications
 * - PWA functionality
 * 
 * @package DGLab\Assets\JS
 * @author DGLab Team
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // =========================================================================
    // DGLab Namespace
    // =========================================================================

    window.DGLab = {
        version: '1.0.0',
        config: {
            csrfToken: $('meta[name="csrf-token"]').attr('content'),
            baseUrl: ''
        }
    };

    // =========================================================================
    // Utility Functions
    // =========================================================================

    /**
     * Format file size to human-readable string
     * @param {number} bytes - File size in bytes
     * @returns {string} Formatted size
     */
    DGLab.formatFileSize = function(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    /**
     * Show toast notification
     * @param {string} message - Toast message
     * @param {string} type - Toast type (success, error, warning)
     * @param {number} duration - Duration in milliseconds
     */
    DGLab.showToast = function(message, type = 'success', duration = 5000) {
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle'
        };
        
        const toast = $(`
            <div class="toast toast-${type}">
                <i class="fas ${icons[type]}"></i>
                <span>${message}</span>
            </div>
        `);
        
        $('#toast-container').append(toast);
        
        setTimeout(() => {
            toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, duration);
    };

    /**
     * Show loading overlay
     * @param {string} text - Loading text
     */
    DGLab.showLoading = function(text = 'Processing...') {
        $('#loading-overlay .loading-text').text(text);
        $('#loading-overlay').fadeIn(200);
    };

    /**
     * Hide loading overlay
     */
    DGLab.hideLoading = function() {
        $('#loading-overlay').fadeOut(200);
    };

    // =========================================================================
    // Chunked Upload Handler
    // =========================================================================

    DGLab.ChunkedUploader = {
        chunkSize: 1024 * 1024, // 1MB chunks
        uploadId: null,
        file: null,
        totalChunks: 0,
        currentChunk: 0,
        
        /**
         * Initialize upload
         * @param {File} file - File to upload
         * @returns {Promise} Upload initialization promise
         */
        init: function(file) {
            this.file = file;
            this.totalChunks = Math.ceil(file.size / this.chunkSize);
            this.currentChunk = 0;
            
            return $.ajax({
                url: '/upload/init',
                method: 'POST',
                data: {
                    filename: file.name,
                    total_size: file.size,
                    mime_type: file.type,
                    csrf_token: DGLab.config.csrfToken
                }
            }).then((response) => {
                if (response.success) {
                    this.uploadId = response.data.upload_id;
                    return response.data;
                }
                throw new Error(response.message || 'Failed to initialize upload');
            });
        },
        
        /**
         * Upload a single chunk
         * @returns {Promise} Chunk upload promise
         */
        uploadChunk: function() {
            const start = this.currentChunk * this.chunkSize;
            const end = Math.min(start + this.chunkSize, this.file.size);
            const chunk = this.file.slice(start, end);
            
            const formData = new FormData();
            formData.append('upload_id', this.uploadId);
            formData.append('chunk_index', this.currentChunk);
            formData.append('chunk', chunk);
            formData.append('csrf_token', DGLab.config.csrfToken);
            
            return $.ajax({
                url: '/upload/chunk',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }).then((response) => {
                this.currentChunk++;
                return response.data;
            });
        },
        
        /**
         * Upload entire file with progress
         * @param {Function} onProgress - Progress callback
         * @returns {Promise} Upload completion promise
         */
        upload: function(onProgress) {
            const uploadNextChunk = () => {
                if (this.currentChunk >= this.totalChunks) {
                    return Promise.resolve();
                }
                
                return this.uploadChunk().then((data) => {
                    const progress = Math.round((this.currentChunk / this.totalChunks) * 100);
                    if (onProgress) {
                        onProgress(progress, data);
                    }
                    
                    if (data.status === 'completed') {
                        return data;
                    }
                    
                    return uploadNextChunk();
                });
            };
            
            return uploadNextChunk();
        }
    };

    // =========================================================================
    // File Upload Handler
    // =========================================================================

    DGLab.FileUpload = {
        file: null,
        uploadId: null,
        
        /**
         * Initialize file upload handlers
         */
        init: function() {
            const $uploadZone = $('#upload-zone');
            const $fileInput = $('#file-input');
            
            // Drag and drop
            $uploadZone.on('dragover', (e) => {
                e.preventDefault();
                $uploadZone.addClass('dragover');
            });
            
            $uploadZone.on('dragleave', () => {
                $uploadZone.removeClass('dragover');
            });
            
            $uploadZone.on('drop', (e) => {
                e.preventDefault();
                $uploadZone.removeClass('dragover');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    this.handleFile(files[0]);
                }
            });
            
            // File input change
            $fileInput.on('change', (e) => {
                if (e.target.files.length > 0) {
                    this.handleFile(e.target.files[0]);
                }
            });
            
            // Remove file
            $('#remove-file').on('click', () => {
                this.clearFile();
            });
        },
        
        /**
         * Handle selected file
         * @param {File} file - Selected file
         */
        handleFile: function(file) {
            // Validate file size
            const maxSize = parseInt($('#file-input').data('max-size'));
            if (file.size > maxSize) {
                DGLab.showToast('File size exceeds maximum allowed', 'error');
                return;
            }
            
            this.file = file;
            
            // Show file info
            $('#file-name').text(file.name);
            $('#file-size').text(DGLab.formatFileSize(file.size));
            $('#file-info').show();
            $('#upload-zone').hide();
            
            // Enable process button
            $('#process-btn').prop('disabled', false);
        },
        
        /**
         * Clear selected file
         */
        clearFile: function() {
            this.file = null;
            this.uploadId = null;
            
            $('#file-input').val('');
            $('#file-info').hide();
            $('#upload-zone').show();
            $('#upload-progress').hide();
            $('#process-btn').prop('disabled', true);
            $('#upload-id').val('');
        },
        
        /**
         * Upload file and return upload ID
         * @param {Function} onProgress - Progress callback
         * @returns {Promise} Upload promise
         */
        upload: function(onProgress) {
            if (!this.file) {
                return Promise.reject(new Error('No file selected'));
            }
            
            // Check if chunked upload is needed (> 5MB)
            if (this.file.size > 5 * 1024 * 1024 && window.toolConfig?.supportsChunking) {
                return this.uploadChunked(onProgress);
            } else {
                return this.uploadDirect(onProgress);
            }
        },
        
        /**
         * Upload file using chunked upload
         * @param {Function} onProgress - Progress callback
         * @returns {Promise} Upload promise
         */
        uploadChunked: function(onProgress) {
            DGLab.ChunkedUploader.file = this.file;
            
            return DGLab.ChunkedUploader.init(this.file).then(() => {
                $('#upload-progress').show();
                
                return DGLab.ChunkedUploader.upload((progress, data) => {
                    $('#progress-fill').css('width', progress + '%');
                    $('#progress-text').text(progress + '%');
                    
                    if (onProgress) {
                        onProgress(progress);
                    }
                    
                    if (data.status === 'completed') {
                        this.uploadId = DGLab.ChunkedUploader.uploadId;
                        $('#upload-id').val(this.uploadId);
                    }
                });
            });
        },
        
        /**
         * Upload file directly (for smaller files)
         * @param {Function} onProgress - Progress callback
         * @returns {Promise} Upload promise
         */
        uploadDirect: function(onProgress) {
            // For direct upload, we'll include the file in the process request
            return Promise.resolve({ status: 'ready' });
        }
    };

    // =========================================================================
    // Tool Processing
    // =========================================================================

    DGLab.ToolProcessor = {
        /**
         * Initialize tool form handler
         */
        init: function() {
            $('#tool-options-form').on('submit', (e) => {
                e.preventDefault();
                this.process();
            });
            
            $('#process-another').on('click', () => {
                this.reset();
            });
        },
        
        /**
         * Process the file
         */
        process: function() {
            const $form = $('#tool-options-form');
            const formData = new FormData($form[0]);
            
            // Add file if not using chunked upload
            if (DGLab.FileUpload.file && !DGLab.FileUpload.uploadId) {
                formData.append('file', DGLab.FileUpload.file);
            }
            
            DGLab.showLoading('Processing your file...');
            
            $.ajax({
                url: window.toolConfig?.apiUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }).then((response) => {
                DGLab.hideLoading();
                
                if (response.success) {
                    this.showResults(response.data);
                    DGLab.showToast('Processing completed successfully!', 'success');
                } else {
                    DGLab.showToast(response.message || 'Processing failed', 'error');
                }
            }).catch((xhr) => {
                DGLab.hideLoading();
                
                const response = xhr.responseJSON || {};
                DGLab.showToast(response.message || 'An error occurred', 'error');
            });
        },
        
        /**
         * Show processing results
         * @param {Object} data - Result data
         */
        showResults: function(data) {
            $('#result-filename').text(data.output_filename);
            $('#result-details').html(`
                File size: ${DGLab.formatFileSize(data.file_size)}<br>
                Processing time: ${data.processing_time}s
            `);
            
            $('#download-btn').attr('href', data.download_url);
            $('#tool-results').show();
            
            // Scroll to results
            $('html, body').animate({
                scrollTop: $('#tool-results').offset().top - 100
            }, 500);
        },
        
        /**
         * Reset the form
         */
        reset: function() {
            DGLab.FileUpload.clearFile();
            $('#tool-results').hide();
            $('#tool-options-form')[0].reset();
            
            $('html, body').animate({
                scrollTop: $('.tool-interface').offset().top - 100
            }, 500);
        }
    };

    // =========================================================================
    // Conditional Form Fields
    // =========================================================================

    DGLab.ConditionalFields = {
        /**
         * Initialize conditional field handlers
         */
        init: function() {
            $('[data-conditional]').each((_, el) => {
                const $group = $(el);
                const conditional = $group.data('conditional');
                
                if (conditional) {
                    Object.keys(conditional).forEach((fieldName) => {
                        const expectedValue = conditional[fieldName];
                        const $field = $(`[name="${fieldName}"]`);
                        
                        $field.on('change', () => {
                            this.checkCondition($group, $field, expectedValue);
                        });
                        
                        // Initial check
                        this.checkCondition($group, $field, expectedValue);
                    });
                }
            });
        },
        
        /**
         * Check and apply conditional visibility
         * @param {jQuery} $group - Form group element
         * @param {jQuery} $field - Field to check
         * @param {*} expectedValue - Expected value
         */
        checkCondition: function($group, $field, expectedValue) {
            const actualValue = $field.val();
            const isMatch = Array.isArray(expectedValue) 
                ? expectedValue.includes(actualValue)
                : actualValue === expectedValue;
            
            $group.toggle(isMatch);
        }
    };

    // =========================================================================
    // Mobile Navigation
    // =========================================================================

    DGLab.MobileNav = {
        /**
         * Initialize mobile navigation
         */
        init: function() {
            const $toggle = $('.menu-toggle');
            const $nav = $('.nav');
            
            $toggle.on('click', () => {
                const isExpanded = $toggle.attr('aria-expanded') === 'true';
                $toggle.attr('aria-expanded', !isExpanded);
                $nav.toggleClass('is-open');
            });
        }
    };

    // =========================================================================
    // Document Ready
    // =========================================================================

    $(document).ready(function() {
        // Initialize components
        DGLab.FileUpload.init();
        DGLab.ToolProcessor.init();
        DGLab.ConditionalFields.init();
        DGLab.MobileNav.init();
        
        // Add CSRF token to all AJAX requests
        $.ajaxSetup({
            headers: {
                'X-CSRF-Token': DGLab.config.csrfToken
            }
        });
        
        console.log('DGLab PWA v' + DGLab.version + ' initialized');
    });

})(jQuery);
