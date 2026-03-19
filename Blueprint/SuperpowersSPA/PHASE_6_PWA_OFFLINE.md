# Phase 6: PWA & Offline Strategy

## Goal
Building advanced Service Worker integration for caching the App Shell and enabling offline fallback for Superpowers components.

## Requirements

### 1. App Shell Caching
- **Logic**: Pre-cache the minimal `shell.super.php` HTML, `superpowers.js`, `superpowers.nav.js`, and global assets (CSS, logos).
- **Strategy**: Cache-first for these core assets.

### 2. Offline Fallback Page
- **Logic**: If an AJAX request for a fragment fails and there's no internet connection, the navigation engine should show an `offline.super.php` component.

### 3. Background Action Sync
- **Logic**: Queue up any `@click` actions that occur while offline (e.g., submitting a form).
- **Behavior**: Use the Background Sync API to replay these actions once the connection is restored.

### 4. Manifest Optimization
- **Logic**: Update `manifest.json` to ensure the PWA can be installed and the correct start URL is specified.

### 5. Runtime Cache for Fragments
- **Strategy**: Stale-while-revalidate for recently visited pages to provide instant-feeling navigation.

## Success Criteria
- [ ] The app boots instantly even on slow connections.
- [ ] Users see a friendly "Offline" component when navigation fails due to no connectivity.
- [ ] Form submissions are queued and sent when the network returns.
- [ ] PWA installation prompt is functional on mobile devices.
