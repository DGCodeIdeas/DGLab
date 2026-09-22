# MangaScript - Phase 5: Delivery & Meticulous Observability

**Status**: IN_PROGRESS
**Source**: `Blueprint/MangaScript/PHASE_5_OBSERVABILITY_DELIVERY.md`

## Objectives
- [ ] Implement multiple export drivers: Markdown, JSON, PDF (via Dompdf), and HTML.
- [ ] Utilize `DownloadManager` to serve generated files with secure, signed, and expiring URLs.
- [ ] Integrate one-time-use tokens for script downloads to ensure security.
- [ ] Log detailed metrics for every generation request to the unified `AuditService`:
- [ ] Input/Output token counts.
- [ ] Provider and Model used (from `llm_unified.php`).
- [ ] Request latency and cost.
- [ ] Tenant and User context.
- [ ] Register `MangaScript` facade for easy access across the framework: `MangaScript::process($input)`.
- [ ] Implement a global `mangascript()` helper function.
- [ ] Create CLI tools (`php cli/mangascript.php`) for bulk script generation and maintenance.
- [ ] Expose MangaScript metrics to the CMS Studio "Pulse App" for system-wide monitoring.
- [ ] Track total costs per provider and tenant usage patterns via the unified audit log.
- [ ] Users can securely download their generated scripts in multiple formats.
- [ ] System administrators can view a detailed cost/usage report for the service.
- [ ] The service is fully documented and integrated into the framework's core lifecycle.

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.
