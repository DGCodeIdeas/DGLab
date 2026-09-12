# sovereign-stack/hub-config

**HUB-01: Sovereign Hub Config & Flags.**

Tenant-aware configuration with per-tenant overrides, and feature flag evaluation with deterministic percentage rollouts and A/B/C variant selection.

## Status

**Shipped at depth 2** (Milestone 0, Task 28). v0.1.0.0 — `GlobalConfigInterface`, `FeatureManagerInterface`, and `Context` are frozen per SDLC-AGRD §2.1.

The depth-2 implementation uses in-memory repository stubs (no DBAL/HUB-02 dependencies). When CORE-19 (DBAL) and HUB-02 (Cache) land, the stubs are replaced with real DBAL-backed repositories — the interfaces and evaluation logic are unchanged.

## Reference

- Blueprint: `Architecture/Hub/HUB-01.md`

## License

MIT.
