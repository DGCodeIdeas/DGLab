# PHASE ESPOKE-16: Sovereign Booking Portal (Guest Booking Portal)

## Tier
External Spoke (Public-facing — untrusted, rate-limited via HUB-07, CSRF-protected, CDN-fronted)

## Component Name
Sovereign Booking Portal — `SovereignStack\External\BookingPortal`. The guest-facing booking
surface for hospitality-tenant properties: branded availability search, pricing display, OTA
widget embed, and the guest-side of the booking Pulse that hands off to `ISPOKE-26` (Reservations)
to hold inventory. Renders per-tenant branding (logo, color theme, domain) from `HUB-21` tenant
config and writes availability holds via `ISPOKE-26`'s `BookingRepository::hold()`.

## Description
ESPOKE-16 is the **public booking page** a guest lands on when they hit `hotel-abc.sovereign.example`
or click an OTA widget. It is intentionally thin: it does not own the booking state machine (that's
`ISPOKE-26`), does not compute final pricing (that's `HUB-22`), and does not persist guest identity
beyond the booking session (that's `HUB-04` issued at check-in, not at search time). What it owns
is the *guest experience*: search → results → room selection → guest details → hold confirmation
→ redirect to payment. Every guest request is rate-limited at `HUB-07` and the entire flow is
tenant-isolated via the lane field set from the request's hostname-to-tenant resolution at
`HUB-21` entry.

The portal supports two render modes. **Branded direct mode:** the guest hits a tenant's custom
domain (e.g. `hotel-abc.sovereign.example`), the portal resolves the hostname to a `tenant_id`
via `HUB-21`, and renders that tenant's branding. **OTA widget mode:** a third-party site embeds
the portal as an iframe with a `tenant_id` query parameter; the portal validates the parameter
against `HUB-21` and renders the same flow with reduced chrome. In both modes, the booking hold
is delegated to `ISPOKE-26` — the portal never writes to the `reservations` table directly.

## Build Status
📝 **Documented — ready for implementation.** Blocked on `CORE-02` (DI Container stub) and on
Bet 3 (Hub Full) ring lock per `HOSPITALITY-VERTICAL.md` §3. Frontend assets (HTML/CSS/JS) are
not yet designed; this blueprint specifies the server-side contract and leaves the view layer to
the implementation team.

## Dependency Status
- **Direct Hub:** `HUB-08` (Sovereign Gateway — request entry, CSRF, hostname-to-tenant routing),
  `HUB-07` (Sovereign Throttle — rate limit: 60 RPM per IP for search, 6 RPM for hold-initiate),
  `HUB-21` (Sovereign Nexus — tenant resolution from hostname / query param), `HUB-22` (Sovereign
  Ledger — pricing lookup for display), `HUB-02` (Sovereign Cache & State — availability cache
  read for search results, 30s TTL), `HUB-12` (Sovereign Notify — booking-confirmation email/
  SMS to guest on `BookingConfirmed` event), `HUB-09` (Sovereign Signal — listens on
  `BookingInitiated` to update UI; listens on `BookingConflict` to show conflict screen).
- **Transitive Core:** `CORE-04` (HTTP Message), `CORE-05` (Middleware), `CORE-06` (Router),
  `CORE-02` (DI Container).
- **Spoke peer:** `ISPOKE-26` (Reservations — `BookingRepository::hold()` is the only write path
  from the portal).

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `SearchRequest` | `final readonly class` | `tenant_id`, `check_in`, `check_out`, `guests`, `room_type_filter`. Validated at construction. |
| `AvailabilitySearch` | class | Reads from `HUB-02` cache (`availability:{tenant_id}:{date}:{room_type}`); falls back to `ISPOKE-26` repository on miss. Returns `iterable<RoomTypeAvailability>`. |
| `HoldInitiator` | class | Calls `ISPOKE-26 BookingRepository::hold()` with a 15-min TTL; renders the "we're holding your room" UI on success. |
| `TenantBrandingRenderer` | class | Resolves tenant branding (logo, theme, contact) from `HUB-21` config; injects into the view model. |
| `BookingConfirmationListener` | class | PSR-14 listener on `BookingConfirmed` (HUB-09); renders the confirmation page if the guest's session is still active, otherwise queues a `HUB-12` email. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\External\BookingPortal;

interface BookingPortalInterface
{
    /**
     * Search for available room types for a tenant. Read-only; never persists.
     * Rate-limited at HUB-07 to 60 RPM per IP.
     */
    public function search(SearchRequest $request): AvailabilityResults;

    /**
     * Initiate a 15-minute hold on a room type. Delegates the write to ISPOKE-26.
     * Rate-limited at HUB-07 to 6 RPM per IP.
     */
    public function initiateHold(string $tenantId, HoldRequest $hold): HoldToken;
}
```

## Interface Contracts

```php
namespace SovereignStack\External\BookingPortal\Contracts;

use SovereignStack\Bridge\Contracts\BoundaryContractInterface;

/**
 * The portal never crosses the Bridge directly — it goes through HUB services.
 * This contract is the documented edge the portal exposes to the Inner Rim.
 */
interface BookingPortalEdgeContract extends BoundaryContractInterface
{
    public function pingHealth(): bool;
    public function tenantBrandingFor(string $hostname): TenantBranding;
}
```

## Integration Strategy
- **Bridge compliance:** the portal never crosses `BRIDGE-01` directly — every internal call goes
  through `HUB-21` / `HUB-22` / `HUB-02` / `ISPOKE-26` via the container. The portal's only
  Bridge-facing contract is `BookingPortalEdgeContract` (health + branding lookup), used by the
  Inner Rim orchestrator for fleet-status reporting.
- **Tenant resolution:** `HUB-08` middleware extracts the `Host` header (or `?tenant=` query
  param in OTA widget mode), validates against `HUB-21`'s tenant registry, and sets the
  `tenant_id` on the request attributes. Every downstream query filters on this — a guest cannot
  search another tenant's availability because the `tenant_id` is server-set, never client-set.
- **Cache discipline:** availability search reads `HUB-02` first; on miss, falls back to a
  tenant-scoped `ISPOKE-26` repository query and warms the cache with a 30s TTL. The portal
  never writes to `HUB-02` directly — `ISPOKE-26` owns the cache-write contract.
- **Failure mode:** if `ISPOKE-26` is unreachable during `initiateHold()`, the portal renders a
  "booking temporarily unavailable" page and emits a `PortalBookingFailed` event on `HUB-09`.
  It does not retry in-process — retries are a `HUB-10` queue concern, not a UX concern.

## Security Properties
1. **Tenant isolation is mechanical.** The `tenant_id` is resolved server-side from hostname or
   validated query param; it is never accepted from a request body. A guest cannot search or
   book against a tenant they did not arrive at.
2. **Rate-limit before hold.** `initiateHold()` is throttled to 6 RPM per IP — a botnet cannot
   exhaust inventory holds by spamming the endpoint. Search is throttled to 60 RPM per IP.
3. **No persistence on the public path.** The portal itself writes nothing to the database —
   every write is delegated to `ISPOKE-26`, which writes the audit row in the same transaction
   as the booking mutation. The portal is stateless from a persistence standpoint.
4. **CSRF on every mutating POST.** `initiateHold()` requires a valid CSRF token issued by the
   portal on the search-results page; `HUB-08` middleware rejects POSTs without a matching
   token. The token is single-use and tenant-bound.
5. **PCI minimization.** The portal never sees a card number — the payment Pulse hands off to
   `ESPOKE-18` (Mobile Check-in) + `ISPOKE-18` (Sovereign Ledger) + `HUB-20` (Vault) tokenize
   path documented in `HOSPITALITY-VERTICAL.md` §2 Workflow 3. The portal's only payment-side
   responsibility is to render the "redirecting to payment" screen.

## CI Verification Criteria
- **Unit:** `AvailabilitySearch` reads from `HUB-02` cache on hit and does not call `ISPOKE-26`;
  on miss, falls back to `ISPOKE-26` and warms the cache with a 30s TTL.
- **Integration:** `initiateHold()` calls `ISPOKE-26 BookingRepository::hold()` exactly once;
  on `ISPOKE-26` unreachable, renders the unavailable page and emits `PortalBookingFailed`.
- **Tenant isolation:** integration test with a request whose `Host` header maps to tenant A but
  whose body sets `tenant_id=B` asserts the request is served against tenant A (server-set wins).
- **Rate-limit:** integration test sending 7 `initiateHold()` POSTs in 60s from one IP asserts
  the 7th is rejected with HTTP 429.
- **Static:** phpstan `level: max` clean; ≥85% branch coverage on `AvailabilitySearch` and
  `HoldInitiator`.
