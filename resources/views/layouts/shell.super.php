~setup {
    $title = $title ?? 'DGLab - Digital Lab Tools';
    $head = $head ?? '';
    $scripts = $scripts ?? '';
    // Global state integration
    $user = global_state('user');
    $notifications = global_state('notifications', []);
}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title>{{ $title }}</title>
    <meta name="description" content="DGLab - A collection of web-based utilities for file processing and digital content manipulation.">

    <meta name="theme-color" content="#0d6efd">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/icon-32x32.png">
    <link rel="manifest" href="/manifest.json">

    <link href="/assets/css/superpowers.nav.css" rel="stylesheet">


    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8f9fa; }
    </style>

    {!! $head !!}
</head>
<body @transition="fade">
    <s:ui.nav />

    <main id="app" s-nav-root>
        @fragment('content')
            @yield('content')
        @endfragment
    </main>

    <s:ui.footer />

    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container"></div>

    <div id="loading-overlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-white bg-opacity-75 d-flex align-items-center justify-content-center" style="z-index: 9999;">
        <div class="text-center">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
            <p class="mt-3 mb-0 text-primary fw-semibold" id="loading-message">Processing...</p>
        </div>
    </div>

    <script src="/assets/js/superpowers.js"></script>
    <script src="/assets/js/superpowers.nav.js"></script>

    {!! $scripts !!}

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js');
            });
        }
    </script>
</body>
</html>
