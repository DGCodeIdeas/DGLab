# sovereign-stack/core-kernel

**CORE-18: The Sovereign Kernel.**

Orchestrates the request lifecycle from boot to terminate. Wires all Core-tier components (DI, Events, HTTP, Middleware, Router, Config, Logger, Error Handler) into the Pulse round-trip: `ServerRequest` → middleware → router → controller → `Response`.

This is the capstone of Milestone 0 — when CORE-18 ships, the walking skeleton is complete and the project reaches MUWV (Minimally Usable Working Version).

## Reference

- Blueprint: `Architecture/Core/CORE-18.md`

## Status

Shipped (Milestone 0, Task 25). v0.1.0.0 — interface frozen per SDLC-AGRD §2.1.

## License

MIT.
