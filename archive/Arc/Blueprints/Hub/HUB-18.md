# PHASE HUB-18: Media Processing Coordination Service

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10) and downgrades this blueprint's own self-assessed
maturity claim to match `hub-blueprint-taxonomy.md`, which correctly lists it as **Experimental**, not
production-ready — the original blueprint text didn't carry that caveat anywhere in its own body.

## Component Name
Sovereign Media Forge

## Description
Coordinated media-asset handling: thumbnail generation, image optimization, video transcoding
requests, metadata extraction. Bridges `HUB-11` (Storage) and specialized processing drivers.

## Build Status
🔴 **Blocked** on `HUB-11` (Storage), `HUB-10` (Queue), `HUB-02` (Cache) — none implemented. Per
`hub-taxonomy/hub-blueprint-taxonomy.md`, this blueprint's own maturity is rated **Experimental** —
treat its interfaces as more likely to change than the rest of the Hub tier, and don't build hard
dependencies on `MediaForgeInterface` from Spokes until it's promoted to Beta/Stable.

## Dependency Status
- **Direct Hub:** `HUB-11`, `HUB-10`, `HUB-02`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-14`, `CORE-19`, `CORE-15`.

## Architectural Design
- **MediaCoordinator** — high-level processing-request API.
- **ImageProcessor** — resize/crop/format conversion (WebP/AVIF).
- **MetadataExtractor** — EXIF, dimensions, mime-type.
- **TransformationPipeline** — chainable operations (resize → optimize → watermark).

```php
$forge->process($file)
    ->resize(800, 600)
    ->format('webp')
    ->store('thumbnails');
```

```php
namespace SovereignStack\Hub\Contracts;

interface MediaForgeInterface
{
    public function process(string $path): MediaPipelineInterface;
    public function getMetadata(string $path): array;
}
```

## Integration Strategy
- **Upward:** consumes `HUB-11` for read/write.
- **Downward:** Spoke applications route uploads through the Forge for optimization/safe storage.
- **Engines:** pure-PHP GD/Imagick wrappers — explicitly no Node-based `sharp`/`ffmpeg-js`, consistent
  with the stack's Node-free principle.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Memory safety | Integration test processing a real 10MB fixture image; assert peak memory via `memory_get_peak_usage()` stays under 64MB — measured, not assumed from GD/Imagick being "generally efficient." |
| Format support | Round-trip test: JPEG → WebP and JPEG → AVIF, decode the output and assert valid image data and expected dimensions, not just "no exception thrown." |
| Concurrency | Integration test dispatching 10 simultaneous `HUB-10` processing jobs against a shared fixture disk; assert no corrupted output and no disk-contention errors. |

## CI Verification Criteria
- Memory-bound test with actual measured peak, blocking.
- Format round-trip test (JPEG→WebP, JPEG→AVIF) with output validation, blocking.
- Concurrency test, blocking.
- Any change promoting this blueprint's maturity from Experimental must update
  `01_MASTER_INDEX.md`/`hub-blueprint-taxonomy.md` in the same commit (Governance Rule 1).

## SemVer Impact
**Minor.** Introduces media transformation capabilities — kept Minor rather than Major specifically
because of its Experimental status; don't treat its interface as a stability commitment yet.
