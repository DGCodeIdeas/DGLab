# Phase 1: Core Storage & Driver System

## Goal
To establish the foundational architecture for the Download Service, allowing for polymorphic file storage and retrieval via a driver-based system.

## Key Components

### 1. DownloadServiceInterface
The primary contract for interacting with the download system.
- `public function download(string $path, ?string $name = null, array $headers = []): Response`
- `public function stream(string $path, ?string $name = null): Response`
- `public function exists(string $path): bool`
- `public function getUrl(string $path, ?DateTime $expiration = null): string`

### 2. StorageDriverInterface
Abstraction for various storage backends.
- `public function read(string $path): string`
- `public function readStream(string $path)`
- `public function has(string $path): bool`
- `public function getAbsolutePath(string $path): string`
- `public function delete(string $path): bool`
- `public function getMetadata(string $path): array`

### 3. LocalDriver
Implementation for local filesystem storage.
- Configured via `config/filesystems.php`.
- Supports root path prefixing for security.
- Uses PHP's native `finfo` for MIME type detection.

### 4. DownloadManager
The central hub for driver orchestration.
- Registers drivers (Local, S3, etc.).
- Resolves the "default" driver from configuration.
- Proxies calls to the active driver.

## Technical Requirements
- **Namespace**: `DGLab\Services\Download`
- **Configuration**: `config/download.php` to define drivers and default settings.
- **Exceptions**: `FileNotFoundException`, `StorageException`, `AccessDeniedException`.

## Success Criteria
- Ability to request a file via the `DownloadManager` and receive a valid `Response` object without knowing the underlying storage implementation.
- Graceful handling of "File not found" errors with descriptive exceptions instead of raw 404 arrays.
