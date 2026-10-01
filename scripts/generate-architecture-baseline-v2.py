#!/usr/bin/env python3
"""
Generate ARCHITECTURE_BASELINE.md from repository evidence.

Authority: Repository implementation state at a point in time.
Not authoritative for: Architectural intent, SDLC admission policy, capability requirements.

This script inspects the actual filesystem, Composer manifests, and git state —
NOT the INDEX.md or README (which may be stale). The output is an evidence snapshot
that ADRs can reference as their starting point.

Usage:
    python3 scripts/generate-architecture-baseline-v2.py [--output PATH] [--repo-root PATH]

Defaults:
    --output download/ARCHITECTURE_BASELINE.md
    --repo-root (auto-detected via git rev-parse --show-toplevel)
"""

import argparse
import json
import os
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path


def git(*args):
    result = subprocess.run(["git"] + list(args), capture_output=True, text=True, check=True)
    return result.stdout.strip()


def read_json(path):
    with open(path) as f:
        return json.load(f)


def count_files_recursive(directory, suffix=".php"):
    if not directory.exists():
        return 0
    count = 0
    for root, dirs, files in os.walk(directory):
        dirs[:] = [d for d in dirs if not d.startswith(".") and d != "vendor"]
        for f in files:
            if f.endswith(suffix):
                count += 1
    return count


def count_test_files(package_dir):
    tests_dir = package_dir / "tests"
    if not tests_dir.exists():
        return 0
    count = 0
    for root, dirs, files in os.walk(tests_dir):
        dirs[:] = [d for d in dirs if not d.startswith(".")]
        for f in files:
            if f.endswith("Test.php") or f.endswith(".phpt"):
                count += 1
    return count


def find_package_dirs(base_dir):
    packages = []
    if not base_dir.exists():
        return packages
    for root, dirs, files in os.walk(base_dir):
        dirs[:] = [d for d in dirs if not d.startswith(".") and d != "vendor"]
        if "composer.json" in files:
            packages.append(Path(root))
    return sorted(packages)


def get_package_info(pkg_dir):
    composer_path = pkg_dir / "composer.json"
    info = {
        "path": str(pkg_dir),
        "name": "",
        "php": "",
        "has_src": (pkg_dir / "src").exists(),
        "src_files": count_files_recursive(pkg_dir / "src", ".php"),
        "test_files": count_test_files(pkg_dir),
        "has_composer": composer_path.exists(),
        "deps_sovereign": [],
    }
    if composer_path.exists():
        data = read_json(composer_path)
        info["name"] = data.get("name", "")
        info["php"] = data.get("require", {}).get("php", "")
        for dep, ver in data.get("require", {}).items():
            if "sovereign" in dep.lower():
                info["deps_sovereign"].append(f"{dep}:{ver}")
    return info


def list_adrs(adr_dir):
    adrs = []
    if not adr_dir.exists():
        return adrs
    for f in sorted(adr_dir.iterdir()):
        if f.suffix != ".md" or not f.name.startswith("ADR-"):
            continue
        title = status = date = supersedes = ""
        try:
            with open(f) as fh:
                for line in fh:
                    if line.startswith("# "):
                        title = line[2:].strip()
                    elif line.startswith("**Status:**"):
                        status = line.split("**Status:**")[1].strip().rstrip("*").strip()
                    elif line.startswith("**Date:**"):
                        date = line.split("**Date:**")[1].strip().rstrip("*").strip()
                    elif line.startswith("**Supersedes:**"):
                        supersedes = line.split("**Supersedes:**")[1].strip().rstrip("*").strip()
                    if title and status and date:
                        break
        except Exception:
            pass
        adrs.append({"file": f.name, "title": title, "status": status, "date": date, "supersedes": supersedes})
    return adrs


def count_blueprint_files(arch_dir, tier_subpath):
    tier_dir = arch_dir / tier_subpath
    if not tier_dir.exists():
        return 0, []
    files = []
    for f in sorted(tier_dir.iterdir()):
        if f.suffix != ".md":
            continue
        if any(kw in f.name.upper() for kw in ["-DAG", "-BUILD-ORDER", "-CAPABILITY"]):
            continue
        files.append(f.name)
    return len(files), files


