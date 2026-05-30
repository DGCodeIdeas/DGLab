# EventDispatcher - Phase 3: Asynchronous Infrastructure

**Status**: COMPLETED
**Source**: `Blueprint/EventDispatcher/PHASE_3_ASYNC_INFRASTRUCTURE.md`

## Objectives
- [ ] consuming tasks to be deferred, improving application responsiveness.
- [ ] A new driver (`app/Core/EventDrivers/QueueDriver.php`) that, instead of executing listeners, pushes them to a background processing system.
- [ ] Integration with existing infrastructure (e.g., Kafka or a dedicated `jobs` table).
- [ ] Logic to safely serialize the Event object and any required metadata (e.g., originating user context) for storage in the queue.
- [ ] Use of the `EncryptionService` if sensitive data within the Event payload needs protection.
- [ ] Implementation of a long-running PHP process (CLI) that listens to the queue.
- [ ] Listener Async Flag
- [ ] Extension of the listener registration to allow marking a listener as `async => true`.
- [ ] The `EventDispatcher` will intelligently route these specific listeners to the `QueueDriver` while executing others via the `SyncDriver`.

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.
