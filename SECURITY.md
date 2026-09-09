# Security Policy

## Supported versions

DGLab is in active development. Only the latest `main` branch is supported.

| Version | Supported |
|---------|-----------|
| main    | ✅        |
| tagged releases | ✅ |

## Reporting a vulnerability

If you discover a security vulnerability, please **do not** open a public issue. Instead:

1. Email the maintainer directly.
2. Include a description of the vulnerability, steps to reproduce, and potential impact.
3. You will receive a response within 48 hours.

## Security properties

DGLab's Core packages implement the following security invariants:

- **Immutable HTTP messages (CORE-04):** every `with*()` method returns a new instance. Header injection (CWE-113/CWE-93) is prevented at the value-object layer — `withHeader()` rejects names or values containing `\r` or `\n`.
- **Stream resource lifecycle (CORE-04):** `Stream::__destruct()` calls `close()` unconditionally. No path exists where a Stream is GC'd while holding an open resource. Under ADR-017 (Fiber-based runtime), this prevents resource accumulation on long-running FrankenPHP workers.
- **Uploaded file path traversal (CORE-04):** `UploadedFile::moveTo()` rejects target paths containing `/../` or `/./` (CWE-22).
- **Middleware pipeline immutability (CORE-05):** the pipeline is frozen after the first `handle()` call. No middleware can be injected mid-request.
- **Router parameter decoding (CORE-06):** route parameters are URL-decoded exactly once via `rawurldecode()`. Double-decoding attacks are prevented by design.
- **Route pattern validation (CORE-06):** path-traversal patterns (`/../`, `/./`) in route definitions are rejected at compile time.

## Dependency security

- All dependencies are pinned to major versions (`psr/http-message: ^2.0`, etc.)
- `composer audit` runs on every CI install
- The Loom's `--require-ci-green` flag prevents tagging when CI is red
