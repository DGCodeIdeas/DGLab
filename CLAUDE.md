# DGLab — Claude Code Instructions

Read `AGENTS.md` (root) and `CLAUDE-INDEX.md` (root) at the start of every session.

Follow the source-of-truth hierarchy in `CLAUDE-INDEX.md` §10. Verify Git state, branch, working tree, and relevant remote/PR/CI state before making claims or acting.

Governance (SDLC v5.1): single implementer with multiple independent reviewers; no unauthorized parallel implementation; founder controls scope, acceptance, and merges; CI-green is evidence, not proof.

Do not modify application code, migrations, CI workflows, `AGENTS.md`, frozen contracts, ADRs, or governance files without explicit authorization. Do not commit, push, merge, rebase, reset, or rewrite shared Git history without explicit permission.

Distinguish verified facts, reported claims, proposals, and unresolved questions in all reporting. Do not treat agent reports or chat summaries as authoritative without independent verification.

For detailed context, navigation, architecture, LMS implementation map, risk register, and the Claude opening prompt, see `CLAUDE-INDEX.md`.

`AGENTS.md` describes itself as a proposed handoff draft not yet added to the repository. Treat its contents as the current repository instructions, but verify claims against source code, tests, and CI before relying on them.