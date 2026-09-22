# External Spokes W1-W5 Implementation Plan

## Overview
Address all 5 External Spoke weaknesses (Tier Score: 85/100, 15 approved blueprints) by creating comprehensive architectural documentation in `/docs/external-spokes/`.

## Document Structure Conventions
Each document follows the established DGLab pattern:
- Navigation & cross-reference header block
- Mermaid architecture diagrams (flowchart, sequenceDiagram, stateDiagram)
- PHP code examples using `Sovereign\Bridge\` and `Sovereign\External\` namespaces
- Configuration reference blocks
- Performance baselines and metrics tables
- Monitoring, alerting, and runbook sections

## Document Breakdown

### W1: `bridge-high-availability.md`
- **Active-Active Replication**: Multiple Bridge instances with shared state, Redis-backed routing/rate-limit synchronization, instance groups
- **Failover Strategy**: Automatic failover via load balancer health checks, passive/active health probes, recovery sequence
- **State Replication**: Routing rule propagation via Redis pub/sub, rate-limit counter merging (CRDT approach), sticky session considerations
- **External Load Balancer**: Config blocks for AWS ALB, GCP LB, K8s Ingress with health check paths
- **Mermaid Diagrams**: Active-active topology, failover sequence, state replication flow
- **Split-Brain Prevention**: Fencing tokens, lease-based worker claims

### W2: `rate-limiting-strategy.md`
- **Tiered Rate Limits**: Per-user/app configuration, premium tiers (Free/Starter/Pro/Enterprise), config reference
- **Burst Allowance**: Token bucket algorithm with configurable burst ratios (±25% above sustained), PHP implementation
- **Backpressure Signaling**: 429 responses with `Retry-After`, `X-RateLimit-*` headers, quota reset info
- **Rate Limit Dashboard**: Grafana panels for per-consumer usage, headroom visualization, Prometheus metrics
- **Mermaid Diagrams**: Token bucket flow, rate limit decision tree

### W3: `api-versioning-strategy.md`
- **Versioning Decision Record**: ADR-006 format evaluating URL path vs header vs semver, with rationale for URL path choice
- **Deprecation Policy**: 12-month minimum support window, sunset timeline (announce→deprecate→sunset→EOL), communication plan
- **Migration Guides**: v1→v2 migration path with breaking changes list, upgrade tooling
- **OpenAPI Integration**: OpenAPI 3.1 per-version specs, SDK generation pipelines, versioned docs
- **Mermaid Diagrams**: Version lifecycle state diagram, deprecation timeline

### W4: `developer-portal.md`
- **Portal Features**: API key management (create/rotate/revoke), request logging viewer, webhook management (register/retry/logs), quota monitoring dashboard
- **Integration Framework**: OAuth 2.0 flows (auth code, client credentials), webhook signature validation (HMAC-SHA256), idempotency keys
- **Starter Kits**: Zapier, IFTTT, Slack, Discord, Webhook.site, Custom REST client patterns
- **Community Governance**: SLA tiers, deprecation policy for community integrations, review/approval workflow
- **Mermaid Diagrams**: Portal architecture, OAuth flow sequence, webhook delivery lifecycle

### W5: `seo-optimization.md`
- **Server-Side Rendering**: Hybrid SSR strategy for public pages, PHP-based SSR with caching, fallback to CSR for authenticated content
- **Structured Data**: JSON-LD schema.org types (Article, Product, Organization, BreadcrumbList), OpenGraph/Facebook, Twitter Cards
- **Dynamic Sitemap**: Content-driven sitemap generation with lastmod/frequency/priority, sitemap index for large sites
- **Core Web Vitals**: LCP, FID/INP, CLS optimization strategies, monitoring via CrUX API + RUM metrics
- **Mermaid Diagrams**: SSR rendering pipeline, structured data placement, sitemap generation flow

## Success Metrics Verification

| # | Metric | Target | Where Documented |
|---|--------|--------|-----------------|
| 1 | Automatic failover | <2 seconds | W1 Failover Strategy |
| 2 | Burst allowance | ±25% above sustained | W2 Burst Allowance |
| 3 | API version support window | 12 months minimum | W3 Deprecation Policy |
| 4 | Integration setup time | <50% custom impl | W4 Starter Kits |
| 5 | Public content indexable | 100% | W5 SSR + Structured Data |

## Implementation Order
Documents are independent and can be created in any order. Suggested order:
1. W1 (Bridge HA) — highest operational criticality
2. W2 (Rate Limiting) — directly affects API consumers
3. W3 (API Versioning) — drives developer experience
4. W4 (Developer Portal) — builds on versioning context
5. W5 (SEO) — separate concern, lowest dependency

## Files to Create
```
docs/external-spokes/
  ├── bridge-high-availability.md    (W1)
  ├── rate-limiting-strategy.md      (W2)
  ├── api-versioning-strategy.md     (W3)
  ├── developer-portal.md            (W4)
  └── seo-optimization.md            (W5)