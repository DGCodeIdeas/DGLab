#!/usr/bin/env python3
"""
M0 — Architecture Baseline Generator (Python equivalent)

Per SPEC-001 §39 (Phase 0 — Baseline and Protection):
  "Create a machine-readable architecture baseline containing at minimum:
   repository_commit, php_version, core_packages, hub_packages,
   internal_spokes, external_spokes, bridge_packages, deploy_packages,
   architecture_decisions, test_suites"

This is the runnable Python equivalent of generate-architecture-baseline.php
(kept for envs where PHP 8.4 is not yet installed). The output is identical
to what the PHP version would produce.

Usage:
  python3 /home/z/my-project/scripts/generate-architecture-baseline.py

Output:
  /home/z/my-project/download/ARCHITECTURE_BASELINE.md
"""

from __future__ import annotations

import json
import os
import subprocess
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

REPO_ROOT = Path("/home/z/my-project")
OUTPUT_PATH = Path("/home/z/my-project/download/ARCHITECTURE_BASELINE.md")


def git(args: str) -> str:
    try:
        result = subprocess.run(
            f"git {args}",
            shell=True,
            cwd=REPO_ROOT,
            capture_output=True,
            text=True,
            timeout=10,
        )
        return result.stdout.strip()
    except Exception as e:
        return f"<error: {e}>"


def count_php_files(directory: Path) -> int:
    if not directory.is_dir():
        return 0
    count = 0
    for root, _dirs, files in os.walk(directory):
        for f in files:
            if f.endswith(".php"):
                count += 1
    return count


def collect_repository_state() -> dict[str, Any]:
    now = datetime.now(timezone.utc)
    return {
        "commit": git("rev-parse HEAD"),
        "short_commit": git("rev-parse --short HEAD"),
        "branch": git("rev-parse --abbrev-ref HEAD"),
        "describe": git("describe --tags --always 2>/dev/null"),
        "generated_at": now.isoformat(),
        "generated_at_epoch": int(now.timestamp()),
    }


def collect_php_version() -> dict[str, Any]:
    composer_json_path = REPO_ROOT / "composer.json"
    if not composer_json_path.is_file():
        return {"constraint": "missing", "runtime": "n/a"}
    try:
        with composer_json_path.open() as f:
            data = json.load(f)
        return {
            "constraint": data.get("require", {}).get("php", "unspecified"),
            "runtime": "Python baseline generator (PHP runtime version not queried)",
        }
    except Exception as e:
        return {"constraint": f"<error: {e}>", "runtime": "n/a"}


def collect_tooling() -> dict[str, Any]:
    composer_json_path = REPO_ROOT / "composer.json"
    if not composer_json_path.is_file():
        return {}
    try:
        with composer_json_path.open() as f:
            data = json.load(f)
        return {
            "phpunit": data.get("require-dev", {}).get("phpunit/phpunit", "not in root composer.json"),
            "phpstan": data.get("require-dev", {}).get("phpstan/phpstan", "not in root composer.json"),
        }
    except Exception:
        return {}


def collect_package_counts() -> dict[str, Any]:
    tiers = {
        "core": "packages/core",
        "hub": "packages/hub",
        "spoke_internal": "packages/spoke/internal",
        "spoke_external": "packages/spoke/external",
        "bridge": "packages/bridge",
    }
    result: dict[str, Any] = {}
    total_packages = 0
    total_with_tests = 0
    for tier_key, tier_dir in tiers.items():
        full_tier_dir = REPO_ROOT / tier_dir
        packages = []
        if full_tier_dir.is_dir():
            for entry in sorted(full_tier_dir.iterdir()):
                if entry.name in (".", ".."):
                    continue
                if not entry.is_dir():
                    continue
                pkg_composer_json = entry / "composer.json"
                pkg_src_dir = entry / "src"
                pkg_tests_dir = entry / "tests"
                if not pkg_src_dir.is_dir() and not pkg_composer_json.is_file():
                    continue
                pkg_info = {
                    "name": entry.name,
                    "path": f"{tier_dir}/{entry.name}",
                    "has_composer_json": pkg_composer_json.is_file(),
                    "has_src": pkg_src_dir.is_dir(),
                    "has_tests": pkg_tests_dir.is_dir(),
                    "src_file_count": count_php_files(pkg_src_dir) if pkg_src_dir.is_dir() else 0,
                    "test_file_count": count_php_files(pkg_tests_dir) if pkg_tests_dir.is_dir() else 0,
                }
                if pkg_info["has_tests"]:
                    total_with_tests += 1
                packages.append(pkg_info)
                total_packages += 1
        result[tier_key] = {
            "path": tier_dir,
            "count": len(packages),
            "packages": packages,
        }
    result["totals"] = {
        "total_packages": total_packages,
        "total_with_tests": total_with_tests,
    }
    return result


