# PHASE HUB-24: GraphQL Schema Registry (Pure PHP)

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10).

## Component Name
Sovereign GraphQL Registry

## Description
Pure-PHP GraphQL schema registry/execution engine: Hub services and Spokes register schema fragments
(Types, Queries, Mutations) unified into a single API via `webonyx/graphql-php` — no Node/Apollo.

## Build Status
🔴 **Blocked** on `HUB-08` (Gateway), `HUB-04` (Identity), `HUB-05` (RBAC) — none implemented.

## Dependency Status
- **Direct Hub:** `HUB-08`, `HUB-04`, `HUB-05`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-02`, `CORE-06`, `CORE-04`.

## Architectural Design
- **SchemaRegistry** — collects schema fragments from registered providers.
- **UnifiedExecutor** — validates/executes queries against the stitched schema.
- **DirectiveEngine** — PHP-based `@auth`, `@cache`, `@tenant` directives — `@tenant` should resolve
  through `HUB-21`'s `TenancyInterface`, and `@auth` through `HUB-05`'s `GateInterface`, rather than
  each directive reimplementing authorization/tenancy logic independently.
- **BatchResolver** — Data Loader pattern, N+1 prevention.

```php
$registry->register('blog', [
    'type_defs' => 'type Post { id: ID!, title: String! }',
    'resolvers' => [
        'Query' => ['post' => fn($root, $args) => $db->find($args['id'])]
    ]
]);
```

```php
namespace SovereignStack\Hub\Contracts;

interface GraphQLInterface
{
    public function execute(string $query, array $variables = [], mixed $context = null): array;
    public function register(string $namespace, array $definition): void;
}
```

## Integration Strategy
- **Upward:** exposed via a single `/graphql` endpoint in `HUB-08`.
- **Downward:** Spoke applications provide `SchemaProvider` classes discovered at boot.
- **Contract:** resolvers return raw arrays/objects; the engine handles JSON conversion.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Namespace collision detection | Unit test: register two namespaces both defining the same root `Query` field; assert schema stitching fails loudly at registration time, not silently overwriting one. |
| Field-level RBAC enforcement | Integration test: query a field guarded by `@auth(ability: "admin.view")` as a non-admin fixture user; assert the field resolves to `null`/an authorization error per the GraphQL spec's partial-response semantics, not a full-request failure that breaks unrelated fields in the same query. |
| Data Loader N+1 prevention | Integration test with a query fetching N related records; assert the DBAL query count stays constant (not O(N)) as N grows — measured via a query-counting fixture, not asserted from "uses Data Loaders." |
| Complex-query latency | State environment before citing "< 20ms" — measure once `CORE-02`/`HUB-08` exist (Finding 10). |

## CI Verification Criteria
- Collision-detection test, blocking.
- Field-level RBAC test with correct partial-response behavior, blocking.
- Query-count (N+1) test, blocking.
- Latency measured and reported with environment stated.

## SemVer Impact
**Minor.** Enables modern, typed data fetching across the stack.
