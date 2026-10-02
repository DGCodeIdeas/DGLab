#!/usr/bin/env python3
"""
Add Blind-Spot Awareness notes to every architectural document.

Per tech-lead directive: "find a way to tell everyone (Human and AI)
about this first hand in clear details before anything else. Also in
every single document with variation in details."

This script scans all .md files in Architecture/ and inserts a
context-specific Blind-Spot Awareness note after the first H1 header
(or after the Status line if present). The note varies by document type.

Usage:
    python3 scripts/add-blind-spot-awareness.py [--dry-run]
"""

import os
import re
import sys
from pathlib import Path


# The marker that indicates a note has already been added
MARKER = "Blind-Spot Awareness"

# Context-specific notes by document type
NOTES = {
    "adr": """<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This ADR's claims are **ratified, not verified**. Ratification establishes a contract; it does not guarantee correctness. The architectural assumptions, edge cases, and interaction scenarios in this ADR should be actively questioned and tested against runtime behavior. A different auditor with a different lens may find issues this ADR's authors missed. **The number of findings found is not the number of findings that exist.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->""",

    "core_blueprint": """<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Core blueprint may contain **unverified assumptions, unstated dependencies, or edge cases not covered**. The contract declared here is a candidate, not a certainty. The implementation may diverge from the blueprint (implementation drift). Dependencies declared in the Upward/Downward sections may be incomplete or incorrect. The verified DAG may not match the declared DAG. **An audit of this blueprint is a starting point, not a complete inventory.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->""",

    "hub_blueprint": """<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Hub blueprint may contain **unverified assumptions, unstated dependencies, or edge cases not covered**. The contract declared here is a candidate, not a certainty. Upward/Downward declarations may have asymmetric drift (producer claims a consumer that the consumer doesn't acknowledge). The blueprint's edge_type classifications may be UNKNOWN or incorrect. Cross-tier dependencies (Hub→Core, Hub→Runtime) may not be fully verified. **An audit of this blueprint is a starting point, not a complete inventory.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->""",

    "spoke_blueprint": """<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->""",

    "bridge_blueprint": """<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Bridge blueprint may contain **unverified assumptions about the adapter boundary, unstated integration dependencies, or edge cases not covered**. The Bridge routes through the Integration DAG, not the tier-DAG family — its dependencies may cross tiers in ways not captured in any single tier's DAG. **The adapter contract should be verified against actual external infrastructure behavior.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->""",

    "deploy_blueprint": """<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Deploy blueprint may contain **stale infrastructure assumptions** (e.g., PHP-FPM vs FrankenPHP per ADR-017, Nginx vs Caddy, Supervisor vs systemd). The deployment configuration should be verified against actual infrastructure. Runtime substrate claims (Anvil v3, worker recycling, signal handling) should be tested against real deployments. **Documentation drift is especially dangerous in deployment blueprints — always verify against the actual runtime.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->""",

    "dag": """<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This DAG is a **candidate inventory, not a complete graph**. Edges may be missing; edge classifications (edge_type, requiredness, gates) may be incorrect or UNKNOWN. The declared and verified views should be actively compared for drift. The four edge status categories (VERIFIED / DECLARED_ONLY / UNDECLARED_VERIFIED / INVALID) are derived from current evidence — new evidence may change them. **The number of edges found is not the number of edges that exist.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->""",

    "crosscutting": """<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This CrossCutting document's **assumptions should be actively questioned**. Governance rules, structural models, and doctrines established here are candidates, not certainties. The document may reference other documents that have drifted. Cross-cutting concerns (security, observability, persistence, events) interact in ways that may not be captured by any single document. **A governance rule is only as good as its enforcement — verify that the rule is actually checked.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->""",

    "index": """<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This INDEX registry's **counts, statuses, and cross-references may have drift**. Per ADR-021 §11, INDEX owns identity/governance only — derived facts (Composer dependencies, namespace imports, implementation status, topological ordering, test state) should be generated, not hand-maintained. The freshness stamp may be stale. Blueprint files may exist without INDEX entries, or INDEX entries may reference blueprints that don't exist. **The registry is a starting point — verify against the actual repository.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->""",

    "verification": """<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This verification/audit document is a **starting point, not a complete inventory**. The number of findings found is not the number of findings that exist. The audit was conducted by a single auditor with systematic blind spots (runtime-only issues, cross-tier drift, architectural assumptions, missing tests, auditor biases, unknown unknowns). **No audit is declared complete.** Findings are closed only when their verification condition passes — not when code changes. See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->""",

    "default": """<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This document may contain **unverified assumptions, unstated dependencies, or edge cases not covered**. Its claims should be actively questioned and tested. **The number of findings found is not the number of findings that exist.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->""",
}


