# Phase 2: Security & Tokenization

## Goal
To secure file delivery by implementing a token-based access system that prevents direct file access, path traversal, and unauthorized hotlinking.

## Key Features

### 1. Download Tokens (`download_tokens` table)
A database-backed system for generating temporary, cryptographically secure download links.
- **Fields**: `id`, `token` (hashed), `file_path`, `driver`, `expires_at`, `max_uses`, `use_count`, `ip_address`, `created_at`.
- **One-time Use**: Optional flag to invalidate the token immediately after a successful download.

### 2. Signed URLs
Generation of HMAC-signed URLs that include expiration timestamps.
- Ensures the URL parameters (like file path and expiry) haven't been tampered with.
- Eliminates the need for database lookups for public-but-temporary links.

### 3. IP and Middleware Integration
- **IP Binding**: Optionally restrict a download token to the IP address that generated it.
- **Access Control**: Integration with existing `AuthMiddleware` to ensure only authenticated users can download specific resources.
- **Referrer Validation**: Blocking requests from unauthorized external domains.

### 4. Path Sanitization
- Strict validation of all requested paths to prevent `../` directory traversal attacks.
- Root-directory jail per driver.

## Technical Requirements
- **Helper**: `Download::temporaryUrl(string $path, int $minutes)`
- **Controller**: A dedicated `DownloadController` to handle the `/dl/{token}` route.

## Success Criteria
- Attempting to access a file without a valid token or signature results in a `403 Forbidden` error.
- Expired tokens or signatures are automatically rejected.
- Tokens can be limited to a single successful download.
