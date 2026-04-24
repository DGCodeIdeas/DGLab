# DownloadService - Phase 4: Observability & Debugging

**Status**: COMPLETED
**Source**: `Blueprint/DownloadService/PHASE_4_OBSERVABILITY.md`

## Objectives
- [ ] `id`, `timestamp`
- [ ] `file_path`, `driver`
- [ ] `status_code` (e.g., 200, 404, 403)
- [ ] `error_message` (for failures)
- [ ] `user_id` (if authenticated)
- [ ] `ip_address`, `user_agent`
- [ ] `download_time_ms`, `bytes_served`
- [ ] Using the PSR-3 logger to record system events:
- [ ] Driver initialization.
- [ ] Storage connection failures.
- [ ] File creation/deletion events.
- [ ] Unauthorized access attempts with detailed context (e.g., "Mismatched HMAC signature").
- [ ] When `APP_DEBUG` is true, include custom X-headers in the download response:
- [ ] `X-Download-Driver`: The driver used.
- [ ] `X-Download-Storage-Path`: The internal path resolved.
- [ ] `X-Download-Latency`: Time taken to resolve and serve the file.
- [ ] A dashboard view in the Admin Panel to search and filter download logs.
- [ ] Visualization of download failure rates and common error patterns.
- [ ] Every "File not found" error is logged with the exact internal path that was attempted.
- [ ] Admins can trace a specific user's download history via the `download_logs` table.
- [ ] Performance bottlenecks (slow storage drivers) are identifiable via the latency logs.

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.
