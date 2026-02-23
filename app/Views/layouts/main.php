<!DOCTYPE html>
<html lang="<?php echo $locale ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    
    <!-- Title -->
    <title><?php echo htmlspecialchars($title ?? APP_NAME); ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Meta Description -->
    <meta name="description" content="<?php echo htmlspecialchars($description ?? 'DGLab PWA - A collection of web tools for file processing and conversion'); ?>">
    
    <!-- Theme Color -->
    <meta name="theme-color" content="#4f46e5">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="<?php echo $base_url; ?>/manifest.json">
    
    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" href="<?php echo $base_url; ?>/assets/icons/icon-192x192.png">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo $base_url; ?>/assets/icons/icon-72x72.png">
    
    <!-- Preconnect to external domains -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    
    <!-- Application CSS -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/app.css?v=<?php echo APP_VERSION; ?>">
    
    <!-- Page-specific CSS -->
    <?php if (isset($page_css)): ?>
        <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/<?php echo $page_css; ?>.css?v=<?php echo APP_VERSION; ?>">
    <?php endif; ?>
</head>
<body class="<?php echo $body_class ?? ''; ?>">
    <!-- Skip to content link for accessibility -->
    <a href="#main-content" class="skip-link">Skip to main content</a>
    
    <!-- Header -->
    <?php include VIEWS_PATH . '/partials/header.php'; ?>
    
    <!-- Main Content -->
    <main id="main-content" class="main-content">
        <?php echo $content; ?>
    </main>
    
    <!-- Footer -->
    <?php include VIEWS_PATH . '/partials/footer.php'; ?>
    
    <!-- Toast Notifications Container -->
    <div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div>
    
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p class="loading-text">Processing...</p>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
    
    <!-- Application JavaScript -->
    <script src="<?php echo $base_url; ?>/assets/js/app.js?v=<?php echo APP_VERSION; ?>"></script>
    
    <!-- Page-specific JavaScript -->
    <?php if (isset($page_js)): ?>
        <script src="<?php echo $base_url; ?>/assets/js/<?php echo $page_js; ?>.js?v=<?php echo APP_VERSION; ?>"></script>
    <?php endif; ?>
    
    <!-- Register Service Worker -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?php echo $base_url; ?>/sw.js')
                    .then(function(registration) {
                        console.log('SW registered:', registration.scope);
                    })
                    .catch(function(error) {
                        console.log('SW registration failed:', error);
                    });
            });
        }
    </script>
</body>
</html>
