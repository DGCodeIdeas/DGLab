# sovereign-stack/core-error-handler

**CORE-08: Global Error & Exception Handler.**

Converts PHP errors (warnings, notices) to `ErrorExceptions`, captures uncaught exceptions, logs faults via PSR-3, and renders CLI/JSON/HTML responses with security-aware stack-trace sanitization.

## Reference

- Blueprint: `Architecture/Core/CORE-08.md`

## Status

Shipped (Milestone 0, Task 22). v1.0.0 — interface frozen per SDLC-AGRD §2.1.

## License

MIT.
