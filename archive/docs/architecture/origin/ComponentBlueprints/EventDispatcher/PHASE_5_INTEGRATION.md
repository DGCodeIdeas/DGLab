# Phase 5: Global Framework Integration

## Overview
The final phase focuses on making the Event Dispatcher an ubiquitous part of the developer experience. It involves providing clean APIs and refactoring core framework components to leverage events.

## Key Components

### 1. Developer API
- **`event()` Global Helper**: A short-hand function to dispatch events (e.g., `event(new UserRegistered($user))`).
- **`Event` Facade**: Providing a static interface to the `EventDispatcher` service.
- **`EventSubscriber` Base**: A convenient base class for grouping multiple listeners.

### 2. Core Refactoring
- **Authentication**: Emit `UserLoggedIn`, `UserLoggedOut`, and `LoginFailed` events.
- **Router**: Emit `RouteMatched` and `RequestHandled` events.
- **Database**: Emit `QueryExecuted` events (conditionally, based on config).
- **DownloadService**: Emit `FileDownloaded` and `DownloadFailed` events to decouple audit logging.

### 3. Documentation & Tooling
- **CLI Commands**:
    - `php cli/events.php list`: List all registered events and their listeners.
    - `php cli/events.php worker`: Start the background event consumer.
- **Developer Guide**: Exhaustive documentation on creating events, listeners, and choosing between sync/async drivers.

## Success Criteria
- [ ] Core framework components (Auth, Router) successfully emit events.
- [ ] `event()` helper is available and functional throughout the application.
- [ ] CLI tools provide clear visibility into the event registry and worker status.
- [ ] Integration tests verify the end-to-end flow from event dispatch to listener execution (both sync and async).
