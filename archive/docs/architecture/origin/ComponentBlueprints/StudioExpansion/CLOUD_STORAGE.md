# Studio Expansion: Cloud Storage & MediaLibrary 2.0

## 1. Storage Abstraction (StorageManager)
The `StorageManager` acts as the central orchestrator for all file operations, providing a driver-based interface (`StorageAdapter`) to support multiple backends.

### 1.1 Storage Drivers
- **Local (Default)**: Existing filesystem (`storage/app/`).
- **Google Drive (Cloud Option)**:
    - **OAuth2 Flow**: User authorizes DGLab to access their Drive (scope: `drive.file`).
    - **Token Storage**: Encrypted refresh tokens stored in the `oauth_tokens` table.
    - **Directory Structure**: Root folder named `DGLab_Studio/{user_id}/`.
- **S3-Compatible (Enterprise Option)**: Support for AWS S3, Cloudflare R2, or MinIO.

### 1.2 StorageManager Interface
- `put(string $path, string|resource $contents, array $options = []): bool`
- `get(string $path): string`
- `stream(string $path): resource`
- `delete(string $path): bool`
- `list(string $directory): array` (Metadata: size, type, last_modified)

## 2. MediaLibrary Service Integration
The `MediaLibraryService` is refactored to consume the `StorageManager`.

### 2.1 Media Management
- **Primary Storage**: User-configurable per workspace (Local, Google Drive, or S3).
- **Workspace Isolation**: Each tenant/user workspace points to a specific `root_path` on the selected driver.
- **Import/Export**:
    - "Import from Google Drive": Stream file from Google Drive to Local temp, then process.
    - "Export to S3": Move processed EPUB/Manga from Local to S3 storage.

### 2.2 Performance & Caching
- **Streaming**: For large EPUBs (MangaScript), the service streams directly between drivers to avoid PHP memory exhaustion.
- **Local Cache**: Frequently accessed assets (e.g., currently editing EPUB, Google Fonts) are cached in `storage/temp/cache/`.
- **Rate Limiting**: Implementation of exponential backoff for Google Drive API requests.

## 3. Storage Configuration UI
- **Component**: `<s:ui:storage-settings />`.
- **Workflow**:
    1. User selects storage provider.
    2. If Google Drive: Redirect to OAuth2 consent screen.
    3. If S3: Input Access Key, Secret Key, and Bucket.
    4. "Test Connection" button with real-time feedback in the Live Console.

## 4. Verification
- [ ] Verify that a file uploaded to MangaScript is correctly stored in the user's Google Drive (`DGLab_Studio/...`) when that driver is active.
- [ ] Test storage driver failover: Log an error to the Live Console if the selected cloud provider is unreachable.
- [ ] Confirm that large file streaming does not exceed the PHP `memory_limit`.
