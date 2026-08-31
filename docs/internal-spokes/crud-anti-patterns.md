# CRUD Anti-Patterns

> **Navigation:** [CRUD Specialization](crud-specialization.md) | [Role Delegation Patterns](role-delegation-patterns.md)
>
> **Status:** 🟢 Active

---

## Purpose

This document catalogues common failures that occur when domain logic is forced into a generic CRUD pattern. Each anti-pattern includes the symptom, root cause, a concrete example from a real-world scenario, the correction, and prevention strategies.

**Goal:** Eliminate domain-logic-in-CRUD instances by recognizing these patterns during design and code review.

---

## Anti-Pattern Index

| # | Anti-Pattern | Severity | Domain |
|---|-------------|----------|--------|
| 1 | The God Entity | 🔴 Critical | Data modelling |
| 2 | Transaction Trance | 🔴 Critical | Concurrency |
| 3 | Validator Abandonment | 🟠 High | Business rules |
| 4 | Query Explosion | 🟠 High | Performance |
| 5 | Permission Leak | 🔴 Critical | Security |
| 6 | Event Overload | 🟡 Medium | Integration |
| 7 | Migration Hell | 🟠 High | Schema evolution |
| 8 | Soft-Delete Abuse | 🟡 Medium | Data lifecycle |

---

## 1. The God Entity

### Symptom
A single generic entity (e.g. `Entity`, `Content`, `Item`) is used to represent dozens of conceptually distinct domain objects. Business logic branches on a `type` column with `switch` statements.

### Root Cause
The CRUD engine's generic interface makes it easy to store everything in one table. Domain distinctions are lost in favour of implementation convenience.

### Example

```php
<?php
// ❌ ANTI-PATTERN: God Entity
class CrudEngine
{
    public function create(string $entityType, array $data): Entity
    {
        // ALL entities stored in a single 'entities' table
        $id = $this->db->insert('entities', [
            'type' => $entityType,          // 'user', 'invoice', 'team', 'comment'
            'data' => json_encode($data),    // everything in a JSON blob
            'created_at' => now(),
        ]);
        return new Entity($id, $entityType, $data);
    }

    public function validate(string $entityType, array $data): void
    {
        // Growing switch statement — OCP violation
        switch ($entityType) {
            case 'user':
                // validate user fields
                break;
            case 'invoice':
                // validate invoice fields
                break;
            // ... every new type adds a case
        }
    }
}
```

### Correction

```php
<?php
// ✅ CORRECTION: Bounded contexts with dedicated entities
class StaffMemberEntity
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly Role $role,
        public readonly TeamId $teamId,
    ) {}
}

// CRUD engine delegates to domain entity, not generic storage
$staffMember = new StaffMemberEntity(
    id: Uuid::generate(),
    name: $data['name'],
    email: $data['email'],
    role: Role::from($data['role']),
    teamId: new TeamId($data['team_id']),
);
$crudEngine->create('staff_members', $staffMember->toArray());
```

### Prevention
- Each domain concept gets its own database table and entity class.
- CRUD generic type parameter is a **strict interface**, not a string.
- New entity types require a new table migration, not a new `case` statement.

---

## 2. Transaction Trance

### Symptom
All CRUD operations are wrapped in database transactions, including read-only queries and cross-boundary operations that should use sagas.

### Root Cause
Developers assume that transactions are the only consistency mechanism, leading to long-held locks, deadlocks, and scaling bottlenecks.

### Example

```php
<?php
// ❌ ANTI-PATTERN: Everything in a transaction
class GenericCrudController
{
    public function create(Request $request): Response
    {
        $this->db->transaction(function () use ($request) {
            // 1. Persist entity
            $entity = $this->crud->create($request->input('type'), $request->input('data'));

            // 2. Send email notification (inside transaction!)
            $this->mailer->send($entity->getEmail(), 'Welcome');

            // 3. Log to external audit system (inside transaction!)
            $this->auditClient->log('created', $entity);

            // Transaction holds locks during external I/O — terrible for throughput
        });
        return response()->json($entity);
    }
}
```

### Correction

```php
<?php
// ✅ CORRECTION: Transaction only for data consistency
class StaffOnboardingController
{
    public function create(Request $request): Response
    {
        // Transaction boundary: only database writes
        $staffId = $this->db->transaction(function () use ($request) {
            return $this->staffRepository->create(
                name: $request->input('name'),
                email: $request->input('email'),
                role: $request->input('role'),
            );
        });

        // After transaction: side effects with compensation
        try {
            $this->mailer->send($request->input('email'), 'Welcome');
            $this->auditClient->log('staff.created', ['id' => $staffId]);
        } catch (MailException $e) {
            // Non-critical failure — log and continue
            $this->logger->warning("Welcome email failed for staff {$staffId}", [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['id' => $staffId]);
    }
}
```

