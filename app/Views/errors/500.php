<?php
/**
 * 500 Server Error Page
 * 
 * @package DGLab\Views\Errors
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f9fafb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1f2937;
        }
        
        .error-container {
            text-align: center;
            padding: 2rem;
            max-width: 500px;
        }
        
        .error-code {
            font-size: 8rem;
            font-weight: 700;
            color: #ef4444;
            line-height: 1;
            margin-bottom: 1rem;
        }
        
        .error-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .error-message {
            font-size: 1.125rem;
            color: #6b7280;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .error-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        
        .btn-outline {
            background: #fff;
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }
        
        .btn-outline:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">500</div>
        <h1 class="error-title">Server Error</h1>
        <p class="error-message">
            Sorry, something went wrong on our end. 
            Please try again later or contact support if the problem persists.
        </p>
        <div class="error-actions">
            <a href="/" class="btn btn-primary">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                Go Home
            </a>
            <button onclick="window.location.reload()" class="btn btn-outline">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                </svg>
                Try Again
            </button>
        </div>
    </div>
</body>
</html>
