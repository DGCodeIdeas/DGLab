# PHP Asset Bundler (Custom Webpack) Blueprint

## Project Vision
To eliminate the dependency on Node.js and npm by implementing a high-performance, pure PHP alternative to Webpack and Terser. This service will handle JavaScript bundling, dependency resolution, minification, mangling, and source map generation entirely within the PHP/Composer ecosystem.

## Architecture
The Asset Bundler will be implemented as a standalone service within the DGLab framework:
- **Service**: `WebpackService` located in `app/Services/`.
- **Configuration**: `config/assets.php` for defining bundle entry points, output paths, and optimization levels.
- **Integration**: The existing `AssetService` will delegate JS/CSS processing to the `WebpackService`.
- **CLI**: A dedicated `cli/webpack.php` command to perform build-time asset optimization.

## Phased Implementation Roadmap

### [Phase 1: Dependency Resolution (COMPLETED)](PHASE_1_DEPENDENCY_RESOLUTION.md)
- Lexical analysis of JS files to identify `import` and `require` statements.
- Recursive dependency graph construction.
- Support for relative and absolute paths within `resources/js/`.

### [Phase 2: Bundling & Manifesting Strategy (PENDING)](PHASE_2_BUNDLING_STRATEGY.md)
- Efficient file concatenation in correct dependency order.
- Content-based hashing (MD5) for immutable cache-busting filenames.
- Dynamic manifest generation (`public/assets/manifest.json`) to map source entry points to compiled assets.

### [Phase 3: Minification & Mangling (PENDING)](PHASE_3_MINIFICATION_MANGLING.md)
- Advanced whitespace and comment removal for JS and CSS.
- Variable and function name mangling (obfuscation) to reduce file size and protect source logic.
- Token-based processing to ensure syntactical correctness after mangling.

### [Phase 4: Source Map Generation (PENDING)](PHASE_4_SOURCE_MAPS.md)
- Implementation of the Source Map Revision 3 proposal in PHP.
- Mapping of compiled/mangled lines and columns back to original source files.
- Automated `//# sourceMappingURL` injection.

### [Phase 5: Integration & Node.js Removal (PENDING)](PHASE_5_INTEGRATION_REMOVAL.md)
- Wiring the `WebpackService` into the application container.
- Updating `AssetService` to utilize the new bundling engine.
- Complete removal of `package.json`, `node_modules/`, and all Node-related CLI scripts.
- Dockerfile and CI/CD optimization for a PHP-only environment.
