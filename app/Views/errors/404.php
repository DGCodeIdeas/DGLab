<?php
/**
 * 404 Not Found Error Page
 * 
 * @package DGLab\Views\Errors
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
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
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
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
        <div class="error-code">404</div>
        <h1 class="error-title">Page Not Found</h1>
        <p class="error-message">
            Sorry, we couldn't find the page you're looking for. 
            It might have been moved or deleted.
        </p>
        <div class="error-actions">
            <a href="/" class="btn btn-primary">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                Go Home
            </a>
            <a href="/tools" class="btn btn-outline">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                </svg>
                Browse Tools
            </a>
        </div>
    </div>
</body>
</html>
