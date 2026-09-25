#!/usr/bin/env python3
"""
M1 — Architecture Boundary Lint

Per SPEC-001 §40 (Phase 1 — Enforce Ring Boundaries) + §41 (Container Service-Locator Rule):

  "The architecture-lint workflow MUST be extended from documentation checks
   into executable dependency analysis."

  "The rule MUST specifically detect container receivers such as
   $container->resolve(...) Container::resolve(...) and equivalent
   container-specific resolution patterns. It MUST NOT generically ban
   ->resolve(...) because legitimate existing methods include
   MiddlewareResolver::resolve(); ContractRegistry::resolve();
   PerRequestHandler resolution; Vanguard resolution."

This script walks the live DGLab repository and enforces:

1. Ring-boundary rules: imports MUST point inward (per ADR-004 tier DAG).
2. Service-locator patterns: container-specific resolution is forbidden
   in production code (constructor-injection only).

Usage:
  python3 /home/z/my-project/scripts/architecture-boundary-lint.py
  php /home/z/my-project/scripts/architecture-boundary-lint.php
  (when PHP 8.4 is available — equivalent PHP version is the canonical form)

Exit code:
  0 = no violations
  1 = violations detected
  2 = checker error

Output:
  Markdown violation report on stderr + JSON summary on stdout.
"""

from __future__ import annotations

import json
import re
import sys
import yaml
from pathlib import Path as PathlibPath
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional

REPO_ROOT = Path("/home/z/my-project")
ALLOWLIST_PATH = REPO_ROOT / ".github" / "architecture-export-allowlist.yaml"


# --- Ring classification -----------------------------------------------------

RING_PATTERNS = [
    # (namespace_prefix, ring_name)
    ("SovereignStack\\Core\\", "core"),
    ("SovereignStack\\Hub\\", "hub"),
    ("SovereignStack\\Internal\\", "spoke-internal"),
    ("SovereignStack\\External\\", "spoke-external"),
    ("SovereignStack\\Spoke\\Internal\\", "spoke-internal"),
    ("SovereignStack\\Spoke\\External\\", "spoke-external"),
    ("SovereignStack\\Bridge\\", "bridge"),
    ("App\\", "outer-rim"),
]

# PSR / PHP / vendor prefixes that are allowed from any ring
ALLOWED_EXTERNAL_PREFIXES = (
    "Psr\\",         # PSR interfaces (PSR-3, 7, 11, 14, 15, 17, etc.)
    "Symfony\\",     # Symfony contracts (where used as PSR substitutes)
    "Monolog\\",     # PSR-3 implementation
    "League\\",      # League utilities (where used)
    "Laminas\\",     # Laminas (Diactoros for PSR-7 fallback)
    "GuzzleHttp\\",  # HTTP client
    # PHP built-ins — no namespace prefix
)

# Path → ring mapping for source files
PATH_RING_MAP = [
    ("packages/core/", "core"),
    ("packages/hub/", "hub"),
    ("packages/spoke/internal/", "spoke-internal"),
    ("packages/spoke/external/", "spoke-external"),
    ("packages/bridge/", "bridge"),
    ("app/", "outer-rim"),
]

# Allowed dependencies per source ring (target rings the source may import from)
ALLOWED_TARGETS = {
    "core": {"core", "external"},  # external = PSR/vendor/PHP
    "hub": {"hub", "core", "external"},
    "spoke-internal": {"spoke-internal", "hub", "core", "external"},
    "spoke-external": {"spoke-external", "hub", "core", "external"},
    "bridge": {"bridge", "hub", "core", "external"},
    "outer-rim": {"outer-rim", "hub", "core", "spoke-internal", "spoke-external", "bridge", "external"},
}


# --- Service-locator patterns (§41, narrowed) -------------------------------

# Container-specific receivers — these are forbidden in production code.
# Patterns deliberately use word-boundary + container-naming to avoid matching
# the legitimate domain-specific resolvers:
#   - MiddlewareResolver::resolve()  (no $container receiver)
#   - ContractRegistry::resolve()    (no $container receiver)
#   - $this->resolver->resolve()     (different receiver variable)
#   - $this->contracts->resolve()    (different receiver variable)
SERVICE_LOCATOR_PATTERNS = [
    # Variable $container (or $c, $di, $containerInterface) calling get/make/resolve/has
    (re.compile(r'\$(container|c|di|containerInterface)\s*->\s*(get|make|resolve|has)\s*\('),
     "container service-locator: ${receiver}->${method}(...)"),

    # Static Container class calling getInstance/resolve
    (re.compile(r'\bContainer\s*::\s*(getInstance|resolve|get|make|has)\s*\('),
     "container static call: Container::${method}(...)"),

    # Laravel-style app() helper (forbidden per SPEC §3)
    (re.compile(r'\bapp\s*\('),
     "Laravel-style app() helper"),
]

