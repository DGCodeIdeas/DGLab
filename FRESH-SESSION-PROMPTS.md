# Fresh Session Prompts — DGLab Research First, Backend Second

Use these prompts after opening the repository in the relevant tool. The startup/reconnaissance prompt is first. Do not ask the agent to implement LMS features until the research gate has been completed and reviewed.

## Prompt 1 — Z.ai: research lead and evidence collector

You are starting a fresh DGLab session. Read the root `AGENTS.md` and `DGLab-FRESH-SESSION-HANDOFF.md` if present. Follow their evidence labels and owner-approval boundaries.

**First, perform read-only reconnaissance only.** Report current branch, dirty state, current `origin/main` SHA, worktrees, local-only artifacts, relevant nested instructions, frozen contracts, ADR index, and current research-agenda presence. Never echo credentials. Do not change files, create branches, commit, push, merge, format, rebase, or reset. Treat the supplied historical baseline as unverified until checked.

Then begin the research-first work package. Your first research deliverable is a reconciliation matrix for agenda renditions V1–V7, including:
- three connecting-theme wordings and how they differ;
- four Half Exceptions formulations and their status as user-originated hypotheses;
- SuperPHP/SPX's placement divergence (“handle separately” versus inclusion as a sixth section);
- what is confirmed by original owner text, what is only an agent interpretation, and what cannot be resolved without owner input.

Next, research SPX/SuperPHP architecture and naming as a standalone track. Evaluate the proposed pipeline (authoring syntax → syntax driver → public language-neutral component document → AST → validation/semantic analysis/compilation), format-neutral information model, JSON-first versus JSON+YAML, schema/semantic validation, provenance/source preservation, diagnostics, versioning, extension rules, security, and third-party interoperability. Use primary sources and existing comparable systems; cite dates/versions and limitations. Re-check candidate naming collisions in the actual current repository.

Then produce the four product-research reports: platform integrity/copyright/fair disputes; avoiding the YouTube model; Half Exceptions; Cancel-Anytime paradox. Separate facts, claims, hypotheses, analogies, implications, and recommendations. The supplied Cancel-Anytime sentence is a hypothesis, not a verified empirical fact. Do not portray Half Exceptions as established engineering terminology.

Deliver reports and a proposed canonical agenda for owner review. Do not create or modify `Architecture/CrossCutting/RESEARCH-AGENDA.md`, ADRs, code, or governance records without explicit authorization. Do not implement the SuperPHP runtime or rename it. Keep research out of LMS implementation PRs. End with a decision register and the next single owner decision needed.

## Prompt 2 — Kilo Code: independent research and source verification

Act as an independent research/review agent, not a second implementer. Read the same project instructions and handoff. Start with read-only repository reconnaissance and independently verify the facts that are verifiable locally. Do not assume Z.ai's report is correct; record agreement/disagreement with paths, commands, commit SHAs, and sources.

Work on a separate research assignment from Z.ai to avoid duplicated effort:
1. Verify the SPX pipeline/design alternatives against primary specifications and comparable implementations.
2. Independently investigate the four product-research tracks, emphasizing counterarguments, source quality, uncertainty, and design trade-offs.
3. Inspect the V1–V7 reconciliation supplied in the handoff and identify where owner input is genuinely necessary.
4. Verify the reported naming collision audit against current DGLab ADRs/registers/source and label external acronym risks separately.
5. Review the proposed research agenda for contradictions, unsupported empirical claims, accidental implementation requirements, and premature abstractions.

Do not modify repository files or publish the agenda. Produce an independent review with:
- sources and publication dates;
- verified vs reported statements;
- contradictory evidence and limits;
- design implications, not mandates;
- unresolved owner decisions;
- critique of Z.ai's research when available.

Do not start LMS implementation until the research gate is accepted. Do not create branches, commits, PRs, ADRs, or repository records unless separately authorized.

## Prompt 3 — Research integration / owner review

Combine Z.ai's research and Kilo Code's independent review without flattening disagreement. Build:
- a track-by-track evidence matrix;
- a source quality/uncertainty register;
- a V1–V7 reconciliation;
- a proposed canonical agenda;
- a separate SPX report;
- a list of owner decisions in priority order.

For every disagreement, state the competing interpretations and evidence. Do not choose on the owner's behalf. Explicitly label the agenda as a draft. Ask the owner to approve the research package and agenda wording before any repository change.

## Prompt 4 — LMS backend phase (only after research acceptance)

The research gate has been reviewed. Re-read current repository instructions, ADRs, frozen contracts, current main and PR #364. Do not assume old state remains current.

Prepare a verified capability matrix with separate columns for implemented, unit-tested, real-MySQL integration-tested, and end-to-end demonstrated. Review PR #364 rather than duplicating published-course discovery. Before HTTP implementation, identify the unresolved owner decisions for auth transport, deployment origin, API contract, roles/permissions, and MySQL test infrastructure.

Propose the next single bounded implementation increment with:
- files/interfaces likely to change;
- dependencies and non-goals;
- exact acceptance criteria;
- unit and real-MySQL tests;
- API/security impact;
- integration owner and review plan.

Do not start until the owner/SAAI explicitly authorizes the increment. Follow SDLC v5.1. Do not infer authorization to merge or publish ADR-022.

## Prompt 5 — Claude: independent founder review

You are an independent senior architect and security reviewer of DGLab's LMS backend. Treat the repository as the source of truth, not this handoff. Verify the current commit and inspect implementation, migrations, API contracts, ADRs, tests, and CI evidence.

Do not rubber-stamp the code. For every claimed capability, classify evidence as:
1. source implementation exists;
2. unit tests exist/pass;
3. real-MySQL integration test exists/pass;
4. complete end-to-end flow exists/pass.

Look specifically for:
- dependency-boundary and composition-root errors;
- authentication, authorization, role checks, session/JWT lifecycle, CSRF, cookie flags, CORS, token leakage and logout/revocation behavior;
- IDOR/cross-user access, unpublished-course exposure, enrollment/content authorization;
- transaction boundaries, uniqueness, foreign keys, race conditions, duplicate submissions and progress consistency;
- request validation, error taxonomy, serialization, pagination limits, API versioning and contract drift;
- migration/recovery safety and environment setup;
- tests that mock away the behavior they claim to verify;
- unhandled failure modes and observability gaps;
- mismatch between docs, actual routes, schemas, and runtime behavior.

For each finding, give severity, affected paths/lines, concrete exploit or failure scenario, evidence, and a proposed fix. Separate confirmed defects from hypotheses requiring execution. Do not edit files, commit, or merge. Finish with a prioritized verification plan and a capability matrix. The human owner makes all acceptance and release decisions.
