# Phase 5: System Management Tools

## 5.1 Manual Background Job Triggers
- **On-Demand Processing**: Ability to manually trigger specific jobs (e.g., force a database migration or run asset obfuscation).
- **Queue Control**: Pausing/Resuming the background job processor.

## 5.2 Remote Cache Management
- **Asset Purging**: A "Purge All" button to clear the server-side `storage/cache/assets/` and simultaneously trigger a Service Worker update to clear client-side caches.
- **Individual Asset Refresh**: Forcing a re-compilation of a specific SCSS or JS file.

## 5.3 Dynamic Configuration Overrides
- **Live Settings**: Modification of certain `.env` settings (like `LOG_LEVEL` or `APP_DEBUG`) from the admin panel without requiring a server restart.
- **Maintenance Mode**: An "Admin-only" mode that restricts public access while allowing admins to perform maintenance.

## 5.4 Emergency "Kill Switch"
- **PWA Deactivation**: A mechanism to remotely unregister the Service Worker or disable specific PWA features (like Background Sync) in case of critical bugs.
- **Service-Specific Disabling**: Ability to temporarily take down a specific tool (e.g., EPUB Font Changer) while keeping the rest of the site operational.