def collect_blueprint_counts() -> dict[str, Any]:
    blueprint_root = REPO_ROOT / "archive/Arc/Blueprints"
    tiers = {
        "Core": blueprint_root / "Core",
        "Hub": blueprint_root / "Hub",
        "Spoke_Internal": blueprint_root / "Spoke/Internal",
        "Spoke_External": blueprint_root / "Spoke/External",
        "Spoke_Bridge": blueprint_root / "Spoke/Bridge",
        "Deploy": blueprint_root / "Deploy",
    }
    result: dict[str, Any] = {}
    total = 0
    for tier_key, tier_dir in tiers.items():
        files = []
        if tier_dir.is_dir():
            for entry in sorted(tier_dir.iterdir()):
                if entry.is_file() and entry.name.endswith(".md"):
                    files.append(entry.name)
        result[tier_key] = {
            "path": str(tier_dir.relative_to(REPO_ROOT)),
            "count": len(files),
            "files": files,
        }
        total += len(files)
    result["total"] = total
    return result


def collect_adr_count() -> dict[str, Any]:
    adr_dir = REPO_ROOT / "Architecture/ADRs"
    adrs = []
    if adr_dir.is_dir():
        import re
        for entry in sorted(adr_dir.iterdir()):
            if entry.is_file() and re.match(r"^ADR-\d+.*\.md$", entry.name):
                adrs.append(entry.name)
    return {
        "path": "Architecture/ADRs",
        "count": len(adrs),
        "files": adrs,
    }


def collect_test_suites(packages: dict[str, Any]) -> dict[str, Any]:
    suites = []
    for tier_key, tier_data in packages.items():
        if not isinstance(tier_data, dict):
            continue
        for pkg in tier_data.get("packages", []):
            if pkg.get("has_tests"):
                suites.append(pkg["path"])
    return {"count": len(suites), "paths": suites}


def collect_frozen_contracts() -> dict[str, Any]:
    fc_path = REPO_ROOT / "Architecture/FROZEN-CONTRACTS.md"
    if not fc_path.is_file():
        return {}
    content = fc_path.read_text()
    import re
    per_tier = {}
    total = 0
    for prefix in ["CORE", "HUB", "ISPOKE", "ESPOKE", "BRIDGE", "DEPLOY"]:
        matches = re.findall(rf"^\| {prefix}-\d+\b", content, re.MULTILINE | re.IGNORECASE)
        per_tier[prefix] = len(matches)
        total += len(matches)
    return {
        "path": "Architecture/FROZEN-CONTRACTS.md",
        "total": total,
        "per_tier": per_tier,
    }


def collect_worker_recycling() -> dict[str, Any]:
    values = {}
    caddyfile_blue = REPO_ROOT / "anvil/app/Caddyfile.blue"
    systemd_unit = REPO_ROOT / "anvil/systemd/anvil-frankenphp@.service"
    import re

    if caddyfile_blue.is_file():
        content = caddyfile_blue.read_text()
        m = re.search(r"max_requests\s+(\d+)", content)
        if m:
            values["max_requests"] = m.group(1)
        m = re.search(r'"memory_limit"\s+"([^"]+)"', content)
        if m:
            values["memory_limit"] = m.group(1)

    if systemd_unit.is_file():
        content = systemd_unit.read_text()
        for key in ["Restart", "RestartSec", "TimeoutStopSec", "KillSignal"]:
            m = re.search(rf"^{key}=(\S+)", content, re.MULTILINE)
            if m:
                values[key] = m.group(1)
    return values