def get_doc_type(filepath: Path, arch_root: Path) -> str:
    """Determine the document type from the file path."""
    rel = filepath.relative_to(arch_root)
    parts = str(rel).replace("\\", "/")

    if parts == "INDEX.md":
        return "index"
    if parts == "README.md":
        return "index"
    if parts == "AUTHORING_GUIDE.md":
        return "crosscutting"
    if parts == "FROZEN-CONTRACTS.md":
        return "crosscutting"
    if parts == "OPEN-DECISIONS.md":
        return "crosscutting"
    if parts == "DEPRECATED_TAGS.md":
        return "crosscutting"

    if parts.startswith("ADRs/"):
        return "adr"

    if parts.startswith("Core/"):
        if "DAG" in parts or "BUILD-ORDER" in parts:
            return "dag"
        return "core_blueprint"

    if parts.startswith("Hub/"):
        if "DAG" in parts or "BUILD-ORDER" in parts:
            return "dag"
        return "hub_blueprint"

    if parts.startswith("Spoke/Internal/"):
        return "spoke_blueprint"
    if parts.startswith("Spoke/External/"):
        return "spoke_blueprint"
    if parts.startswith("Spoke/Bridge/"):
        return "bridge_blueprint"

    if parts.startswith("Deploy/"):
        return "deploy_blueprint"

    if parts.startswith("CrossCutting/"):
        return "crosscutting"

    if parts.startswith("Verification/"):
        return "verification"

    if parts.startswith("Migration/"):
        return "crosscutting"

    if parts.startswith("ADRs/"):
        return "adr"

    if parts.startswith("Critiques/"):
        return "default"

    return "default"


def insert_note(content: str, note: str) -> str:
    """Insert the blind-spot note after the first H1 header."""
    lines = content.split("\n")

    # Find the first H1 line
    h1_idx = None
    for i, line in enumerate(lines):
        if line.startswith("# "):
            h1_idx = i
            break

    if h1_idx is None:
        # No H1 found — prepend
        return note + "\n\n" + content

    # Find the next blank line after H1 (end of the header block)
    insert_after = h1_idx
    for i in range(h1_idx + 1, min(h1_idx + 20, len(lines))):
        if lines[i].strip() == "":
            insert_after = i
            break
        # If we hit another ## or content, insert after the H1 line
        if lines[i].startswith("##") or (lines[i].strip() and not lines[i].startswith("**")):
            insert_after = i - 1
            break

    # Also check for Status/Date lines after H1 — insert after those
    for i in range(h1_idx + 1, min(h1_idx + 15, len(lines))):
        line = lines[i].strip()
        if line.startswith("**Status:**") or line.startswith("**Date:**") or line.startswith("**Author:**"):
            insert_after = i
        elif line == "" and insert_after >= h1_idx:
            # First blank line after the metadata block
            insert_after = i
            break
        elif line.startswith("---"):
            # Horizontal rule — insert before it
            insert_after = i - 1
            break

    # Insert the note
    lines.insert(insert_after + 1, "")
    lines.insert(insert_after + 2, note)
    lines.insert(insert_after + 3, "")

    return "\n".join(lines)


def process_file(filepath: Path, arch_root: Path, dry_run: bool = False) -> bool:
    """Process a single file. Returns True if modified."""
    try:
        with open(filepath, encoding="utf-8") as f:
            content = f.read()
    except Exception as e:
        print(f"  SKIP {filepath}: {e}")
        return False

    # Skip if already has the marker
    if MARKER in content:
        print(f"  SKIP {filepath.relative_to(arch_root)}: already has note")
        return False

    # Skip if it's the doctrine itself
    if "BLIND-SPOT-DOCTRINE" in str(filepath):
        print(f"  SKIP {filepath.relative_to(arch_root)}: is the doctrine itself")
        return False

    doc_type = get_doc_type(filepath, arch_root)
    note = NOTES.get(doc_type, NOTES["default"])

    new_content = insert_note(content, note)

    if new_content == content:
        print(f"  SKIP {filepath.relative_to(arch_root)}: no change (insert failed)")
        return False

    if not dry_run:
        with open(filepath, "w", encoding="utf-8") as f:
            f.write(new_content)

    print(f"  ✅ {filepath.relative_to(arch_root)}: added {doc_type} note")
    return True


def main():
    dry_run = "--dry-run" in sys.argv

    # Find the Architecture/ directory
    script_path = Path(__file__).resolve()
    repo_root = script_path.parent.parent
    arch_root = repo_root / "Architecture"

    if not arch_root.exists():
        print(f"ERROR: Architecture/ not found at {arch_root}")
        sys.exit(1)

    print(f"Scanning {arch_root} for .md files...")

    md_files = []
    for root, dirs, files in os.walk(arch_root):
        dirs[:] = [d for d in dirs if not d.startswith(".")]
        for f in files:
            if f.endswith(".md"):
                md_files.append(Path(root) / f)

    md_files.sort()
    print(f"Found {len(md_files)} .md files\n")

    modified = 0
    skipped = 0
    for filepath in md_files:
        if process_file(filepath, arch_root, dry_run):
            modified += 1
        else:
            skipped += 1

    print(f"\n{'DRY RUN: ' if dry_run else ''}Modified: {modified}, Skipped: {skipped}, Total: {len(md_files)}")


if __name__ == "__main__":
    main()
