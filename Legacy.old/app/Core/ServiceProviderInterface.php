<?php

namespace DGLab\Core;

/**
 * Service Provider Interface
 *
 * Service providers encapsulate the registration and bootstrapping logic
 * for related services. They are the primary mechanism for extending
 * the application with new functionality.
 */
interface ServiceProviderInterface
{
    /**
     * Register services in the container
     *
     * This method is called immediately when the provider is registered.
     * Use it to bind services to the container.
     *
     * @param Application $app The application container
     * @return void
     */
    public function register(Application $app): void;

    /**
     * Boot the service provider
     *
     * This method is called after all providers have been registered.
     * Use it for initialization that depends on other services.
     *
     * @param Application $app The application container
     * @return void
     */
    public function boot(Application $app): void;
}
