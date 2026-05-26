# Phase 4: Asset Pipeline II - Pure-PHP DX

## Goal
Building Source Map support and achieving 100% Node-free developer experience.

## Requirements

### 1. Minification & Mangling
- **Logic**: Use a pure-PHP JS minifier (or implement a basic token-based one) to remove whitespace and comments.
- **Support**: Variable mangling for local function scopes (Phase 3 of AssetBundler).

### 2. Source Map Generation (Revision 3)
- **Format**: JSON-based V3 source maps.
- **Logic**: Track line and column numbers during concatenation and minification.
- **Logic**: Generate an `app.js.map` file.

### 3. `//# sourceMappingURL` Injection
- **Logic**: Append the correct mapping URL to the bottom of the bundled JS.

### 4. Full Node.js Removal
- **Action**: Delete `package.json`, `package-lock.json`, and the `node_modules/` directory.
- **Action**: Update the Dockerfile to remove Node.js and npm installation steps.
- **Action**: Update the build script (`cli/build-assets.php`) to use `php cli/webpack.php`.

## Success Criteria
- [ ] No Node.js binaries or npm packages remain in the repository.
- [ ] Developer tools (e.g., Chrome DevTools) can map bundled code back to original source files via Source Maps.
- [ ] Assets are minified correctly without syntax errors.
