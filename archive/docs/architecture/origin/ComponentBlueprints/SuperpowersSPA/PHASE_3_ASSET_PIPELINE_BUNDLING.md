# Phase 3: Asset Pipeline I - Pure-PHP Bundling

## Goal
Finalizing the `WebpackService` to handle JavaScript bundling and content-based hashing without Node.js.

## Requirements

### 1. Bundle Concatenation
- **Logic**: Use the dependency graph from `WebpackService` Phase 1 to determine file order.
- **Support**: Wrap each module in a scope-safe closure or use ES modules if target browsers allow.
- **Logic**: Concatenate all resolved files into a single string for bundling.

### 2. Content-Based Hashing
- **Logic**: Use `md5(content)` to generate a 12-character hash for the output filename (e.g., `app.a1b2c3d4.js`).
- **Persistence**: Store the mapping in `public/assets/manifest.json`.

### 3. Manifest Generation
- **Format**: `{"app.js": "app.a1b2c3d4.js"}`.
- **Logic**: Ensure the `AssetService` (used in the `asset()` helper) reads from the manifest to resolve the correct URL.

### 4. Cleanup
- **Logic**: Automatically delete old hashed files when a new version is generated.

## Success Criteria
- [ ] Running `php cli/webpack.php build` generates a hashed JS file in `public/assets/`.
- [ ] `public/assets/manifest.json` is created/updated.
- [ ] The `asset('app.js')` helper correctly resolves to the hashed filename.
