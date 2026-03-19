# Phase 4: Event-Driven & Async Infrastructure

## Goal
To leverage the `EventDispatcher` for handling complex, long-running script generation tasks, providing a responsive and scalable experience via the `QueueDriver`.

## Key Components

### 1. Asynchronous Task Dispatching
- Implement dot-notation events: `mangascript.job.requested` and `mangascript.job.completed`.
- Use the `QueueDriver` from `EventDispatcher` to process large novels in the background.
- Support for distributed workers to handle multiple generation tasks in parallel.

### 2. Event-Driven UI Updates (SuperPHP)
- Dispatch progress events (e.g., `mangascript.chapter.processed`) during the generation cycle.
- Integration with SuperPHP's reactive state to update progress bars and status in the Studio App workspace.

### 3. Error Handling & Retry Logic
- Implement specialized listeners to handle AI provider timeouts or failures.
- Use the unified `AuditService` to log and analyze generation failures.
- Automatic retry logic for transient errors (e.g., Rate Limits).

### 4. Background Analytics
- Dispatch events for usage tracking, cost calculation, and performance metrics.
- Asynchronously aggregate this data for tenant-level reporting via the `AuditService`.

## Technical Requirements
- **Events**: Definition of `MangaScriptEvent` base class.
- **Listeners**: Implementation of `ProcessMangaScriptListener` for the asynchronous worker.
- **Observability**: Integration with the unified `AuditService` for tracking lifecycle.

## Success Criteria
- Large novels can be submitted and processed without timing out the web request.
- The UI reflects real-time progress of the background generation task via SuperPHP.
- Failed generation attempts are recorded in the unified audit trail with detailed error context.
