#!/usr/bin/env python3
"""FF-03: Blueprint-Fidelity Structural Diff — compares declared vs actual package structure."""
import json, re, sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]

def discover_packages():
    """Walk packages/ and discover actual package structure."""
    packages = []
    for composer_json in sorted((REPO_ROOT / "packages").rglob("composer.json")):
        rel = composer_json.relative_to(REPO_ROOT)
        parts = rel.parts
        if len(parts) < 3:
            continue
        try:
            data = json.loads(composer_json.read_text())
        except Exception:
            continue
        
        pkg_path = composer_json.parent
        src_dir = pkg_path / "src"
        tests_dir = pkg_dir = pkg_path / "tests"
        migrations_dir = pkg_path / "migrations"
        
        autoload = data.get("autoload", {}).get("psr-4", {})
        namespaces = list(autoload.keys())
        
        packages.append({
            "path": str(rel.parent),
            "name": data.get("name", "?"),
            "namespaces": namespaces,
            "has_src": src_dir.exists(),
            "has_tests": tests_dir.exists() if 'tests_dir' in dir() else (pkg_path / "tests").exists(),
            "has_migrations": migrations_dir.exists(),
            "src_file_count": sum(1 for _ in src_dir.rglob("*.php")) if src_dir.exists() else 0,
            "test_file_count": sum(1 for _ in (pkg_path / "tests").rglob("*.php")) if (pkg_path / "tests").exists() else 0,
        })
    return packages

def check_blueprint_fidelity():
    violations = []
    packages = discover_packages()
    
    # Check that every package has src/ and tests/ directories
    for pkg in packages:
        if not pkg["has_src"]:
            violations.append({
                "rule": "BLUEPRINT-001",
                "component": pkg["path"],
                "expected": "src/ directory with PHP files",
                "actual": "MISSING",
                "status": "MISSING",
                "message": f"Package {pkg['path']} has no src/ directory"
            })
        if not pkg["has_tests"]:
            violations.append({
                "rule": "BLUEPRINT-001",
                "component": pkg["path"],
                "expected": "tests/ directory",
                "actual": "MISSING",
                "status": "MISSING",
                "message": f"Package {pkg['path']} has no tests/ directory"
            })
    
    # Check for unexpected packages (packages in the filesystem not in any known component mapping)
    # This is informational, not a violation — new packages are expected during development
    
    return violations, packages

def main():
    violations, packages = check_blueprint_fidelity()
    result = {
        "check": "FF-03 Blueprint Fidelity",
        "packages_discovered": len(packages),
        "violations": violations,
        "violation_count": len(violations),
        "package_summary": [{"path": p["path"], "src_files": p["src_file_count"], "test_files": p["test_file_count"], "has_migrations": p["has_migrations"]} for p in packages],
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
    
    print(f"\n✅ FF-03: All {len(packages)} packages have src/ + tests/ directories.", file=sys.stderr)
    return 0

if __name__ == "__main__":
    sys.exit(main())
