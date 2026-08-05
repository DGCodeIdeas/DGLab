# PHASE HUB-11: File Storage Abstraction (Cloud/Multi-disk)

## Tier
Hub (Shared Services)

## Resolves
Clarifies this component's identity against the `HUB-11`/`HUB-10` mislabel found while rewriting
`HUB-10.md` — **this** is Cloud Storage, not Queue; nothing in this file needed correcting on its own
account, but the disambiguation is recorded here too so both files are unambiguous read in either
order.

## Component Name
Sovereign Cloud Storage

## Description
Extends `CORE-14` with cloud filesystem support (S3, R2, GCS) and a multi-disk management layer,
letting applications switch between local and cloud storage via configuration alone.

## Build Status
🔴 **Blocked** on `CORE-14` (Filesystem) and `CORE-10` (Config) — neither implemented.

## Dependency Status
- **Upward:** `CORE-14`, `CORE-10`. *(Matches taxonomy.)*
- **Downward:** `HUB-03` (deploys compiled assets to CDN-backed storage), `HUB-18` (Media Forge
  reads/writes through this), `HUB-23` (Reporter stores export files here).

## Architectural Design
- **StorageManager** — resolves named disks (`avatars`, `exports`) to drivers.
- **S3Driver** — `FilesystemInterface` implementation for S3-compatible APIs.
- **UrlSigner** — temporary, time-limited URLs for private cloud files.
- **DiskSync** — migrates files between disks (e.g., Local → S3).

```php
namespace SovereignStack\Hub\Contracts;

use SovereignStack\Core\Filesystem\FilesystemInterface;

interface StorageInterface
{
    public function disk(string $name): FilesystemInterface;
    public function url(string $path): string;
    public function temporaryUrl(string $path, \DateTimeInterface $expiration): string;
}
```

## Integration Strategy
- **Upward:** built on `CORE-14`.
- **Downward:** Spoke applications use `StorageInterface` for user-generated content, agnostic of the
  underlying physical storage.
- **Service:** injected into `HUB-03` for CDN-backed asset deployment.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Driver interchangeability | Integration test: write via `Local`, read via `S3` against a fixture bucket (e.g., MinIO in CI), assert byte-identical content. |
| Signed-URL expiry precision | Test a signed URL at `expiration - 1s` (valid) and `expiration + 1s` (invalid) against real clock time, not a mocked clock, to catch off-by-one/clock-skew bugs a mock would hide. |
| Streaming memory bound | Integration test uploading a real (or realistically-sized synthetic) 500MB file; assert peak PHP memory via `memory_get_peak_usage()` stays under the stated bound — measured, not asserted from confidence in stream usage alone. |

## CI Verification Criteria
- Driver-interchangeability test against a real fixture backend (MinIO or equivalent), blocking.
- Signed-URL boundary test against real time, blocking.
- Streaming memory test with actual measured peak reported.

## SemVer Impact
**Minor.** Extends storage capabilities without breaking the Core interface.
