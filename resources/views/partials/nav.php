<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <!-- Logo with offset effect -->
        <a class="navbar-brand d-flex align-items-center" href="/">
            <span class="bg-white text-primary rounded-circle d-inline-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                <i class="fas fa-flask"></i>
            </span>
            <span class="fw-bold">DGLab</span>
        </a>
        
        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Navigation Links -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?= ($_SERVER['REQUEST_URI'] === '/' ? 'active' : '') ?>" href="/">
                        <i class="fas fa-home me-1"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/services') === 0 ? 'active' : '') ?>" href="/services">
                        <i class="fas fa-tools me-1"></i> Services
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-h me-1"></i> More
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li>
                                                    <li>
                            <a class="dropdown-item text-primary fw-bold" href="/admin">
                                <i class="fas fa-shield-alt me-2"></i> Admin Panel
                            </a>
                        </li>
                        <li><a class="dropdown-item" href="/docs">
                                <i class="fas fa-book me-2"></i> Documentation
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="/api">
                                <i class="fas fa-code me-2"></i> API
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="https://github.com/dglab/pwa" target="_blank" rel="noopener">
                                <i class="fab fa-github me-2"></i> GitHub
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
            
            <!-- Install PWA Button (hidden by default, shown via JS) -->
            <button id="install-pwa" class="btn btn-outline-light btn-sm ms-lg-3 d-none">
                <i class="fas fa-download me-1"></i> Install
            </button>
        </div>
    </div>
</nav>
