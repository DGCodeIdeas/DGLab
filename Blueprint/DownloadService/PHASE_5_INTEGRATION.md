# Phase 5: Global Integration

## Goal
To seamlessly transition the entire application to the new Download Service and provide a unified API for future development.

## Key Actions

### 1. Route Refactoring
- Replace the legacy `/download/{filename}` closure in `routes/web.php` with a controller-based route handled by the `DownloadManager`.
- Implement a fallback mechanism or redirection for old-style URLs during the transition.

### 2. Service-to-Service Migration
- Update internal services like `EpubFontChanger` to use the `DownloadManager` for returning file results.
- Example:
  ```php
  // Before
  return ['file' => $tempFile];

  // After
  return [
      'download_url' => Download::temporaryUrl($tempFile, 60),
      'file_id' => $fileId
  ];
  ```

### 3. Developer SDK/Helpers
- Provide a global `download()` helper function.
- Create a "Download" facade for easy access to the manager.
- Comprehensive documentation in the README/Blueprints for other developers.

### 4. Deprecation & Cleanup
- Formally deprecate direct access to the `storage/uploads/temp` directory from controllers.
- Remove old helper functions and redundant code once the migration is verified.

## Technical Requirements
- **Documentation**: Update the framework's core documentation.
- **Testing**: Integration tests covering the end-to-end flow from file generation to secured download.

## Success Criteria
- No remaining direct calls to `Response::download()` with raw paths in the application.
- All services successfully generate and return secure, trackable download URLs.
- Zero downtime during the transition from the legacy route to the new system.
