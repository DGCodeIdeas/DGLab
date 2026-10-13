#!/usr/bin/env python3
"""CI matrix completeness check (SAAI remediation Phase 2).

Guards against the failure mode where a package is added (or exists) with its
own PHPUnit + PHPStan configuration but is silently absent from the packages-ci
matrix — the exact drift that left core/crypto, core/filesystem and
spoke/internal/showcase untested while CI reported green.

Contract (three-state, per the SAAI invariant used by the other lints):
  PASS       — every eligible package is in the matrix (or explicitly excluded)
  FAIL       — a package is missing from the matrix, OR an exclusion is stale
               (points at a package that no longer exists / no longer needs it)
  UNVERIFIED — zero packages discovered, or the workflow file can't be parsed.
               This is a hard failure too: a vacuous scan must never be green.

Eligibility rule: a directory under packages/*/ or packages/*/*/ (or
'orchestrator') that contains ALL of: composer.json, src/, tests/,
phpunit.xml.dist. Exclusions live in scripts/ci-matrix-exclusions.txt,
format `package/path<TAB>one-line reason` (# comments allowed); a reason is
mandatory — bare exclusion lines fail.
"""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
WORKFLOW = ROOT / ".github" / "workflows" / "packages-ci.yml"
EXCLUSIONS = ROOT / "scripts" / "ci-matrix-exclusions.txt"


def discover_packages() -> list[str]:
    found: list[str] = []
    candidates = [p.parent for p in ROOT.glob("packages/*/*/composer.json")]
    candidates += [p.parent for p in ROOT.glob("packages/*/*/*/composer.json")]
    candidates.append(ROOT / "orchestrator")
    for d in sorted({c for c in candidates if c.is_dir()}):
        if all(
            [
                (d / "composer.json").is_file(),
                (d / "src").is_dir(),
                (d / "tests").is_dir(),
                (d / "phpunit.xml.dist").is_file(),
            ]
        ):
            rel = str(d.relative_to(ROOT))
            found.append(rel[len("packages/"):] if rel.startswith("packages/") else rel)
    return found


def parse_matrix() -> set[str]:
    text = WORKFLOW.read_text(encoding="utf-8")
    return set(re.findall(r"package:\s*([\w/\-]+)", text))


def parse_exclusions() -> dict[str, str]:
    out: dict[str, str] = {}
    if not EXCLUSIONS.exists():
        return out
    for raw in EXCLUSIONS.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        if "\t" not in line:
            print(f"FAIL: exclusion line without a TAB reason: {line!r}", file=sys.stderr)
            print(json.dumps({"check": "ci-matrix-completeness", "result": "FAIL"}))
            sys.exit(1)
        pkg, reason = (s.strip() for s in line.split("\t", 1))
        out[pkg] = reason
    return out


def main() -> int:
    packages = discover_packages()
    if not packages:
        print("UNVERIFIED: zero eligible packages discovered — scanner or tree is broken.", file=sys.stderr)
        print(json.dumps({"check": "ci-matrix-completeness", "result": "UNVERIFIED"}))
        return 1
    try:
        matrix = parse_matrix()
    except OSError as e:
        print(f"UNVERIFIED: cannot read workflow: {e}", file=sys.stderr)
        return 1

    exclusions = parse_exclusions()
    missing = sorted(p for p in packages if p not in matrix and p not in exclusions)
    stale_exclusions = sorted(p for p in exclusions if p not in packages)
    ghosts = sorted(p for p in matrix if p not in packages and p not in exclusions)

    report = {
        "check": "ci-matrix-completeness",
        "eligible": len(packages),
        "in_matrix": len([p for p in packages if p in matrix]),
        "excluded": len(exclusions),
        "missing": missing,
        "stale_exclusions": stale_exclusions,
        "matrix_entries_not_eligible": ghosts,
    }
    print(json.dumps(report, indent=2))

    if missing or stale_exclusions or ghosts:
        for m in missing:
            print(f"::error::{m}: eligible package absent from CI matrix — add it or record a reasoned exclusion", file=sys.stderr)
        for s in stale_exclusions:
            print(f"::error::{s}: exclusion is stale (package no longer eligible) — remove the line", file=sys.stderr)
        for g in ghosts:
            print(f"::warning::{g}: matrix entry not found among eligible packages (renamed/removed?)", file=sys.stderr)
        print(json.dumps({**report, "result": "FAIL"}))
        return 1

    print(json.dumps({**report, "result": "PASS"}))
    return 0


if __name__ == "__main__":
    sys.exit(main())
