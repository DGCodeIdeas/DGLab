# Fix in Production — Error Record

Generated: 2026-10-31. Working tree contains the NeoSPX migration (`packages/core/neospx` → `Core/NeoSpx/`) and a resolved conflict-marker fix in `.github/workflows/packages-ci.yml`.

## 1. Resolved during this session

| Item | Location | Status |
|---|---|---|
| Unresolved merge-conflict markers (`<<<<<<<`/`=======`/`>>>>>>>`) in CI matrix | `.github/workflows/packages-ci.yml` (lines 113-126) | Resolved — removed legacy `core/filesystem` duplicate entry, kept migrated `Core/Filesystem` entry |
| NeoSPX orphaned package (no root Composer entry, foreign bootstrap path) | `packages/core/neospx` | Migrated to `Core/NeoSpx/`; root autoload + tests + phpstan + CI matrix updated |

## 2. Open PHPStan errors (pre-existing in NeoSPX lexer)

File: `Core/NeoSpx/Lexer/NeoSPXLexer.php` (level: max). 12 errors, unchanged from baseline (`packages/core/neospx` had 10; migration added 2 unused-variable findings at lines 119 and 956).

| Line | Identifier | Message |
|---|---|---|
| 119 | variable.unused | Variable `$start` is never read |
| 180 | booleanAnd.rightNotBoolean | Only booleans are allowed in &&, int\|false given on the right side |
| 192 | booleanOr.rightNotBoolean | Only booleans are allowed in \|\|, int\|false given on the right side |
| 303 | booleanAnd.rightNotBoolean | Only booleans are allowed in &&, int\|false given on the right side |
| 310 | booleanAnd.rightNotBoolean | Only booleans are allowed in &&, int\|false given on the right side |
| 393 | if.condNotBoolean | Only booleans are allowed in an if condition, int\|false given |
| 398 | booleanAnd.rightNotBoolean | Only booleans are allowed in &&, int\|false given on the right side |
| 752 | booleanAnd.rightNotBoolean | Only booleans are allowed in &&, int\|false given on the right side |
| 768 | booleanAnd.rightNotBoolean | Only booleans are allowed in &&, int\|false given on the right side |
| 955 | booleanAnd.rightNotBoolean | Only booleans are allowed in &&, int\|false given on the right side |
| 956 | variable.unused | Variable `$attrStart` is never read |
| 957 | booleanAnd.rightNotBoolean | Only booleans are allowed in &&, int\|false given on the right side |

## 3. Verification evidence

- `composer dump-autoload`: success.
- `Core/NeoSpx` PHPUnit: 27/27 tests, 74 assertions, OK.
- Autoload resolution: `DGLab\Core\NeoSpx\Lexer\NeoSPXLexer` → `Core/NeoSpx/Lexer/NeoSPXLexer.php`.
- `scripts/check-ci-matrix-completeness.py`: PASS (22/22).
- `scripts/architecture-boundary-lint.py`: exit 0.
- `scripts/fitness/naming-drift.py`: 0 violations.
- `composer validate`: no errors (pre-existing unbound-version warnings only).

## 4. Remaining risks

- 20 components remain under `packages/`; migration not complete.
- NeoSPX PHPStan errors (12) must be fixed in production code, not suppressed.
- No commit has been made; all changes are in the working tree.
