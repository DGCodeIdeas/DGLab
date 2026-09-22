# ADR-004: Kahn's Algorithm with Tier-Priority Re-Sort for DependencyGraph

**Status:** Accepted
**Date:** 2026-08-04
**Deciders:** DGLab architecture team

## Context

`orchestrator/src/DependencyGraph.php` is the load-bearing component of CORE-01 ("Loom", the polyrepo orchestrator). Its `getResolutionOrder()` method decides the sequence in which the orchestrator evaluates ~50+ repositories across three tiers (Core, Hub, Spoke) during a release promotion cycle. The output drives the merge-gate evaluation order during CI and the SemVer-bump cascade — a major bump in a Core repo blocks downstream Hub releases until they re-pin and re-test.

The actual implementation in the repo (verified 2026-08-04) uses **Kahn's algorithm** with an in-degree map and an adjacency list, augmented by a `usort`-based tier-priority re-sort after each node dequeue. The seed queue is built by iterating `TIER_ORDER = ['core' => 0, 'hub' => 1, 'spoke' => 2]` and enqueueing zero-in-degree nodes tier-by-tier. After each dequeue, the queue is re-sorted so that lower-tier (Core) nodes always come before higher-tier (Hub/Spoke) nodes regardless of insertion order. Cycle detection is the trivial Kahn's post-condition: `if (count($resolved) !== count($nodes)) throw new RuntimeException('Circular dependency detected in the dependency graph.')`.

Finding 19 in `00_CRITIQUE.md` flags "Why Kahn's algorithm for tier ordering (implemented in `DependencyGraph.php` but not decided)" as undocumented. The current implementation works for ~10 repos (the CORE-01 CI criterion: "Full dependency check for 10 repos must complete in < 2 seconds") but as the Hub tier expands to ~30 blueprints, the algorithmic complexity and the tier-priority re-sort cost need to be justified. This ADR records why Kahn's was chosen.

Two forces shaped the choice. First, the orchestrator must produce a *tier-respecting* topological order: even when the dependency graph would permit a Hub-tier node to be resolved before an unrelated Core-tier node, we want Core first because any Core failure should short-circuit the entire release. Second, the algorithm must detect cycles explicitly — a Spoke that transitively depends on itself is a real risk in a polyrepo where `composer.json` constraints drift, and stack-overflowing the PHP process during a release is unacceptable.

## Decision

We adopt **Kahn's algorithm** with a tier-priority re-sort of the ready queue on each dequeue, as currently implemented in `orchestrator/src/DependencyGraph.php`. Specifically: (1) build an in-degree map and adjacency list; (2) seed the ready queue with all zero-in-degree nodes, iterated in `TIER_ORDER` (Core → Hub → Spoke) to give Core a head start; (3) dequeue a node, append to the resolved list, decrement in-degrees of its neighbors, and enqueue any neighbor whose in-degree drops to zero; (4) after each dequeue/enqueue cycle, re-sort the queue with `\usort($queue, fn($a, $b) => TIER_ORDER[$a] <=> TIER_ORDER[$b])` so the next dequeue always prefers the lowest tier; (5) after the loop, assert `count($resolved) === count($nodes)`. If they differ, a cycle exists; throw `RuntimeException`. Complexity is O(V+E) for the topological pass plus O(V log V) cumulative for the re-sorts — negligible at 50 nodes.

## Alternatives Considered

