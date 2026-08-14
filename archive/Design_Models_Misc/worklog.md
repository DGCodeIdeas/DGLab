---
Task ID: 1
Agent: main (Super Z)
Task: Visualize Application Structure as a Wheel — Core / Hub / Inner Spokes / Inner Rim / Outer Spokes / Outer Rim, with depth-variable Pulse flow from an Entity on the Outer Rim.

Work Log:
- Read uploaded chat export `zai_export_2026-08-04(1).json` to confirm prior context (DGLab blueprints) — determined the user's Wheel description is a fresh conceptual request.
- Classified task as Type 2 (Data Visualization & Charts) — structural diagram with concentric rings + radial spokes + flow paths.
- Loaded `charts` skill; consulted `references/radial-grid.md` for radial layout conventions (though the user's Wheel is a custom concentric diagram, not a SWOT/BSC pattern — used custom SVG instead).
- Batched 6 clarifying questions via AskUserQuestion: layer meaning, pulse visualization, entity count, style, spoke count, annotations.
- User answers: app-architecture layer mapping (Core=Domain Model … Outer Rim=UI/Edge); BOTH static PNG + animated HTML; 3 entities with varying pulse depths; Blueprint style; 8 inner / 16 outer spokes; all annotation types.
- Wrote `/home/z/my-project/scripts/build_wheel.py` — generates SVG with 6 concentric layers, 8 tapered thick inner spokes, 16 tapered thin outer spokes, depth axis (top), in-wheel layer labels (lower-left gap), 3 entity pulse paths (solid inward arrow + dashed outward arrow + depth endpoint ring), animated SMIL pulse dots (staggered begins), and a right-side legend panel with layer descriptions + pulse flow explanation + per-entity depth table.
- First render had Entity C label clipped (SVG viewBox too short for CY=500 + R=420 + label offset). Fixed: CY=470, CANVAS_H=1100, refined label text-anchor logic per angle sector (right=start, left=end, top/bottom=middle).
- Screenshot via Playwright at deviceScaleFactor=2 → 2800×2222 PNG.
- VLM verification (z-ai vision) confirmed: all 6 layers visible and labeled, spokes correctly thick/thin differentiated, all 3 entity labels (A, B, C) with depth labels fully visible (no clipping), no label/spoke overlap, legend fully readable.

Stage Summary:
- Deliverables (in `/home/z/my-project/download/`):
  - `wheel.html` (23 KB) — animated HTML for live viewing; SMIL `animateMotion` with keyTimes `0;0.4;0.55;1` and keyPoints `0;1;1;0` (inward → pause → outward), staggered begin times (0s, 1.2s, 2.4s).
  - `wheel.png` (668 KB, 2800×2222 @ 2× DPI) — static, dots at depth endpoints, suitable for embedding in docs/slides.
- Persisted script: `/home/z/my-project/scripts/build_wheel.py` (re-runnable for iterations).
- Palette: Blueprint — bg #F8FAFC, primary #1E40AF/#4C6EF5, pulse accent #EA580C (orange), subtle 20px/100px grid background.
- Layer mapping (outside-in): Outer Rim=UI/Edge, Outer Spokes=Adapters, Inner Rim=API, Inner Spokes=Services, Hub=Aggregates, Core=Domain Model.
- 3 example entities: A (shallow, → Outer Spokes), B (medium, → Inner Rim), C (deep, → Core).

---
Task ID: 2
Agent: main (Super Z)
Task: Add full PROMPTS module (§11) for varied AI agents (local VSCode: Cline/Aider/Continue; cloud: Jules/Devin/Claude web/Codex/Copilot Workspace) to SDLC-AGRD-v3.4.md.

Work Log:
- Discovered SDLC-AGRD-v3.4.md already existed in /upload/ with substantive v3.3→v3.4 SDLC refinements (§10 changelog, 414 lines) but NO PROMPTS module. Decision: preserve existing v3.4 content, append §11 as the new PROMPTS module (the user's explicit request).
- Read v3.3 and v3.1 to anchor document voice/style (direct, calls out real failure modes, doesn't oversell, "honest caveat" callouts pattern).
- Read existing worklog (1 prior entry from Task ID 1 — wheel visualization).
- Designed §11 PROMPTS module structure: 14 subsections covering (11.1) why this module exists, (11.2) agent taxonomy matrix, (11.3) shared context block to paste before every task prompt, (11.4) Cooldown 0 prompts, (11.5) Milestone 0 prompts with kill-trigger, (11.6) lap widen prompts (Jules+Cline two-phase), (11.7) lap deepen prompts with per-blueprint relative floor check, (11.8) cooldown prompts (lint-scope expansion + content Ring Lock), (11.9) per-depth-level reference table, (11.10) ADR-gated event prompts (interface freeze violation + OD resolution), (11.11) bet-kill and Milestone-0-kill prompts, (11.12) review-pass prompt (gap-hunting that found real issues across v3.x), (11.13) worklog discipline + cloud-local hand-off bundle, (11.14) "what this module deliberately does NOT promise" honest-caveat closing.
- All prompts encode v3.4's actual rules: §2.1 interface freeze, §4.3 per-blueprint relative floor (NOT global lap floor — that was the v3.2 spec bug), §6 lint expansion in cooldowns only, §4.2 bet kill at 1.5× time-box, §4 Milestone 0 kill at 8 weeks, §8.1 OD-02 in Cooldown 1 / OD-06 during Milestone 0, §4.3 lap-1 widen constraint excluding CORE-16/HUB-04 until OD-02 resolves, §11.13 append-only worklog, INDEX.md §5.2 37/96 coverage with §9 50% trigger.
- Applied via 3 Edit operations (chunked for safety): (1) bridge text after §10 + §11 header through §11.3, (2) §11.4 through §11.7, (3) §11.8 through §11.14.
- Verified final structure: 1314 lines, 14 subsections under §11, clean section header hierarchy, file ends naturally on §11.14's closing paragraph (no artificial termination marker per Rule 6).
- Mirrored v3.4 from /upload/ to /download/ for user-facing access. SHA256 matches between both locations: f02bc958b598804bf67d4ff3d5e5a6398f2af9b2d93ac7b9200662c7ef95cad3.

Stage Summary:
- Deliverable: SDLC-AGRD-v3.4.md (1314 lines, 1315 with trailing newline) at BOTH:
  - /home/z/my-project/upload/SDLC-AGRD-v3.4.md (matches prior version pattern: v3, v3.1, v3.3 all live in /upload/)
  - /home/z/my-project/download/SDLC-AGRD-v3.4.md (user-facing download directory per Rule 5)
- v3.4 now contains: original §§1–9 SDLC model (preserved unchanged from prior v3.4), §10 v3.3→v3.4 changelog (preserved), §11 PROMPTS module (NEW — 891 lines of new content, 14 subsections, ~9 copy-paste-ready prompt templates in fenced code blocks).
- PROMPTS module is calibrated to v3.4's rules; §11.14 explicitly states prompts need re-calibration when v3.5 lands after lap 1 data (per §10's "stop iterating without lap data" assessment).
- Style consistency: §11 uses the same direct/no-fluff voice as §§1–10, includes "honest caveat" callouts (§11.14) in the same pattern as §§6, 9, and explicitly cites v3.x history (v3.2 floor bug, v3.1 Phase structure reintroducing Radial Incremental) as object lessons inside prompts.
- Hand-off note for next agent: this v3.4 is ready to push as Architecture/CrossCutting/SDLC-AGRD.md per its status line. §11 should be gap-hunted alongside the rest of the document the next time §11.12's review-pass prompt runs (likely after lap 1 data lands, per §10's assessment).

---
Task ID: 3
Agent: main (Super Z)
Task: De-specify agent product names in SDLC-AGRD-v3.4 §11 (make it capability-class-based so Kilo, Zoo, or any future tool slots in by capability match); create a separate MEMORY.md as the entry-point file every agent reads first.

Work Log:
- Read user's two requirements: (1) don't lock in specific agent names — "I might use kilo or zoo(formerly roo) or anything else"; (2) "I need a MEMORY.md."
- Reviewed existing §11.2 taxonomy table (8 product-named rows: Cline/Roo Code, Aider, Continue.dev, Copilot Workspace, Jules, Devin, Claude web, Codex) — needed full refactor to capability-class rows with product names as illustrative examples only.
- Reviewed all "AGENT: <name>" lines and inline "(Cline)"/"(Jules)"/"(Claude web)" step references across §§11.4–11.13 — needed comprehensive sweep.
- Amended §11.2: replaced title "Agent taxonomy — local vs. cloud, capability matrix" → "Agent taxonomy — capability classes, not product names"; added framing paragraph ("This module is agent-agnostic. Capability classes are the requirement; product names are illustrative, not prescriptive. New agents — Kilo, Zoo, future tools — slot in by capability match."); replaced 8-row product-named table with 6-row capability-class table (Local Editor / Local CLI / Local Inline / Cloud PR / Cloud Async / Cloud Conversational) with "Examples (illustrative, not prescriptive)" column; added "Classification rule for a new agent" paragraph (match on capability, not vendor; classify conservatively as Cloud Async when in doubt).
- Amended §11.3: added "Step 0 — read MEMORY.md first" paragraph before the shared context block, making MEMORY.md a non-negotiable pre-task read for every capability class; noted cloud agents must receive MEMORY.md in hand-off bundle since they have no other way to see it.
- First MultiEdit attempt: 36 edits, failed on §11.7 due to case typo (`<BLUEPRINT-id>` vs `<BLUEPRINT-ID>`). Tool processes sequentially (not truly atomic) — §11.4, §11.5, §11.6 edits applied before §11.7 failed.
- Second MultiEdit attempt: 36 edits with corrected casing, but §11.4 old_str no longer matched (already updated by first attempt). Failed on first edit.
- Third MultiEdit attempt: 33 edits covering only the sections that hadn't applied yet (§10 bridge, §11.1 intro, §11.7, §11.8 intro + lint AGENT + content Ring Lock steps, §11.10 ADR full block, §11.10 OD full block, §11.11 bet kill full block, §11.11 Milestone 0 kill full block + closing paragraph, §11.12 review full block, §11.13 worklog template + hand-off scenarios + final paragraph, §11.14 cloud-agent-drift bullet). All 33 applied successfully.
- Verified no stray product-name references remain in prompt templates via grep — only acceptable remaining references are: §1 narrative (examples), §11.2 examples column (explicitly illustrative), §11.13 worklog template (examples in the template field). All AGENT: lines and step references are now capability-class-based.
- Created MEMORY.md at /home/z/my-project/MEMORY.md (279 lines, 11 sections): (1) project identity — 6 rings, 96 blueprints, Pulse, polyrepo; (2) team reality — 1 solo tech lead + AI agents, 1 marketer, 1 media; (3) methodology pointer — v3.4 Spiral Deepening, section-by-section reading guide, key concepts; (4) the 7 rules every agent must internalize (interface freeze, per-blueprint floor, lint in cooldowns, bet kill 1.5×, Milestone 0 kill 8wk, OD sequencing, lap-1 widen constraint); (5) file map — 14 entries with paths and update cadences; (6) vocabulary — 16 terms (Pulse, Ring Lock, depth, lap, widen, deepen, bet, cooldown, Milestone 0, exemplar, ADR, OD, Soft-Freeze violation, per-blueprint relative floor, capability class); (7) current state snapshot (last updated 2026-08-10 — all at zero, Milestone 0 not started); (8) OD status table (6 ODs, all open, with resolution timing per §8.1); (9) known failure modes — v3.x history table (6 versions, each with its flaw and fix); (10) agent conventions — capability-class framing, before-every-task checklist, worklog discipline, hand-off discipline, kill-trigger philosophy; (11) update protocol — when to update MEMORY.md vs worklog, the one exception (§7 state updates by any agent), agent-edit prohibition without tech-lead approval.
- Mirrored MEMORY.md and updated v3.4 to /home/z/my-project/download/. SHA256 verified matching between /upload/ and /download/ for both files.

Stage Summary:
- Deliverables (in /home/z/my-project/download/):
  - MEMORY.md (279 lines, 11 sections) — NEW file, entry-point for any agent on first contact
  - SDLC-AGRD-v3.4.md (1365 lines, up from 1314) — updated with de-specified §11 + MEMORY.md references
- Also at /home/z/my-project/MEMORY.md (canonical location alongside worklog.md) and /home/z/my-project/upload/SDLC-AGRD-v3.4.md (matches v3.x pattern).
- §11.2 now has 6 capability classes (Local Editor / Local CLI / Local Inline / Cloud PR / Cloud Async / Cloud Conversational) with product names as illustrative examples only. Classification rule for new agents included. Conservative-default rule included (treat unknown as Cloud Async).
- Every AGENT: line and every step reference in §§11.4–11.13 now uses capability classes ("any Local Editor Agent," "any Cloud Async Agent," "any Cloud Conversational Agent") with "(§11.2)" citations, never bare product names as the requirement.
- §11.3 Step 0 makes MEMORY.md a non-negotiable pre-task read. §11.13 hand-off bundle explicitly includes MEMORY.md for cloud agents.
- §11.14's agent-drift bullet updated to cover ALL agents (not just cloud), names Kilo/Zoo/future-tools as examples of new agents appearing, and cites the capability-class framing as the mitigation.
- MEMORY.md is curated (not append-only), tech-lead-updated when durable facts change, with one exception: §7 current-state updates allowed by any agent completing a Milestone-0 blueprint / lap step / cooldown, with mandatory worklog audit trail.
- Hand-off note for next agent: MEMORY.md §7 currently shows all-zeros (Milestone 0 not started). The first agent to work on Milestone 0 must update §7's "Last updated" date and matrix snapshot as blueprints are admitted. The §11.3 Step 0 protocol is now the entry point — any task prompt that doesn't start with "read MEMORY.md" is incomplete.

---
Task ID: 4
Agent: main (Super Z)
Task: Create MEMORY_INSTRUCTIONS.md as the operational-process companion to MEMORY.md (which holds durable state).

Work Log:
- Read user's new request: "Also i need a MEMORY_INSTRUCTIONS.md" — fresh ask, distinct from the prior session's de-specify-agents + MEMORY.md work (Task ID 3, already complete per worklog review).
- Read MEMORY.md (279 lines at that point) and worklog.md (3 prior Task IDs) to anchor context: project is DGLab Wheel, methodology is SDLC-AGRD v3.4, MEMORY.md is the durable-STATE entry-point file already created in Task ID 3.
- Designed the STATE/PROCESS split: MEMORY.md = what the project IS (identity, team, methodology, snapshot, ODs, vocabulary, failure history); MEMORY_INSTRUCTIONS.md = HOW to act on a task (boot sequence, pre-task checklist, surface-don't-decide rules, kill-trigger decision tree, hand-off bundle, worklog protocol, anti-patterns). Non-overlapping by design — both files are required reads, neither replaces the other.
- Wrote /home/z/my-project/MEMORY_INSTRUCTIONS.md (410 lines, 12 sections): (1) purpose & relationship table to other files; (2) boot sequence — exact 7-step read order; (3) pre-task checklist with 12 atomic checkboxes; (4) surface-don't-decide — the 7 hard stops (interface freeze violation, global lap floor temptation, mid-bet lint creep, bet kill at 1.5×, Milestone 0 kill at 8wk, OD pre-resolution, MEMORY.md drift) each with trigger / do / do-not / worklog-entry-format; (5) kill-trigger qualitative-difference table — bet kill vs Milestone 0 kill, why conflating them is the #1 agent failure mode; (6) hand-off bundle protocol with full paste-able template and 4 scenarios (Local→Cloud Async, Cloud Async→Local, Local→Local different session, Cloud Conversational→any) plus what does NOT go in a bundle; (7) worklog append protocol with template and do's/don'ts; (8) OD encounter protocol with the OD-02-during-Milestone-0 special case called out; (9) interface freeze protocol — what freezes, what doesn't; (10) MEMORY.md update-eligibility matrix — who may edit which field, with the §7-state exception; (11) anti-patterns table — 10 forbidden patterns that "look reasonable," each with why-it-looks-reasonable / why-forbidden / reference; (12) honest caveats — same voice as v3.4 §11.14, explicit about what this file does NOT promise.
- Cross-referenced from MEMORY.md: §5 file map row added (MEMORY_INSTRUCTIONS.md between MEMORY.md and worklog.md); §10 "Before every task" checklist expanded from 5 steps to 6 (added step 2 = read MEMORY_INSTRUCTIONS.md, renumbered, added surface-at-first-hard-stop citation to step 6).
- Mirrored both files to /home/z/my-project/download/: MEMORY_INSTRUCTIONS.md (new, 410 lines) and MEMORY.md (updated, 282 lines, up from 279). SHA256 verified matching between canonical and mirror for both.

Stage Summary:
- Deliverables (in /home/z/my-project/download/):
  - MEMORY_INSTRUCTIONS.md (410 lines, 12 sections) — NEW file, the operational playbook
  - MEMORY.md (282 lines, up from 279) — updated with cross-references to MEMORY_INSTRUCTIONS.md in §5 file map and §10 pre-task checklist
- Also at canonical locations: /home/z/my-project/MEMORY_INSTRUCTIONS.md and /home/z/my-project/MEMORY.md (alongside worklog.md).
- STATE/PROCESS split is now clean: MEMORY.md answers "what is the project and where is it right now?"; MEMORY_INSTRUCTIONS.md answers "how do I act on a task without breaking the methodology?". Both required reads per the updated MEMORY.md §10 checklist.
- MEMORY_INSTRUCTIONS.md is curated (not append-only), tech-lead-updated when the methodology's process rules change — same curation discipline as MEMORY.md. No exceptions for agent edits (unlike MEMORY.md §7, MEMORY_INSTRUCTIONS.md has no fields an agent may edit without tech-lead approval).
- The 7 hard stops in §4 of MEMORY_INSTRUCTIONS.md encode the same 7 rules from MEMORY.md §4, but in operational form (trigger / do / do-not / worklog format) rather than statement form. This is intentional — agents who skim MEMORY.md's rules get a second exposure in the format they'll actually encounter during a task.
- Hand-off bundle template (§6.1) is the contract for local↔cloud agent transitions. Cloud agents receiving an incomplete bundle must surface it, not improvise — same surface-don't-decide philosophy.
- Anti-patterns table (§11) includes the 10 patterns that have actually bitten prior SDLC versions, with explicit "why it looks reasonable" columns because that's the failure mode — every one of these feels like the right call in the moment.
- Hand-off note for next agent: the boot sequence is now MEMORY.md → MEMORY_INSTRUCTIONS.md → worklog.md (last 3) → v3.4 §11.x prompt → shared context block → execute → worklog append. The §11.3 Step 0 protocol (read MEMORY.md first) remains correct but should be interpreted as "read MEMORY.md AND MEMORY_INSTRUCTIONS.md" — the v3.4 §11.3 wording pre-dates MEMORY_INSTRUCTIONS.md and may be worth a minor update on the next v3.4 revision pass (defer to tech lead, not an agent edit).
