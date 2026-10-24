# NeoSPX Decision Register

**Last Updated:** 2026-10-24  
**Specification:** NEOSPX-EBNF-GRAMMAR-SPECIFICATION.md (Revised 3)  
**SHA-256:** 6aaf636e3b43f32c202aeae359b6676fa91e13006ee2854116a6adc1f3b2323a  

---

## Decision Register

| Decision ID | Title | Status | Owner | Date Decided | Affected Milestones | Resolution | Evidence |
|-------------|-------|--------|-------|--------------|---------------------|------------|----------|
| OD-01 | PHP expressions verbatim | **APPROVED** | Tech Lead | 2026-10-20 | M1-M5 | Latte strategy; PHP expressions verbatim in templates | SPX-EASE §5.1.1 |
| OD-02 | Verbose keywords, single canonical form | Tech Lead | 2026-10-20 | M1-M5 | Anka strategy; one written form per construct | SPX-EASE §5.1.2 |
| OD-03 | XML-style component tags | Tech Lead | 2026-10-20 | M1-M5 | Explicit `<s:...>` tags | SPX-EASE §5.1.2 |
| OD-04 | HTML-superset | Tech Lead | 2026-10-20 | M1-M5 | HTML-superset base syntax | ADR-005 |
| OD-05 | Full 12+ directive set | Tech Lead | 2026-10-20 | M1-M5 | Full directive set | ADR-005 |
| OD-EXPR-BOUNDARY | **APPROVED** (2026-10-23) | Tech Lead | M2+ | Complex exprs must be parenthesized | ADR-023 pending |

---

## Pending Decisions (Awaiting Owner)

| Decision ID | Title | Status | Owner | Options | Recommended | Blocks |
|--------------|-------|--------|-------|--------|-------------|--------|
| ADR-023 | NeoSPX canonical identity | Tech Lead | Publication | Approve ADR-023 / Amend ADR-005 | Approve ADR-023 | Publication |
| ADR-022 | Auth transport (LMS) | Founder | LMS HTTP | Session cookies / JWT / Both | Session cookies (same-origin) | LMS HTTP |
| ADR-022 | MySQL integration-test contract | Founder | CI | MySQL service in CI | Configure MySQL service | CI |
| F-EASE-03 | Context-aware escaping | Tech Lead | M4 | Automatic (Latte) / Explicit opt-in | Automatic | Compiler |
| F-EASE-06 | Dual surface (single vs dual syntax) | Tech Lead | M3 | Single / Dual | Single (v1) | Parser |
| F-EASE-11 | Debug info in output | Tech Lead | M4 | Defer / Include | Defer to v1.1 | Compiler |
| F-NAM-06 | Component doc format | Tech Lead | M5 | JSON-first / JSON+YAML | JSON-first | Integration |
| F-VUE-02 | Dual authoring modes | Tech Lead | M3 | Defer / Implement | Defer to v2 | Parser |
| F-VUE-04 | Build-free vs compiled | Milestone 4 | Compile / Defer | Compile (MVP) | Compiler |
| F-VUE-05 | Scoped styles vs SCSS | Tech Lead | M5 | Scoped / SCSS | Scoped | Integration |
| F-COMPILER-04 | Fault-tolerant parsing | Tech Lead | M3 | Implement / Defer | Implement (panic mode) | Parser |
| F-COMPILER-12 | Early cutoff | Tech Lead | M4 | Implement (hash) / Defer | Implement (hash) | Compiler |
| F-FW-01 | Feature-sliced convention | Tech Lead | LMS | Implement / Defer | Implement (hand-wired) | LMS |
| F-FW-05 | Onboarding SUS ≥68 | Founder | LMS | Implement / Defer | Defer | LMS |
| F-REC-01 | Connecting theme | Owner | Research | Owner selects | Owner decides | Research |
| F-REC-02 | SuperPHP placement | Owner | Research | Separate section | Owner decides | Research |
| F-REC-03 | Half Exceptions | Owner | Research | Owner confirms | Owner confirms | Research |

---

## Open Decisions Requiring Owner Input

