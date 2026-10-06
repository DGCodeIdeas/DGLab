# Implementation Guide: Routing Configuration

## Overview
This guide walks through configuring the High-Performance Sovereign Router ([CORE-06](/docs/blueprints/Core/CORE-06.md)). The router uses PHP 8.3 Attributes for declaring routes directly on controller methods, with Trie-based prefix matching for sub-2ms resolution.

**Reference**: [ADR-004 Routing Strategy](/docs/architecture/decisions/ADR-004-routing-strategy.md)

## Prerequisites
- DI Container configured ([DI Setup Guide](./di-container-setup.md))
- Basic controller structure in place

## Step 1: Define Routes with Attributes

Create `app/Http/Controllers/Api/UserController.php`:

```php
<?php
namespace App\Http\Controllers\Api;

use Sovereign\Core\Routing\Attribute\Route;
use Sovereign\Core\Routing\Attribute\Group;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Group('/api/v1', middleware: ['auth:api', 'throttle:60,1'])]
class UserController
{
    #[Route('/users', method: 'GET', name: 'users.index')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        // GET /api/v1/users
        return new JsonResponse(['data' => User::all()]);
    }

    #[Route('/users', method: 'POST', name: 'users.store')]
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        // POST /api/v1/users
        $data = $request->getParsedBody();
        // ...
    }

    #[Route('/users/{id:\d+}', method: 'GET', name: 'users.show')]
    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        // GET /api/v1/users/42
        $user = User::findOrFail($id);
        return new JsonResponse(['data' => $user]);
    }

    #[Route('/users/{id:\d+}', method: 'DELETE', name: 'users.destroy')]
    public function destroy(ServerRequestInterface $request, int $id): ResponseInterface
    {
        // DELETE /api/v1/users/42
        // ...
    }
}
```

## Step 2: Route Discovery

The router automatically scans configured directories for controllers with `#[Route]` attributes. Configure scan paths in `config/routing.php`:

```php
<?php
return [
    'scan_paths' => [
        'app/Http/Controllers/Api',
        'app/Http/Controllers/Web',
        'app/Http/Controllers/Admin',
    ],

    'cache_path' => storage_path('framework/routes/compiled.php'),

    'middleware_aliases' => [
        'auth' => App\Http\Middleware\Authenticate::class,
        'auth:api' => App\Http\Middleware\AuthenticateApi::class,
        'throttle' => App\Http\Middleware\ThrottleRequests::class,
        'csrf' => App\Http\Middleware\VerifyCsrfToken::class,
    ],
];
```

Trigger manual discovery:

```bash
php s-forge route:scan
```

Expected output:
```
Discovered 12 routes in 4 controllers
  GET    /api/v1/users          → UserController@index
  POST   /api/v1/users          → UserController@store
  GET    /api/v1/users/{id}     → UserController@show
  DELETE /api/v1/users/{id}     → UserController@destroy
  ...
```

## Step 3: Route Caching for Production

Compile routes to a flat PHP array for production:

```bash
php s-forge route:cache
```

Verification:
```bash
php s-forge route:list
```

Expected output:
```
+--------+----------------------+---------------------+------------+
| Method | Path                 | Handler             | Middleware |
+--------+----------------------+---------------------+------------+
| GET    | /api/v1/users        | UserController@index | auth,throttle |
| POST   | /api/v1/users        | UserController@store | auth,throttle |
| ...    | ...                  | ...                 | ...        |
+--------+----------------------+---------------------+------------+
```

Clear cache after route changes:
```bash
php s-forge route:clear
```

## Step 4: Generate URLs from Named Routes

Use the `UrlGenerator` to create URLs from route names:

```php
<?php
use Sovereign\Core\Routing\UrlGenerator;

class UserController
{
    public function __construct(
        private UrlGenerator $url
    ) {}

    public function show(int $id): ResponseInterface
    {
        // Generate: /api/v1/users/42
        $url = $this->url->to('users.show', ['id' => $id]);

        return new JsonResponse([
            'links' => [
                'self' => $url,
            ]
        ]);
    }
}
```

## Step 5: Group Middleware

Routes can be grouped with shared middleware at the class level (via `#[Group]`) or at the method level:

```php
<?php
class AdminController
{
    #[Route('/admin/dashboard', method: 'GET', middleware: ['auth', 'admin', 'csrf'])]
    public function dashboard(): ResponseInterface
    {
        // Route-specific middleware overrides group defaults
    }
}
```

Middleware execution order:
1. Global middleware (configured in Kernel)
2. Group middleware (`#[Group]` attribute)
3. Route middleware (`#[Route]` attribute)

## Step 6: Test Route Resolution

```bash
php s-forge route:match GET /api/v1/users/42
```

Expected output:
```
Matched route: users.show
Controller: App\Http\Controllers\Api\UserController@show
Parameters: ['id' => 42]
Middleware: ['auth:api', 'throttle:60,1']
```

## Advanced Patterns

### Route Prefixing with Versioning
```php
#[Group('/api/v2', middleware: ['auth:api'])]
class V2UserController
{
    #[Route('/users', method: 'GET')]
    public function index(): ResponseInterface { /* v2 implementation */ }
}
```

### Parameter Constraints
```php
#[Route('/posts/{slug}', method: 'GET')]                    // Any string
#[Route('/posts/{id:\d+}', method: 'GET')]                  // Only digits
#[Route('/posts/{uuid:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}}', method: 'GET')]  // UUIDv4
```

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| `404 Not Found` for valid route | Route cache stale | Run `route:clear` or `route:cache` after changes |
| `Route [name] not defined` | Typo in route name | Check `route:list` for exact names |
| Controller not discovered | Scan path missing | Add path to `config/routing.php` |
| `Middleware class not found` | Wrong alias in config | Check `middleware_aliases` map |
| Route matches wrong controller | Duplicate paths with same method | Add parameter constraints to differentiate |

## Verification Checklist
- [ ] Routes defined with `#[Route]` attribute on controller methods
- [ ] Route discovery finds all controllers
- [ ] Route cache compiles to <2ms resolution for 500 routes
- [ ] Named routes generate correct URLs
- [ ] Middleware executes in correct order
- [ ] Parameter constraints validate input

## Next Steps
Proceed to [Event System Wiring](./event-system-wiring.md) for decoupled communication.