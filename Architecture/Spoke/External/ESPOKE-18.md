# PHASE ESPOKE-18: Sovereign Mobile Check-in (Guest Mobile Check-in)

## Tier
External Spoke (Public-facing — untrusted, rate-limited via HUB-07, mobile-web-optimized,
e-signature capture, room-access integration)

## Component Name
Sovereign Mobile Check-in — `SovereignStack\External\MobileCheckIn`. The guest-side mobile web
flow for contactless check-in at hospitality-tenant properties: registration form, ID document
upload, e-signature capture, room assignment confirmation, and digital key issuance. Hands off to
`ISPOKE-27` (Front Desk Ops) for the staff-side check-in Pulse and to `HUB-20` (Vault) for
PCI/PII tokenization. The guest never sees the staff console; the staff never sees the guest's
raw ID document.

## Description
ESPOKE-18 is the **mobile check-in flow** a guest walks through on their phone the day of arrival.
The flow is: (1) guest taps the check-in link from their `HUB-12` booking-confirmation SMS,
(2) verifies identity (existing `HUB-04` session if they booked while logged in, otherwise an
SMS OTP challenge), (3) fills the registration form (name, address, emergency contact), (4)
uploads an ID document photo (front + back), (5) reviews and e-signs the registration card,
(6) receives a room assignment + digital room key (QR code or NFC token). The whole flow is
designed to take under 90 seconds; a guest who abandons at step 4 is sent a `HUB-12` reminder
2 hours before arrival.

The spoke is the **privacy-critical surface** of the hospitality vertical. ID documents are
PCI-DSS-equivalent PII — they cannot be persisted in clear text, cannot be logged in full, and
must be tokenized at the edge. ESPOKE-18 streams each upload directly to `HUB-20` (Vault) for
tokenization, stores only the resulting token, and discards the original from memory before
responding to the guest. The staff-side `ISPOKE-27` console can *retrieve* a document by token
(when a front-desk agent clicks "view ID"), but the retrieval is audited (`HUB-06`) and the
document is watermarked with the agent's identity + timestamp to deter screenshot exfiltration.

The e-signature is a separate concern from the ID upload. The signature is captured as an SVG
path (vector, not raster) plus a cryptographic hash of the registration-card PDF the guest is
signing. The hash + path are stored together; the PDF itself is generated server-side from the
form data and the signature, never sent from the client. This makes the signature non-repudiable:
a guest cannot claim "I signed a different document" because the hash binds the signature to a
specific PDF content.

## Build Status
📝 **Documented — ready for implementation.** Blocked on `CORE-02` (DI Container stub) and on
Bet 3 (Hub Full) ring lock per `HOSPITALITY-VERTICAL.md` §3. The room-access integration (QR/NFC
issuance) depends on the property's physical lock vendor — the adapter is pluggable but the
default implementation is stubbed pending vendor selection.

## Dependency Status
- **Direct Hub:** `HUB-08` (Sovereign Gateway — request entry, OTP challenge, CSRF), `HUB-07`
  (Sovereign Throttle — rate limit: 10 check-in-initiate per hour per guest), `HUB-04` (Sovereign
  Identity — guest session, SMS OTP), `HUB-21` (Sovereign Nexus — tenant resolution from booking
  link), `HUB-12` (Sovereign Notify — check-in confirmation SMS with room number + key),
  `HUB-09` (Sovereign Signal — `CheckInInitiated` / `CheckInCompleted` events), `HUB-20`
  (Sovereign Vault — ID document tokenization), `HUB-11` (Sovereign Cloud Storage — signed
  registration-card PDF + signature persistence, 7-year retention per hospitality regulation),
  `HUB-06` (Sovereign Auditor — every step of the flow audited, every document retrieval
  audited), `HUB-02` (Sovereign Cache & State — OTP attempt counter, 5-attempt lockout).
