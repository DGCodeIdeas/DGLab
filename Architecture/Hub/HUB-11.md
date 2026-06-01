# PHASE HUB-11: File Storage Abstraction (Cloud/Multi-disk)

## Tier
Hub

## Component Name
Sovereign Cloud Storage

## Description
An extension of the `CORE-14` Storage Driver that adds support for cloud-based filesystems (AWS S3, Cloudflare R2, Google Cloud Storage) and introduces a multi-disk management layer. It allows applications to transparently switch between local and cloud storage via configuration.

## Context7 Research
- **Depends on**: `CORE-14: Filesystem`, `CORE-10: Config`.
- **Drivers**: Implements S3-compatible drivers using pure PHP stream wrappers or thin SDK integrations.
- **Patterns**: Strategy Pattern (Drivers), Factory Pattern (Disk resolution).

## Architectural Design
- **StorageManager**: Resolves named disks (e.g., `avatars`, `exports`) to specific drivers.
- **S3Driver**: Implementation of `FilesystemInterface` targeting S3-compatible APIs.
- **UrlSigner**: Utility for generating temporary, time-limited URLs for private cloud files.
- **DiskSync**: A utility for migrating files between different disks (e.g., Local to S3).

### Configuration Example
```php
'disks' => [
    'local' => ['driver' => 'local', 'root' => '/storage'],
    's3' => [
        'driver' => 's3',
        'key' => '...',
        'secret' => '...',
        'bucket' => 'sovereign-assets',
    ],
]
```

## Interface Contracts

### StorageInterface (Hub Extension)
```php
namespace Sovereign\Hub\Contracts;

use Sovereign\Core\Filesystem\FilesystemInterface;

interface StorageInterface
{
    /**
     * Get a specific disk instance.
     */
    public function disk(string $name): FilesystemInterface;

    /**
     * Generate a public URL for a file.
     */
    public function url(string $path): string;

    /**
     * Generate a temporary signed URL.
     */
    public function temporaryUrl(string $path, \DateTimeInterface $expiration): string;
}
```

## Integration Strategy
- **Upward**: Built on top of the `CORE-14` interface.
- **Downward**: Spoke applications use `StorageInterface` for all user-generated content, remaining agnostic of the underlying physical storage.
- **Service**: Injected into `HUB-03` (Asset Pipeline) to allow deploying compiled assets directly to a CDN-backed S3 bucket.

## CI Verification Criteria
- **Driver Interchangeability**: A file written via the `Local` driver must be readable via the `S3` driver (given shared underlying storage or sync).
- **Security**: Signed URLs must expire exactly at the designated time and become invalid.
- **Streaming**: Must verify that uploading a 500MB file uses < 50MB of RAM by leveraging PHP streams.

## SemVer Impact
**Minor**. Extends storage capabilities without breaking the Core interface.
