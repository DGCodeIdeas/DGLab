<footer class="bg-dark text-light py-5 mt-auto">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="mb-3">
                    <span class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                        <i class="bi bi-flask text-white"></i>
                    </span>
                    <span class="h5 mb-0">DGLab</span>
                </div>
                <p class="text-muted mb-3">
                    A collection of web-based utilities for file processing and digital content manipulation. Built with modern web technologies.
                </p>
                <div class="social-links">
                    <a href="https://github.com/dglab/pwa" class="text-muted me-3" target="_blank" rel="noopener" aria-label="GitHub">
                        <i class="bi bi-github fs-4"></i>
                    </a>
                    <a href="#" class="text-muted me-3" aria-label="Twitter">
                        <i class="bi bi-twitter fs-4"></i>
                    </a>
                    <a href="#" class="text-muted" aria-label="Discord">
                        <i class="bi bi-discord fs-4"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-2 col-md-6 offset-lg-1">
                <h6 class="text-uppercase fw-bold mb-3">Services</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <a href="/services/epub-font-changer" class="text-muted text-decoration-none hover-white" @prefetch>EPUB Font Changer</a>
                    </li>
                    <li class="mb-2">
                        <a href="/services" class="text-muted text-decoration-none hover-white" @prefetch>All Services</a>
                    </li>
                    <li class="mb-2">
                        <a href="/api" class="text-muted text-decoration-none hover-white" @prefetch>API Access</a>
                    </li>
                </ul>
            </div>

            <div class="col-lg-2 col-md-6">
                <h6 class="text-uppercase fw-bold mb-3">Resources</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <a href="/docs" class="text-muted text-decoration-none hover-white" @prefetch>Documentation</a>
                    </li>
                    <li class="mb-2">
                        <a href="/docs/api" class="text-muted text-decoration-none hover-white" @prefetch>API Reference</a>
                    </li>
                    <li class="mb-2">
                        <a href="/docs/adding-services" class="text-muted text-decoration-none hover-white" @prefetch>Add a Service</a>
                    </li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h6 class="text-uppercase fw-bold mb-3">Legal</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <a href="/privacy" class="text-muted text-decoration-none hover-white" @prefetch>Privacy Policy</a>
                    </li>
                    <li class="mb-2">
                        <a href="/terms" class="text-muted text-decoration-none hover-white" @prefetch>Terms of Service</a>
                    </li>
                    <li class="mb-2">
                        <a href="/licenses" class="text-muted text-decoration-none hover-white" @prefetch>Licenses</a>
                    </li>
                </ul>
            </div>
        </div>

        <hr class="my-4 border-secondary">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="mb-0 text-muted">
                    <small>&copy; {{ date('Y') }} DGLab. All rights reserved.</small>
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="mb-0 text-muted">
                    <small>Made with <i class="bi bi-heart-fill text-danger"></i> for the community</small>
                </p>
            </div>
        </div>
    </div>
</footer>

<style>
.hover-white:hover {
    color: white !important;
}
</style>
