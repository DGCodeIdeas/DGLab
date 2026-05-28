# Phase 4: Observability & Debugging

## Goal
To provide 100% visibility into the download lifecycle, making it trivial to diagnose "File not found" or "Access Denied" issues.

## Key Features

### 1. Download Audit Logs (`download_logs` table)
A detailed record of every download interaction.
- **Fields**:
  - `id`, `timestamp`
  - `file_path`, `driver`
  - `status_code` (e.g., 200, 404, 403)
  - `error_message` (for failures)
  - `user_id` (if authenticated)
  - `ip_address`, `user_agent`
  - `download_time_ms`, `bytes_served`

### 2. Structured Application Logging
- Using the PSR-3 logger to record system events:
  - Driver initialization.
  - Storage connection failures.
  - File creation/deletion events.
  - Unauthorized access attempts with detailed context (e.g., "Mismatched HMAC signature").

### 3. Debug Mode Headers
- When `APP_DEBUG` is true, include custom X-headers in the download response:
  - `X-Download-Driver`: The driver used.
  - `X-Download-Storage-Path`: The internal path resolved.
  - `X-Download-Latency`: Time taken to resolve and serve the file.

### 4. Admin Panel Integration
- A dashboard view in the Admin Panel to search and filter download logs.
- Visualization of download failure rates and common error patterns.

## Technical Requirements
- **Migration**: Schema for `download_logs`.
- **Observer**: `DownloadEventObserver` to handle log insertion without blocking the request.

## Success Criteria
- Every "File not found" error is logged with the exact internal path that was attempted.
- Admins can trace a specific user's download history via the `download_logs` table.
- Performance bottlenecks (slow storage drivers) are identifiable via the latency logs.
