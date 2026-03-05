<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- SEO Meta Tags -->
    <title><?= htmlspecialchars($title ?? 'DGLab - Digital Lab Tools') ?></title>
    <meta name="description" content="DGLab - A collection of web-based utilities for file processing and digital content manipulation.">
    <meta name="keywords" content="EPUB, fonts, file processing, web tools, PWA">
    <meta name="author" content="DGLab">
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#0d6efd">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="DGLab">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/icon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/icon-180x180.png">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    
    <!-- Preconnect to CDNs -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.2/css/all.min.css" rel="stylesheet" integrity="sha384-BY+fdrpOd3gfeRvTSMT+VUZmA728cfF9Z2G42xpaRkUGu2i3DyzpTURDo5A6CaLK" crossorigin="anonymous">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $this->asset('css/app.css') ?>">
    
    <!-- Critical CSS (inline) -->
    <style>
        /* Critical styles for above-the-fold content */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .hero-section {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 4rem 0;
        }
        .service-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
    </style>
    
    <!-- No-JS Fallback -->
    <noscript>
        <style>
            .js-only { display: none !important; }
        </style>
        <div class="alert alert-warning text-center m-0 rounded-0">
            <i class="fas fa-exclamation-triangle me-2"></i>
            JavaScript is required for full functionality. Please enable JavaScript in your browser.
        </div>
    </noscript>
</head>
<body>
    <!-- Navigation -->
    <?php $this->partial('nav') ?>
    
    <!-- Main Content -->
    <main id="app">
        <?php $this->yield('content') ?>
    </main>
    
    <!-- Footer -->
    <?php $this->partial('footer') ?>
    
    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container"></div>
    
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-white bg-opacity-75 d-flex align-items-center justify-content-center" style="z-index: 9999;">
        <div class="text-center">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 mb-0 text-primary fw-semibold" id="loading-message">Processing...</p>
        </div>
    </div>
    
    <!-- Scripts -->
    <!-- jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" integrity="sha384-1H217gwSVyLSIfaLxHbE9d3BKbwAOX8scrL4c5iiT6wIIAqgHvgJYcJdKhdj4w7b" crossorigin="anonymous"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    
    <!-- Application Scripts -->
    <script src="<?= $this->asset('js/app.js') ?>"></script>
    
    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('[PWA] Service Worker registered:', registration.scope);
                    })
                    .catch(function(error) {
                        console.log('[PWA] Service Worker registration failed:', error);
                    });
            });
        }
    </script>
</body>
</html>
