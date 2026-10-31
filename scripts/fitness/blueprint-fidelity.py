#!/usr/bin/env python3
"""FF-03: Blueprint-Fidelity Structural Diff — compares declared vs actual package structure."""
import json
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]

# Tier directories to scan (post-migration architecture)
TIER_DIRS = [
    "Core",
    "Hub",
    "Bridge",
    "Spokes/Internal",
    "Spokes/External",
]

# Components that are implemented and should have tests
IMPLEMENTED_COMPONENTS = [
    "Core/Config",
    "Core/Container",
    "Core/Crypto",
    "Core/Database",
    "Core/ErrorHandler",
    "Core/EventDispatcher",
    "Core/Filesystem",
    "Core/HttpMessage",
    "Core/Kernel",
    "Core/Logger",
    "Core/Middleware",
    "Core/NeoSpx",
    "Core/Router",
    "Core/View",
    "Hub/Config",
    "Hub/Identity",
    "Bridge/Vanguard",
    "Spokes/Internal/Codex",
    "Spokes/Internal/Lms",
    "Spokes/Internal/Showcase",
    "Spokes/External/Canvas",
    "orchestrator",
]


def discover_components():
    """Walk tier directories and discover actual component structure."""
    components = []

    for tier_dir in TIER_DIRS:
        tier_path = REPO_ROOT / tier_dir
        if not tier_path.exists():
            continue

        for component_dir in sorted(tier_path.iterdir()):
            if not component_dir.is_dir():
                continue

            # Skip if not an implemented component
            rel_path = str(component_dir.relative_to(REPO_ROOT))
            if rel_path not in IMPLEMENTED_COMPONENTS:
                continue

            # Check for test directory
            tests_dir = component_dir / "tests"
            has_tests = tests_dir.exists()

            # Check for phpunit.xml.dist
            phpunit_config = component_dir / "phpunit.xml.dist"
            has_phpunit = phpunit_config.exists()

            # Count PHP source files (directly in component dir, no src/ subdir)
            src_files = list(component_dir.rglob("*.php"))
            # Exclude test files
            src_file_count = sum(1 for f in src_files if "tests" not in f.parts)

            # Count test files
            test_file_count = sum(1 for _ in tests_dir.rglob("*.php")) if has_tests else 0

            # Check for migrations
            migrations_dir = component_dir / "migrations"
            has_migrations = migrations_dir.exists()

            # Determine namespace from root composer.json
            composer_json = REPO_ROOT / "composer.json"
            namespace = ""
            if composer_json.exists():
                try:
                    data = json.loads(composer_json.read_text())
                    autoload = data.get("autoload", {}).get("psr-4", {})
                    for ns, path in autoload.items():
                        if isinstance(path, list):
                            path = path[0]
                        if path.rstrip("/") == rel_path:
                            namespace = ns.rstrip("\\")
                            break
                except Exception:
                    pass

            components.append({
                "path": rel_path,
                "namespace": namespace or "?",
                "has_tests": has_tests,
                "has_phpunit": has_phpunit,
                "has_migrations": has_migrations,
                "src_file_count": src_file_count,
                "test_file_count": test_file_count,
            })

    return components


def check_blueprint_fidelity():
    violations = []
    components = discover_components()

    # Check that every component has tests/ directory and phpunit.xml.dist
    for comp in components:
        if not comp["has_tests"]:
            violations.append({
                "rule": "BLUEPRINT-001",
                "component": comp["path"],
                "expected": "tests/ directory",
                "actual": "MISSING",
                "status": "MISSING",
                "message": f"Component {comp['path']} has no tests/ directory"
            })
        if not comp["has_phpunit"]:
            violations.append({
                "rule": "BLUEPRINT-002",
                "component": comp["path"],
                "expected": "phpunit.xml.dist",
                "actual": "MISSING",
                "status": "MISSING",
                "message": f"Component {comp['path']} has no phpunit.xml.dist"
            })

        # Verify no src/ subdirectory (architecture rule)
        component_dir = REPO_ROOT / comp["path"]
        src_dir = component_dir / "src"
        if src_dir.exists():
            violations.append({
                "rule": "BLUEPRINT-003",
                "component": comp["path"],
                "expected": "no src/ subdirectory (source files directly in component)",
                "actual": "src/ directory exists",
                "status": "VIOLATION",
                "message": f"Component {comp['path']} has forbidden src/ subdirectory"
            })

    # Check for missing implemented components (discovered vs expected)
    discovered_paths = {c["path"] for c in components}
    missing = set(IMPLEMENTED_COMPONENTS) - discovered_paths
    for m in missing:
        violations.append({
            "rule": "BLUEPRINT-004",
            "component": m,
            "expected": "component directory with tests/",
            "actual": "MISSING",
            "status": "MISSING",
            "message": f"Expected component not found: {m}"
        })

    return violations, components


def main():
    violations, components = check_blueprint_fidelity()
    result = {
        "check": "FF-03 Blueprint Fidelity",
        "components_discovered": len(components),
        "violations": violations,
        "violation_count": len(violations),
        "component_summary": [
            {
                "path": c["path"],
                "namespace": c["namespace"],
                "src_files": c["src_file_count"],
                "test_files": c["test_file_count"],
                "has_tests": c["has_tests"],
                "has_phpunit": c["has_phpunit"],
                "has_migrations": c["has_migrations"],
            }
            for c in components
        ],
    }
    print(json.dumps(result, indent=2))

    if violations:
        print("\n=== FF-03 Blueprint Fidelity Report ===", file=sys.stderr)
        for v in violations:
            print(f"[{v['rule']}] {v['component']}", file=sys.stderr)
            print(f"  expected: {v['expected']}", file=sys.stderr)
            print(f"  actual:   {v['actual']}", file=sys.stderr)
            print(f"  status:   {v['status']}", file=sys.stderr)
        return 1

    print(f"\n✅ FF-03: All {len(components)} components have tests/ + phpunit.xml.dist + no src/.", file=sys.stderr)
    return 0


if __name__ == "__main__":
    sys.exit(main())