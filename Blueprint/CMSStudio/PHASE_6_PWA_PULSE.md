# Phase 6: PWA Pulse & Client-Side Insights

## Goals
Extend the Pulse App to monitor the health and performance of the PWA from the client's perspective. This phase introduces the "Pulse App" with client-side telemetry.

## 6.1 Service Worker Lifecycle Tracking
- **Registration Stats**: Success/failure rates of SW registrations across different browsers.
- **Update Frequency**: How often the SW is updated and how long it takes to activate.
- **Offline Readiness**: Percentage of users who have successfully cached the "offline.html" page.

## 6.2 Cache Storage Analytics
- **Storage Quota**: Total storage consumed by the PWA Cache API on user devices.
- **Cache Hit/Miss Ratios**: Metrics for assets (JS, CSS, fonts, images) to optimize caching strategies.
- **Stale Content Tracking**: Detection of clients running outdated versions of the application.

## 6.3 Background Sync & IndexedDB Health
- **Sync Events**: Tracking of successful and failed Background Sync events.
- **IndexedDB Usage**: Monitoring the size and integrity of local client-side databases.
- **Data Conflict Resolution**: Logs for any issues encountered during data synchronization between client and server.

## 6.4 Client-Side Error Reporting
- **JS Error Logging**: Capturing `window.onerror` and `unhandledrejection` events.
- **SW Error Logging**: Capturing errors occurring inside the Service Worker context.
- **Device Context**: Errors must be logged with browser version, OS, and PWA mode (standalone vs. browser).

## 6.5 Performance Monitoring (Core Web Vitals)
- **LCP, FID, CLS**: Real-time monitoring of Core Web Vitals to ensure a smooth user experience.
- **Asset Load Times**: Tracking the performance of the `AssetService` in serving compiled JS/CSS.
- **Time to Interactive (TTI)**: Specifically for the PWA boot sequence.

## 6.6 User Interface: The "Pulse App" (Client View)
- **"Command Center" Vibe**: Like a NASA control room.
- **Global PWA Health Map**: Visualization of PWA health by browser, OS, and region.
- **Telemetry Charts**: High-density charts showing PWA cache hit rates and Core Web Vitals trends.
- **Telemetry Overlays**: The sidebar of the Content App (Phase 4) now shows real-time PWA cache hit rates for specific content.

## 6.7 Security & Isolation
- **Client Metadata Isolation**: Ensure all client-side metrics are strictly bound to their respective tenant contexts.
- **Privacy-Preserving Geolocation**: Geolocation at the region-level only to preserve user privacy.
