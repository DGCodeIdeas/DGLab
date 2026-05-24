# Phase 3: Service Provider Refactoring (PSR-compliant)

## Objective
Standardize service registration by refactoring the `ServiceProvider` pattern to be more modular and decoupled, allowing for clean discovery and registration of services in the PSR-11 container.

## Technical Requirements
1.  **Interface Contract**: Define a clear `ServiceProviderInterface` with `register()` and `boot()` methods.
2.  **Modular Registration**: Move all service registrations from `Application::registerBaseServices()` into dedicated providers (e.g., `DatabaseServiceProvider`, `EventServiceProvider`).
3.  **Discovery**: Implement a mechanism in `Application` to load and register multiple providers from configuration.

## Implementation Steps
1.  Create/Update `app/Core/ServiceProviderInterface.php`.
2.  Create `app/Providers/CoreServiceProvider.php` to handle basic framework services.
3.  Update `config/app.php` to include a `providers` array.
4.  Modify `Application` to iterate over configured providers and call `register()`.

## Verification
-   Run `vendor/bin/phpunit` (ensure core services still resolve via new providers).