- **Transitive Core:** `CORE-04`, `CORE-05`, `CORE-06`, `CORE-16` (Binary Encryption Envelope —
  encrypts the signed PDF at rest in `HUB-11`), `CORE-02`.
- **Spoke peer:** `ISPOKE-27` (Front Desk Ops — `CheckInService::checkIn()` completes the
  staff-side Pulse; ESPOKE-18 triggers it via PSR-14 event).

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `CheckInSession` | `final readonly class` | `id` (ULID), `tenant_id`, `guest_id`, `reservation_id`, `current_step` (`identity` / `form` / `id_upload` / `esign` / `complete`), `started_at`. State machine over `current_step`. |
| `IdDocumentUploader` | class | Streams upload to `HUB-20` Vault for tokenization. Returns a `DocumentToken`. Discards original from memory before responding. |
| `ESignatureCapture` | class | Receives SVG path + PDF-content hash from client. Validates hash matches server-generated PDF. Returns `Signature` object. |
| `RegistrationCardPdf` | class | Server-side PDF generator from form data. Hash is computed server-side; client never sends the PDF, only the hash. |
| `RoomKeyIssuer` | class | Calls the configured `RoomAccessVendorInterface` to issue a QR/NFC key for the assigned room. Pluggable — default is a stub. |
| `CheckInCompletionListener` | class | Listens on `CheckInCompleted` (HUB-09, emitted by `ISPOKE-27`); sends the confirmation SMS via `HUB-12` with room number + key. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\External\MobileCheckIn;

interface MobileCheckInInterface
{
    /**
     * Start a check-in session for a guest + reservation. Validates the reservation
     * is in 'confirmed' state and check_in_date is today.
     */
    public function startSession(string $reservationId, string $guestId): CheckInSession;

    /**
     * Upload an ID document. Streams directly to HUB-20 Vault; returns the token.
     * The original document is discarded from memory before this method returns.
     */
    public function uploadIdDocument(string $sessionId, UploadedFile $file): DocumentToken;

    /**
     * Capture an e-signature. Validates the PDF-content hash matches the server-
     * generated PDF; throws on mismatch.
     */
    public function captureSignature(string $sessionId, string $svgPath, string $pdfHash): Signature;

    /**
     * Complete the check-in. Triggers ISPOKE-27 CheckInService::checkIn() via PSR-14
     * event; on success, issues the room key and sends the confirmation SMS.
     */
    public function complete(string $sessionId): CheckInResult;
}
```

## Interface Contracts

```php
namespace SovereignStack\External\MobileCheckIn\Contracts;

use SovereignStack\Bridge\Contracts\BoundaryContractInterface;

/**
 * The check-in flow never crosses the Bridge directly. This contract is the
 * edge the Inner Rim orchestrator pings for fleet-status reporting.
 */
