~setup {
    $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
    $auth = \DGLab\Core\Application::getInstance()->get(\DGLab\Services\Auth\AuthManager::class);
    $user = $auth->user();
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
                @if($user)
                    <li class="nav-item">
                        <a class="nav-link" href="/logout">
                            <i class="bi bi-box-arrow-right me-1"></i> Logout ({{ $user->username }})
                        </a>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link {{ $currentUri === '/login' ? 'active' : '' }}" href="/login" @prefetch>
                            <i class="bi bi-person me-1"></i> Login
                        </a>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</nav>
