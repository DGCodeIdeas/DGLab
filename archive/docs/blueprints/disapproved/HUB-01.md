# HUB-01.md

## Phase ID

`HUB-01`

## Tier

`Hub`

## Component Name and Description

**User Management Service** – Provides a cohesive API for creating, updating, retrieving, and deleting user entities across multi‑tenant installations. Handles validation, password hashing, and emits domain events for downstream listeners.

## Context7 Research

- **PHP Best Practices**: Use strict typing, immutable DTOs, and dependency injection. Follow [PSR‑12](https://www.php-fig.org/psr/psr-12/) coding style.
- **PSR‑7**: Request/response objects for HTTP‑driven user actions.
- **PSR‑11**: Service container for injecting the `UserRepositoryInterface`.
- **PSR‑14**: Event dispatcher for `UserCreated`, `UserUpdated`, `UserDeleted` events.
- **PSR‑15**: Middleware for authentication/authorization checks.
- **Design Patterns**: Repository, Factory, Domain Event, and Strategy for password hashing algorithms.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Service\User;

use Psr\Container\ContainerInterface; // PSR‑11
use Psr\EventDispatcher\EventDispatcherInterface; // PSR‑14
use App\Repository\UserRepositoryInterface;
use App\Dto\UserDto;

interface UserServiceInterface
{
    public function create(UserDto $dto): int; // returns user ID
    public function update(int $id, UserDto $dto): void;
    public function delete(int $id): void;
    public function find(int $id): ?UserDto;
}

final class UserService implements UserServiceInterface
{
    private UserRepositoryInterface $repo;
    private EventDispatcherInterface $dispatcher;
    private PasswordHasherInterface $hasher; // Strategy pattern

    public function __construct(
        UserRepositoryInterface $repo,
        EventDispatcherInterface $dispatcher,
        PasswordHasherInterface $hasher
    ) {
        $this->repo = $repo;
        $this->dispatcher = $dispatcher;
        $this->hasher = $hasher;
    }

    public function create(UserDto $dto): int
    {
        $dto->password = $this->hasher->hash($dto->password);
        $id = $this->repo->persist($dto);
        $this->dispatcher->dispatch(new UserCreatedEvent($id, $dto));
        return $id;
    }
    // ... other methods omitted for brevity
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component UserService {
        +create(UserDto): int
        +update(int, UserDto): void
        +delete(int): void
        +find(int): ?UserDto
    }
    component UserRepository <<interface>>
    component EventDispatcher <<interface>>
    component PasswordHasher <<interface>>
    UserService --> UserRepository
    UserService --> EventDispatcher
    UserService --> PasswordHasher
```

## Integration Strategy

The service is registered in the Core container (`CORE-03` – Router) via a PSR‑11 factory. Controllers in the Core tier retrieve the service through `$container->get(UserServiceInterface::class)`. Events emitted are consumed by Core‑level listeners defined in `CORE-07` (Event Dispatcher).

## CI Verification Criteria

- Unit test coverage ≥ 90% for `UserService` (PHPUnit).
- Integration tests verify event dispatching using a mock `EventDispatcherInterface`.
- Performance: `create` operation ≤ 5 ms for DB insert + hashing on typical hardware.
- Security: Password hashing uses Argon2id with a cost that yields ≤ 200 µs per hash.

## SemVer Impact

**Minor** – Introduces a new public API (`UserServiceInterface`) and emits new domain events, requiring downstream consumers to adapt.
