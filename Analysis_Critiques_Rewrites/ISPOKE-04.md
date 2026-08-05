# PHASE ISPOKE-04: Staff Identity and Onboarding Portal

## Tier
Internal Spoke (Staff-only Application)

## Resolves
Cross-references checked against `01_MASTER_INDEX.md` §3 — clean. Adds stated benchmark methodology
(Finding 10).

## Component Name
Sovereign Staff Hub

## Description
Internal identity-management portal: staff onboarding, SSO configuration, MFA enforcement, access
request workflows — extends `HUB-04`/`HUB-05` with internal-only security requirements.

## Build Status
🔴 **Blocked** on `HUB-04`, `HUB-05`, `HUB-12` (Notify — correctly referenced), `HUB-20` (Vault) —
none implemented.

## Dependency Status
- **Direct Hub:** `HUB-04`, `HUB-05`, `HUB-12`, `HUB-20`, `HUB-26`, `HUB-15`, `HUB-16`. *(Verified —
  correct.)*
- **Transitive Core:** `CORE-16`, `CORE-19`, `CORE-04`, `CORE-03`.

## Architectural Design
- **OnboardingWizard** — step-by-step account/MFA setup UI.
- **AccessRequester** — workflow engine for temporary/permanent permission requests.
- **ProfileManager** — staff-specific profile settings.
- **SsoConfigurator** — SAML/OIDC connection management for internal identity providers.

## Integration Strategy
- **Bootstrapping:** specialized `HUB-04` consumer with stricter internal policies.
- **UI Rendering:** `HUB-26` form/wizard components.
- **Notifications:** `HUB-12` for onboarding invitations and security alerts.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| MFA enforcement | Integration test: attempt portal access with a fixture user lacking an active MFA challenge; assert denial at the middleware level, not just a UI-hidden control. |
| Audit completeness | Integration test: perform each identity-change operation (create, role change, deactivate); assert exactly one corresponding `HUB-06` entry per operation, no gaps. |
| Notification queuing | Integration test: create a staff account; assert the onboarding email job is queued via `HUB-10` (not `HUB-12` directly — `HUB-12` routes to `HUB-10` for delivery per `HUB-12.md`) within the same transaction as account creation. |

## CI Verification Criteria
- MFA-enforcement middleware test, blocking.
- 100% audit-completeness test, blocking.
- Notification-queuing-in-same-transaction test, blocking.

## SemVer Impact
**Minor.** Secures the internal human element of the Sovereign Stack.
