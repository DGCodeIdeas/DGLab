# sovereign-stack/bridge-vanguard

**BRIDGE-01: The Vanguard.**

PSR-15 boundary middleware enforcing default-deny contract routing, WAF inspection, and DTO transformation at the SovereignStack tier boundary. The Vanguard is the outermost middleware on the external-facing pipeline — every public request crosses it before reaching the Kernel's router.

## Status

**Shipped at depth 2** (Milestone 0, Task 29). v0.1.0.0 — `BoundaryContractInterface` and `DtoTransformerInterface` are frozen per SDLC-AGRD §2.1.

Depth-2 scope: the Vanguard enforces contract lookup (default-deny: unregistered routes return 403) and WAF inspection (SQLi/XSS/path-traversal regex). JWT verification, rate limiting, network forwarding, and HUB-06 audit are pass-through stubs (log to PSR-3). When HUB-02/HUB-04/HUB-06/CORE-16 land, the stubs are replaced with real implementations — the interfaces and chain order are unchanged.

## Reference

- Blueprint: `Architecture/Spoke/Bridge/BRIDGE-01.md`

## License

MIT.