### Prevention
- Transactions scope = database writes only.
- External side effects happen **after** transaction commits.
- Use **outbox pattern** for critical side effects (events that must be delivered).

---

## 3. Validator Abandonment

### Symptom
Business rules are implemented inside CRUD hooks or controllers rather than in a dedicated domain validation layer. Validation logic becomes untestable and coupled to the CRUD framework.

### Root Cause
The CRUD hook system is convenient and familiar, so developers place all validation there instead of in the domain layer.

### Example

```php
<?php
// ❌ ANTI-PATTERN: Business rules in CRUD hooks
$crudEngine->registerHook('beforeUpdate', 'staff_members', function (array $data, Context $ctx): array {
    // ❌ Business logic leaking into CRUD infrastructure
    $staff = $this->staffRepo->find($data['id']);
    if ($data['role'] === 'admin' && $staff->getRole() !== 'manager') {
        // Only managers can be promoted to admin
        if ($ctx->getUser()->getRole() !== 'super_admin') {
            throw new AuthorizationException('Only super admins can promote to admin');
        }
    }

    // ❌ Cross-entity logic in hook
    $activeTasks = $this->taskRepo->countActiveByStaff($data['id']);
    if ($data['status'] === 'inactive' && $activeTasks > 0) {
        throw new ValidationException("Cannot deactivate staff with {$activeTasks} active tasks");
    }

    return $data;
});
```

### Correction

```php
<?php
// ✅ CORRECTION: Domain validation in dedicated validators
class StaffRoleTransitionValidator implements DomainValidatorInterface
{
    public function validate(string $operation, string $entityType, array $data, Context $ctx): void
    {
        if ($operation !== 'update') return;

        $staff = $this->staffRepo->find($data['id']);

        // Rule: Only managers can be promoted to admin
        if (isset($data['role']) && $data['role'] === 'admin') {
            if ($staff->getRole() !== 'manager') {
                throw new ValidationException('Only managers can be promoted to admin');
            }
        }
    }
}

class StaffDeactivationValidator implements DomainValidatorInterface
{
    public function validate(string $operation, string $entityType, array $data, Context $ctx): void
    {
        if (!isset($data['status']) || $data['status'] !== 'inactive') return;

        $activeTasks = $this->taskRepo->countActiveByStaff($data['id']);
        if ($activeTasks > 0) {
            throw new ValidationException(
                "Cannot deactivate staff with {$activeTasks} active assignments"
            );
        }
    }
}

// Clean registration in ServiceProvider
$validatorRegistry->register('staff_members', [
    new StaffRoleTransitionValidator(),
    new StaffDeactivationValidator(),
]);
```

### Prevention
- All business rules go in `DomainValidatorInterface` implementations.
- CRUD hooks handle only **infrastructure concerns**: logging, caching, default values.
- Validators are unit-testable independently of the CRUD engine.

---

## 4. Query Explosion

### Symptom
Generic list endpoints return too many results, triggering N+1 queries, loading unused columns, or failing to paginate. Consumers are forced to filter and transform in application code.

### Root Cause
A single `list(string $type, array $filters)` method attempts to serve all read use cases. Specialized query needs get added as after-filters in application code.

### Example

```php
<?php
// ❌ ANTI-PATTERN: One list endpoint for everything
class GenericListController
{
    public function index(Request $request): Response
    {
        // Single endpoint returning ALL columns for ALL staff
        $staff = $this->crud->list('staff_members', $request->all());

        // ❌ Application code compensating for missing query capabilities
        $activeStaff = array_filter($staff, fn($s) => $s['status'] === 'active');
        $activeStaff = array_map(function ($s) {
            // ❌ N+1: loading tasks for each staff member
            $s['active_tasks'] = $this->taskRepo->countActiveByStaff($s['id']);
            return $s;
        }, $activeStaff);

        return response()->json(array_values($activeStaff));
    }
}
```

### Correction

