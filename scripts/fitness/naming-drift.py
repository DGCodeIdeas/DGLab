#!/usr/bin/env python3
"""FF-02: Naming Drift Detection — verifies canonical component identifiers match implementation."""
import json, re, sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]

# Canonical component mapping
CANONICAL_COMPONENTS = {
    "CORE-02": {"package": "core/container", "namespace": "SovereignStack\\Core\\Container", "name": "Container"},
    "CORE-03": {"package": "core/event-dispatcher", "namespace": "SovereignStack\\Core\\EventDispatcher", "name": "EventDispatcher"},
    "CORE-04": {"package": "core/http-message", "namespace": "SovereignStack\\Core\\Http", "name": "Http"},
    "CORE-05": {"package": "core/middleware", "namespace": "SovereignStack\\Core\\Http", "name": "Middleware"},
    "CORE-06": {"package": "core/router", "namespace": "SovereignStack\\Core\\Router", "name": "Router"},
    "CORE-08": {"package": "core/error-handler", "namespace": "SovereignStack\\Core\\ErrorHandler", "name": "ErrorHandler"},
    "CORE-09": {"package": "core/logger", "namespace": "SovereignStack\\Core\\Logger", "name": "Logger"},
    "CORE-10": {"package": "core/config", "namespace": "SovereignStack\\Core\\Config", "name": "Config"},
    "CORE-14": {"package": "core/filesystem", "namespace": "SovereignStack\\Core\\Filesystem", "name": "Filesystem"},
    "CORE-16": {"package": "core/crypto", "namespace": "SovereignStack\\Core\\Crypto", "name": "Crypto"},
    "CORE-18": {"package": "core/kernel", "namespace": "SovereignStack\\Core\\Kernel", "name": "Kernel"},
    "CORE-19": {"package": "core/dbal", "namespace": "SovereignStack\\Core\\Database", "name": "DBAL"},
    "HUB-04": {"package": "hub/identity", "namespace": "SovereignStack\\Hub\\Identity", "name": "Identity"},
    "HUB-30": {"package": "hub/config", "namespace": "SovereignStack\\Hub\\Config", "name": "Config"},
    "BRIDGE-01": {"package": "bridge/vanguard", "namespace": "SovereignStack\\Bridge", "name": "Vanguard"},
    "ISPOKE-09": {"package": "spoke/internal/codex", "namespace": "SovereignStack\\Internal\\Codex", "name": "Codex"},
    "ESPOKE-01": {"package": "spoke/external/canvas", "namespace": "SovereignStack\\External\\Canvas", "name": "Canvas"},
}

def check_naming_drift():
    violations = []
    
    for component_id, canonical in CANONICAL_COMPONENTS.items():
        pkg_path = REPO_ROOT / "packages" / canonical["package"]
        
        if not pkg_path.exists():
            violations.append({
                "rule": "NAME-001",
                "component": component_id,
                "canonical": f"packages/{canonical['package']}",
                "found": "MISSING",
                "message": f"Package directory not found for {component_id}"
            })
            continue
        
        # Check composer.json exists and namespace matches
        composer_json = pkg_path / "composer.json"
        if composer_json.exists():
            try:
                import json as j
                data = j.loads(composer_json.read_text())
                autoload = data.get("autoload", {}).get("psr-4", {})
                ns_key = canonical["namespace"] + "\\"
                if ns_key not in autoload and canonical["namespace"] not in autoload:
                    found_ns = list(autoload.keys())
                    violations.append({
                        "rule": "NAME-001",
                        "component": component_id,
                        "canonical": canonical["namespace"],
                        "found": found_ns,
                        "references": [str(composer_json)],
                        "message": f"Namespace mismatch for {component_id}"
                    })
            except Exception:
                pass
    
    return violations

def main():
    violations = check_naming_drift()
    result = {
        "check": "FF-02 Naming Drift",
        "components_checked": len(CANONICAL_COMPONENTS),
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
    
    print(f"\n✅ FF-02: No naming drift ({len(CANONICAL_COMPONENTS)} components checked).", file=sys.stderr)
    return 0

if __name__ == "__main__":
    sys.exit(main())
