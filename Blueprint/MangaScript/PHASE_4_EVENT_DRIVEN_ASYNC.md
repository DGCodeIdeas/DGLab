# Phase 4: Event-Driven & Async Infrastructure

## Goal
To leverage the `EventDispatcher` for handling complex, long-running script generation tasks, providing a responsive and scalable experience.

## Key Components

### 1. Asynchronous Task Dispatching
- Implement `MangaScriptRequested` and `MangaScriptCompleted` events.
- Use the `QueueDriver` from `EventDispatcher` to process large novels in the background.
- Support for distributed workers to handle multiple generation tasks in parallel.

### 2. Event-Driven UI Updates
- Dispatch progress events (e.g., `MangaScriptChapterProcessed`) during the generation cycle.
- Integration with a real-time event broadcaster (e.g., WebSockets) to update the frontend progress bars and status.

### 3. Error Handling & Retry Logic
- Implement specialized listeners to handle AI provider timeouts or failures.
- Use the `EventDispatcher` audit trail to log and analyze generation failures.
- Automatic retry logic for transient errors (e.g., Rate Limits).

### 4. Background Analytics
- Dispatch events for usage tracking, cost calculation, and performance metrics.
- Asynchronously aggregate this data for tenant-level reporting.

## Technical Requirements
- **Events**: Definition of `MangaScriptEvent` base class.
- **Listeners**: Implementation of `ProcessMangaScriptListener` for the asynchronous worker.
- **Observability**: Integration with `EventAuditService` for tracking lifecycle.

## Success Criteria
- Large novels can be submitted and processed without timing out the web request.
- The UI reflects real-time progress of the background generation task.
- Failed generation attempts are recorded in the audit trail with detailed error context.
