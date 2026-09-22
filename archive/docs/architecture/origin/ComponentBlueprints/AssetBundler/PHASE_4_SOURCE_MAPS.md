# Phase 4: Source Map Generation (V3 Implementation)

## Goal
To implement the Source Map Revision 3 specification in pure PHP, allowing developers to debug the original source code in the browser while running optimized/mangled assets.

## Key Features
- **VLQ (Variable-Length Quantity) Encoding**: Implementation of the base64-based VLQ encoding required for Source Map mappings.
- **Mapping Tracker**: A mechanism to track the position (line and column) of each token in the original source and its corresponding position in the compiled/mangled output.
- **JSON Generator**: Producing a valid Source Map JSON file that includes the `version`, `sources`, `names`, and `mappings` fields.
- **Source Map URL Injection**: Automatically appending the `//# sourceMappingURL=` comment to the end of the bundled JavaScript file.

## Technical Requirements
- **Primary Class**: `DGLab\Services\Webpack\SourceMapGenerator`
- **Interface**: `SourceMapInterface` with `generate(): string`.
- **Coordinate Transformation**: Converting file offsets to line/column pairs during the bundling and minification process.
- **Support for Multiple Sources**: Correctly handling multiple source files within a single bundle's source map.

## Success Criteria
- Browser developer tools (Chrome, Firefox, etc.) correctly display the original source code when debugging bundled assets.
- Source maps are generated with minimal performance overhead during the build process.
- The generated maps are valid according to the official V3 specification.
