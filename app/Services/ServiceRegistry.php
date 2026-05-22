<?php

namespace DGLab\Services;

use DGLab\Core\Application;
use DGLab\Services\Auth\AuthManager;
use DGLab\Services\Tenancy\TenancyService;
use DGLab\Services\Download\DownloadManager;
use DGLab\Services\MangaScript\MangaScriptService;
use DGLab\Services\MangaScript\AI\RoutingEngine;
use DGLab\Core\Request;

/**
 * Service Registry
 *
 * Central place to register all application services.
 */
class ServiceRegistry
{
    public static function register(Application $app): void
    {
        // Auth Services
        $app->singleton(\DGLab\Services\Auth\UUIDService::class, fn() => new \DGLab\Services\Auth\UUIDService());
        $app->singleton(\DGLab\Services\Auth\PasswordService::class, fn() => new \DGLab\Services\Auth\PasswordService());
        $app->singleton(\DGLab\Services\Auth\JWTService::class, fn() => new \DGLab\Services\Auth\JWTService());
        $app->singleton(\DGLab\Services\Auth\KeyManagementService::class, fn($app) => new \DGLab\Services\Auth\KeyManagementService($app->getBasePath() . '/storage/keys'));
        $app->singleton(\DGLab\Services\Auth\Repositories\UserRepository::class, fn($app) => new \DGLab\Services\Auth\Repositories\UserRepository($app->get(\DGLab\Services\Auth\UUIDService::class)));
        $app->singleton(\DGLab\Core\Cache::class, fn($app) => new \DGLab\Core\Cache($app->getBasePath() . '/storage/cache'));
        $app->singleton(\DGLab\Services\Auth\RateLimiter::class, fn($app) => new \DGLab\Services\Auth\RateLimiter($app->get(\DGLab\Core\Cache::class)));

        $app->set(AuthManager::class, function ($app) {
            return new AuthManager($app);
        });

        $app->set(TenancyService::class, function ($app) {
            return new TenancyService($app->get(Request::class));
        });

        $app->set(DownloadManager::class, function ($app) {
            return DownloadManager::getInstance();
        });

        $app->set(RoutingEngine::class, function ($app) {
            return new RoutingEngine($app);
        });

        $app->set(MangaScriptService::class, function ($app) {
            return new MangaScriptService(
                $app,
                $app->get(RoutingEngine::class),
                $app->get(\DGLab\Core\AuditService::class)
            );
        });
    }
}
