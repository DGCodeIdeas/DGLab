# Phase 3: Asynchronous Infrastructure

## Overview
Phase 3 introduces the capability to handle events asynchronously. This allows time-consuming tasks to be deferred, improving application responsiveness.

## Key Components

### 1. `QueueDriver` Implementation
- A new driver (`app/Core/EventDrivers/QueueDriver.php`) that, instead of executing listeners, pushes them to a background processing system.
- Integration with existing infrastructure (e.g., Kafka or a dedicated `jobs` table).

### 2. Event Serialization
- Logic to safely serialize the Event object and any required metadata (e.g., originating user context) for storage in the queue.
- Use of the `EncryptionService` if sensitive data within the Event payload needs protection.

### 3. Background Worker / Consumer
- Implementation of a long-running PHP process (CLI) that listens to the queue.
- **Payload Unpacking**: Logic to reconstruct the Event and Listener objects from the serialized data.
- **Execution Environment**: Ensuring the background worker has access to the full `Application` container and its services.

### 4. Per-Listener Async Flag
- Extension of the listener registration to allow marking a listener as `async => true`.
- The `EventDispatcher` will intelligently route these specific listeners to the `QueueDriver` while executing others via the `SyncDriver`.

## Success Criteria
- [ ] Events marked for async processing are successfully serialized and queued.
- [ ] The background worker can consume a queued event and execute the corresponding listener.
- [ ] Support for constructor DI within listeners running in the background worker process.
- [ ] Graceful handling of serialization/deserialization for complex objects.
