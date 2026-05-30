# Event Dispatcher Core Service Blueprint

## Project Vision
To implement a high-performance, developer-friendly, and meticulously observable Event Dispatcher as a core foundational service within the DGLab framework. This service will enable loose coupling between system components by allowing them to communicate via events, supporting both immediate (synchronous) and deferred (asynchronous) execution through a robust driver-based architecture.

## Architecture
The Event Dispatcher will be integrated as a top-level core service:
- **Core Engine**: `EventDispatcher` located in `app/Core/`, acting as the central registry and orchestrator.
- **Contract Layer**: Strict internal interfaces for `DispatcherInterface`, `EventInterface`, and `ListenerInterface`.
- **Driver System**: Pluggable execution strategies:
    - `SyncDriver`: Immediate execution within the current request cycle.
    - `QueueDriver`: Deferred execution via the framework's background worker system (Kafka/Database).
- **Observability Layer**: Integrated `EventAuditService` to record every dispatch, listener outcome, and performance metric.

## Phased Implementation Roadmap

### [Phase 1: Core Engine & Synchronous Execution (COMPLETED)](PHASE_1_CORE_ENGINE.md)
- Definition of core interfaces and base classes.
- Implementation of the `SyncDriver` for immediate listener execution.
- Integration of the Dispatcher into the `Application` container.

### [Phase 2: Advanced Routing & Control (COMPLETED)](PHASE_2_ROUTING_CONTROL.md)
- Implementation of wildcard listener patterns (e.g., `user.*`).
- Priority-based listener ordering.
- Propagation stopping mechanism for complex event chains.

### [Phase 3: Asynchronous Infrastructure (COMPLETED)](PHASE_3_ASYNC_INFRASTRUCTURE.md)
- Implementation of the `QueueDriver`.
- Event and listener serialization logic.
- Development of the background worker/consumer to process deferred events.

### [Phase 4: Meticulous Observability & Audit (COMPLETED)](PHASE_4_OBSERVABILITY_AUDIT.md)
- Database-backed audit trail for all event activities.
- Failure recovery and retry logic for asynchronous listeners.
- Performance tracking for listener execution times.

### [Phase 5: Global Framework Integration (COMPLETED)](PHASE_5_INTEGRATION.md)
- Introduction of the `event()` global helper and `Event` facade.
- Refactoring core framework hooks (Auth, Router) to emit events.
- Documentation for creating custom events and subscribers.
