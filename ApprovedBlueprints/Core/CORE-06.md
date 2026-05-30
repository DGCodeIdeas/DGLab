# PHASE CORE-06: Attribute-Based Router

## Tier
Core

## Component Name
High-Performance Sovereign Router

## Description
A routing engine that uses PHP 8.3 Attributes for route definition. It features a compiled route cache for sub-5ms resolution and supports RESTful patterns, named parameters, and nested groups.

## Context7 Research
- **PHP 8.3 Features**: Extensive use of `Attributes` on Class and Method levels.
- **Patterns**: Trie-based prefix matching for route resolution.
- **Reference**: Modern routing best practices for zero-allocation matching.

## Architectural Design
- **RouteAttribute**: `#[Route('/path', method: 'GET')]`.
- **RouteCollector**: Scans directories for classes with route attributes.
- **Dispatcher**: Matches the current PSR-7 Request to a specific Controller/Action.
- **UrlGenerator**: Reverse-engineers URLs from named routes.

## Integration Strategy
Depends on `CORE-04` (Request) and `CORE-02` (Container) for controller resolution. It is the final destination for the `CORE-05` (Middleware) chain.

## CI Verification Criteria
- **Resolution Speed**: < 2ms for a set of 500 registered routes.
- **Compilation**: Must be able to "dump" the route table to a native PHP file for production.

## SemVer Impact
**Major**. Defines the entry point and navigation structure of the application.