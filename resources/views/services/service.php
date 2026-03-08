
<!-- Page Header -->
<section class="bg-primary text-white py-4">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="/" class="text-white opacity-75">Home</a></li>
                <li class="breadcrumb-item"><a href="/services" class="text-white opacity-75">Services</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page"><?= htmlspecialchars($service->getName()) ?></li>
            </ol>
        </nav>
        <div class="d-flex align-items-center">
            <div class="bg-white bg-opacity-25 rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px;">
                <i class="fas <?= htmlspecialchars($service->getIcon()) ?> fa-2x"></i>
            </div>
            <div>
                <h1 class="h3 fw-bold mb-1"><?= htmlspecialchars($service->getName()) ?></h1>
                <p class="mb-0 opacity-90"><?= htmlspecialchars($service->getDescription()) ?></p>
            </div>
        </div>
    </div>
</section>

<!-- Service Interface -->
<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <!-- Main Form -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Upload Your File</h5>
                    </div>
                    <div class="card-body p-4">
                        <form id="service-form" action="/api/services/<?= htmlspecialchars($service->getId()) ?>/process" method="POST" enctype="multipart/form-data">
                            <?= $this->csrfField() ?>
                            
                            <!-- File Upload Zone -->
                            <div class="mb-4">
                                <label for="file" class="form-label fw-semibold">EPUB File</label>
                                <div class="upload-zone border border-2 border-dashed rounded-3 p-5 text-center" id="drop-zone">
                                    <i class="fas fa-cloud-upload-alt text-muted display-4 mb-3"></i>
                                    <p class="mb-2">Drag and drop your EPUB file here</p>
                                    <p class="text-muted small mb-3">or</p>
                                    <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('file').click()">
                                        <i class="fas fa-folder-open me-2"></i> Browse Files
                                    </button>
                                    <input type="file" class="d-none" id="file" name="file" accept=".epub,application/epub+zip" required>
                                    <p class="text-muted small mt-3 mb-0">
                                        <i class="fas fa-info-circle me-1"></i> Maximum file size: 100MB
                                    </p>
                                </div>
                                
                                <!-- File Info -->
                                <div id="file-info" class="d-none mt-3">
                                    <div class="d-flex align-items-center bg-light rounded-3 p-3">
                                        <i class="fas fa-file-alt text-primary fa-2x me-3"></i>
                                        <div class="flex-grow-1">
                                            <p class="mb-0 fw-semibold" id="file-name"></p>
                                            <p class="mb-0 text-muted small" id="file-size"></p>
                                        </div>
                                        <button type="button" class="btn btn-link text-danger" id="remove-file">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Font Selection -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Select Font</label>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="form-check card border h-100">
                                            <div class="card-body">
                                                <input class="form-check-input" type="radio" name="font" id="font-merriweather" value="merriweather" checked>
                                                <label class="form-check-label w-100" for="font-merriweather">
                                                    <strong class="d-block mb-1">Merriweather</strong>
                                                    <small class="text-muted">Elegant serif font for reading</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check card border h-100">
                                            <div class="card-body">
                                                <input class="form-check-input" type="radio" name="font" id="font-opendyslexic" value="opendyslexic">
                                                <label class="form-check-label w-100" for="font-opendyslexic">
                                                    <strong class="d-block mb-1">OpenDyslexic</strong>
                                                    <small class="text-muted">Designed for dyslexic readers</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check card border h-100">
                                            <div class="card-body">
                                                <input class="form-check-input" type="radio" name="font" id="font-fira-sans" value="fira-sans">
                                                <label class="form-check-label w-100" for="font-fira-sans">
                                                    <strong class="d-block mb-1">Fira Sans</strong>
                                                    <small class="text-muted">Modern sans-serif font</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Target Elements -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Apply To</label>
                                <div class="row g-2">
                                    <div class="col-6 col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="target_elements[]" id="elt-body" value="body" checked>
                                            <label class="form-check-label" for="elt-body">Body Text</label>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="target_elements[]" id="elt-h1" value="h1">
                                            <label class="form-check-label" for="elt-h1">Headings (H1-H6)</label>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="target_elements[]" id="elt-blockquote" value="blockquote">
                                            <label class="form-check-label" for="elt-blockquote">Blockquotes</label>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="target_elements[]" id="elt-code" value="code">
                                            <label class="form-check-label" for="elt-code">Code Blocks</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg" id="submit-btn" disabled>
                                    <i class="fas fa-magic me-2"></i> Change Font
                                </button>
                            </div>
                        </form>
                        
                        <!-- Progress (hidden initially) -->
                        <div id="progress-container" class="d-none mt-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-semibold" id="progress-status">Uploading...</span>
                                <span class="text-muted" id="progress-percent">0%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                        
                        <!-- Result (hidden initially) -->
                        <div id="result-container" class="d-none mt-4">
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Success!</strong> Your file has been processed.
                            </div>
                            <div class="d-grid">
                                <a href="#" class="btn btn-success btn-lg" id="download-link" download>
                                    <i class="fas fa-download me-2"></i> Download EPUB
                                </a>
                            </div>
                        </div>
                        
                        <!-- Error (hidden initially) -->
                        <div id="error-container" class="d-none mt-4">
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Error!</strong> <span id="error-message"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Info Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>About This Service</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-3">
                            This service allows you to change the fonts in your EPUB e-books. Perfect for improving readability or personalizing your reading experience.
                        </p>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i> Supports EPUB 2 and 3
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i> Chunked upload for large files
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i> Open source fonts (OFL licensed)
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-check text-success me-2"></i> Preserves original formatting
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Fonts Info Card -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-font me-2"></i>Available Fonts</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6 class="fw-bold mb-1">Merriweather</h6>
                            <p class="small text-muted mb-0">An elegant serif font designed for screen reading.</p>
                        </div>
                        <div class="mb-3">
                            <h6 class="fw-bold mb-1">OpenDyslexic</h6>
                            <p class="small text-muted mb-0">A font designed to increase readability for readers with dyslexia.</p>
                        </div>
                        <div class="mb-0">
                            <h6 class="fw-bold mb-1">Fira Sans</h6>
                            <p class="small text-muted mb-0">A modern sans-serif font with excellent legibility.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.upload-zone {
    transition: all 0.2s ease;
    cursor: pointer;
}
.upload-zone:hover,
.upload-zone.dragover {
    border-color: #0d6efd !important;
    background-color: rgba(13, 110, 253, 0.05);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file');
    const fileInfo = document.getElementById('file-info');
    const fileName = document.getElementById('file-name');
    const fileSize = document.getElementById('file-size');
    const removeFile = document.getElementById('remove-file');
    const submitBtn = document.getElementById('submit-btn');
    const form = document.getElementById('service-form');
    
    // Drag and drop
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });
    
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            updateFileInfo(files[0]);
        }
    });
    
    // File input change
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            updateFileInfo(fileInput.files[0]);
        }
    });
    
    // Remove file
    removeFile.addEventListener('click', () => {
        fileInput.value = '';
        fileInfo.classList.add('d-none');
        dropZone.classList.remove('d-none');
        submitBtn.disabled = true;
    });
    
    // Update file info display
    function updateFileInfo(file) {
        fileName.textContent = file.name;
        fileSize.textContent = formatBytes(file.size);
        fileInfo.classList.remove('d-none');
        dropZone.classList.add('d-none');
        submitBtn.disabled = false;
    }
    
    // Format bytes
    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Form submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const progressContainer = document.getElementById('progress-container');
        const progressBar = document.getElementById('progress-bar');
        const progressPercent = document.getElementById('progress-percent');
        const progressStatus = document.getElementById('progress-status');
        const resultContainer = document.getElementById('result-container');
        const errorContainer = document.getElementById('error-container');
        const downloadLink = document.getElementById('download-link');
        
        // Show progress
        progressContainer.classList.remove('d-none');
        resultContainer.classList.add('d-none');
        errorContainer.classList.add('d-none');
        submitBtn.disabled = true;
        
        const formData = new FormData(form);
        
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                progressBar.style.width = '100%';
                progressPercent.textContent = '100%';
                progressStatus.textContent = 'Complete!';
                
                setTimeout(() => {
                    progressContainer.classList.add('d-none');
                    resultContainer.classList.remove('d-none');
                    downloadLink.href = result.download_url;
                    downloadLink.download = result.filename;
                }, 500);
            } else {
                throw new Error(result.error || 'Processing failed');
            }
        } catch (error) {
            progressContainer.classList.add('d-none');
            errorContainer.classList.remove('d-none');
            document.getElementById('error-message').textContent = error.message;
            submitBtn.disabled = false;
        }
    });
});
</script>

