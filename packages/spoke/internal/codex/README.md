# sovereign-stack/spoke-codex

**ISPOKE-09: Sovereign Codex.**

Collaborative knowledge base and wiki for staff. Markdown editing, version history, and the public/internal boundary enforcement that BRIDGE-01 depends on.

## Status

**Shipped at depth 2** (Milestone 0, Task 30). v0.1.0.0 — `KnowledgeBaseInterface` is frozen per SDLC-AGRD §2.1.

Depth-2 implementation uses in-memory storage. When CORE-19 (DBAL) lands, the storage is replaced with a DBAL-backed implementation — the interface and DocumentManager logic are unchanged.

## Reference

- Blueprint: `Architecture/Spoke/Internal/ISPOKE-09.md`

## License

MIT.
