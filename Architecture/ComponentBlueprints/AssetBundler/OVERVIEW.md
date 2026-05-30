# Asset Bundler: Pure-PHP Frontend Pipeline

## Project Vision
To provide a completely Node-free, high-performance asset bundling solution as a core service within the DGLab framework. This service enables developers to use modern JavaScript (ES6 modules) and CSS while maintaining a zero-dependency production environment.

## Core Pillars
1. **Node-Free Development**: No npm, webpack, or vite required. All bundling is handled by PHP.
2. **Dependency Resolution**: Automatic tracking of JS imports and CSS @imports.
3. **Meticulous Observability**: Built-in source mapping and build performance metrics.
4. **Security**: Content Hash based cache-busting for all generated assets.

## Phased Implementation Roadmap

### [Phase 1: Dependency Resolution Engine (COMPLETED)](PHASE_1_DEPENDENCY_RESOLUTION.md)
Building the regex-based resolver to parse imports and build dependency graphs.

### [Phase 2: Bundling Strategy & Manifests (COMPLETED)](PHASE_2_BUNDLING_STRATEGY.md)
Implementing the ordered concatenation logic and the dynamic manifest.json generator.

### [Phase 3: Minification & Mangling (COMPLETED)](PHASE_3_MINIFICATION_MANGLING.md)
Integrating pure-PHP minification for production assets.

### [Phase 4: Source Maps & DX (COMPLETED)](PHASE_4_SOURCE_MAPS.md)
Generating VLQ-encoded source maps for bundled JS and CSS to ensure easy debugging.

### [Phase 5: Integration & Node Removal (COMPLETED)](PHASE_5_INTEGRATION_REMOVAL.md)
Full framework integration, decommissioning node_modules, and providing the build-assets CLI tool.