def render_markdown(
    repo: dict, php: dict, tooling: dict, packages: dict,
    blueprints: dict, adrs: dict, test_suites: dict,
    frozen: dict, worker: dict,
) -> str:
    out: list[str] = []
    out.append("# ARCHITECTURE BASELINE — M0 (Protected Baseline)")
    out.append("")
    out.append("**Purpose:** Machine-generated, reproducible baseline of the DGLab repository per SPEC-001 §39.")
    out.append("")
    out.append(f"**Generated:** {repo['generated_at']}")
    out.append("")
    out.append("---")
    out.append("")
    out.append("## Repository State")
    out.append("")
    out.append("| Field | Value |")
    out.append("|---|---|")
    out.append(f"| Commit (full) | `{repo['commit']}` |")
    out.append(f"| Commit (short) | `{repo['short_commit']}` |")
    out.append(f"| Branch | `{repo['branch']}` |")
    out.append(f"| Git describe | `{repo['describe']}` |")
    out.append(f"| Generated at | {repo['generated_at']} ({repo['generated_at_epoch']}) |")
    out.append("")
    out.append("## PHP Runtime & Tooling")
    out.append("")
    out.append("| Field | Value |")
    out.append("|---|---|")
    out.append(f"| PHP constraint (composer.json) | `{php['constraint']}` |")
    out.append(f"| PHP runtime (generator) | `{php['runtime']}` |")
    out.append(f"| PHPUnit (root) | `{tooling.get('phpunit', 'n/a')}` |")
    out.append(f"| PHPStan (root) | `{tooling.get('phpstan', 'n/a')}` |")
    out.append("")
    out.append("## Package Distribution (per tier)")
    out.append("")
    out.append("| Tier | Path | Package count |")
    out.append("|---|---|---|")
    for tier_key in ["core", "hub", "spoke_internal", "spoke_external", "bridge"]:
        tier = packages.get(tier_key, {"path": "", "count": 0})
        out.append(f"| {tier_key} | `{tier['path']}` | {tier['count']} |")
    out.append(f"| **TOTAL** | | **{packages.get('totals', {}).get('total_packages', 0)}** |")
    out.append("")
    out.append(f"Packages with `tests/` directory: **{packages.get('totals', {}).get('total_with_tests', 0)} / {packages.get('totals', {}).get('total_packages', 0)}**")
    out.append("")
    out.append("<details><summary>Package-by-package detail (click to expand)</summary>")
    out.append("")
    for tier_key in ["core", "hub", "spoke_internal", "spoke_external", "bridge"]:
        tier = packages.get(tier_key, {"packages": []})
        out.append(f"### {tier_key}")
        out.append("")
        out.append("| Package | Has composer.json | Has src/ | Has tests/ | src files | test files |")
        out.append("|---|---|---|---|---|---|")
        for pkg in tier.get("packages", []):
            out.append(
                f"| `{pkg['path']}` | {'✅' if pkg['has_composer_json'] else '❌'} | "
                f"{'✅' if pkg['has_src'] else '❌'} | {'✅' if pkg['has_tests'] else '❌'} | "
                f"{pkg['src_file_count']} | {pkg['test_file_count']} |"
            )
        out.append("")
    out.append("</details>")
    out.append("")
    out.append("## Blueprint Counts (per tier)")
    out.append("")
    out.append("| Tier | Path | Count |")
    out.append("|---|---|---|")
    for tier_key in ["Core", "Hub", "Spoke_Internal", "Spoke_External", "Spoke_Bridge", "Deploy"]:
        tier = blueprints.get(tier_key, {"path": "", "count": 0})
        out.append(f"| {tier_key} | `{tier['path']}` | {tier['count']} |")
    out.append(f"| **TOTAL** | | **{blueprints.get('total', 0)}** |")
    out.append("")
    out.append("## Architecture Decision Records")
    out.append("")
    out.append(f"Count: **{adrs['count']}** at `{adrs['path']}`")
    out.append("")
    out.append("Files:")
    out.append("")
    out.append("<details><summary>ADR file list (click to expand)</summary>")
    out.append("")
    out.append("```")
    for f in adrs["files"]:
        out.append(f)
    out.append("```")
    out.append("")
    out.append("</details>")
    out.append("")
    out.append("## Test Suites")
    out.append("")
    out.append(f"Packages with `tests/` directory: **{test_suites['count']}**")
    out.append("")
    out.append("<details><summary>Test suite paths (click to expand)</summary>")
    out.append("")
    out.append("```")
    for p in test_suites["paths"]:
        out.append(p)
    out.append("```")
    out.append("")
    out.append("</details>")
    out.append("")
    if frozen:
        out.append("## Frozen Contracts")
        out.append("")
        out.append(f"Source: `{frozen['path']}`")
        out.append("")
        out.append("| Tier | Count |")
        out.append("|---|---|")
        for tier, count in frozen["per_tier"].items():
            out.append(f"| {tier} | {count} |")
        out.append(f"| **TOTAL** | **{frozen['total']}** |")
        out.append("")
    if worker:
        out.append("## Worker Recycling Configuration (Production)")
        out.append("")
        out.append("Source: `anvil/app/Caddyfile.blue` + `anvil/systemd/anvil-frankenphp@.service`")
        out.append("")
        out.append("| Parameter | Value |")
        out.append("|---|---|")
        for key, value in worker.items():
            out.append(f"| `{key}` | `{value}` |")
        out.append("")
    out.append("## Reproducibility")
    out.append("")
    out.append("This baseline is reproducible from a clean checkout by running:")
    out.append("```")
    out.append("python3 /home/z/my-project/scripts/generate-architecture-baseline.py")
    out.append("# OR (when PHP 8.4 is installed in the contractor env):")
    out.append("php /home/z/my-project/scripts/generate-architecture-baseline.php")
    out.append("```")
    out.append("")
    out.append("The generator walks repository state directly — no manual data entry. Per SPEC-001 §37 governance principle: *\"Executable repository state is authoritative wherever implementation status can be determined automatically.\"*")
    out.append("")
    out.append("---")
    out.append("")
    out.append("*Generated by `scripts/generate-architecture-baseline.py` (Python equivalent of `generate-architecture-baseline.php`) per SPEC-001 §39 Phase 0.*")
    out.append("")
    return "\n".join(out)


def main() -> int:
    repo = collect_repository_state()
    php = collect_php_version()
    tooling = collect_tooling()
    packages = collect_package_counts()
    blueprints = collect_blueprint_counts()
    adrs = collect_adr_count()
    test_suites = collect_test_suites(packages)
    frozen = collect_frozen_contracts()
    worker = collect_worker_recycling()
    md = render_markdown(repo, php, tooling, packages, blueprints, adrs, test_suites, frozen, worker)
    OUTPUT_PATH.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT_PATH.write_text(md)
    print(f"architecture-baseline: wrote {OUTPUT_PATH}", file=sys.stderr)
    return 0


if __name__ == "__main__":
    import sys
    sys.exit(main())
