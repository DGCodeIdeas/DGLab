# PHASE CORE-04: HTTP Message & Factory

## Tier
Core

## Component Name
PSR-7 HTTP Primitives

## Description
Implementation of immutable HTTP Request and Response objects. This provides the standardized "language" for all web-based communication in the Sovereign Stack, ensuring compatibility with any PSR-compliant client or server.

## Context7 Research
- **PSR Compliance**: PSR-7 (HTTP Message Interfaces) and PSR-17 (HTTP Factories).
- **Reference**: `/php-fig/http-message` for interface definitions.
- **Immutability**: Every modification (e.g., `withHeader`) returns a new instance.

## Architectural Design
- **Request**: Implements `ServerRequestInterface`.
- **Response**: Implements `ResponseInterface`.
- **Stream**: Implements `StreamInterface` for memory-efficient body handling (using `php://temp`).
- **UploadedFile**: Handles file uploads with standardized metadata access.

## Integration Strategy
Used by `CORE-05` (Middleware) and `CORE-06` (Router). It forms the input/output contract for every controller in the Hub/Spoke tiers.

## CI Verification Criteria
- **Compliance**: Must pass `http-interop/http-factory-tests`.
- **Memory Safety**: Large file uploads must be handled via streams without loading the entire content into RAM.

## SemVer Impact
**Minor**. Establishes the web contract but does not change existing logic (in a fresh reboot context).