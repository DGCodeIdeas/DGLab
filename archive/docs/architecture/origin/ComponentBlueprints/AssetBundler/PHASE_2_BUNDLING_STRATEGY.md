# Phase 2: Bundling & Manifesting Strategy

## Goal
To implement the bundling logic that concatenates multiple JavaScript files into a single optimized bundle, and to manage asset versioning using content-based hashing and a central manifest file.

## Key Features
- **File Concatenation**: Combining files in the correct order determined by the dependency graph.
- **Header Injection**: Each bundled file should be wrapped with a comment header indicating its source path for easier debugging.
- **Content Hashing**: Calculating an MD5 hash of the bundled content to generate a unique, cache-bustable filename (e.g., `app.a1b2c3d4.js`).
- **Manifest Generation**: Creating and maintaining a JSON manifest (`public/assets/manifest.json`) that maps source file paths to their latest compiled versions.
- **Automatic Cleanup**: A mechanism to remove old hashed versions from `storage/cache/assets/` to prevent disk bloat.

## Technical Requirements
- **Primary Class**: `DGLab\Services\Webpack\Bundler`
- **Interface**: `BundlerInterface` with `bundle(array $files, string $outputPath): string`.
- **Atomic Writes**: Ensuring files are written atomically to avoid serving partial or corrupted assets during high-concurrency requests.
- **Manifest Path Mapping**: The manifest should store relative paths from `resources/js/` as keys and full URLs as values.

## Success Criteria
- A single JavaScript file containing all dependencies is generated.
- The `manifest.json` correctly identifies the hashed filename.
- The application can use the manifest to include the correct `<script>` tag in the HTML.