| Decision ID | Description | Owner | Blocking | Recommended |
|--------------|-------------|-------|----------|------------|
| ADR-023 | NeoSPX canonical identity (supersedes ADR-005) | Tech Lead | Publication | **Approve ADR-023** |
| ADR-022 | Auth transport (LMS) | Founder | LMS HTTP | Session cookies (same-origin) |
| ADR-022 | MySQL integration-test contract | Founder | CI | Configure MySQL service |
| F-EASE-03 | Context-aware escaping | Tech Lead | M4 | Automatic (Latte-style) |
| F-EASE-06 | Dual surface syntax | Tech Lead | M3 | Single syntax v1 |
| F-EASE-11 | Debug info in output | Tech Lead | M4 | Defer to v1.1 |
| F-NAM-06 | Component doc format | Tech Lead | M5 | JSON-first |
| F-VUE-02 | Dual authoring modes | Tech Lead | M3 | Defer to v2 |
| F-VUE-04 | Build-free vs compiled | Tech Lead | M4 | Compile (MVP) |
| F-VUE-05 | Scoped styles vs SCSS | Tech Lead | M5 | Scoped styles |
| F-COMPILER-04 | Fault-tolerant parsing | Tech Lead | M3 | Implement (panic mode) |
| F-COMPILER-12 | Early cutoff | Tech Lead | M4 | Implement (hash-based) |
| F-FW-01 | Feature-sliced convention | Tech Lead | LMS | Implement (hand-wired) |
| F-FW-05 | Onboarding SUS ≥68 | Founder | LMS | Defer |
| F-REC-01 | Connecting theme | Owner | Research | Owner selects |
| F-REC-02 | SuperPHP placement | Owner | Research | Separate section |
| F-REC-03 | Half Exceptions | Owner | Research | Owner confirms |

---

## Approved ODs (Locked)

| OD | Title | Approved | Date | Evidence |
|-----|-------|----------|------|----------|
| OD-01 | PHP expressions verbatim | Tech Lead | 2026-10-20 | SPX-EASE §5.1.1 |
| OD-02 | Verbose keywords, single canonical | Tech Lead | 2026-10-20 | SPX-EASE §5.1.2 |
| OD-03 | XML-style component tags | Tech Lead | 2026-10-20 | SPX-EASE §5.1.2 |
| OD-04 | HTML-superset | Tech Lead | 2026-10-20 | SPX-EASE §5.1.2 |
| OD-05 | Full 12+ directive set | Tech Lead | 2026-10-20 | SPX-EASE §5.1.2 |
| OD-EXPR-BOUNDARY | **APPROVED 2026-10-23** | Tech Lead | 2026-10-23 | SPX-EASE §5.1.1 / ADR-023 |

---

## Unresolved ODs (Deferred)

| OD | Title | Status | Deferred To | Notes |
|------|-------|--------|-------------|-------|
| F-EASE-03 | Context-aware escaping | W | Milestone 4 | Automatic vs explicit |
| F-EASE-06 | Dual surface syntax | W | Milestone 3 | Single vs dual |
| F-EASE-11 | Debug info | W | Milestone 4 | Defer vs include |
| F-NAM-06 | Component doc format | W | Milestone 5 | JSON-first vs JSON+YAML |
| F-VUE-02 | Dual authoring modes | W | Milestone 3 | Defer vs implement |
| F-VUE-04 | Build-free vs compiled | W | Milestone 4 | Compile vs defer |
| F-VUE-05 | Scoped styles vs SCSS | W | Milestone 5 | OD-09 related |
| F-COMPILER-04 | Fault-tolerant parsing | W | Milestone 3 | Panic mode |
| F-COMPILER-12 | Early cutoff | W | Milestone 4 | Hash-based cutoff |
| F-FW-01 | Feature-sliced convention | W | LMS track | Conventions |
| F-FW-05 | Onboarding SUS ≥68 | W | LMS handoff | Founder owns UI |
| F-REC-01 | Connecting theme | W | Research | Owner decision |
| F-REC-02 | SuperPHP placement | W | Research | Owner decision |
| F-REC-03 | Half Exceptions | W | Research | Owner decision |

---

## Security Decisions (F-COMPILER-05)

| Decision | Options | Recommended | Status |
|----------|---------|-------------|--------|
| Allowlist scope | Minimal / Extended / Configurable | Minimal pure functions | Awaiting Tech Lead |
| Config format | Hardcoded / YAML / PHP | YAML config file | Awaiting Tech Lead |
| Enforcement layer | Compile-time / Runtime / Both | Compile-time AST + Runtime disable_functions | Awaiting Tech Lead |
| Enforcement layer | Compile-time only / Runtime only / Both | Compile-time AST + Runtime disable_functions | Awaiting Tech Lead |

---

*Last Updated: 2026-10-24 | Specification: NEOSPX-EBNF-GRAMMAR-SPECIFICATION.md (SHA-256: 6aaf636e3b43f32c202aeae359b6676fa91e13006ee2854116a6adc1f3b2323a)*
