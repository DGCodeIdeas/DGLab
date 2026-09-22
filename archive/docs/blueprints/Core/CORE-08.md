# PHASE CORE-08: Global Error & Exception Handler

## Tier
Core

## Component Name
Sovereign Resilience Engine

## Description
A centralized error management system that converts all PHP errors (Warnings, Notices) into `ErrorExceptions` and provides a unified rendering strategy for both CLI and Web (JSON or HTML) outputs.

## Context7 Research
- **PHP 8.3 Error Handling**: Leveraging `Throwable` interface for catch-all handling.
- **Security**: Ensures sensitive environment data (e.g., DB passwords) is never leaked in production stack traces.

## Architectural Design
- **ExceptionHandler**: Registered via `set_exception_handler`.
- **ErrorHandler**: Registered via `set_error_handler`.
- **RendererInterface**: Strategies for different output formats (Console, SuperPHP-based error pages, JSON).
- **AuditBridge**: Automatically dispatches a `security.error` event to `CORE-03` (Event Dispatcher).

## Integration Strategy
Initialized in the `Kernel` immediately after the Container. It depends on `CORE-09` (Logger) to record faults.

## CI Verification Criteria
- **Intercept Rate**: 100% of uncaught exceptions must be captured and logged.
- **Production Mode**: Must verify that `display_errors` is forced to `0` and a generic "Server Error" is shown to users.

## SemVer Impact
**Minor**. Critical for DX and system stability.