```php
<?php
// ✅ CORRECTION: Specialized read model with optimized query
class ActiveStaffWithTaskCountQuery
{
    public function execute(ActiveStaffQuery $query): array
    {
        // Single query with JOIN — no N+1
        return $this->db->table('staff_members as s')
            ->select([
                's.id', 's.name', 's.email', 's.role',
                $this->db->raw('COUNT(t.id) as active_tasks'),
            ])
            ->leftJoin('workflow_tasks as t', function ($join) {
                $join->on('t.assignee_id', '=', 's.id')
                     ->whereIn('t.status', ['pending', 'in_progress']);
            })
            ->where('s.status', 'active')
            ->groupBy('s.id')
            ->orderBy('s.name')
            ->paginate($query->perPage)
            ->toArray();
    }
}
```

### Prevention
- Create **dedicated query objects** for each read use case.
- Generic `list()` is for **internal admin-only** use, never for public/cross-service APIs.
- Implement pagination limits at the CRUD engine level (max 100 per page, default 25).

---

## 5. Permission Leak

### Symptom
Generic CRUD operations bypass or ignore fine-grained authorization. A user can read, update, or delete entities they should not have access to, because the CRUD engine does not evaluate intent.

### Root Cause
The CRUD operates at the entity level, but authorization operates at the field and action level. The generic interface does not carry permission context.

### Example

```php
<?php
// ❌ ANTI-PATTERN: CRUD without authorization context
class GenericCrudController
{
    public function update(string $type, string $id, Request $request): Response
    {
        // No authorization check — any authenticated user can update anything
        $entity = $this->crud->update($type, $id, $request->all());
        return response()->json($entity);
    }
}
```

### Correction

```php
<?php
// ✅ CORRECTION: Intent-based authorization
class StaffMemberController
{
    public function updateRole(string $id, UpdateRoleRequest $request): Response
    {
        $staffMember = $this->staffRepo->find($id);

        // Intent: this is a role change, not a generic update
        $this->authorizer->authorize('staff.update_role', [
            'target' => $staffMember,
            'new_role' => $request->input('role'),
            'actor' => $request->user(),
        ]);

        // Now perform the specific operation
        $this->staffRepo->changeRole($id, $request->input('role'));

        return response()->json($staffMember->refresh());
    }
}
```

### Prevention
- CRUD controller routes are **never** exposed directly. Wrap each operation in an intent-expressing controller method.
- Use `#[Route]` attributes with middleware that perform authorization _before_ routing to CRUD.
- RBAC (HUB-05) checks happen at the spoke boundary, not inside the CRUD engine.

---

## 6. Event Overload

### Symptom
Every CRUD operation publishes a domain event, flooding the event system with thousands of low-level "entity changed" events. Subscribers struggle to filter meaningful events from noise.

### Root Cause
The CRUD engine's `afterCreate`/`afterUpdate`/`afterDelete` hooks publish events automatically for **every operation** rather than for **domain-relevant state changes**.

### Example

```php
<?php
// ❌ ANTI-PATTERN: Event on every CRUD operation
$crudEngine->registerHook('afterUpdate', '*', function (Entity $old, Entity $new, Context $ctx): void {
    // ❌ Fires for EVERY update — field changes, metadata updates, counter increments
    $this->events->dispatch(new EntityChangedEvent(
        entityType: $new->getType(),
        entityId: $new->getId(),
        oldState: $old,
        newState: $new,
    ));
});
```

### Correction

```php
<?php
// ✅ CORRECTION: Only publish domain-relevant events
$crudEngine->registerHook('afterUpdate', 'staff_members', function (Entity $old, Entity $new, Context $ctx): void {
    // Only publish if a domain-relevant field changed
    $relevantFields = ['role', 'team_id', 'status', 'manager_id'];

    $changedFields = array_keys(array_diff_assoc(
        array_intersect_key($new->toArray(), array_flip($relevantFields)),
        array_intersect_key($old->toArray(), array_flip($relevantFields))
    ));

    if (!empty($changedFields)) {
        $this->events->dispatch(new StaffMemberUpdatedEvent(
            staffId: $new->getId(),
            changedFields: $changedFields,
            previousValues: array_intersect_key($old->toArray(), array_flip($changedFields)),
        ));
    }
});
```

