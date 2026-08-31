# PHASE ISPOKE-02: Internal Developer Portal and Documentation Hub

## Tier
Internal Spoke (Staff-only Application)

## Component Name
Sovereign Forge Portal

## Description
A dedicated portal for internal developers and engineers. It hosts the system's documentation (including these blueprints), API specifications, code standards, and developer onboarding guides. It also provides interactive tools for testing Hub API versioning and exploring the GraphQL schema.

## Sequencing Rationale
Follows the Admin Panel. Essential for scaling the engineering team and maintaining architectural alignment.

## Context7 Research
### Direct Hub Dependencies
- `HUB-11: File Storage Abstraction` (for doc files)
- `HUB-14: Search Abstraction Layer`
- `HUB-24: GraphQL Schema Registry`
- `HUB-26: Shared UI Component Library`
- `HUB-28: Hub API Versioning Strategy`
- `HUB-15: Health Check`
- `HUB-16: Hub-level Orchestration Hooks`

### Transitive Core Dependencies
- `CORE-14: Filesystem Abstraction`
- `CORE-13: CLI Engine`
- `CORE-20: Developer CLI Toolchain`
- `CORE-03: Event Dispatcher`

## Architectural Design
- **DocEngine**: Parses Markdown blueprints and technical specs into a searchable web interface.
- **ApiExplorer**: An interactive UI for exploring the Hub's REST and GraphQL APIs.
- **StandardsGuide**: A living document section that pulls PSR-12 and internal coding standards directly from the repo.
- **GraphiQL_PHP**: A pure PHP-rendered implementation of the GraphQL explorer using `HUB-24`.

## Integration Strategy
- **Bootstrapping**: Leverages `HUB-11` to read architectural documents from the `/Architecture` directory.
- **UI Rendering**: Uses `HUB-26` Documentation layouts and components.
- **Orchestration**: Reports deployment status and doc-build health via `HUB-16` and `HUB-15`.
- **Search**: Hooks into `HUB-14` to provide full-text search across all internal documentation.

## CI Verification Criteria
- **Markdown Integrity**: Every blueprint file must be correctly rendered without broken internal links.
- **Search Latency**: Searching across 81 blueprints must return results in < 100ms.
- **API Sync**: The GraphQL explorer must always reflect the current schema registered in `HUB-24`.

## SemVer Impact
**Minor**. Enhances developer productivity and architectural governance.
