# PHASE ESPOKE-16: Sovereign Booking Portal (Guest Booking Portal)


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

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


---

## Doctrines Applied + Rewrite Notes (PR #351)

> **This section was added in PR #351 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../../Verification/INTEGRITY-GATE.md`](../../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../../ADRs/ADR-021-tier-stratified-build-order.md`](../../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`../../Core/CORE-VERIFIED-DAG.md`](../../Core/CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../../Verification/SHORTCOMINGS-REGISTER.md`](../../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../../FROZEN-CONTRACTS.md`](../../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #351

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../../CrossCutting/SDLC-AGRD.md`](../../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — ESPOKE-16 — shipped per the verified DAG).

### What Was NOT Changed in PR #351

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/spoke/external/booking/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #351 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
