# Phase 9: Governance & System Control

## Goals
Implement high-level system management, configuration overrides, and the emergency "Kill Switch." This phase introduces the "Control App" with a "Command Center" aesthetic.

## 9.1 Manual System Management
- **Background Job Triggers**: Ability to manually trigger or retry specific jobs (e.g., database migrations, asset obfuscation).
- **Queue Control**: Pausing or resuming the background job processor globally or per tenant.

## 9.2 Remote Cache Management
- **Asset Purging**: A "Purge All" button to clear server-side `storage/cache/assets/` and simultaneously trigger a Service Worker update to clear client-side caches (from Phase 6).
- **Individual Asset Refresh**: Forcing a re-compilation of a specific SCSS or JS file.

## 9.3 Dynamic Configuration Overrides
- **Live Settings**: Modification of certain `.env` settings (like `LOG_LEVEL` or `APP_DEBUG`) from the Control App without requiring a server restart.
- **Maintenance Mode**: An "Admin-only" mode that restricts public access while allowing admins to perform maintenance.

## 9.4 Emergency "Kill Switch"
- **PWA Deactivation**: A mechanism to remotely unregister the Service Worker or disable specific PWA features (like Background Sync) in case of critical bugs.
- **Service-Specific Disabling**: Ability to temporarily take down a specific tool (e.g., EPUB Font Changer) while keeping the rest of the site operational.

## 9.5 User Interface: The "Control App"
- **"Command Center" Vibe**: Like a NASA control room.
- **Glowing Control Panel**: A high-density dashboard for managing system-wide overrides.
- **Emergency Button**: A dedicated, prominent "Kill Switch" area for critical system deactivation.
- **Override Timeline**: A time-axis view that shows configuration overrides and their impact on system performance (from Phase 5).

## 9.6 Security & Governance
- **Override Audit Logs**: Every configuration override or kill-switch activation is logged with maximum detail and duration tracking.
- **Role-Based Overrides**: Only the highest-level roles (e.g., `SuperAdmin`) can activate the emergency kill switch.
