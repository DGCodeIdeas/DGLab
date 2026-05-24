# Phase 1: PSR-11 Container Interface & Implementation

## Objective
Modernize the `Application` core by implementing the `Psr\Container\ContainerInterface`.

## Technical Requirements
1.  **PSR-11 Compliance**: The `Application` class must implement `Psr\Container\ContainerInterface`.
2.  **Standard Exceptions**:
    -   Must throw `Psr\Container\NotFoundExceptionInterface` for missing entries.
    -   Must throw `Psr\Container\ContainerExceptionInterface` for general resolution errors.
3.  **Backward Compatibility**: Retain `get()`, `has()`, and `set()` methods while aligning them with the interface requirements.

## Implementation Steps
1.  Update `app/Core/Application.php` to implement `Psr\Container\ContainerInterface`.
2.  Define custom exception classes in `app/Core/Exceptions/`:
    -   `EntryNotFoundException` (implements `NotFoundExceptionInterface`)
    -   `ContainerException` (implements `ContainerExceptionInterface`)
3.  Refine `Application::get()` to throw `EntryNotFoundException` when a service is missing.
4.  Refine `Application::get()` to catch resolution errors and throw `ContainerException`.
5.  Ensure `has()` strictly returns bool.

## Verification
-   Run `vendor/bin/phpunit` (ensure no regressions in existing service resolution).
-   Static analysis: `vendor/bin/phpstan analyse app/Core/Application.php`.
