<?php

namespace DGLab\Core\Middleware;

use DGLab\Core\MiddlewareInterface;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Core\Application;

class AdminAuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        // Simple authentication using a password in the query string or session
        // For production, this should be replaced with a robust auth system

        $adminPassword = $_ENV['ADMIN_PASSWORD'] ?? 'dglab_admin_2024';

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if already authenticated in session
        if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
            return $next($request);
        }

        // Check for password in request
        $providedPassword = $request->get('password') ?? $request->post('password');

        if ($providedPassword === $adminPassword) {
            $_SESSION['is_admin'] = true;
            return $next($request);
        }

        // Not authenticated, return 401
        return new Response('Unauthorized. Please provide valid admin credentials.', 401, [
            'Content-Type' => 'text/plain',
            'WWW-Authenticate' => 'Basic realm="Admin Access"'
        ]);
    }
}
