<?php

namespace DGLab\Services;

use DGLab\Core\Application;
use DGLab\Services\Auth\AuthManager;
use DGLab\Services\Tenancy\TenancyService;
use DGLab\Services\Download\DownloadManager;
use DGLab\Services\MangaScript\MangaScriptService;
use DGLab\Services\MangaScript\AI\RoutingEngine;

/**
 * Service Registry
 *
 * Central place to register all application services.
 */
class ServiceRegistry
{
    public static function register(Application $app): void
    {
        $app->set(AuthManager::class, function ($app) {
            return new AuthManager($app);
        });

        $app->set(TenancyService::class, function ($app) {
            return new TenancyService($app->get(\DGLab\Database\Connection::class));
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
