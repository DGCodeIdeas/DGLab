# Phase 7: Nexus Live-Reload

## Goals
- Integrate the documentation service with the Nexus WebSocket server.
- Implement real-time browser updates when a Markdown file is modified on disk.
- Establish the `DocWatchEvent` bridge.

## Real-time Pipeline
1.  A filesystem watcher (in CLI mode) detects changes to `.md` files.
2.  It dispatches a `doc.changed` event to the `EventDispatcher`.
3.  The `BroadcastDriver` pushes the event to Nexus.
4.  The browser receives the message and triggers a Superpowers `reload()` or `morph()` on the affected fragment.

## Deliverables
1.  `doc.changed` event definition.
2.  Nexus message handler for live-reload.
3.  Client-side listener in `superpowers.js` to handle refresh signals.
