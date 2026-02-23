<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - <?php echo APP_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        
        .offline-container {
            text-align: center;
            padding: 2rem;
            max-width: 400px;
        }
        
        .offline-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }
        
        .offline-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .offline-message {
            font-size: 1.125rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .offline-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background: #fff;
            color: #4f46e5;
        }
        
        .btn-primary:hover {
            background: #f3f4f6;
        }
        
        .btn-outline {
            background: transparent;
            color: #fff;
            border: 2px solid rgba(255,255,255,0.3);
        }
        
        .btn-outline:hover {
            background: rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>
    <div class="offline-container">
        <div class="offline-icon">
            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 1l22 22M16.72 11.06A10.94 10.94 0 0 1 19 12.55M5 12.55a10.94 10.94 0 0 1 5.17-2.39M10.71 5.05A16 16 0 0 1 22.58 9M1.42 9a15.91 15.91 0 0 1 4.7-2.88M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/>
            </svg>
        </div>
        <h1 class="offline-title">You're Offline</h1>
        <p class="offline-message">
            It looks like you've lost your internet connection. 
            Please check your connection and try again.
        </p>
        <div class="offline-actions">
            <button class="btn btn-primary" onclick="window.location.reload()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                </svg>
                Try Again
            </button>
            <a href="/" class="btn btn-outline">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                Go Home
            </a>
        </div>
    </div>
</body>
</html>
