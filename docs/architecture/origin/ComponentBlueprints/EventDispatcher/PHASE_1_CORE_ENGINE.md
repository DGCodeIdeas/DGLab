# Phase 1: Core Engine & Synchronous Execution

## Overview
Phase 1 establishes the foundational architecture of the Event Dispatcher. It focuses on defining the internal contracts and implementing the primary `EventDispatcher` class along with a `SyncDriver` for immediate event processing.

## Key Components

### 1. Internal Interfaces
- **`EventInterface`**: A marker interface for all event classes.
- **`ListenerInterface`**: Defines the `handle(EventInterface $event)` method.
- **`DispatcherInterface`**: Defines methods for `dispatch()`, `listen()`, and `removeListener()`.
- **`EventDriverInterface`**: Defines the contract for execution strategies.

### 2. Core Implementation
- **`EventDispatcher` (app/Core/EventDispatcher.php)**:
    - Maintains a registry of events and their associated listeners.
    - Resolves listeners through the `Application` container to support Dependency Injection.
    - Orchestrates execution via registered drivers.
- **`SyncDriver` (app/Core/EventDrivers/SyncDriver.php)**:
    - The default driver that iterates through listeners and executes them sequentially.

### 3. Application Integration
- Registration of the `EventDispatcher` as a singleton in `config/services.php` or `Application.php`.
- Bootstrapping the dispatcher early in the request lifecycle to ensure availability for other core services.

## Success Criteria
- [ ] Ability to define a custom Event class.
- [ ] Ability to register a Listener closure or class.
- [ ] `EventDispatcher::dispatch()` successfully triggers registered synchronous listeners.
- [ ] Listeners can receive dependencies via constructor injection.
