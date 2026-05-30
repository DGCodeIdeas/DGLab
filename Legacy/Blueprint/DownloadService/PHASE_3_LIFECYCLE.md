# Phase 3: Lifecycle & Cleanup

## Goal
To manage the temporary nature of many application-generated files, ensuring the storage layer remains clean and disk space is preserved.

## Key Features

### 1. Expiration Policies
- **Global TTL**: A default time-to-live for all files in specific directories (e.g., `storage/uploads/temp`).
- **Metadata-based TTL**: Storing expiration timestamps in the `download_tokens` or a dedicated `file_metadata` table.

### 2. Automated Cleanup Engine
- A service that scans storage drivers for files that have exceeded their retention period.
- **Dry-run Mode**: Ability to see what *would* be deleted without performing the action.
- **Protected Files**: A mechanism to mark specific files or patterns as "permanent" or "exempt" from cleanup.

### 3. Task Integration
- Implementation of a `Download:Cleanup` command.
- Integration with the framework's Cron/Scheduler to run the cleanup job periodically (e.g., hourly).

### 4. Event Hooks
- Ability to trigger actions before or after a file is deleted (e.g., logging, notifying an external service).

## Technical Requirements
- **Config**: `cleanup_threshold` (e.g., 24 hours).
- **Service**: `CleanupService` within the Download namespace.

## Success Criteria
- Files in the temporary directory are automatically removed after the configured threshold.
- The system logs the number of files cleaned and the disk space reclaimed during each run.
- No files marked as "permanent" are accidentally deleted.
