# SDLC-06: Generator Specifications

> **This project is developed by both humans and AI systems. Both are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.**

> **⚠️ Blind-Spot Awareness:** This document specifies the deterministic generators that produce build orders, DAGs, and other derived artifacts. The specifications themselves are a starting point, not a complete specification. Every generator's input/output contract, every reproducibility criterion, every CI regeneration check is open to challenge when the generator produces output that doesn't match reality. The number of generators specified here is not the number of generators that exist. See [`Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md) for the governance framework.

**Status:** Canonical (Batch 2 of the SDLC rewrite, per Tech-Lead directive 2026-10-06).
**Date:** 2026-10-07 (initial rewrite).
**Authority:** Per [ADR-014](../ADRs/ADR-014-ratify-agrd-canonical-sdlc.md) + [ADR-021](../ADRs/ADR-021-tier-stratified-build-order.md) (build orders are generated, not authored).
**Related:** [SDLC-01](SDLC-01-Foundations.md) (Foundations), [SDLC-02](SDLC-02-Governance.md) (Governance — two-DAG model + Eligible(X) formula), [SDLC-03](SDLC-03-InterfaceFreeze.md) (Interface Freeze), [SDLC-04](SDLC-04-CooldownMechanics.md) (Cooldown Mechanics), [SDLC-05](SDLC-05-AI-Assisted-Development-Protocol.md) (AI-Assisted Development).

---

## §1. Why Generators Exist

DGLab has several artifacts that are DERIVED from canonical sources, not independently authored:

- **Build orders** (CORE-BUILD-ORDER.md, HUB-BUILD-ORDER.md) — derived from the Declared DAG + Verified DAG + SDLC admission state.
- **DAG artifacts** (CORE-DECLARED-DAG.md, CORE-VERIFIED-DAG.md, HUB-DECLARED-DAG.md, HUB-VERIFIED-DAG.md) — derived from blueprints + composer.json + source imports.
- **LINT output** (the JSON summary from architecture-boundary-lint) — derived from the source code.
- **INDEX cross-reference checks** — derived from INDEX.md §2 + the actual file tree.

These artifacts are NOT independently authored. They are GENERATED. The reason (per [SDLC-02](SDLC-02-Governance.md) §2.2):

> "Build orders are generated artifacts derived from both DAGs plus SDLC admission state — not independently authored."

If these artifacts were authored independently, they would drift from their sources. A blueprint changes; the build order doesn't get updated; the build order is now stale. This is exactly the kind of "declared vs verified" drift that the two-DAG governance model is designed to prevent.

Generators solve this by making the artifacts DETERMINISTIC OUTPUTS of their inputs. Change the inputs, regenerate the artifact. The artifact is always consistent with its sources.

---

## §2. The Generator Contract

Every generator in DGLab follows this contract:

```
  ┌────────────────────────────────────────────┐
  │           GENERATOR CONTRACT               │
  ├────────────────────────────────────────────┤
  │  INPUTS:                                   │
  │    - Canonical source files (blueprints,  │
  │      ADRs, INDEX, composer.json, source    │
  │      code, etc.)                           │
  │    - Generator configuration (if any)      │
  ├────────────────────────────────────────────┤
  │  OUTPUT:                                   │
  │    - Generated artifact (markdown, JSON,  │
  │      YAML, etc.)                           │
  │    - Generator manifest (input SHAs,       │
  │      generator version, timestamp)         │
  ├────────────────────────────────────────────┤
  │  INVARIANTS:                               │
  │    1. Determinism: same inputs → same      │
  │       output (byte-for-byte, modulo        │
  │       timestamp)                           │
  │    2. Reproducibility: any CI runner can   │
  │       regenerate the artifact              │
  │    3. Source-truth: the artifact reflects  │
  │       the inputs at generation time        │
  │    4. No hidden state: the generator       │
  │       reads only from declared inputs      │
  │       (no environment-specific paths,     │
  │       no hardcoded /home/z/my-project)     │
  ├────────────────────────────────────────────┤
  │  FAILURE MODES:                            │
  │    - Generator exits non-zero → CI fails  │
  │    - Output doesn't match committed        │
  │      artifact → CI fails (drift detected)  │
  │    - Generator can't find inputs → CI     │
  │      fails (UNVERIFIED, not PASS — per    │
  │      SDLC-02 §3.2)                        │
  └────────────────────────────────────────────┘
```

---

## §3. The Determinism Invariant

### §3.1 What Determinism Means

A generator is deterministic if: given the same inputs, it produces the same output (byte-for-byte, modulo timestamp).

This means:
- The generator's algorithm is fixed (no random elements, no time-dependent logic except the timestamp).
- The generator's input set is fixed (no environment-specific files, no network calls).
- The generator's output format is fixed (no platform-specific line endings, no locale-specific formatting).

### §3.2 Why Determinism Matters

Determinism is a verification condition. If the generator is deterministic:

- **Drift is detectable** — if the committed artifact doesn't match the regenerated artifact, that's drift. CI can detect it.
- **Bugs are reproducible** — if the generator produces wrong output, the bug can be reproduced by re-running the generator.
- **Trust is possible** — the artifact is a function of its inputs. The inputs are canonical. Therefore the artifact is canonical.

If the generator is NOT deterministic, none of these hold. The artifact is just "what the generator happened to produce this time."

### §3.3 The Hardcoded-Path Anti-Pattern

The most common determinism violation is hardcoded paths. A generator that reads from `/home/z/my-project/` is NOT deterministic across environments — it only works on one developer's machine.

PR #314 fixed this for `architecture-boundary-lint.py` (the hardcoded `REPO_ROOT = Path("/home/z/my-project")` was replaced with dynamic `Path(__file__).resolve().parent.parent`). The fix is documented in [SDLC-02](SDLC-02-Governance.md) §3.2 — the PASS/FAIL/UNVERIFIED invariant was being violated because the generator found 0 files (wrong path) and reported "no violations" (false-positive green).

Per [SDLC-02](SDLC-02-Governance.md) §3.2:

> "A green CI result MUST mean the control actually executed and inspected the boundary — not that the workflow step succeeded without running."

Generators MUST NOT have hardcoded paths. They MUST derive their root from the script's location (`Path(__file__).resolve().parent.parent` or equivalent) or from an environment variable (`GITHUB_WORKSPACE` in CI).

---

## §4. The Reproducibility Invariant

### §4.1 What Reproducibility Means

A generator is reproducible if: any CI runner can regenerate the artifact, given the same inputs.

This means:
- The generator's dependencies are declared (in `composer.json`, `requirements.txt`, or equivalent).
- The generator's runtime is portable (no environment-specific assumptions).
- The generator's output is committed (so drift is detectable in PR review).

### §4.2 The CI Regeneration Check

The CI regeneration check is the verification condition for reproducibility:

```
  ┌────────────────────────────────────────────┐
  │  CI REGENERATION CHECK                     │
  ├────────────────────────────────────────────┤
  │  1. Run the generator on the CI runner     │
  │     (with the same inputs as the commit)   │
  ├────────────────────────────────────────────┤
  │  2. Compare the regenerated output to the  │
  │     committed artifact                     │
  ├────────────────────────────────────────────┤
  │  3. If they match → CI passes (drift-free) │
  │     If they don't match → CI fails         │
  │     (drift detected; committed artifact    │
  │     is stale)                              │
  └────────────────────────────────────────────┘
```

### §4.3 Current State (PR #314)

PR #314 added the "Verify scan coverage" step to `architecture-boundary-lint.yml`. This is a partial CI regeneration check — it verifies that `files_scanned > 0` (the generator actually ran). It does NOT yet verify that the regenerated output matches the committed artifact (because architecture-boundary-lint's output is to stdout/stderr, not to a committed file).

The full CI regeneration check (compare regenerated output to committed artifact) is a future expansion. The current check is necessary but not sufficient.

### §4.4 The Self-Test (Negative Regression Test)

PR #314 also added the `--self-test` flag to `architecture-lint` (the PHP linter). This is a negative regression test — it verifies the generator (in this case, the lint's structural completeness check) correctly detects missing files.

Per [SDLC-03](SDLC-03-InterfaceFreeze.md) §5.3:

> "A structural checker that only tests the happy path isn't sufficient."

The self-test is the negative regression test. It verifies the generator's failure mode (does it actually fail when it should?). Without the self-test, a generator that silently produces empty output (false-positive green) is indistinguishable from a generator that correctly produces empty output (no violations).

---

## §5. The Source-Truth Invariant

### §5.1 What Source-Truth Means

A generator's output reflects its inputs at generation time. The artifact is a FUNCTION of the inputs — nothing more, nothing less.

This means:
- The artifact contains NO information that isn't in the inputs.
- The artifact contains ALL the information from the inputs that the generator is designed to extract.
- The artifact is NOT a curated or edited version of the inputs.

### §5.2 Why Source-Truth Matters

If the artifact contains information not in the inputs (e.g., a manually-added note), then:
- The artifact can drift from the inputs (the note becomes stale).
- The artifact's provenance is unclear (which part came from the generator, which was manual?).
- The artifact can't be regenerated cleanly (the manual note would be lost on regeneration).

If the artifact omits information from the inputs (e.g., the generator filters out certain edges), then:
- The artifact is incomplete (consumers don't see the full picture).
- The filter is itself a decision (who decided what to filter?).
- The filter should be documented in the generator's configuration, not in the artifact.

### §5.3 The Generator Manifest

To make source-truth verifiable, every generated artifact includes a GENERATOR MANIFEST:

```markdown
<!-- GENERATED ARTIFACT — Do not edit manually. -->
<!-- Generated from: <input files> + <governance resolutions> + <admission state> -->
<!-- Reproducible by: scripts/<generator-script> -->
<!-- Authority: <what the artifact is authoritative for> -->
<!-- Generated by: <Task ID + date> -->
```

This manifest:
- Declares the artifact is generated (not authored).
- Lists the inputs (so consumers can verify source-truth).
- Names the generator script (so consumers can reproduce).
- Declares the artifact's authority (what it's authoritative for, what it isn't).
- Records the generation event (Task ID + date).

The manifest is at the TOP of the artifact. Any consumer reading the artifact sees the manifest first. If the consumer wants to verify the artifact, they run the generator and compare.

---

## §6. The No-Hidden-State Invariant

### §6.1 What No-Hidden-State Means

A generator reads ONLY from its declared inputs. It does NOT read from:

- Environment-specific paths (e.g., `/home/z/my-project/` — per §3.3).
- Network resources (e.g., fetching data from a URL).
- Undeclared configuration files (e.g., reading `.env` if `.env` isn't in the inputs list).
- Global state (e.g., reading from a database).

### §6.2 Why No-Hidden-State Matters

Hidden state breaks reproducibility. If the generator reads from `/home/z/my-project/`, the artifact can only be regenerated on a machine with that path. If the generator reads from a network resource, the artifact can't be regenerated offline. If the generator reads from `.env`, the artifact depends on a file that isn't committed.

The generator's input set must be EXPLICIT and COMMITTED. Anything else is hidden state.

### §6.3 The Architecture-Boundary-Lint Example

PR #314 fixed the architecture-boundary-lint's hidden state (the hardcoded `REPO_ROOT = Path("/home/z/my-project")`). The fix:

```python
# Before (PR #314):
REPO_ROOT = Path("/home/z/my-project")

# After (PR #314):
REPO_ROOT = Path(__file__).resolve().parent.parent
```

The "after" version derives `REPO_ROOT` from the script's location. The script's location is part of the committed repository. Therefore `REPO_ROOT` is reproducible across environments.

This is the no-hidden-state invariant: the generator's inputs are derived from committed sources, not from environment-specific paths.

---

## §7. Current Generators in DGLab

### §7.1 Architecture-Lint (PHP)

- **Script:** `Architecture/Verification/lint/run.php`
- **Inputs:** All `.md` files under `Architecture/` (recursive).
- **Output:** Stderr report + exit code (0 = pass, 1 = violations, 2 = error).
- **Self-test:** `--self-test` flag (added PR #314).
- **Authority:** Reference existence, misattribution phrases, structural completeness for Architecture/ documentation.

### §7.2 Architecture-Boundary-Lint (Python)

- **Script:** `scripts/architecture-boundary-lint.py`
- **Inputs:** All `.php` files under `packages/*/src/` and `app/` (recursive), plus `.github/architecture-export-allowlist.yaml`.
- **Output:** JSON summary on stdout + human-readable report on stderr.
- **CI step:** "Verify scan coverage" (added PR #314) — checks `files_scanned > 0`.
- **Authority:** Ring-boundary enforcement, service-locator prohibition, export-allow-list enforcement for executable source code.

### §7.3 Build-Order Generators (Planned)

- **Scripts:** `scripts/generate-core-build-order.py` (planned), `scripts/generate-hub-build-order.py` (planned).
- **Inputs:** CORE-DECLARED-DAG.md + CORE-VERIFIED-DAG.md + SDLC admission state + governance resolutions (for Hub: HUB-DECLARED-DAG.md + HUB-VERIFIED-DAG.md + same).
- **Output:** `Architecture/Core/CORE-BUILD-ORDER.md` (generated artifact), `Architecture/Hub/HUB-BUILD-ORDER.md` (generated artifact).
- **Authority:** Topological build waves derived from the two-DAG model.
- **Status:** NOT YET IMPLEMENTED. The current `CORE-BUILD-ORDER.md` and `HUB-BUILD-ORDER.md` are partially hand-authored. The generators are a future expansion (per the Tech-Lead directive to make the architecture pipeline more authoritative).

### §7.4 DAG Generators (Planned)

- **Scripts:** `scripts/generate-declared-dag.py` (planned), `scripts/generate-verified-dag.py` (planned).
- **Inputs (Declared):** All blueprint files (Upward/Downward dependency lists).
- **Inputs (Verified):** All `composer.json` files + all source `use` statements + filesystem evidence.
- **Output:** `Architecture/Core/CORE-DECLARED-DAG.md`, `Architecture/Core/CORE-VERIFIED-DAG.md`, `Architecture/Hub/HUB-DECLARED-DAG.md`, `Architecture/Hub/HUB-VERIFIED-DAG.md`.
- **Authority:** The two DAGs that gate Eligible(X) (per [SDLC-02](SDLC-02-Governance.md) §2).
- **Status:** NOT YET IMPLEMENTED. The current DAG artifacts are partially hand-authored. The generators are a future expansion.

---

## §8. The CI Regeneration Check (Future Expansion)

### §8.1 The Goal

The CI regeneration check verifies that committed generated artifacts match the output of running the generator on the same inputs. If they don't match, the committed artifact is stale (drift detected).

### §8.2 The Workflow Step

```yaml
- name: Regenerate build order
  run: python3 scripts/generate-hub-build-order.py > /tmp/regenerated.md

- name: Compare regenerated to committed
  run: |
    if ! diff -q /tmp/regenerated.md Architecture/Hub/HUB-BUILD-ORDER.md > /dev/null; then
      echo "::error::Hub build order is stale. Regenerate via: python3 scripts/generate-hub-build-order.py > Architecture/Hub/HUB-BUILD-ORDER.md"
      diff /tmp/regenerated.md Architecture/Hub/HUB-BUILD-ORDER.md
      exit 1
    fi
    echo "✓ Hub build order is up-to-date"
```

### §8.3 Status

This is a FUTURE EXPANSION. The current CI checks (Path Gate, pr-title-lint, architecture-lint, architecture-boundary-lint) do NOT include the regeneration check for build orders or DAGs. The current generators (architecture-lint, architecture-boundary-lint) don't produce committed artifacts — they produce stdout/stderr.

The regeneration check is planned for the post-rewrite phase, after the generators (§7.3, §7.4) are implemented.

---

## §9. The Blind-Spot Doctrine Cross-Reference

### §9.1 The Audit-Is-A-Starting-Point Rule

Per the Blind-Spot Doctrine (per [SDLC-02](SDLC-02-Governance.md) §5 rule #1):

> "An audit is a starting point, not a complete inventory."

This applies to generators: a generator's output is a starting point, not a complete inventory of the inputs. The generator extracts what its algorithm extracts; it may miss things that a different algorithm would catch.

For example, `architecture-boundary-lint` scans PHP `use` statements. It does NOT scan:
- `require` / `include` statements.
- String-based class references (e.g., `$class = 'SovereignStack\Hub\Identity\Entity\User'; $class::method()`).
- Dynamic class loading.

These are KNOWN gaps. The generator's output is a starting point — it catches the common case (explicit `use` statements) but not every case.

### §9.2 The Verification-Condition Rule

Per the Blind-Spot Doctrine (per [SDLC-02](SDLC-02-Governance.md) §5 rule #2):

> "A finding is closed only when its verification condition passes — not when code changes, not when CI is green."

For generators, this means: a generator's output is NOT verified by "the generator ran and produced output." The verification condition is: "the output matches the inputs" (source-truth, §5) AND "the output is reproducible" (reproducibility, §4) AND "the output is deterministic" (determinism, §3).

PR #312's CI false-positive (architecture-boundary-lint scanned 0 files, reported "no violations", workflow step succeeded) was a violation of this rule. The generator produced output ("no violations") but the verification condition ("the generator actually scanned files") was NOT checked. PR #314's "Verify scan coverage" step fixed this.

### §9.3 The CI-Green-≠-Verification Rule

Per the Blind-Spot Doctrine (per [SDLC-02](SDLC-02-Governance.md) §5 rule #6):

> "CI green ≠ verification conditions met."

For generators, this means: a generator's CI step exiting 0 is NOT the same as the generator being correct. The CI step must verify the SPECIFIC conditions (determinism, reproducibility, source-truth, no-hidden-state). A generator that silently produces empty output exits 0 but is not correct.

---

## §10. What This Document Does NOT Specify

- **Spiral Deepening model** — see [SDLC-01](SDLC-01-Foundations.md).
- **Two-DAG governance / Eligible formula** — see [SDLC-02](SDLC-02-Governance.md).
- **Interface freeze / ADR format** — see [SDLC-03](SDLC-03-InterfaceFreeze.md).
- **Cooldown mechanics** — see [SDLC-04](SDLC-04-CooldownMechanics.md).
- **AI-assisted development protocol** — see [SDLC-05](SDLC-05-AI-Assisted-Development-Protocol.md).
- **Specific generator implementations** — see the scripts themselves (`Architecture/Verification/lint/run.php`, `scripts/architecture-boundary-lint.py`).
- **The four-tool lint separation** — see [SDLC-02](SDLC-02-Governance.md) §3.1.

---

## §11. Provenance

This document is part of the SDLC rewrite (Batch 2, PR #318, 2026-10-07), per Tech-Lead directive: "then 3 SDLC the next" (after Core Batch 1).

**Doctrines applied (per Tech-Lead directive):**
- Blind-Spot Doctrine (banner + §9 cross-references the 3 relevant binding rules)
- Nuclear-Grade Doctrine (cross-reference at §4.4 self-test — depth 5 binding affects what generators must verify)
- Integrity Gate (§4.2 CI regeneration check is the between-PR analog of convergence criteria)
- Two-DAG Governance (§1 the entire concept of derived artifacts; §2 generator contract; §7.3-7.4 planned DAG generators)
- FROZEN-CONTRACTS (§7.1 architecture-lint checks that frozen structural files exist; the generator's authority includes structural completeness)

**Verification conditions for this document:**
- Architecture-lint: scans this file for invalid tokens, misattribution phrases, structural completeness. Must pass.
- Cross-reference integrity: every link points to a canonical document.
- Doctrine application: every doctrine listed in §11 is actually applied in the document body.

This document is a starting point. It is not a complete specification. The number of generators specified here is not the number of generators that exist. The current generators (§7.1, §7.2) are partially specified; the planned generators (§7.3, §7.4, §8) are aspirational and will be specified in detail when implemented.