| Alternative | Pros | Cons | Verdict |
|---|---|---|---|
| **DFS-based topological sort** (recursive depth-first, emit on `finish`) | Simpler code (~10 LOC); no in-degree map; natural cycle detection via the "gray node" visited-set | Tier ordering is non-deterministic: DFS emits nodes in post-order, meaning a Hub node will be emitted *before* its Core dependencies unless we reverse the output. Even with reversal, within-tier ordering is driven by graph topology, not tier priority. A second pass to re-sort by tier gives equivalent complexity to Kahn's with worse cache locality. | Rejected — tier-respecting ordering is a hard requirement |
| **Tarjan's Strongly Connected Components** (SCC) | Detects *which* nodes form a cycle; produces a DAG of SCCs that can be linearized | Overkill: we do not need to know *which* nodes form a cycle, only that one exists. Tarjan's is ~3× the code of Kahn's and harder to test. SCC decomposition adds no operational value when the response to any cycle is "halt and notify." | Rejected — over-engineered |
| **Priority-queue topo sort** (replace `usort` with `SplPriorityQueue` seeded by tier) | Avoids the O(V log V) re-sort; each dequeue is O(log V) | `SplPriorityQueue` does not support stable ordering of equal-priority elements without a tiebreaker, so within a tier the order becomes non-deterministic. Deterministic output matters: the same graph must always produce the same order so CI logs are reproducible. | Rejected — non-determinism is worse than the minor sort cost |
| **External scheduler** (GitHub Actions `needs:` graph, Buildkite pipeline) | Zero custom algorithm code; offloads correctness to the CI vendor | Tied to a single CI vendor's semantics; the orchestrator runs *outside* CI (it triggers CI per repo, then aggregates); cannot express tier-priority overrides | Rejected — wrong layer of abstraction |
| **Plain `usort` over the node list (no topological sort)** | Trivial code | Cannot express dependency ordering — a Hub repo would be evaluated before its Core dependencies, violating the foundational invariant | Rejected — does not solve the problem |

## Consequences

**Positive:**
- Tier ordering is *guaranteed* by construction: even if a Hub node has no incoming edges, the seed loop in `TIER_ORDER` will enqueue all Core-tier zero-in-degree nodes first, and the per-dequeue `usort` keeps Core ahead of Hub ahead of Spoke throughout. The orchestrator's output is auditable.
- Cycle detection is the trivial Kahn's post-condition (`count($resolved) !== count($nodes)`), which is impossible to get wrong.
- Complexity is bounded: O(V+E) for the topological pass, O(V log V) for the cumulative re-sort. At 50 nodes this completes in milliseconds, comfortably under the CORE-01 CI budget.

**Negative:**
- The per-dequeue `usort` is O(Q log Q) per dequeue and O(V · Q log Q) cumulative in the worst case. At ~50 nodes this is irrelevant, but if the orchestrator ever scales to thousands of repos, this should be replaced with a bucket-based priority queue.
- The cycle detection is uninformative: it tells you *that* a cycle exists but not *where*. A future enhancement should add Tarjan's SCC on the failure path to identify the cycle's members for the operator.
- The tier-priority re-sort makes the algorithm's output sensitive to the `TIER_ORDER` constant. If a fourth tier is added, the constant must be updated; the algorithm itself has no concept of "tier" beyond this constant.

**Neutral:**
- The `DependencyGraph` class is now load-bearing for every release in the ecosystem. It has unit tests (`orchestrator/tests/DependencyGraphTest.php`) but no property-based test for cycle detection on random graphs; adding one would harden the algorithm.
- The `\usort` comparator is a stable closure, so PHP 8.3's `usort` (stable since PHP 8.0) produces deterministic output. Any future move to `SplPriorityQueue` must add an explicit tiebreaker.

## Links
- Related ADRs: ADR-001 (polyrepo — the orchestrator exists because of the polyrepo decision), ADR-002 (CORE-02 is the first Core repo the orchestrator must sequence)
- Related blueprints: CORE-01 (Polyrepo Orchestrator / "Loom"), CORE-17 (Service Provider System — uses a similar graph at runtime within a single process)
- Related findings: Finding 19 (no ADRs existed), Finding 8 (CORE-02 is the most-depended-on node in this graph)
- External references: Kahn's original paper (Kahn, 1962, "Topological Sorting of Large Networks"); Cormen et al., *Introduction to Algorithms*, §22.4 (Topological Sort); PHP manual on `usort` stability (php.net/manual/en/function.usort.php — stable since PHP 8.0)
