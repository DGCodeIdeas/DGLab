# PHASE HUB-18: Media Processing Coordination Service

## Tier
Hub (Shared Services)

## Component Name
Sovereign Media Forge

## Description
A coordinated service for handling media assets (images, video, documents). It handles thumbnail generation, image optimization, video transcoding requests, and metadata extraction. It acts as a bridge between `HUB-11` (Storage) and specialized processing drivers.

## Sequencing Rationale
Relies on `HUB-11` for file persistence and `HUB-10` for background processing. Essential for Spokes that handle user-generated content.

## Context7 Research
- **Direct Hub Dependencies**: `HUB-11: File Storage`, `HUB-10: Queue & Job Dispatcher`, `HUB-02: Shared Cache`.
- **Transitive Core Dependencies**: `CORE-14: Filesystem`, `CORE-19: DBAL`, `CORE-15: Cache Abstraction`.
- **Engines**: Leverages `GD` or `Imagick` via pure PHP wrappers. Avoids Node.js-based sharp/ffmpeg-js.

## Architectural Design
- **MediaCoordinator**: High-level API for requesting processing tasks.
- **ImageProcessor**: Handles resizing, cropping, and format conversion (WebP/AVIF).
- **MetadataExtractor**: Extracts EXIF data, dimensions, and mime-types.
- **TransformationPipeline**: Allows chaining operations (e.g., "resize" -> "optimize" -> "watermark").

### Pipeline Example
```php
$forge->process($file)
    ->resize(800, 600)
    ->format('webp')
    ->store('thumbnails');
```

## Interface Contracts

### MediaForgeInterface
```php
namespace Sovereign\Hub\Contracts;

interface MediaForgeInterface
{
    /**
     * Start a processing pipeline for a stored file.
     */
    public function process(string $path): MediaPipelineInterface;

    /**
     * Get metadata for a file.
     */
    public function getMetadata(string $path): array;
}
```

## Integration Strategy
- **Upward**: Consumes `HUB-11` for reading/writing assets.
- **Downward**: Spoke applications use the Forge to ensure all user uploads are optimized and safely stored.
- **Contract**: Returns a `ProcessedMedia` object containing the new path, dimensions, and checksum.

## CI Verification Criteria
- **Memory Safety**: Processing a 10MB image must not exceed 64MB of PHP memory.
- **Format Support**: Must successfully convert JPEG to WebP and AVIF.
- **Concurrency**: Must handle 10 simultaneous processing jobs via `HUB-10` without disk contention.

## SemVer Impact
**Minor**. Introduces media transformation capabilities.
