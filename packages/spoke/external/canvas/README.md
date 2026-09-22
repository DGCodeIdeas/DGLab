# sovereign-stack/spoke-canvas

**ESPOKE-01: Sovereign Canvas.**

Public-facing CMS and delivery engine. Renders high-performance, SEO-optimized pages from content fetched via BRIDGE-01 (never directly from ISPOKE-09). Includes stale-while-revalidate caching fallback for Bridge unavailability.

## Status

**Shipped at depth 2** (Milestone 0, Task 31). v0.1.0.0 — `ContentDeliveryInterface` and `SeoValidationInterface` are frozen per SDLC-AGRD §2.1.

Depth-2 implementation uses in-memory content storage and simple HTML rendering. When HUB-02 (Cache), HUB-03 (Assets), HUB-26 (UI Components), and CORE-11/12 (SuperPHP) land, the renderer is upgraded — the interfaces are unchanged.

## Reference

- Blueprint: `Architecture/Spoke/External/ESPOKE-01.md`

## License

MIT.
