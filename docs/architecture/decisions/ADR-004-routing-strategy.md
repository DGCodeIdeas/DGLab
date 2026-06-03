# ADR-004: Routing Strategy (Attribute-Based with Trie Matching)

## Status
Accepted

## Context
The Sovereign Stack requires a high-performance routing engine capable of handling complex web applications. Requirements from [CORE-06](/ApprovedBlueprints/Core/CORE-06.md):

- **PHP 8.3 Attribute-based route definitions** on class and method level for declarative route registration
- **Sub-2ms resolution** for 500 registered routes to avoid bottlenecking request throughput
- **Compiled route cache** dumping route table to native PHP for production Opcache optimization
- **RESTful support**: GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD
- **Named parameters** with regex constraints: `/users/{id:\d+}/posts/{slug}`
- **Nested groups** with shared prefixes and middleware: `/api/v1/users`

The routing engine is the final destination of the [CORE-05](/ApprovedBlueprints/Core/CORE-05.md) middleware chain and directly depends on [CORE-04](/ApprovedBlueprints/Core/CORE-04.md) (PSR-7) for request/response handling.

## Decision
Adopt a **Trie-based prefix matching router** with PHP 8.3 Attribute route definitions:

### Architecture
```mermaid
graph TD
    subgraph Definition[Route Definition]
        R1[#[Route'/api/users', method: 'GET']]
        R2[#[Route'/api/users/{id}', method: 'GET']]
        R3[#[Group'/admin', middleware: 'auth']]
    end

    subgraph Registration[RouteCollection]
        Scanner[RouteCollector: scan directories]
        Scanner --> Trie[TrieBuilder]
    end

    subgraph Resolution[Route Dispatch]
        Request[PSR-7 Request] --> Matcher[TrieMatcher]
        Trie --> Matcher
        Matcher --> Ctrl[Controller::action]
    end

    subgraph Production[Production Mode]
        Dump[RouteDumper] --> PHPArray[compiled.php array]
        PHPArray --> Opcache[Opcache]
    end
```

### Route Attribute
```php
#[Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class Route {
    public function __construct(
        public string $path,
        public string $method = 'GET',
        public ?string $name = null,
        public array $middleware = [],
    ) {}
}

// Group attribute for prefix and shared middleware
#[Attribute(\Attribute::TARGET_CLASS)]
class Group {
    public function __construct(
        public string $prefix,
        public array $middleware = [],
    ) {}
}
```

### Usage Example
```php
#[Group('/api/v1', middleware: ['auth:api'])]
class UserController {
    #[Route('/users', method: 'GET', name: 'users.index')]
    public function index(ServerRequestInterface $request): ResponseInterface {
        // ...
    }

    #[Route('/users/{id:\d+}', method: 'GET', name: 'users.show')]
    public function show(ServerRequestInterface $request, int $id): ResponseInterface {
        // ...
    }
}
```

### Trie Matching Algorithm
- Each route segment (between `/`) becomes a node in the trie
- Static segments are matched by string equality (O(1) hash lookup)
- Parameter segments `{name}` are matched by regex constraint if specified
- Wildcard segments `{name?}` support optional trailing segments
- The trie is built once and can be serialized to a PHP array for production

## Rationale
- **Performance**: Trie matching is O(k) where k is the URL path depth, independent of total route count. This guarantees <2ms even at 500+ routes
- **PHP 8.3 Leverage**: Attributes co-locate routes with their controller methods, eliminating separate route files and enabling IDE navigation
- **Compilation**: The route cache dump enables Opcache to store the entire route table in shared memory, achieving near-zero resolution overhead
- **Forward Compatibility**: The PSR-7 Request/Response contract ensures the router works with any PSR-7/PSR-15 compatible middleware stack

## Consequences
### Positive
- Route definitions are self-documenting (visible directly in controllers)
- No separate `routes/web.php` file needed (though one can be provided for edge cases)
- Compiled route cache survives deployment restarts without re-scanning

### Negative
- Controllers must be scanned on first request (or during `s-forge route:cache`) to discover routes
- Route attributes cannot be dynamically altered at runtime without re-scanning
- Optional parameters `{id?}` require special trie handling for segments that may or may not exist

## Alternatives Considered
1. **FastRoute (nikic/fastroute)** - Industry-standard PHP router with regex-based matching. Rejected because trie-based matching is simpler for the attribute compilation use case and avoids a runtime regex dependency.
2. **Separate Route Files** (Laravel-style) - Explicit route files are more familiar to PHP developers but duplicate the route definition and require keeping files in sync with controllers. Rejected for DRY violation.
3. **Annotation-based (PHPDoc comments)** - Works in older PHP versions but is slower to parse than native Attributes. Rejected because the stack targets PHP 8.3+ exclusively.

## Compliance Checklist
- [x] Decision documented in [CORE-06](/ApprovedBlueprints/Core/CORE-06.md)
- [x] Resolution speed: <2ms for 500 routes
- [x] Route cache compilation implemented
- [x] Named parameters with regex constraints supported

## Related ADRs
- [ADR-001](./ADR-001-di-container-design.md) - Controllers resolved through DI container
- [ADR-003](./ADR-003-validation-framework-strategy.md) - Route parameter validation before dispatch
