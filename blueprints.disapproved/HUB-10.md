# HUB-10.md

## Phase ID

`HUB-10`

## Tier

`Hub`

## Component Name and Description

**Search Service** – Provides full‑text and faceted search across tenant data using Elasticsearch. Exposes a PSR‑7 compatible query endpoint and abstracts index management.

## Context7 Research

- **PHP Best Practices**: Use DTOs for query parameters, avoid N+1 queries, handle pagination safely.
- **PSR‑7**: Request carries search criteria.
- **PSR‑11**: Service container registration of `SearchServiceInterface`.
- **PSR‑14**: Emits `SearchPerformedEvent` for analytics.
- **Design Patterns**: Repository for index access, Strategy for query building, Builder for complex query objects.
- **Performance**: Aim for < 5 ms query latency for typical filters.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Service\Search;

use Psr\Container\ContainerInterface; // PSR‑11
use Psr\EventDispatcher\EventDispatcherInterface; // PSR‑14
use Elasticsearch\ClientBuilder;

interface SearchServiceInterface
{
    public function query(SearchQuery $query): SearchResult;
}

final class SearchService implements SearchServiceInterface
{
    private \Elasticsearch\Client $client;
    private EventDispatcherInterface $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->client = ClientBuilder::create()->build();
        $this->dispatcher = $dispatcher;
    }

    public function query(SearchQuery $query): SearchResult
    {
        $params = $query->toArray();
        $response = $this->client->search($params);
        $result = SearchResult::fromElasticResponse($response);
        $this->dispatcher->dispatch(new SearchPerformedEvent($query, $result));
        return $result;
    }
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component SearchService {
        +query(SearchQuery): SearchResult
    }
    component SearchQuery <<interface>>
    component SearchResult <<interface>>
    component EventDispatcher <<interface>>
    SearchService --> EventDispatcher
    SearchService --> ElasticsearchClient
```

## Integration Strategy

Registered in the Core DI container (`CORE-02`). Controllers in the Core tier invoke `SearchServiceInterface` for `/search` endpoints. Index lifecycle (create/delete) is coordinated with Core migration scripts (`CORE-12`).

## CI Verification Criteria

- Unit test coverage ≥ 93% for query translation.
- Integration tests against a Dockerized Elasticsearch instance verify result accuracy.
- Latency: typical filtered query ≤ 5 ms, full‑text ≤ 10 ms.
- Throughput: ≥ 2 k queries/sec sustained.

## SemVer Impact

**Minor** – Introduces a new searchable API and events, requiring client adaptation.
