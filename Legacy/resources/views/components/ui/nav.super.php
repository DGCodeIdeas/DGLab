~setup {
    $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
}
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="/" @prefetch>
            <span class="bg-white text-primary rounded-circle d-inline-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                <i class="bi bi-flask"></i>
            </span>
            <span class="fw-bold">DGLab</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link {{ $currentUri === '/' ? 'active' : '' }}" href="/" @prefetch>
                        <i class="bi bi-house me-1"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ strpos($currentUri, '/services') === 0 ? 'active' : '' }}" href="/services" @prefetch>
                        <i class="bi bi-tools me-1"></i> Services
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-three-dots me-1"></i> More
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li>
                            <a class="dropdown-item" href="/docs" @prefetch>
                                <i class="bi bi-book me-2"></i> Documentation
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="/api" @prefetch>
                                <i class="bi bi-code-slash me-2"></i> API
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="https://github.com/dglab/pwa" target="_blank" rel="noopener">
                                <i class="bi bi-github me-2"></i> GitHub
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>

            <button id="install-pwa" class="btn btn-outline-light btn-sm ms-lg-3 d-none">
                <i class="bi bi-download me-1"></i> Install
            </button>
        </div>
    </div>
</nav>