interface MobileCheckInEdgeContract extends BoundaryContractInterface
{
    public function pingHealth(): bool;
    public function activeSessionCount(string $tenantId): int;
}
```

## Integration Strategy
- **Bridge compliance:** the spoke goes through `HUB-04` / `HUB-21` / `HUB-12` / `HUB-09` /
  `HUB-20` / `HUB-11` / `HUB-06` / `HUB-02` / `ISPOKE-27` for every internal call. Its only
  Bridge-facing contract is `MobileCheckInEdgeContract` (health + active-session count).
- **ID document flow:** the client (mobile browser) uploads directly to ESPOKE-18 (not to a
  CDN); ESPOKE-18 streams the upload to `HUB-20` Vault in a single pass, receives the token,
  and discards the original. The token is stored on the `CheckInSession`; the original is
  never persisted to disk. Retrieval by staff is via `HUB-20` token + `HUB-06` audit + watermark
  overlay applied by `HUB-11` on retrieval.
- **Signature flow:** the client captures the SVG path (vector) and computes a hash of the
  server-generated PDF content. ESPOKE-18 validates the hash matches what the server would
  generate from the form data; on mismatch, it rejects (the client may be trying to sign a
  different document). On match, it stores `(svgPath, pdfHash, pdfRef)` together — the PDF
  itself is persisted to `HUB-11` encrypted via `CORE-16`.
- **Room-key issuance:** `RoomKeyIssuer` calls the configured `RoomAccessVendorInterface`
  (e.g., Assa Abloy, Salto, dormakaba). The adapter is pluggable — the default is a `StubVendor`
  that returns a QR code with the room number and a 24h expiry. Adding a real vendor is a new
  adapter implementation, not a code change to the flow.
- **Failure mode:** if `ISPOKE-27` is unreachable at `complete()` time, ESPOKE-18 renders a
  "please see the front desk" screen and emits a `CheckInFailed` event on `HUB-09`. The guest
  is never left in a half-checked-in state — the `CheckInSession` stays at `esign` step and
  can be retried, or completed in person at the front desk.

## Security Properties
1. **ID documents never touch disk in clear text.** The upload is streamed directly to `HUB-20`
   Vault; the original is discarded from ESPOKE-18's memory before the response is sent. The
   token is the only persisted reference. A compromise of ESPOKE-18's filesystem reveals nothing.
2. **Document retrieval is audited and watermarked.** Every `HUB-20` token retrieval by staff
   is logged to `HUB-06` with `retrieved_by`, `retrieved_at`, `purpose`. The retrieved document
   is watermarked (visible + invisible) with the agent's identity + timestamp by `HUB-11` on
   retrieval — a screenshot of a leaked document identifies the leaker.
3. **E-signature is non-repudiable.** The signature is bound to the PDF by hash; the PDF is
   generated server-side from the form data the guest themselves entered; the signature is
   captured as a vector path (not a raster image, which could be re-used). A guest disputing
   "I didn't sign that" can be shown: (a) the PDF they signed, (b) the hash matches, (c) the
   signature vector matches what their browser captured.
4. **OTP lockout is mechanical.** `HUB-02` cache tracks OTP attempts per `guest_id`; 5 wrong
  attempts in 15 minutes locks the session for 30 minutes and emits a `SuspiciousOtpAttempts`
  event on `HUB-09`. A guest cannot brute-force the OTP step.
5. **Rate-limit on session-start, not just IP.** `HUB-07` keys on `guest_id`, not IP — a guest
   cannot start 50 check-in sessions in parallel from different devices to exhaust `ISPOKE-27`
   capacity. Limit: 10 sessions per hour per guest.
6. **Encryption at rest.** The signed PDF in `HUB-11` is encrypted via `CORE-16` (Binary
   Encryption Envelope) with a per-tenant key. A compromise of `HUB-11` storage reveals
   ciphertext only.

## CI Verification Criteria
- **Unit:** `IdDocumentUploader` discards the original from memory after tokenization (asserted
  via a memory probe in the test); `ESignatureCapture` rejects a hash that doesn't match the
  server-generated PDF; `CheckInSession` state machine rejects illegal transitions (`complete →
  form`, `id_upload → complete` skipping `esign`).
- **Integration (MySQL 8 InnoDB + HUB-20 mock):** end-to-end Pulse (`startSession` → `uploadIdDocument`
  → `captureSignature` → `complete` → `ISPOKE-27.checkIn()` → `RoomKeyIssuer` → `HUB-12` SMS)
  completes in under 5s at p95 with the stub room-access vendor.
- **OTP lockout:** integration test with 5 wrong OTPs asserts the session is locked for 30 min
  and `SuspiciousOtpAttempts` is emitted.
- **Audit on retrieval:** integration test where a staff agent retrieves an ID document asserts
  a `HUB-06` audit row is written with `retrieved_by`, `retrieved_at`, `purpose`.
- **Static:** phpstan `level: max` clean; ≥90% branch coverage on `IdDocumentUploader`,
  `ESignatureCapture`, and `CheckInSession`.