### Prevention
- Define **domain event triggers** in configuration (see [CRUD Specialization Event config](crud-specialization.md#domain-events-from-crud-lifecycle)).
- Infrastructure changes (last login, view count, cache timestamp) do not publish events.
- Event consumers declare which events they subscribe to — if no subscriber, the event should not be published.

---

## 7. Migration Hell

### Symptom
Schema changes in a generic CRUD system cascade unexpectedly. Adding a field to one entity type breaks queries for all other types sharing the same table.

### Root Cause
A shared table structure (e.g. a single `entities` table with JSON data) means any schema change affects all consumers, regardless of domain boundaries.

### Example

```sql
-- ❌ ANTI-PATTERN: Single entities table for all types
CREATE TABLE entities (
    id          VARCHAR(64) PRIMARY KEY,
    type        VARCHAR(32) NOT NULL,      -- 'user', 'invoice', 'team', etc.
    data        JSON NOT NULL,             -- all fields in JSON
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    INDEX idx_type (type)
);

-- Adding an indexed column for 'user' requires migrating ALL rows
ALTER TABLE entities ADD COLUMN email VARCHAR(255) NULL;
-- Now 'invoice' rows have an unused 'email' column
```

### Correction

```sql
-- ✅ CORRECTION: Dedicated table per bounded context
CREATE TABLE staff_members (
    id          VARCHAR(64) PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255) NOT NULL UNIQUE,
    role        VARCHAR(32) NOT NULL,
    team_id     VARCHAR(64) NOT NULL,
    status      VARCHAR(16) NOT NULL DEFAULT 'active',
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    INDEX idx_team (team_id),
    INDEX idx_status (status)
);

CREATE TABLE invoices (
    id          VARCHAR(64) PRIMARY KEY,
    tenant_id   VARCHAR(64) NOT NULL,
    total       DECIMAL(10,2) NOT NULL,
    status      VARCHAR(16) NOT NULL DEFAULT 'draft',
    issued_at   DATETIME,
    paid_at     DATETIME,
    created_at  DATETIME NOT NULL,
    INDEX idx_tenant_status (tenant_id, status)
);
```

### Prevention
- Each domain entity gets its own table and migration.
- The CRUD engine's "generic" nature applies at the **behavioural** level (same Create/Read/Update/Delete pattern), not the **storage** level.
- Schema migrations are domain-scoped: changing `staff_members` does not affect `invoices`.

---

## 8. Soft-Delete Abuse

### Symptom
All entities use soft-delete (a `deleted_at` column), even for data that should be permanently removed. The database accumulates millions of soft-deleted rows, degrading query performance.

### Root Cause
Soft-delete is the default for the generic CRUD engine, applied uniformly without considering data retention requirements.

### Correction

```php
<?php
// ✅ CORRECTION: Intentional deletion strategy per entity type
class CrudEngine
{
    public function delete(string $entityType, string $id): void
    {
        $strategy = $this->deleteStrategies[$entityType] ?? HardDeleteStrategy::class;
        (new $strategy($this->db))->delete($entityType, $id);
    }
}

// Hard delete for ephemeral data (sessions, temp files)
class HardDeleteStrategy implements DeleteStrategyInterface
{
    public function delete(string $entityType, string $id): void
    {
        $this->db->table($entityType)->where('id', $id)->delete();
    }
}

// Soft delete for compliance data (staff records, invoices)
class SoftDeleteStrategy implements DeleteStrategyInterface
{
    public function delete(string $entityType, string $id): void
    {
        $this->db->table($entityType)->where('id', $id)->update([
            'deleted_at' => now(),
            'deleted_by' => auth()->id(),
        ]);
    }
}

// Archival delete for historical data (old audit logs)
class ArchiveDeleteStrategy implements DeleteStrategyInterface
{
    public function delete(string $entityType, string $id): void
    {
        // Move to cold storage before deleting from primary table
        $entity = $this->db->table($entityType)->where('id', $id)->first();
        $this->archive->store($entityType, $entity);
        $this->db->table($entityType)->where('id', $id)->delete();
    }
}
```

### Prevention
- Define deletion strategy in entity configuration.
- Soft-delete is **opt-in**, not default.
- Implement TTL-based hard delete for soft-deleted records exceeding retention period.

---

## Anti-Pattern Detection Checklist

Use this checklist during code review to catch CRUD anti-patterns early.

| Check | Question | Related Anti-Pattern |
|-------|----------|---------------------|
| [ ] | Does a single table store multiple domain concepts? | God Entity |
| [ ] | Are external I/O calls inside database transactions? | Transaction Trance |
| [ ] | Are business rules in CRUD hooks instead of validators? | Validator Abandonment |
| [ ] | Does the list endpoint lack specialized query support? | Query Explosion |
| [ ] | Are CRUD operations exposed without authorization? | Permission Leak |
| [ ] | Are events published for every entity change? | Event Overload |
| [ ] | Does changing one entity type risk breaking others? | Migration Hell |
| [ ] | Is soft-delete applied to data that should be hard-deleted? | Soft-Delete Abuse |

---

> **Document Version:** 1.0
> **Last Updated:** Current Session
> **Status:** 🟢 Active
> **Review Cycle:** Quarterly