# Phase 1: JS Dependency Resolution (Lexical Analysis)

## Goal
To implement a PHP-based engine capable of analyzing JavaScript files to discover their internal dependencies via `import` and `require` statements. This will allow for the automatic generation of a dependency graph for any given entry point.

## Key Features
- **Regex-based Scanner**: A robust lexical scanner to detect and extract dependency paths from common JS module syntax:
  - `import ... from 'path';`
  - `import 'path';`
  - `require('path');`
- **Path Normalization**: Resolving relative paths (e.g., `./utils.js`) and parent directory paths (e.g., `../core/app.js`) to absolute filesystem paths within the `resources/js/` directory.
- **Dependency Graph Storage**: A data structure (`DependencyGraph` class) to track nodes (files) and edges (dependencies), ensuring no duplicate files are included and detecting circular dependencies.
- **Extension Awareness**: Automatically appending `.js` if not explicitly provided in the import statement.

## Technical Requirements
- **Primary Class**: `DGLab\Services\Webpack\DependencyResolver`
- **Interface**: `DependencyResolverInterface` with methods for `resolve(string $entryPath): array`.
- **Recursive Traversal**: The resolver should recursively scan each discovered file to build a full list of required modules for the bundle.

## Success Criteria
- Given `resources/js/app.js`, the resolver should return a flat, correctly ordered list of all required files (e.g., `[\"resources/js/vendor/jquery.js\", \"resources/js/app.js\"]`).
