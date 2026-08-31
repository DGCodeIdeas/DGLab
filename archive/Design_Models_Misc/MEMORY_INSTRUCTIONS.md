# MEMORY_INSTRUCTIONS.md — Cross-Session Memory Management for DGLab

**Project:** DGLab (Sovereign Stack)  
**Applies to:** All AI agents (local, cloud, any vendor)  
**Last Updated:** 2026-08-10  
**Status:** Canonical — governs how explicit memory is stored, updated, and retired

---

## 1. Two Kinds of Memory

Distinguish carefully — they serve different purposes and have different rules.

| Type | What It Is | How It Works | Who Controls It |
|------|-----------|--------------|-----------------|
| **Dream Memory** | Auto-consolidated conversation summaries | The platform (Kimi, Claude, etc.) compresses conversations nightly and stores patterns | Automatic — you don't manage this |
| **Explicit Memory** (`memory_instruction`) | Short standing instructions you deliberately save | You say "remember that..." and the agent stores a concise instruction | You — via explicit request only |

**This document governs Explicit Memory only.** Dream Memory is passive context; Explicit Memory is active directive.

---

## 2. When to Store Explicit Memory

Store an instruction only when you **explicitly** tell an agent to remember something. A fact merely mentioned in conversation is NOT a memory request.

**Valid triggers (say these exactly or equivalently):**
- "Remember that..."
- "Store this..."
- "Note that..."
- "Don't forget that..."
- "Add to memory..."
- "Keep this in mind..."
- "以后记住..." / "记一下..." / "别忘了..."

**If uncertain whether the user asked you to remember:** Ask for clarification. Never assume.

**What to store:**
- Project conventions ("We use snake_case for methods, camelCase for properties")
- Personal preferences ("I prefer diff output before committing")
- Domain facts that affect output ("My target market is East African hospitality")
- Workflow habits ("I review every ADR before Ring Lock")
- Tool preferences ("I use Kilo for implementation, not Cline")

**What NOT to store:**
- Anything about minors (under 18) — absolute prohibition, even if explicitly requested
- Race, ethnicity, religion — unless clearly relevant to the project domain and explicitly requested
- Criminal history or allegations — prohibited
- Precise location data (addresses, coordinates) — prohibited
- Political affiliations or opinions — prohibited
- Health/medical information (conditions, diagnoses, mental health, sex life) — prohibited
- Temporary state ("I'm tired today" — Dream Memory handles this)
- Conversation content or file contents — Dream Memory handles this; use file attachments instead

---

## 3. Memory Format and Constraints

Every stored instruction must follow these rules:

| Constraint | Rule |
|-----------|------|
| **Length** | ≤ 500 characters per instruction |
| **Language** | Same language as the user's request |
| **Form** | Single concise instruction — one fact, one rule, one preference |
| **Scope** | Project-relevant only — no personal life, no general knowledge |
| **Count** | Maximum 50 instructions total across all sessions |

**Good example:**
```
User: "Remember that I always want PHPUnit tests generated alongside implementation code."
Stored: "Generate PHPUnit tests alongside every implementation task."
```

**Bad example (too long, mixes multiple facts):**
```
User: "Remember that I use Kilo for implementation, I hate verbose comments, 
      and I want you to check run.php before every merge."
Stored as one: ❌ — split into three separate instructions.
```

**Bad example (personal, not project):**
```
User: "Remember that I have diabetes."
Stored: ❌ — health information, prohibited.
```

---

## 4. Memory Management Operations

### 4.1 Add

Use when the user explicitly asks you to store something new.

**Process:**
1. Verify the content is allowed (§2 prohibited list)
2. Distill to a single concise instruction (≤ 500 chars)
3. Store in the same language as the request
4. Confirm: "Added memory #[N]: [content]"

**Never say "I'll remember" without actually storing.** The storage must happen.

### 4.2 Replace

Use when:
- The user corrects previously stored information ("I said X, but I meant Y")
- Stored instruction has factual conflicts requiring correction
- Circumstances change (old memory: "We use PostgreSQL"; new reality: "We use MySQL 8")

**Process:**
1. Identify the existing instruction by its ID
2. Replace with the corrected content
3. Confirm: "Replaced memory #[N] with: [new content]"

**Prefer replace over add** to avoid duplicates.

### 4.3 Remove

Use when:
- The user explicitly says "forget...", "delete...", "忘掉...", "删掉...", "不要再..."
- The instruction is no longer relevant, accurate, or useful
- The user shows clear understanding of instruction management and requests removal

**Process:**
1. Identify the instruction by its ID
2. Delete it permanently
3. Confirm: "Removed memory #[N]"

**For complete reset:** Ask the user for confirmation before deleting all content iteratively. This is irreversible.

---

## 5. Project-Specific Memory Inventory

These instructions are canonical for the DGLab project. Store them once, reference them everywhere.

