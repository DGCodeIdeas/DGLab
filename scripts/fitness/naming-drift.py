#!/usr/bin/env python3
"""FF-02: Naming Drift Detection — verifies canonical component identifiers match implementation."""
import json
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]

# Canonical component mapping aligned with Architecture/INDEX.md §2
# Each entry: component_id -> {tier_dir, namespace, component_name}
CANONICAL_COMPONENTS = {
    "CORE-02": {"tier_dir": "Core/Container", "namespace": "SovereignStack\\Core\\Container", "name": "Container"},
    "CORE-03": {"tier_dir": "Core/EventDispatcher", "namespace": "SovereignStack\\Core\\EventDispatcher", "name": "EventDispatcher"},
    "CORE-04": {"tier_dir": "Core/HttpMessage", "namespace": "SovereignStack\\Core\\Http", "name": "HttpMessage"},
    "CORE-05": {"tier_dir": "Core/Middleware", "namespace": "SovereignStack\\Core\\Http", "name": "Middleware"},
    "CORE-06": {"tier_dir": "Core/Router", "namespace": "SovereignStack\\Core\\Router", "name": "Router"},
    "CORE-08": {"tier_dir": "Core/ErrorHandler", "namespace": "SovereignStack\\Core\\ErrorHandler", "name": "ErrorHandler"},
    "CORE-09": {"tier_dir": "Core/Logger", "namespace": "SovereignStack\\Core\\Logger", "name": "Logger"},
    "CORE-10": {"tier_dir": "Core/Config", "namespace": "SovereignStack\\Core\\Config", "name": "Config"},
    "CORE-14": {"tier_dir": "Core/Filesystem", "namespace": "SovereignStack\\Core\\Filesystem", "name": "Filesystem"},
    "CORE-16": {"tier_dir": "Core/Crypto", "namespace": "SovereignStack\\Core\\Crypto", "name": "Crypto"},
    "CORE-18": {"tier_dir": "Core/Kernel", "namespace": "SovereignStack\\Core\\Kernel", "name": "Kernel"},
    "CORE-19": {"tier_dir": "Core/Database", "namespace": "SovereignStack\\Core\\Database", "name": "Database"},
    "HUB-04": {"tier_dir": "Hub/Identity", "namespace": "SovereignStack\\Hub\\Identity", "name": "Identity"},
    "HUB-30": {"tier_dir": "Hub/Config", "namespace": "SovereignStack\\Hub\\Config", "name": "Config"},
    "BRIDGE-01": {"tier_dir": "Bridge/Vanguard", "namespace": "SovereignStack\\Bridge", "name": "Vanguard"},
    "ISPOKE-09": {"tier_dir": "Spokes/Internal/Codex", "namespace": "SovereignStack\\Internal\\Codex", "name": "Codex"},
    "ESPOKE-01": {"tier_dir": "Spokes/External/Canvas", "namespace": "SovereignStack\\External\\Canvas", "name": "Canvas"},
}

# Additional implemented components that may not have formal IDs yet
ADDITIONAL_IMPLEMENTED = [
    "Core/NeoSpx",
    "Core/View",
    "Spokes/Internal/Showcase",
    "Spokes/Internal/Lms",
    "orchestrator",
]


def load_root_composer_autoload():
    """Load PSR-4 autoload mappings from root composer.json."""
    composer_json = REPO_ROOT / "composer.json"
    if not composer_json.exists():
        return {}
    try:
        data = json.loads(composer_json.read_text())
        autoload = data.get("autoload", {}).get("psr-4", {})
        # Normalize: ensure trailing backslash
        normalized = {}
        for ns, path in autoload.items():
            if isinstance(path, list):
                path = path[0]
            normalized[ns.rstrip("\\") + "\\"] = path
        return normalized
    except Exception:
        return {}


def check_naming_drift():
    violations = []
    autoload = load_root_composer_autoload()

    for component_id, canonical in CANONICAL_COMPONENTS.items():
        tier_dir = canonical["tier_dir"]
        expected_ns = canonical["namespace"]

        # Check component directory exists
        component_path = REPO_ROOT / tier_dir
        if not component_path.exists():
            violations.append({
                "rule": "NAME-001",
                "component": component_id,
                "canonical": tier_dir,
                "found": "MISSING",
                "message": f"Component directory not found: {tier_dir}"
            })
            continue

        # Check namespace in root composer.json autoload
        ns_key = expected_ns + "\\"
        if ns_key not in autoload:
            # Try exact match without trailing backslash
            if expected_ns not in autoload:
                violations.append({
                    "rule": "NAME-001",
                    "component": component_id,
                    "canonical": expected_ns,
                    "found": list(autoload.keys()),
                    "references": [str(REPO_ROOT / "composer.json")],
                    "message": f"Namespace not found in root composer.json autoload for {component_id}"
                })
                continue

        # Verify the path in autoload matches the actual directory
        actual_path = autoload[ns_key]
        if isinstance(actual_path, list):
            actual_path = actual_path[0]
        # Path in composer.json is relative to repo root
        expected_rel_path = tier_dir + "/"
        if not actual_path.endswith(expected_rel_path):
            violations.append({
                "rule": "NAME-002",
                "component": component_id,
                "canonical": expected_rel_path,
                "found": actual_path,
                "references": [str(REPO_ROOT / "composer.json")],
                "message": f"Autoload path mismatch for {component_id}: expected {expected_rel_path}, got {actual_path}"
            })

    # Check additional implemented components have autoload entries
    for tier_dir in ADDITIONAL_IMPLEMENTED:
        component_path = REPO_ROOT / tier_dir
        if not component_path.exists():
            violations.append({
                "rule": "NAME-003",
                "component": tier_dir,
                "canonical": tier_dir,
                "found": "MISSING",
                "message": f"Additional implemented component directory not found: {tier_dir}"
            })
            continue

    return violations


def main():
    violations = check_naming_drift()
    result = {
        "check": "FF-02 Naming Drift",
        "components_checked": len(CANONICAL_COMPONENTS),
        "additional_components_checked": len(ADDITIONAL_IMPLEMENTED),
        "violations": violations,
        "violation_count": len(violations),
    }
    print(json.dumps(result, indent=2))

    if violations:
        print("\n=== FF-02 Naming Drift Report ===", file=sys.stderr)
        for v in violations:
            print(f"[{v['rule']}] component: {v['component']}", file=sys.stderr)
            print(f"  canonical: {v['canonical']}", file=sys.stderr)
            print(f"  found:     {v['found']}", file=sys.stderr)
        return 1

    print(f"\n✅ FF-02: No naming drift ({len(CANONICAL_COMPONENTS)} canonical + {len(ADDITIONAL_IMPLEMENTED)} additional components checked).", file=sys.stderr)
    return 0


if __name__ == "__main__":
    sys.exit(main())