# Legitimate callers — these are NOT container service-locators. Listed so the
# checker can audit them against false-positive regressions.
LEGITIMATE_RESOLVE_CALLERS = [
    "packages/core/middleware/src/MiddlewareResolver.php",
    "packages/core/middleware/src/MiddlewareResolverInterface.php",
    "packages/core/middleware/src/PerRequestHandler.php",
    "packages/core/middleware/src/MiddlewarePipelineInterface.php",
    "packages/bridge/vanguard/src/Vanguard.php",
    "packages/bridge/vanguard/src/ContractRegistry.php",
]


# --- Violation types --------------------------------------------------------

@dataclass
class Violation:
    rule_id: str
    source_file: str
    source_ring: str
    target: str
    target_ring: str
    rule_violated: str

    def to_dict(self) -> dict:
        return {
            "rule_id": self.rule_id,
            "source_file": self.source_file,
            "source_ring": self.source_ring,
            "target": self.target,
            "target_ring": self.target_ring,
            "rule_violated": self.rule_violated,
        }


@dataclass
class ScanResult:
    files_scanned: int = 0
    imports_scanned: int = 0
    violations: list[Violation] = field(default_factory=list)
    legitimate_callers_seen: list[str] = field(default_factory=list)


# --- Ring classification helpers --------------------------------------------

def ring_for_namespace(namespace: str) -> str:
    """Classify a fully-qualified namespace into a ring."""
    if not namespace:
        return "external"

    # Strip leading backslash
    ns = namespace.lstrip("\\")

    # Check ring patterns
    for prefix, ring in RING_PATTERNS:
        if ns.startswith(prefix):
            return ring

    # PSR/vendor/PHP external — allowed
    return "external"


def ring_for_path(file_path: Path) -> Optional[str]:
    """Classify a source file path into a ring."""
    rel = str(file_path.relative_to(REPO_ROOT))
    for prefix, ring in PATH_RING_MAP:
        if rel.startswith(prefix):
            return ring
    return None  # not in a tier we check (e.g., scripts/, anvil/, docs/)


# --- PHP `use` statement parsing --------------------------------------------

# Match: use Some\Namespace\ClassName;  OR  use Some\Namespace\ClassName as Alias;
USE_RE = re.compile(
    r'^\s*use\s+(?!function|const)([A-Za-z_][A-Za-z_0-9\\\\]*)\s*(?:as\s+\w+)?\s*;',
    re.MULTILINE
)

# Match: use Some\Namespace\{Class1, Class2, Class3};
USE_GROUP_RE = re.compile(
    r'^\s*use\s+([A-Za-z_][A-Za-z_0-9\\\\]*)\\\\\{([^}]+)\}\s*;',
    re.MULTILINE
)


def parse_use_statements(content: str) -> list[str]:
    """Extract fully-qualified class names from `use` statements in PHP code."""
    imports = []

    # Simple `use Foo\Bar\Baz;` and `use Foo\Bar\Baz as Alias;`
    for m in USE_RE.finditer(content):
        imports.append(m.group(1))

    # Grouped `use Foo\Bar\{Baz1, Baz2};`
    for m in USE_GROUP_RE.finditer(content):
        prefix = m.group(1)
        for item in m.group(2).split(","):
            item = item.strip()
            # Strip possible "as Alias" suffix
            item = re.sub(r"\s+as\s+\w+", "", item)
            if item:
                imports.append(prefix + "\\" + item)

    return imports


# --- Service-locator pattern detection ---------------------------------------

def find_service_locator_violations(content: str, source_file: Path) -> list[tuple[int, str, str]]:
    """
    Find container service-locator usage in production code.
    Returns list of (line_number, target_pattern, description) tuples.
    """
    findings: list[tuple[int, str, str]] = []
    lines = content.split("\n")

    for line_num, line in enumerate(lines, start=1):
        # Skip comments
        stripped = line.lstrip()
        if stripped.startswith("//") or stripped.startswith("*") or stripped.startswith("/*"):
            continue

        for pattern, description in SERVICE_LOCATOR_PATTERNS:
            if pattern.search(line):
                findings.append((line_num, line.strip(), description))
                break  # don't double-report the same line

    return findings


