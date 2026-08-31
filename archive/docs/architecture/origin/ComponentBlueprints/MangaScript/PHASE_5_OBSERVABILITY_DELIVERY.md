# Phase 5: Delivery & Meticulous Observability

## Goal
To finalize the MangaScript service by integrating it with the `DownloadService` for script distribution and establishing a comprehensive observability layer for monitoring and optimization via the unified `AuditService`.

## Key Components

### 1. Secure Script Export via DownloadService
- Implement multiple export drivers: Markdown, JSON, PDF (via Dompdf), and HTML.
- Utilize `DownloadManager` to serve generated files with secure, signed, and expiring URLs.
- Integrate one-time-use tokens for script downloads to ensure security.

### 2. Meticulous Usage & Performance Auditing
- Log detailed metrics for every generation request to the unified `AuditService`:
    - Input/Output token counts.
    - Provider and Model used (from `llm_unified.php`).
    - Request latency and cost.
    - Tenant and User context.

### 3. Global Framework Integration
- Register `MangaScript` facade for easy access across the framework: `MangaScript::process($input)`.
- Implement a global `mangascript()` helper function.
- Create CLI tools (`php cli/mangascript.php`) for bulk script generation and maintenance.

### 4. Admin Dashboard Analytics
- Expose MangaScript metrics to the CMS Studio "Pulse App" for system-wide monitoring.
- Track total costs per provider and tenant usage patterns via the unified audit log.

## Technical Requirements
- **Integration**: `DownloadManager`, Unified `AuditService`, `Facade`.
- **Drivers**: Implementation of `MangaScriptExportDriverInterface`.

## Success Criteria
- Users can securely download their generated scripts in multiple formats.
- System administrators can view a detailed cost/usage report for the service.
- The service is fully documented and integrated into the framework's core lifecycle.