| # | Instruction | Why It Matters |
|---|-------------|----------------|
| 1 | "DGLab uses MySQL 8 (InnoDB) as the primary datastore, not PostgreSQL. ADR-013 is canonical." | Prevents PostgreSQL hallucinations |
| 2 | "ULID (CHAR(26)) is the canonical PK format for anything cross-service." | Prevents UUID/int drift |
| 3 | "HUB-31 (Real-Time Analytics) is Proposed (ADR-011), not Accepted. Never implement dependencies on it." | Prevents phantom dependencies |
| 4 | "The team is 1 tech lead (solo, AI-augmented), 1 marketer, 1 media. No 3-engineer assumptions." | Prevents parallel-bet hallucinations |
| 5 | "AGRD v3.4 Spiral Deepening is the canonical SDLC. Milestone 0 comes first, then calibration." | Prevents timeline fiction |
| 6 | "Frozen interfaces (AGRD §2.1) require an ADR to change. No casual edits." | Prevents interface drift |
| 7 | "Canonical source hierarchy: INDEX.md > OPEN-DECISIONS.md > ADRs > CrossCutting > Blueprints > Code." | Prevents source confusion |
| 8 | "The 'Sovereign' naming prefix has known collisions — check INDEX.md before using it." | Prevents naming collisions |
| 9 | "Anvil's install.sh breaks Xubuntu DNS. Use NetworkManager dnsmasq plugin, not system dnsmasq on port 53." | Prevents repeated DNS breakage |
| 10 | "Every agent output must cite canonical file + section. No uncited architectural claims." | Prevents hallucinated authority |

**Note:** These 10 are suggestions for the tech lead to store explicitly. They are NOT automatically stored — the user must request each one.

---

## 6. Cross-Session Context Rules

### 6.1 What Agents Remember Automatically

- **Within a single session:** The full conversation context
- **Across sessions (same platform):** Dream Memory consolidation — patterns, preferences, project context
- **Across platforms:** NOTHING. Claude does not know what Kimi knows. Cline does not know what Jules knows.

### 6.2 What Explicit Memory Bridges

Explicit Memory is the only thing that travels with the user across platforms. When you open a new session with any agent:

1. The agent loads your stored Explicit Memory instructions
2. It does NOT load Dream Memory from other platforms
3. It does NOT load conversation history from other platforms

**Implication:** If you want every agent to know something, you must store it as Explicit Memory. Dream Memory is platform-siloed.

### 6.3 The Handoff Gap

When switching from one agent to another mid-task:

| Gap | Fix |
|-----|-----|
| Agent A (Cloud) drafted an ADR; Agent B (Local) needs to implement it | Use PROMPTS.md §6 Handoff Protocol — paste canonical citations, not conversation summaries |
| Agent A remembered your preference; Agent B doesn't know it | Check Explicit Memory — if it's not stored there, Agent B won't know it |
| Agent A made a decision; Agent B contradicts it | Check canonical docs — agents don't share decision state, only canonical files do |

**Rule:** Canonical files are the only shared state. Explicit Memory is the second-best. Conversation history is NOT shared state.

---

## 7. Verification: What's Actually Stored?

To check what instructions are currently in your Explicit Memory:

```
"Show me my stored memory instructions."
"List all memories."
"What have I asked you to remember?"
```

The agent will return a numbered list. Review it periodically — stale instructions cause drift.

**Recommended audit frequency:** Every cooldown (2 weeks per AGRD v3.4 §7).

---

## 8. Memory Hygiene Checklist

| Check | Frequency | Action |
|-------|-----------|--------|
| Duplicates | Every add/replace | Prefer replace over add |
| Stale instructions | Every cooldown | Remove instructions that no longer apply |
| Length > 500 chars | Every add | Split into multiple concise instructions |
| Prohibited content | Every add | Reject if it matches §2 prohibited list |
| Count approaching 50 | Monthly | Ask user which to remove before adding new ones |
| Cross-platform consistency | When switching agents | Verify critical instructions are stored on the new platform too |

---

## 9. Examples: Store, Replace, Remove

### Store

```
User: "Remember that I always want you to show me a diff before applying any file change."
→ Stored: "Show a diff before applying any file change."
→ Confirmation: "Added memory #1: Show a diff before applying any file change."
```

### Replace

```
User: "I changed my mind — I want you to apply changes immediately without showing diffs."
→ Replaced memory #1: "Apply file changes immediately without showing diffs."
→ Confirmation: "Replaced memory #1."
```

### Remove

```
User: "Forget that I wanted diffs — I don't need that anymore."
→ Removed memory #1
→ Confirmation: "Removed memory #1."
```

### Complete Reset (requires confirmation)

```
User: "Delete all my memory instructions."
→ Response: "This will permanently delete all stored instructions. Confirm: yes/no?"
→ User: "yes"
→ Confirmation: "All memory instructions removed."
```

---

## 10. Emergency: Memory Corruption

If you suspect an agent is acting on stale, wrong, or hallucinated memory:

1. **Stop the session.** Do not continue.
2. **Request the memory list.** "Show me my stored memory instructions."
3. **Identify the bad instruction.**
4. **Remove or replace it.**
5. **Restart the task** with the corrected memory state.

**Do not** try to "override" a bad memory by repeating the correct fact in conversation — that creates conflict, not clarity. Fix the stored instruction first.

---

## 11. Changelog

| Version | Date | Changes |
|---------|------|---------|
| v1.0 | 2026-08-10 | Initial — governs Explicit Memory for DGLab across all agents and platforms. |

---

*This document is governed by the canonical source hierarchy (PROMPTS.md §3). If it contradicts Architecture/INDEX.md or an accepted ADR, INDEX.md/ADR wins.*
