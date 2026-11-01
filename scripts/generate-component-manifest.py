#!/usr/bin/env python3
"""Generate component manifest for inventory-driven CI discovery."""
import json
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]

# Tier directories to scan (post-migration architecture)
TIER_DIRS = [
    "Core",
    "Hub",
    "Bridge",
    "Spokes/Internal",
    "Spokes/External",
]

# Component status definitions
# "implemented" - has source code and tests, should be tested
# "planned" - directory exists but may be placeholder
# "external" - external integration, special handling

# Known implemented components (have phpunit.xml.dist and tests/)
IMPLEMENTED_COMPONENTS = {
    "Core/Config": {"tier": "core", "status": "implemented"},
    "Core/Container": {"tier": "core", "status": "implemented"},
    "Core/Crypto": {"tier": "core", "status": "implemented"},
    "Core/Database": {"tier": "core", "status": "implemented"},
    "Core/ErrorHandler": {"tier": "core", "status": "implemented"},
    "Core/EventDispatcher": {"tier": "core", "status": "implemented"},
    "Core/Filesystem": {"tier": "core", "status": "implemented"},
    "Core/HttpMessage": {"tier": "core", "status": "implemented"},
    "Core/Kernel": {"tier": "core", "status": "implemented"},
    "Core/Logger": {"tier": "core", "status": "implemented"},
    "Core/Middleware": {"tier": "core", "status": "implemented"},
    "Core/NeoSpx": {"tier": "core", "status": "implemented"},
    "Core/Router": {"tier": "core", "status": "implemented"},
    "Core/View": {"tier": "core", "status": "implemented"},
    "Hub/Config": {"tier": "hub", "status": "implemented"},
    "Hub/Identity": {"tier": "hub", "status": "implemented"},
    "Bridge/Vanguard": {"tier": "bridge", "status": "implemented"},
    "Spokes/Internal/Codex": {"tier": "spoke-internal", "status": "implemented"},
    "Spokes/Internal/Lms": {"tier": "spoke-internal", "status": "implemented"},
    "Spokes/Internal/Showcase": {"tier": "spoke-internal", "status": "implemented"},
    "Spokes/External/Canvas": {"tier": "spoke-external", "status": "implemented"},
    "orchestrator": {"tier": "orchestrator", "status": "implemented"},
}


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

            rel_path = str(component_dir.relative_to(REPO_ROOT))

            # Determine status
            if rel_path in IMPLEMENTED_COMPONENTS:
                status = IMPLEMENTED_COMPONENTS[rel_path]["status"]
                tier = IMPLEMENTED_COMPONENTS[rel_path]["tier"]
            else:
                # Check if it has phpunit.xml.dist (sign of implementation)
                has_phpunit = (component_dir / "phpunit.xml.dist").exists()
                has_tests = (component_dir / "tests").exists()
                if has_phpunit and has_tests:
                    status = "implemented"
                elif has_phpunit or has_tests:
                    status = "partial"
                else:
                    status = "planned"
                tier = tier_dir.lower().replace("/", "-")

            # Check for phpunit.xml.dist
            has_phpunit = (component_dir / "phpunit.xml.dist").exists()

            # Check for tests directory
            has_tests = (component_dir / "tests").exists()

            # Count source files
            src_files = list(component_dir.rglob("*.php"))
            src_file_count = sum(1 for f in src_files if "tests" not in f.parts)

            # Count test files
            test_file_count = sum(1 for _ in (component_dir / "tests").rglob("*.php")) if has_tests else 0

            # Check for migrations
            has_migrations = (component_dir / "migrations").exists()

            # Determine namespace from root composer.json
            namespace = ""
            composer_json = REPO_ROOT / "composer.json"
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
                "tier": tier,
                "status": status,
                "namespace": namespace,
                "has_phpunit": has_phpunit,
                "has_tests": has_tests,
                "has_migrations": has_migrations,
                "src_file_count": src_file_count,
                "test_file_count": test_file_count,
            })

    return components


def main():
    components = discover_components()

    # Output manifest
    manifest = {
        "generated_at": "auto-generated",
        "total_components": len(components),
        "implemented": len([c for c in components if c["status"] == "implemented"]),
        "planned": len([c for c in components if c["status"] == "planned"]),
        "partial": len([c for c in components if c["status"] == "partial"]),
        "components": components,
    }

    # Write JSON manifest
    output_path = REPO_ROOT / "component-manifest.json"
    output_path.write_text(json.dumps(manifest, indent=2))

    # Also output for CI consumption (bash arrays)
    print("COMPONENT_PATHS=(")
    for c in components:
        if c["status"] == "implemented" and c["has_phpunit"]:
            print(f'  "{c["path"]}"')
    print(")")

    print("\nTIER_PATHS=(")
    tiers = set(c["tier"] for c in components)
    for t in sorted(tiers):
        print(f'  "{t}"')
    print(")")

    print(f"\nTotal components: {len(components)}")
    print(f"  Implemented: {manifest['implemented']}")
    print(f"  Planned: {manifest['planned']}")
    print(f"  Partial: {manifest['partial']}")

    return 0


if __name__ == "__main__":
    sys.exit(main())