# PHASE ISPOKE-04: Staff Identity and Onboarding Portal

## Tier
Internal Spoke (Staff-only Application)

## Component Name
Sovereign Staff Hub

## Description
The internal identity management portal for Sovereign Stack staff. It manages staff-specific onboarding, SSO configuration, MFA enforcement, and access request workflows. It extends `HUB-04` and `HUB-05` for internal-only security requirements.

## Sequencing Rationale
Ensures that the people building and managing the system are properly authenticated and authorized using the same foundational security layers.

## Context7 Research
### Direct Hub Dependencies
- `HUB-04: Global Identity & Authentication`
- `HUB-05: RBAC & Permission Engine`
- `HUB-12: Notification Service`
- `HUB-20: Vault` (for temporary credentials)
- `HUB-26: Shared UI Component Library`
- `HUB-15: Health Check`
- `HUB-16: Hub-level Orchestration Hooks`

### Transitive Core Dependencies
- `CORE-16: Binary Encryption Envelope`
- `CORE-19: DBAL`
- `CORE-04: HTTP Message`
- `CORE-03: Event Dispatcher`

## Architectural Design
- **OnboardingWizard**: A step-by-step UI for setting up new staff accounts and MFA.
- **AccessRequester**: A workflow engine for staff to request temporary or permanent permissions.
- **ProfileManager**: Staff-specific profile settings, including internal communication preferences.
- **SsoConfigurator**: Interface for managing SAML/OIDC connections for internal staff identity providers.

## Integration Strategy
- **Bootstrapping**: Acts as a specialized consumer of `HUB-04`, applying stricter internal security policies.
- **UI Rendering**: Utilizes `HUB-26` Form and Wizard components.
- **Orchestration**: Reports onboarding completion status and security health via `HUB-16` and `HUB-15`.
- **Notifications**: Uses `HUB-12` to send onboarding invitations and security alerts.

## CI Verification Criteria
- **MFA Enforcement**: Must verify that a staff user cannot access the portal without an active MFA challenge (mocked in tests).
- **Audit Completeness**: 100% of staff identity changes must be captured in `HUB-06`.
- **Notification Success**: Onboarding emails must be successfully queued in `HUB-10` during the creation process.

## SemVer Impact
**Minor**. Secures the internal human element of the Sovereign Stack.