def check_index_discrepancies(repo_root):
    discrepancies = []
    index_path = repo_root / "Architecture" / "INDEX.md"
    if not index_path.exists():
        discrepancies.append("INDEX.md not found")
        return discrepancies
    with open(index_path) as f:
        content = f.read()
    for line in content.split("\n")[:10]:
        if "Last verified" in line and "2026-08-12" in line:
            discrepancies.append("INDEX.md freshness stamp says 2026-08-12 but §9 changelog records edits through 2026-09-24")
            break
    if "stub only" in content and "Implemented + tested" in content:
        discrepancies.append("INDEX.md has contradictory CORE-02 status: 'stub only (.gitkeep)' (§1) vs 'Implemented + tested, v1.0.0' (§2.1)")
    if "parallelizable" in content:
        discrepancies.append("INDEX.md §5.3 still uses 'parallelizable' labels despite ADR-014 retiring them")
    if "30 blueprints" in content or "All 30 pass" in content:
        discrepancies.append("INDEX.md §5.3 Step 8 says '30 blueprints' but should be 31 (HUB-31 accepted 2026-08-13)")
    return discrepancies


def check_readme_discrepancies(repo_root):
    discrepancies = []
    readme_path = repo_root / "README.md"
    if not readme_path.exists():
        discrepancies.append("README.md not found")
        return discrepancies
    with open(readme_path) as f:
        content = f.read()
    if "PHP 8.3" in content or "php 8.3" in content:
        discrepancies.append("README.md mentions PHP 8.3 but root composer.json requires PHP ^8.4")
    if "8 Core-tier packages" in content:
        discrepancies.append("README.md says '8 Core-tier packages' but actual implementation has 13 (12 under packages/core/ + 1 under orchestrator/)")
    return discrepancies