# --- Main scan logic ---------------------------------------------------------



# --- Export allow-list (per SPEC §41 + Composition Principle) ---

def load_export_allowlist() -> dict[str, set[str]]:
    """
    Load the export allow-list from .github/architecture-export-allowlist.yaml.
    Returns a dict mapping package namespace prefix -> set of allowed class names.
    Packages without entries are not enforced.
    """
    if not ALLOWLIST_PATH.is_file():
        return {}
    data = yaml.safe_load(ALLOWLIST_PATH.read_text()) or {}
    packages = data.get("packages", {})
    result = {}
    for ns, config in packages.items():
        public_surface = config.get("public_surface", [])
        if public_surface:
            result[ns] = set(public_surface)
    return result


def check_export_violation(
    import_name: str,
    source_file: str,
    allowlist: dict[str, set[str]],
) -> Optional[tuple[str, str, str]]:
    """
    Check if an import violates the export allow-list.
    Returns (rule_id, target, rule_violated) if violation, None if OK.
    Only checks CROSS-PACKAGE imports — intra-package imports are always allowed.
    """
    if not allowlist:
        return None

    # Find which package the import belongs to
    target_ns = None
    for ns in allowlist:
        if import_name.startswith(ns + "\\") or import_name == ns:
            target_ns = ns
            break

    if target_ns is None:
        return None

    # If the import is in the public surface, it's allowed
    if import_name in allowlist[target_ns]:
        return None

    # Check if the source file is in the SAME package (intra-package import)
    # Use the last namespace component as the package name match.
    # e.g., SovereignStack\Hub\Identity -> "identity" -> check if "identity" in source path
    # e.g., SovereignStack\Spoke\Showcase -> "showcase" -> check if "showcase" in source path
    ns_parts = target_ns.split("\\")
    package_name = ns_parts[-1].lower() if ns_parts else ""
    if package_name != "" and package_name in source_file.lower():
        return None

    # Cross-package import of non-exported symbol — VIOLATION
    return (
        "ARCH-EXPORT-001",
        import_name,
        f"Import of non-exported symbol from {target_ns}: {import_name} "
        f"(per SPEC §41: only symbols in the export allow-list may be imported by consumers)",
    )



def is_production_source(file_path: Path) -> bool:
    """Determine if a PHP file is production code (not tests, not vendor, not scripts)."""
    rel = str(file_path.relative_to(REPO_ROOT))
    if "/tests/" in rel or rel.startswith("tests/"):
        return False
    if "/vendor/" in rel or rel.startswith("vendor/"):
        return False
    if "/ci/" in rel:
        return False
    if "/Fixtures/" in rel:
        return False
    if file_path.name.startswith("Test") or file_path.name.endswith("Test.php"):
        return False
    if file_path.name in ("bootstrap.php", "run.php"):
        return False
    # Production source: under packages/*/src/ or app/
    return ("/src/" in rel) or rel.startswith("app/")


