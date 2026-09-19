# Studio Expansion: Live Console & Unified SSO

## 1. Global Live Console (Pulse Console)
The Live Console is a persistent, global UI element in the Studio Hub that provides real-time, granular feedback for all background tasks.

### 1.1 UI/UX Specification
- **Component**: `<s:ui:console />` (Global bottom drawer/sidebar).
- **Two States**:
    - **Collapsed**: A thin horizontal bar (approx. 40px height) at the bottom. Displays the *latest active task* name and a *percentage progress bar*.
    - **Expanded**: A vertically scrollable log area. Occupies the bottom 40% of the viewport (mobile) or can be docked to the right (desktop).
- **Visual Log Style**:
    - Sequential output, **no timestamps**.
    - Prefixes: `> ` (info), `✅ ` (success), `❌ ` (error), `🔄 ` (processing).
    - Log lines wrap for narrow screens; font is monospace and small.
- **Responsiveness**:
    - **Narrow (<640px)**: Bottom sheet with drag handle. Full-width.
    - **Wide (>640px)**: Toggleable between bottom-docked or right-docked (vertical sidebar).

### 1.2 Backend Architecture (Event Piping)
- **Job Events**: Every long-running service (MangaScript, EpubFontChanger, etc.) must emit granular events via `EventDispatcher`.
    - `job.started`, `job.progress`, `job.log`, `job.completed`, `job.failed`.
- **WebSocket Broadcasting (Ratchet)**:
    - A dedicated WebSocket server (`cli/websocket.php`) handles real-time broadcasting.
    - Users subscribe to a private channel: `user.{user_id}.console`.
    - The `QueueDriver` or the background worker (`cli/worker.php`) pushes event payloads to the WebSocket server after each granular step.
- **Polling Fallback**: If WebSockets are unavailable, the console component fallback to an AJAX polling mechanism (`GET /api/studio/console/pulse`).

## 2. Unified SSO (Sign In for All)
DGLab uses a single-identity model where one login session covers all Studio Apps and framework services.

### 2.1 Implementation Details
- **Shared Session**: All services (MangaScript, MangaImage, CMS) reside within the same domain/sub-directory ecosystem of the Superpowers SPA.
- **Auth Guard**: Uses the existing `SessionGuard` (for web) and `JwtGuard` (for API/SPA requests).
- **Login Flow**:
    - User logs in via the central `AuthController`.
    - A JWT is issued and stored in an `HttpOnly` cookie or `localStorage`.
    - Every subsequent request includes the token in the `Authorization` header.
- **Cross-App Navigation**: Switching between "Spokes" (e.g., from MangaScript to MangaImage) is handled by the SPA router (`superpowers.nav.js`), preserving the auth state and active console session.

## 3. Real-Time Progress Bars
- **Component**: `<s:ui:progress-bar />`.
- **Attributes**: `task-id`, `label`, `value` (0-100).
- **Reactivity**: The component binds to the global console state. When a `job.progress` event is received, the progress bar updates its width and percentage text via SuperPHP reactive diffing.

## 4. Verification
- [ ] Verify that starting a job in MangaScript immediately adds a entry to the console.
- [ ] Confirm that logging out of the Studio Hub correctly clears the session for all Spokes.
- [ ] Test the console's vertical expansion and log wrapping on a 320px width viewport.