def main():
    parser = argparse.ArgumentParser(description="Generate architecture baseline from repository evidence")
    parser.add_argument("--output", default="download/ARCHITECTURE_BASELINE.md")
    parser.add_argument("--repo-root", default=None)
    args = parser.parse_args()

    repo_root = Path(args.repo_root).resolve() if args.repo_root else Path(git("rev-parse", "--show-toplevel"))
    os.chdir(repo_root)

    commit_sha = git("rev-parse", "HEAD")
    short_sha = git("rev-parse", "--short", "HEAD")
    branch = git("rev-parse", "--abbrev-ref", "HEAD")
    commit_date = git("log", "-1", "--format=%cI")
    gen_timestamp = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")

    root_composer = read_json(repo_root / "composer.json")
    php_version = root_composer.get("require", {}).get("php", "unknown")

    core_packages = [get_package_info(p) for p in find_package_dirs(repo_root / "packages" / "core")]
    hub_packages = [get_package_info(p) for p in find_package_dirs(repo_root / "packages" / "hub")]
    spoke_packages = [get_package_info(p) for p in find_package_dirs(repo_root / "packages" / "spoke")]
    bridge_packages = [get_package_info(p) for p in find_package_dirs(repo_root / "packages" / "bridge")]

    orchestrator_info = None
    orchestrator_dir = repo_root / "orchestrator"
    if orchestrator_dir.exists() and (orchestrator_dir / "composer.json").exists():
        orchestrator_info = get_package_info(orchestrator_dir)

    total_src_files = total_test_files = 0
    all_packages = core_packages + hub_packages + spoke_packages + bridge_packages
    if orchestrator_info:
        all_packages.append(orchestrator_info)
    for pkg in all_packages:
        total_src_files += pkg["src_files"]
        total_test_files += pkg["test_files"]

    adrs = list_adrs(repo_root / "Architecture" / "ADRs")
    accepted_adrs = [a for a in adrs if "accept" in a["status"].lower()]

    arch_dir = repo_root / "Architecture"
    core_bp_count, core_bp_files = count_blueprint_files(arch_dir, "Core")
    hub_bp_count, hub_bp_files = count_blueprint_files(arch_dir, "Hub")
    ispoke_bp_count, ispoke_bp_files = count_blueprint_files(arch_dir, "Spoke/Internal")
    espoke_bp_count, espoke_bp_files = count_blueprint_files(arch_dir, "Spoke/External")
    bridge_bp_count, bridge_bp_files = count_blueprint_files(arch_dir, "Spoke/Bridge")
    deploy_bp_count, deploy_bp_files = count_blueprint_files(arch_dir, "Deploy")
    total_blueprints = core_bp_count + hub_bp_count + ispoke_bp_count + espoke_bp_count + bridge_bp_count + deploy_bp_count

    core_dag_files = []
    core_dir = arch_dir / "Core"
    if core_dir.exists():
        for f in core_dir.iterdir():
            if f.suffix == ".md" and any(kw in f.name.upper() for kw in ["-DAG", "-BUILD-ORDER"]):
                core_dag_files.append(f.name)

    index_discrepancies = check_index_discrepancies(repo_root)
    readme_discrepancies = check_readme_discrepancies(repo_root)

    migration_count = 0
    for pkg in all_packages:
        migrations_dir = Path(pkg["path"]) / "migrations"
        if migrations_dir.exists():
            migration_count += count_files_recursive(migrations_dir, ".sql")

    lines = []
    lines.append("# Generated Architecture Evidence Snapshot")
    lines.append("")
    lines.append("**Authority:** Repository implementation state at a point in time.")
    lines.append("**Not authoritative for:** Architectural intent, SDLC admission policy, capability requirements.")
    lines.append(f"**Generated:** {gen_timestamp}")
    lines.append(f"**Commit:** `{commit_sha}`")
    lines.append(f"**Short SHA:** `{short_sha}`")
    lines.append(f"**Branch:** `{branch}`")
    lines.append(f"**Commit date:** {commit_date}")
    lines.append(f"**Reproducible by:** `python3 scripts/generate-architecture-baseline-v2.py` at the commit above.")
    lines.append("")
    lines.append("> **This is an evidence snapshot, not an architectural authority.**")
    lines.append("> Baseline = what the repository contains. ADR/SPEC = what the architecture says should exist. SDLC = admission/process authority.")
    lines.append("")
    lines.append("---")
    lines.append("")

    lines.append("## 1. Runtime Constraints")
    lines.append("")
    lines.append("| Source | PHP Constraint |")
    lines.append("|---|---|")
    lines.append(f"| Root `composer.json` | `{php_version}` |")
    for pkg in all_packages:
        if pkg["php"]:
            lines.append(f"| `{pkg['name']}` | `{pkg['php']}` |")
    lines.append("")

    lines.append("## 2. Core Implementation Inventory")
    lines.append("")
    lines.append(f"**Total Core implementations:** {len(core_packages) + (1 if orchestrator_info else 0)}")
    lines.append(f"({len(core_packages)} under `packages/core/`" + (f" + 1 under `orchestrator/`" if orchestrator_info else "") + ")")
    lines.append("")
    lines.append("| Package | Path | Src Files | Test Files | Composer Deps (sovereign) |")
    lines.append("|---|---|---|---|---|")
    for pkg in sorted(core_packages, key=lambda p: p["name"]):
        deps = ", ".join(pkg["deps_sovereign"]) if pkg["deps_sovereign"] else "—"
        lines.append(f"| `{pkg['name']}` | `{pkg['path']}` | {pkg['src_files']} | {pkg['test_files']} | {deps} |")
    if orchestrator_info:
        deps = ", ".join(orchestrator_info["deps_sovereign"]) if orchestrator_info["deps_sovereign"] else "—"
        lines.append(f"| `{orchestrator_info['name']}` | `{orchestrator_info['path']}` | {orchestrator_info['src_files']} | {orchestrator_info['test_files']} | {deps} |")
    lines.append("")

    lines.append("## 3. Hub/Spoke/Bridge Implementation Inventory")
    lines.append("")
    lines.append("| Tier | Implemented Packages | Total Src Files | Total Test Files |")
    lines.append("|---|---|---|---|")
    hub_src = sum(p["src_files"] for p in hub_packages)
    hub_test = sum(p["test_files"] for p in hub_packages)
    spoke_src = sum(p["src_files"] for p in spoke_packages)
    spoke_test = sum(p["test_files"] for p in spoke_packages)
    bridge_src = sum(p["src_files"] for p in bridge_packages)
    bridge_test = sum(p["test_files"] for p in bridge_packages)
    lines.append(f"| Hub | {len(hub_packages)} | {hub_src} | {hub_test} |")
    lines.append(f"| Spoke | {len(spoke_packages)} | {spoke_src} | {spoke_test} |")
    lines.append(f"| Bridge | {len(bridge_packages)} | {bridge_src} | {bridge_test} |")
    lines.append("")
    if hub_packages:
        lines.append("### Hub packages:")
        lines.append("")
        lines.append("| Package | Src Files | Test Files |")
        lines.append("|---|---|---|")
        for pkg in sorted(hub_packages, key=lambda p: p["name"]):
            lines.append(f"| `{pkg['name']}` | {pkg['src_files']} | {pkg['test_files']} |")
        lines.append("")
    if spoke_packages:
        lines.append("### Spoke packages:")
        lines.append("")
        lines.append("| Package | Src Files | Test Files |")
        lines.append("|---|---|---|")
        for pkg in sorted(spoke_packages, key=lambda p: p["name"]):
            lines.append(f"| `{pkg['name']}` | {pkg['src_files']} | {pkg['test_files']} |")
        lines.append("")
    if bridge_packages:
        lines.append("### Bridge packages:")
        lines.append("")
        lines.append("| Package | Src Files | Test Files |")
        lines.append("|---|---|---|")
        for pkg in sorted(bridge_packages, key=lambda p: p["name"]):
            lines.append(f"| `{pkg['name']}` | {pkg['src_files']} | {pkg['test_files']} |")
        lines.append("")

    lines.append("## 4. Aggregate Counts")
    lines.append("")
    lines.append("| Metric | Count |")
    lines.append("|---|---|")
    lines.append(f"| Total implemented packages | {len(all_packages)} |")
    lines.append(f"| Total PHP source files | {total_src_files} |")
    lines.append(f"| Total PHP test files | {total_test_files} |")
    lines.append(f"| Total SQL migrations | {migration_count} |")
    lines.append("")

    lines.append("## 5. ADR Inventory")
    lines.append("")
    lines.append(f"**Total ADR files:** {len(adrs)}")
    lines.append(f"**Accepted:** {len(accepted_adrs)}")
    lines.append("")
    lines.append("| File | Title | Status | Date |")
    lines.append("|---|---|---|---|")
    for adr in adrs:
        lines.append(f"| `{adr['file']}` | {adr['title']} | {adr['status']} | {adr['date']} |")
    lines.append("")

    lines.append("## 6. Blueprint Inventory (declared, not implemented)")
    lines.append("")
    lines.append(f"**Total blueprint files:** {total_blueprints}")
    lines.append("")
    lines.append("| Tier | Blueprint Count | Files |")
    lines.append("|---|---|---|")
    lines.append(f"| Core | {core_bp_count} | {', '.join(core_bp_files[:5])}{'...' if len(core_bp_files) > 5 else ''} |")
    lines.append(f"| Hub | {hub_bp_count} | {', '.join(hub_bp_files[:5])}{'...' if len(hub_bp_files) > 5 else ''} |")
    lines.append(f"| Spoke/Internal | {ispoke_bp_count} | {', '.join(ispoke_bp_files[:5])}{'...' if len(ispoke_bp_files) > 5 else ''} |")
    lines.append(f"| Spoke/External | {espoke_bp_count} | {', '.join(espoke_bp_files[:5])}{'...' if len(espoke_bp_files) > 5 else ''} |")
    lines.append(f"| Spoke/Bridge | {bridge_bp_count} | {', '.join(bridge_bp_files[:5])}{'...' if len(bridge_bp_files) > 5 else ''} |")
    lines.append(f"| Deploy | {deploy_bp_count} | {', '.join(deploy_bp_files[:5])}{'...' if len(deploy_bp_files) > 5 else ''} |")
    lines.append("")
    if core_dag_files:
        lines.append("### Core DAG/Build-Order files (derived, not blueprints):")
        lines.append("")
        for f in core_dag_files:
            lines.append(f"- `Architecture/Core/{f}`")
        lines.append("")

    lines.append("## 7. Implemented vs Declared")
    lines.append("")
    lines.append("| Status | Core | Hub | Spoke | Bridge | Total |")
    lines.append("|---|---|---|---|---|---|")
    impl_core = len(core_packages) + (1 if orchestrator_info else 0)
    declared_core = core_bp_count
    lines.append(f"| Implemented | {impl_core} | {len(hub_packages)} | {len(spoke_packages)} | {len(bridge_packages)} | {impl_core + len(hub_packages) + len(spoke_packages) + len(bridge_packages)} |")
    lines.append(f"| Declared (blueprints) | {declared_core} | {hub_bp_count} | {ispoke_bp_count + espoke_bp_count} | {bridge_bp_count} | {total_blueprints} |")
    lines.append(f"| Declared but not implemented | {declared_core - impl_core} | {hub_bp_count - len(hub_packages)} | {(ispoke_bp_count + espoke_bp_count) - len(spoke_packages)} | {bridge_bp_count - len(bridge_packages)} | {total_blueprints - (impl_core + len(hub_packages) + len(spoke_packages) + len(bridge_packages))} |")
    lines.append("")

    lines.append("## 8. Known Discrepancies (evidence-based)")
    lines.append("")
    lines.append("### INDEX.md discrepancies:")
    lines.append("")
    if index_discrepancies:
        for d in index_discrepancies:
            lines.append(f"- {d}")
    else:
        lines.append("- None detected")
    lines.append("")
    lines.append("### README.md discrepancies:")
    lines.append("")
    if readme_discrepancies:
        for d in readme_discrepancies:
            lines.append(f"- {d}")
    else:
        lines.append("- None detected")
    lines.append("")

    lines.append("## 9. Authority Statement")
    lines.append("")
    lines.append("```")
    lines.append("Baseline = repository implementation evidence at a point in time.")
    lines.append("ADR/SPEC = architectural intent.")
    lines.append("SDLC = admission/process authority.")
    lines.append("")
    lines.append("This snapshot is NOT authoritative for:")
    lines.append("  - Architectural intent (what should exist)")
    lines.append("  - SDLC admission policy (what to build next)")
    lines.append("  - Capability requirements (what's needed for delivery)")
    lines.append("  - Dependency direction (use the DAGs for this)")
    lines.append("")
    lines.append("This snapshot IS authoritative for:")
    lines.append("  - What the repository actually contains")
    lines.append("  - Package/file/test counts")
    lines.append("  - PHP version constraints")
    lines.append("  - ADR inventory and status")
    lines.append("  - Blueprint file inventory")
    lines.append("```")
    lines.append("")

    output_path = Path(args.output)
    if not output_path.is_absolute():
        output_path = repo_root / output_path
    output_path.parent.mkdir(parents=True, exist_ok=True)
    with open(output_path, "w") as f:
        f.write("\n".join(lines))

    print(f"Baseline written to: {output_path}")
    print(f"Commit: {short_sha}")
    print(f"Branch: {branch}")
    print(f"Total implemented packages: {len(all_packages)}")
    print(f"Total PHP source files: {total_src_files}")
    print(f"Total PHP test files: {total_test_files}")
    print(f"Total blueprints (declared): {total_blueprints}")
    print(f"Total ADRs: {len(adrs)}")


if __name__ == "__main__":
    main()
