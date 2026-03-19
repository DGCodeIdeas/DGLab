<?php

namespace DGLab\Core;

use DGLab\Services\Auth\AuthManager;

/**
 * Base Controller
 *
 * Provides common functionality for all application controllers.
 */
abstract class BaseController extends Controller
{
    /**
     * Get the authenticated user
     */
    protected function user()
    {
        return auth()->user();
    }

    /**
     * Check if the user is authenticated
     */
    protected function check(): bool
    {
        return auth()->check();
    }

    /**
     * Authorize an action
     */
    protected function authorize(string $ability, array $arguments = []): void
    {
        if (!auth()->can($ability, $arguments)) {
            throw new \Exception("Unauthorized", 403);
        }
    }
}
