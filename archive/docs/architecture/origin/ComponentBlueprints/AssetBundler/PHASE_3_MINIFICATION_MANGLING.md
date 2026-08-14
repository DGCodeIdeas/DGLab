# Phase 3: Minification & Mangling (PHP-based Optimization)

## Goal
To implement advanced asset optimization techniques, including minification and variable mangling, using pure PHP. This replaces the functionality previously provided by `terser`.

## Key Features
- **Token-based Minification**: Parsing JavaScript into tokens to safely remove whitespace and comments while preserving functional syntax.
- **Variable & Function Mangling**: An algorithm to rename local variables and internal functions to short, single-character strings (e.g., `userCount` -> `a`).
- **Scope Analysis**: Identifying the scope of variables to ensure mangling doesn't break references or global variables.
- **CSS Minification**: Enhancing the current `matthiasmullie/minify` capabilities with more aggressive optimization rules (e.g., color code shortening, property merging).
- **Optimization Levels**: Configurable levels of optimization (e.g., `simple`, `mangled`, `aggressive`).

## Technical Requirements
- **Primary Class**: `DGLab\Services\Webpack\Optimizer`
- **Interface**: `OptimizerInterface` with `optimize(string $content, string $type, array $options): string`.
- **Mangle Table**: Maintaining a mapping of original names to mangled names during the optimization process.
- **Exception List**: A list of reserved words (JS keywords) and global properties that must NOT be mangled.

## Success Criteria
- The output JavaScript and CSS files are significantly smaller than the source files.
- Mangled code remains fully functional in all target browsers.
- No Node.js binaries or libraries are required for this process.
