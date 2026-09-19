# PHASE HUB-24: GraphQL Schema Registry (Pure PHP)

## Tier
Hub (Shared Services)

## Component Name
Sovereign GraphQL Registry

## Description
A pure PHP implementation of a GraphQL schema registry and execution engine. It allows Hub services and Spoke applications to register their own schema fragments (Types, Queries, Mutations) which are then unified into a single, performant API.

## Sequencing Rationale
Depends on `HUB-08` (Gateway) for routing and `HUB-04` (Identity) for field-level authorization.

## Context7 Research
- **Direct Hub Dependencies**: `HUB-08: API Gateway`, `HUB-04: Identity`, `HUB-05: RBAC`.
- **Transitive Core Dependencies**: `CORE-02: DI Container`, `CORE-06: Router`, `CORE-04: HTTP Message`.
- **Engine**: Built using `webonyx/graphql-php`. Strictly no Node.js/Apollo.
- **Patterns**: Schema Stitching (PHP-side), Data Loader (for N+1 prevention).

## Architectural Design
- **SchemaRegistry**: Collects schema definitions from all registered Hub and Spoke providers.
- **UnifiedExecutor**: Validates and executes GraphQL queries against the stitched schema.
- **DirectiveEngine**: Implements PHP-based directives for `@auth`, `@cache`, and `@tenant`.
- **BatchResolver**: Implements the Data Loader pattern to optimize database queries.

### Schema Registration Example
```php
$registry->register('blog', [
    'type_defs' => 'type Post { id: ID!, title: String! }',
    'resolvers' => [
        'Query' => ['post' => fn($root, $args) => $db->find($args['id'])]
    ]
]);
```

## Interface Contracts

### GraphQLInterface
```php
namespace Sovereign\Hub\Contracts;

interface GraphQLInterface
{
    /**
     * Execute a GraphQL query string.
     */
    public function execute(string $query, array $variables = [], mixed $context = null): array;

    /**
     * Register a new schema fragment.
     */
    public function register(string $namespace, array $definition): void;
}
```

## Integration Strategy
- **Upward**: Exposed via a single `/graphql` endpoint in `HUB-08`.
- **Downward**: Spoke applications provide `SchemaProvider` classes that the Registry discovers during boot.
- **Contract**: Resolvers must return raw arrays or objects that the engine converts to JSON.

## CI Verification Criteria
- **Validation**: Schema stitching must fail if two namespaces attempt to define the same Root Query field.
- **Security**: Must enforce field-level RBAC (`HUB-05`) via directives.
- **Performance**: A complex multi-join query must execute in < 20ms (using Data Loaders).

## SemVer Impact
**Minor**. Enables modern, typed data fetching across the stack.