def scan_file(file_path: Path, result: ScanResult, allowlist: dict[str, set[str]] | None = None) -> None:
    """Scan a single PHP file for ring-boundary and service-locator violations."""
    source_ring = ring_for_path(file_path) or "external"
    if source_ring not in ALLOWED_TARGETS:
        # Not a tier we check (e.g., scripts/, docs/)
        return

    try:
        content = file_path.read_text(encoding="utf-8", errors="replace")
    except Exception:
        return

    result.files_scanned += 1
    rel = str(file_path.relative_to(REPO_ROOT))

    # --- Ring-boundary check: parse `use` statements ---
    imports = parse_use_statements(content)
    result.imports_scanned += len(imports)

    allowed = ALLOWED_TARGETS[source_ring]

    for imp in imports:
        target_ring = ring_for_namespace(imp)

        # Same ring or explicitly allowed → OK
        if target_ring == source_ring or target_ring == "external":
            continue

        # Cross-ring import — is it allowed?
        if target_ring in allowed:
            continue

        # Forbidden direction — record violation
        violation = Violation(
            rule_id="ARCH-BOUNDARY-001",
            source_file=rel,
            source_ring=source_ring,
            target=imp,
            target_ring=target_ring,
            rule_violated=f"{source_ring} MUST NOT depend on {target_ring} (per SPEC-001 §40 / ADR-004 tier-enforcement DAG)",
        )
        result.violations.append(violation)

    # --- Export allow-list check (per SPEC §41) ---
    if allowlist:
        for imp in imports:
            export_violation = check_export_violation(imp, rel, allowlist)
            if export_violation:
                rule_id, target, rule = export_violation
                result.violations.append(Violation(
                    rule_id=rule_id,
                    source_file=rel,
                    source_ring=source_ring,
                    target=target,
                    target_ring="(non-exported)",
                    rule_violated=rule,
                ))

    # --- Service-locator check ---
    if "tests/" in rel or "/Fixtures/" in rel:
        return  # don't flag service-locator in test code

    locator_findings = find_service_locator_violations(content, file_path)
    for line_num, line_text, description in locator_findings:
        # Check if this is a legitimate caller
        if rel in LEGITIMATE_RESOLVE_CALLERS:
            result.legitimate_callers_seen.append(f"{rel}:{line_num}")
            continue

        # Skip if the matched line is the method DEFINITION (not a call)
        # — e.g., `public function resolve(...)` is fine
        if "function resolve" in line_text or "function get" in line_text or "function make" in line_text:
            continue

        violation = Violation(
            rule_id="ARCH-LOCATOR-001",
            source_file=rel,
            source_ring=source_ring,
            target=f"line {line_num}: {line_text}",
            target_ring="(service locator)",
            rule_violated=f"production code MUST NOT use container service-locator (per SPEC-001 §41 / §3): {description}",
        )
        result.violations.append(violation)


def scan_repository() -> ScanResult:
    """Walk the repository and scan every production PHP file."""
    result = ScanResult()
    allowlist = load_export_allowlist()

    # Scan packages/{core,hub,spoke,bridge}/*/src/
    for tier in ["core", "hub", "spoke/internal", "spoke/external", "bridge"]:
        tier_path = REPO_ROOT / "packages" / tier
        if not tier_path.is_dir():
            continue
        for php_file in tier_path.rglob("*.php"):
            if "/src/" in str(php_file.relative_to(REPO_ROOT)):
                scan_file(php_file, result, allowlist)

    # Scan app/
    app_path = REPO_ROOT / "app"
    if app_path.is_dir():
        for php_file in app_path.rglob("*.php"):
            scan_file(php_file, result)

    return result


def main() -> int:
    result = scan_repository()

    # Emit JSON summary to stdout
    summary = {
        "files_scanned": result.files_scanned,
        "imports_scanned": result.imports_scanned,
        "violations_count": len(result.violations),
        "violations": [v.to_dict() for v in result.violations],
        "legitimate_callers_seen": result.legitimate_callers_seen,
        "legitimate_callers_expected": LEGITIMATE_RESOLVE_CALLERS,
        "allowlist_packages_enforced": list(allowlist.keys()) if "allowlist" in dir() else [],
    }
    print(json.dumps(summary, indent=2))

    # Emit human-readable report to stderr
    print("\n=== Architecture Boundary Lint Report ===\n", file=sys.stderr)
    print(f"Files scanned:      {result.files_scanned}", file=sys.stderr)
    print(f"Imports scanned:    {result.imports_scanned}", file=sys.stderr)
    print(f"Violations:         {len(result.violations)}", file=sys.stderr)
    print(f"Legitimate callers seen: {len(result.legitimate_callers_seen)} / {len(LEGITIMATE_RESOLVE_CALLERS)} expected", file=sys.stderr)

    if result.violations:
        print("\n--- Violations ---\n", file=sys.stderr)
        for v in result.violations:
            print(f"[{v.rule_id}] {v.source_file}", file=sys.stderr)
            print(f"  source ring:   {v.source_ring}", file=sys.stderr)
            print(f"  target:        {v.target}", file=sys.stderr)
            print(f"  target ring:   {v.target_ring}", file=sys.stderr)
            print(f"  rule:          {v.rule_violated}", file=sys.stderr)
            print("", file=sys.stderr)

    if not result.violations:
        print("\n✅ No violations. Architecture boundary rules pass.", file=sys.stderr)

    # Exit code: 0 = no violations, 1 = violations, 2 = error
    return 0 if not result.violations else 1


if __name__ == "__main__":
    sys.exit(main())
