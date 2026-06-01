# DownloadService - Phase 1: Core Storage & Driver System

**Status**: COMPLETED
**Source**: `Blueprint/DownloadService/PHASE_1_CORE_STORAGE.md`

## Objectives
- [ ] based system.
- [ ] `public function download(string $path, ?string $name = null, array $headers = []): Response`
- [ ] `public function stream(string $path, ?string $name = null): Response`
- [ ] `public function exists(string $path): bool`
- [ ] `public function getUrl(string $path, ?DateTime $expiration = null): string`
- [ ] `public function read(string $path): string`
- [ ] `public function readStream(string $path)`
- [ ] `public function has(string $path): bool`
- [ ] `public function getAbsolutePath(string $path): string`
- [ ] `public function delete(string $path): bool`
- [ ] `public function getMetadata(string $path): array`
- [ ] Configured via `config/filesystems.php`.
- [ ] Supports root path prefixing for security.
- [ ] Uses PHP's native `finfo` for MIME type detection.
- [ ] Registers drivers (Local, S3, etc.).
- [ ] Resolves the "default" driver from configuration.
- [ ] Proxies calls to the active driver.
- [ ] Ability to request a file via the `DownloadManager` and receive a valid `Response` object without knowing the underlying storage implementation.
- [ ] Graceful handling of "File not found" errors with descriptive exceptions instead of raw 404 arrays.

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.
