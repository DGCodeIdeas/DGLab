# Nexus: Studio & Superpowers Integration Blueprint

Nexus is the bridge that turns the Superpowers SPA from a "Pull" application into a truly "Reactive" platform.

## 1. Live Console Integration (MVP)
The first use case for Nexus is the Studio App Console.

### 1.1 The Bridge (`LiveConsoleProvider`)
- **Role**: Listens for any `job.log` or `job.progress` events dispatched by the `Worker`.
- **Workflow**:
    1. Worker processes a chunk of work.
    2. Worker calls `Event::dispatch(new JobLogEvent($msg))`.
    3. `BroadcastDriver` sends it to Nexus via Redis.
    4. Nexus pushes it to the client subscribed to `console.jobs.{id}`.

## 2. Reactive State Push (Phase 2)
Leveraging the Phase 9 State Persistence of Superpowers.

### 2.1 Dirty Check Hook
In the `ActionController`, when a request finishes:
- Current logic: Sends diffs in the JSON response.
- **Nexus logic**: If the user has an active WebSocket, the diffs are pushed as an `event: "state.update"`.
- **Benefit**: Real-time synchronization across multiple tabs or even different users (for `@global` state).

## 3. Server-Initiated Fragments
Nexus allows the server to trigger a UI update without a user interaction.
- **Command**: `fragment.update`.
- **Payload**: `{ "target": "#sidebar", "html": "..." }`.
- **Client Action**: The `superpowers.nav.js` intercepts this packet and performs a morphing update on the specified DOM element.

## 4. Frontend Implementation (`superpowers.nexus.js`)
A new lightweight client-side library to handle the Nexus connection.
- Auto-reconnect with backoff.
- Automatic JWT inclusion during handshake.
- Event routing to Superpowers components.
- Global `window.Nexus` object for manual subscriptions.
