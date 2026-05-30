# PHASE CORE-15: Cache Abstraction

## Tier
Core

## Component Name
PSR-6/16 Unified Cache

## Description
A standardized caching layer implementing both PSR-6 (Pool/Item) for complex operations and PSR-16 (SimpleCache) for common use cases. Supports multiple drivers including File, APCu, and Redis.

## Context7 Research
- **PSR Compliance**: PSR-6 and PSR-16.
- **Serialization**: Uses `serialize/unserialize` with a safety allow-list or JSON for portability.

## Architectural Design
- **CacheManager**: Factory for creating different cache drivers.
- **FileDriver**: Stores cached data in local files.
- **TaggableCache**: (Advanced) Allows grouping cache keys for bulk invalidation.

## Integration Strategy
Depends on `CORE-14` (Filesystem) for the File driver. Used by `CORE-06` (Router) to store compiled route tables.

## CI Verification Criteria
- **Hit Rate Performance**: Reading a cached item must be < 0.05ms.
- **TTL Accuracy**: Items must be correctly evicted within 1 second of their expiration time.

## SemVer Impact
**Minor**. Essential for production performance.