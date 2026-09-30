# ELQ-Derived ISPOKE Consumer Matrix

**Analysis date:** 2026-09-30
**Source:** ELQ-ANALYSIS.md §6 (10 reusable ISPOKEs, post E3→HUB-32 promotion)
**Consumers:** 18 existing ESPOKEs (ESPOKE-01..18; ESPOKE-19 Eloq excluded as origin)
**Analyst task ID:** ESPOKE-CONSUMER-MAP-7

---

## 1. Executive Summary

The 10 reusable ISPOKEs derived from the ELQ repository (post E3→HUB-32 promotion) were tested against the 18 existing DGLab ESPOKE blueprints in a 10×18 = 180-cell consumer matrix. The cell distribution is **YES = 25 (13.9%)**, **MAYBE = 34 (18.9%)**, **NO = 121 (67.2%)**. The matrix is sharply asymmetric: the ISPOKEs were designed for a writing-app domain (long-form authoring, paraphrasing, readability, backup), but most of the 18 existing ESPOKEs are transactional/operational apps (checkout, billing, search, gateway, analytics) that have no long-form content surface. This produces low average YES counts across most ISPOKEs, with one notable exception.

**ISPOKE-E15 (Theme Manager)** is the standout: 11 of 18 ESPOKEs (61.1%) clearly benefit from it. This crosses the Hub-promotion-candidate threshold defined in APP-MODEL-REFINEMENT-5 extension #2 (≥50% of ESPOKEs). However, E15 is small (91 LOC) and was already flagged in ELQ-ANALYSIS.md §6.4.4 as a candidate for absorption into HUB-26 UI Elements rather than promotion to a standalone new Hub. The recommendation here is to ratify that absorption: when HUB-26 ships, E15 should be merged into it rather than being shipped as a separate ISPOKE first.

The original three Hub-promotion candidates from ELQ-ANALYSIS.md §6.4 (E8 BYOK Vault, E9 Remote Backup Orchestrator, E13 Privacy & Audit Ledger-partial) all **fail the 50% threshold** in this matrix: E8=1 YES (5.6%), E9=2 YES (11.1%), E13=2 YES (11.1%). All three should **remain ISPOKEs** per the APP-MODEL-REFINEMENT-5 deferred-promotion rule. No ISPOKE in this analysis warrants immediate Hub ratification like E3 did — E15's near-universal fit is better served by absorption into HUB-26 (which is in the SDLC-AUDIT-1 NONE-dependency tier and can proceed) than by creating a 33rd Hub.

The strongest non-Eloq consumer is **ESPOKE-11 Sovereign Beacon (Support Centre)** at 6 YES cells (60% of ISPOKEs). Beacon's knowledge-base + support-ticket workflow is the closest existing analog to Eloq's document model in the catalog. The next-strongest is **ESPOKE-12 Sovereign Forge (Dev Portal)** at 3 YES + 7 MAYBE (every cell is at least MAYBE — Forge is the most "ISPOKE-curious" ESPOKE). These two ESPOKEs should be the first cross-app composition test targets, since they exercise the broadest range of ELQ ISPOKE capabilities.

---

## 2. The 10 Reusable ISPOKEs (extracted from ELQ-ANALYSIS.md §6)

The 14 ISPOKEs accepted from the ELQ analysis reduce to 10 reusable after E3 was immediately promoted to HUB-32 per Decision 3 of ELQ-DECISIONS-RATIFY-6.5. The 4 Private ISPOKEs (E1 Document Vault, E2 Block Editor Surface, E12 Censorship & Redaction Engine, E14 Productivity Metrics) are excluded from this analysis per the task scope.

### 2.1 ISPOKE-E4: Paraphrase Engine

- **Classification:** feature (Eloq-specific domain logic that is nevertheless broadly applicable)
- **Reusable:** true
- **ELQ source:** `src/services/paraphrase.types.ts` (309 LOC, 22 doc types + 10 styles), `server-api.cjs:298-396` (DOCUMENT_TYPE_DESCRIPTIONS + PARAPHRASE_STYLE_GUIDES), `server-api.cjs:1022-1226` (/api/ai/paraphrase endpoint), `server-api.cjs:398-670` (localParaphraseFallback), `src/components/paraphrase/paraphrase-modal.component.ts` (879 LOC)
- **Consumes (Hub):** none
- **Consumes (Hub post-ratification):** HUB-32 AI Inference Hub (was ISPOKE-E3; invokes the LLM)
- **Description:** Assembles paraphrase prompts from `{documentType, style, selectionType, customInstruction, surroundingContext, contextBefore, contextAfter, useSurroundingContext, isDocUncensored}`. Returns 4 alternatives each with `{text, label, tone, explanation, fitScore}`. **22 document types** across 5 categories: Fiction SFW/NSFW/Speculative/Suspense/Horror/YA/Historical/Poetry/Fanfic × 9; Academic Research/Thesis/LitReview/STEM/Humanities × 5; Legal Contract/Brief/Compliance × 3; Business Memo/TechDocs/Marketing × 3; Journalism/Memoir × 2. **10 paraphrase styles** (natural/closer/vivid/concise/dramatic/formal/simplified/active/lyrical/dialogue).
- **Porting notes:** The 22 doc types + 10 styles become PHP enums with method implementations (`promptFragment(): string`) on each case, unifying the client metadata (`paraphrase.types.ts:49-309`) and server prompt fragments (`server-api.cjs:298-396`) into a single source of truth. The prompt assembly logic becomes a `ParaphrasePromptBuilder` service. The local fallback (`server-api.cjs:398-670`) is 270 LOC of hardcoded templates — port selectively, since the LLM should produce these dynamically.

### 2.2 ISPOKE-E5: Linguix Quality Reviewer

- **Classification:** feature (writing-app domain feature with reusable pattern)
- **Reusable:** true
- **ELQ source:** `src/services/ai.service.ts:65-131` (localLinguisticReview), `server-api.cjs:920-1019` (/api/ai/review endpoint with Gemini responseSchema), `src/components/editor/editor.component.ts:239-294` (scan + accept/dismiss/applyAll), `index.html:108-148` (review-highlight CSS)
- **Consumes (Hub post-ratification):** HUB-32 AI Inference Hub (was ISPOKE-E3)
- **Description:** Linguix/Grammarly-style inline grammar review. Returns `Suggestion[] = {original, suggestion, type: Spelling|Grammar|Clarity|Style|Tone, explanation}`. Computes a Linguix score 0-100 from issue density. Local rule-based fallback: duplicate word detection, 10 common typo corrections, 5 filler-phrase simplifications. UI renders inline highlights with 4 category colors (red/purple/blue/emerald) + active suggestion in amber.
- **Porting notes:** The Gemini responseSchema ports to PHP as a structured JSON schema passed to the Gemini REST API's `generationConfig.responseSchema` field. The local rule-based reviewer (ai.service.ts:65-131) ports line-by-line — regex + dictionary lookup. The CSS classes (`index.html:108-148`) port unchanged. The accept/dismiss/applyAll UI flow (`editor.component.ts:269-294`) becomes SuperPHP form actions.

### 2.3 ISPOKE-E6: Readability Auditor

- **Classification:** abstraction (reusable infrastructure)
- **Reusable:** true
- **ELQ source:** `src/services/readability.service.ts` (604 LOC — pure TypeScript, no Angular primitives)
- **Consumes (Hub):** none
- **Consumes (Core):** none (pure library)
- **Description:** Computes **5 readability formulae** — Flesch Reading Ease, Flesch-Kincaid Grade Level, Gunning Fog Index, Coleman-Liau Index, Automated Readability Index — from raw HTML input. Abbreviation-aware sentence splitter (13 abbreviations protected: Mr./Mrs./Ms./Dr./Prof./St./Sr./Jr./e.g./i.e./etc./vs./approx./No., plus decimals and ellipses). English syllable counter with heuristics for silent 'e', 'le' syllable, trailing 'ed'/'es' patterns, vowel-group counting, and special-case adjustment for `ia|io|iu|eo|ua|uo` diphthongs. **6 audience profiles** (Middle Grade 4.0-6.5, Young Adult 6.0-8.5, General Fiction 7.0-9.5, Literary Fiction 9.5-13.0, Academic/Technical 12.0-18.0, Casual/Web 5.0-7.5) with benchmark authors. 5-status audience-match feedback (optimal/slightly_dense/too_dense/slightly_simple/too_simple) with actionable tips.
- **Porting notes:** This is the single most directly portable service in the repo. Pure TypeScript, no Angular primitives, no I/O. PHP port is line-by-line: `stripHtml()` ports with `preg_replace`; `splitIntoSentences()` ports with `preg_replace_callback` + `preg_split`; `countSyllables()` ports unchanged. The 6 audience profiles become a PHP enum or static array. The 5 formulae are public-domain math (Flesch 1948, Flesch-Kincaid 1975, Gunning Fog 1952, Coleman-Liau 1975, ARI 1967).

### 2.4 ISPOKE-E7: RAG Retriever

- **Classification:** abstraction
- **Reusable:** true
- **ELQ source:** `src/services/rag.service.ts` (157 LOC), `src/services/ai.service.ts:133-174` (getEmbedding + getBatchEmbeddings with concurrency=3)
- **Consumes (Hub):** HUB-14 Search (vector store), HUB-10 Queue (batch embedding jobs)
- **Consumes (Hub post-ratification):** HUB-32 AI Inference Hub (was ISPOKE-E3 — `getEmbedding()`)
- **Consumes (Core):** CORE-03 EventDispatcher (indexing events)
- **Description:** Chapter chunking (500 words with 50-word overlap, capped at 50 chunks in ELQ — should be configurable in PHP port). Batch embedding via concurrent fetch (concurrency=3). In-memory vector store with cosine similarity search. BM25-ish keyword fallback when embeddings fail (custom scoring `(matches / (matches + 1.5)) * (term.length > 4 ? 2.0 : 1.0)` — note: this is NOT standard BM25, just a heuristic).
- **Porting notes:** ELQ's in-memory `Chunk[]` array (lost on reload) is replaced by HUB-14 Search (persistent vector store). The 50-chunk cap is browser-memory-specific — PHP port removes it and uses HUB-14's pagination. The `setTimeout(25)` cooperative yield becomes a HUB-10 Queue job for batch embedding. The cosine similarity is 9 LOC of pure math — port unchanged. The BM25 fallback should be replaced with a proper BM25 implementation (e.g., `teamtnt/tntsearch` library).
- **Open design question (see §8):** E7 overlaps with HUB-14 Search Abstraction Layer, which is already consumed by ESPOKE-04 Discovery and ESPOKE-17 Concierge. The boundary between E7 (chunking + embeddings + semantic search) and HUB-14 (keyword search) needs explicit definition.

### 2.5 ISPOKE-E8: BYOK Vault

- **Classification:** abstraction
- **Reusable:** true
- **ELQ source:** `src/services/crypto.service.ts:186-204` (saveApiKey/removeApiKey/getActiveKeyForProvider), `src/services/ai.service.ts:31-62` (resolveModelPayload routing), `src/components/byok-modal/byok-modal.component.ts` (302 LOC UI)
- **Consumes (Hub):** HUB-20 Vault (server-side secret storage, `SensitiveParameterValue`), HUB-04 Identity (user ownership of keys)
- **Consumes (Core):** CORE-16 Crypto (Encrypter for at-rest encryption of API keys, PasswordHasher for key derivation)
- **Description:** Per-user API key vault. Each `ApiKeyConfig = {provider: gemini|openai|groq|anthropic|openrouter|custom, name, key, endpointUrl?, isActive, addedAt}`. Provider-specific routing: `resolveModelPayload(modelId, customModel)` picks the right key based on the model's `providerType`. SHA-256 fingerprint of the raw key for UI display (without revealing the key itself). Active key per provider.
- **Porting notes:** ELQ stores keys in localStorage — XSS-vulnerable. PHP port uses HUB-20 Vault (MySQL `vault_secrets` table with AES-256-GCM-encrypted blobs). The Encrypter from CORE-16 wraps each key. `SensitiveParameterValue` (PHP 8.2+) is used in the `ApiKeyConfig` value object to prevent accidental `var_dump`/log leakage. The provider-routing logic becomes a `KeyResolver` service. The fingerprint computation uses `hash('sha256', $rawKey)` truncated to 8 bytes for display.
- **Original Hub-promotion candidate:** YES (ELQ-ANALYSIS.md §6.4.1) — DEFER until second consumer per APP-MODEL-REFINEMENT-5. Reassessed in §6 below.

### 2.6 ISPOKE-E9: Remote Backup Orchestrator

- **Classification:** abstraction
- **Reusable:** true
- **ELQ source:** `src/services/backup.service.ts` (1397 LOC), `server-api.cjs:1271-1750` (5 backup endpoints), `src/components/backup-modal/backup-modal.component.ts` (1335 LOC UI)
- **Consumes (Hub):** HUB-10 Queue (auto-backup scheduler), HUB-25 Chronos (interval scheduling — pending per HUB-FOUNDATION-SWEEP-2), HUB-11 Cloud Storage (S3/R2/MinIO target), HUB-06 Auditor (backup event logging)
- **Consumes (Core):** CORE-16 Crypto (envelope for encrypted_vault method), CORE-14 Filesystem (local_dir target), CORE-02 DBAL (backup log persistence)
- **Description:** Six backup methods: encrypted_vault (AES-GCM-256 with PBKDF2-derived key, versioned JSON envelope), json_vault (plain JSON), zip_bundle (multi-folder markdown ZIP with Manifest.json + Manuscripts/ + ChatHistory/ + Analytics/wordcount-history.csv), incremental (delta since last timestamp), active_doc (single doc only), disaster_html (single-file self-contained HTML reader). Five remote targets: download (HTTP response), local_dir (server filesystem), github (REST API PUT to repo contents or Gist), s3 (AWS SigV4), webdav (PROPFIND/PUT), webhook (POST/PUT with HMAC-SHA256 secret). Auto-backup scheduler with interval-based trigger (10/30/60 min or on every save). Restore inspection + merge/replace modes.
- **Porting notes:** ELQ's 1397-LOC backup.service.ts is over-engineered. PHP port splits into: `BackupMethodStrategy` enum + `RemoteTargetStrategy` enum + `BackupOrchestrator` service. The disaster_html generator (193 LOC) ports nearly verbatim — it's just HTML string assembly. The encrypted_vault envelope format should be REPLACED with DGLab's CORE-16 Envelope (strictly superior: versioned, kid, sodium_memzero). A one-time importer handles existing ELQ backups. The AWS SigV4 hand-rolled code is replaced with aws-sdk-php's S3Client. The GitHub REST API calls port line-by-line via Guzzle.
- **Original Hub-promotion candidate:** YES (ELQ-ANALYSIS.md §6.4.2) — DEFER until second consumer. Reassessed in §6 below.

### 2.7 ISPOKE-E10: EPUB Importer

- **Classification:** abstraction
- **Reusable:** true
- **ELQ source:** `src/services/import.service.ts` (382 LOC)
- **Consumes (Hub):** none
- **Consumes (Core):** CORE-14 Filesystem (uploaded file handling), CORE-02 DBAL (chapter persistence)
- **Description:** Parses .epub and .txt files into Chapter[]. EPUB pipeline: ZipArchive open → read META-INF/container.xml for OPF rootfile → parse OPF manifest (id→href map) + spine (itemref order) + TOC (NCX or XHTML) → for each spine item, parse HTML and either detect AO3 (URLs containing archiveofourown.org or .userstuff/.userstuff1/.userstuff2/dl.tags selectors) and extract structured metadata (Rating, Archive Warning, Categories, Fandom, Relationships, Additional Tags, Stats from dl.tags; byline from .byline; summary from blockquote.userstuff) to build a preface chapter, or extract paragraphs/quotes/hr as content. Aggressive attribute sanitization (on*/mso-*/style/class/id stripped).
- **Porting notes:** JSZip → ZipArchive (ext-zip). DOMParser (browser) → Masterminds/HTML5 (better HTML5 support than DOMDocument). The AO3-specific CSS selectors (`.userstuff`, `dl.tags`, `.byline`) work in Masterminds/HTML5 via Symfony\Component\CssSelector. The preface HTML generation (import.service.ts:195-268) is 73 LOC of Tailwind-styled badge HTML — port directly with SuperPHP template partials. EPUB-3 nav.xhtml is partially supported in ELQ; PHP port should fully support both EPUB-2 (toc.ncx) and EPUB-3 (nav.xhtml).

### 2.8 ISPOKE-E11: Manuscript Exporter

- **Classification:** abstraction
- **Reusable:** true
- **ELQ source:** `src/services/export.service.ts` (755 LOC), `src/components/export-modal/export-modal.component.ts` (613 LOC UI)
- **Consumes (Hub):** none
- **Consumes (Core):** CORE-14 Filesystem (export file generation), CORE-16 Crypto (no direct use)
- **Consumes (ISPOKE — CRITICAL):** **ISPOKE-E12 (Censorship & Redaction Engine — for redaction-aware content generation)**. E12 is `reusable: false` (private to Eloq ESPOKE). See §8 Open Question #1.
- **Description:** Five export formats: pdf (running headers/footers, page numbers, redaction-aware, chapter page breaks, metadata banner), markdown (HTML→MD conversion + chapter headings), txt (plain text with paragraph breaks), html (self-contained with inline CSS), epub (proper EPUB-2 structure with mimetype/OEBPS/META-INF/container.xml). Redaction-aware: respects per-doc CensorshipConfig + 6 redaction styles when generating export content. Optional metadata banner (export date, chapter count, redaction note, "REDACTED COPY - CONFIDENTIAL" footer).
- **Porting notes:** jsPDF → mpdf (closest to ELQ's HTML→PDF usage with running headers/footers). JSZip for EPUB generation → ZipArchive with mimetype as first entry stored (not deflated) per EPUB spec. Markdown generation → league/commonmark reverse (HTML→Markdown via league/html-to-markdown). HTML export is trivial string assembly. TXT export is HTML-stripped plain text. The redaction-aware content generation (`export.service.ts:46-67`) calls `BlockService.redactHtml()` — this dependency on ISPOKE-E12 means the export modal must compose both, **but E12 is private**. For cross-ESPOKE consumption, E11 must support a "no-redaction" mode where the CensorshipConfig is absent or a no-op implementation is injected.

### 2.9 ISPOKE-E13: Privacy & Audit Ledger (slim, post-split)

- **Classification:** abstraction
- **Reusable:** true
- **ELQ source:** `src/services/privacy.service.ts` (134 LOC), `src/components/privacy-modal/privacy-modal.component.ts` (225 LOC UI)
- **Consumes (Hub):** HUB-06 Auditor (server-side audit log infrastructure — the mechanism is subsumed by HUB-06 per ELQ-ANALYSIS.md §6.4.3)
- **Consumes (Core):** CORE-02 DBAL (audit log persistence), CORE-03 EventDispatcher (audit event subscription)
- **Description:** 5-category audit log (document, encryption, ai_inference, byok, auth) with 4 statuses (allowed, encrypted, revoked, processed). Capped at 100 entries in ELQ (should be configurable in PHP port). JSON export. **5 privacy policy items** as a static array (zero retention, local encryption, no training, BYOK isolation, unbiased neutrality) — UI dashboard displaying each with a status badge (guaranteed, enforced, user_controlled). Zero-telemetry toggle persisted per-user.
- **Porting notes:** The AuditEvent value object (`privacy.service.ts:3-11`) becomes a PHP readonly class. The 5 policy items (`privacy.service.ts:31-67`) become a PHP enum or static array. The `logEvent` method becomes a thin wrapper around HUB-06 Auditor's `record()` method. The 100-entry cap is localStorage-specific — PHP port uses HUB-06's retention policy. The JSON export is trivial.
- **Original Hub-promotion candidate:** PARTIAL (ELQ-ANALYSIS.md §6.4.3) — SPLIT: mechanism → HUB-06; policy labels → slim ISPOKE. Reaffirmed in §6 below.

### 2.10 ISPOKE-E15: Theme Manager

- **Classification:** abstraction
- **Reusable:** true
- **ELQ source:** `src/services/theme.service.ts` (91 LOC), `index.html:48-60` (anti-flash script)
- **Consumes (Hub):** none
- **Consumes (Core):** CORE-07 SuperPHP Templates (for SSR theme application)
- **Description:** Three-state theme mode (light, dark, system-follows-OS). Listens to `prefers-color-scheme: dark` media query (browser-side). Synchronizes DOM `<html>` class + `color-scheme` CSS property. Anti-flash pre-hydration script reads localStorage before Angular boots (in PHP port: read `Sec-CH-Prefers-Color-Scheme` header or session).
- **Porting notes:** ELQ's anti-flash script (13 lines of plain JS) is a direct copy. PHP port: read `Sec-CH-Prefers-Color-Scheme` client hint header (Chrome 111+) for SSR; fall back to session-stored preference. The `ThemeMode` enum becomes a PHP backed enum. The system-dark detection becomes client-side JS in the SSR template.
- **Original Hub-promotion candidate:** PARTIAL (ELQ-ANALYSIS.md §6.4.4) — "may be subsumed by HUB-26 UI Elements". Reassessed as NEW full Hub-promotion candidate in §6 below.

---

## 3. The 18 Existing ESPOKE Blueprints (inventory)

All 18 blueprints at `/home/z/my-project/Architecture/Spoke/External/ESPOKE-01.md` through `ESPOKE-18.md` were read in full. **None is a stub** — all 18 have detailed architectural designs, interface contracts (PHP code blocks), integration strategies, benchmark methodologies, and CI verification criteria. The earlier ESPOKEs (01-15) were also corrected against Pattern A-H catalog issues (wrong Hub/Core IDs) per the master index. The hospitality-vertical ESPOKEs (16-18) are richer, more recent designs with security properties and CI criteria explicitly listed.

### 3.1 ESPOKE-01: Sovereign Canvas (CMS)

- **Component Name:** Sovereign Canvas (CMS) — `SovereignStack\External\Canvas`
- **Tier:** External Spoke (Public-facing Application)
- **Primary purpose:** Public-facing CMS and delivery engine — renders high-performance, SEO-optimized pages for end-users, consuming content from `ISPOKE-09` (Internal Knowledge Base) exclusively via `BRIDGE-01` transformation layer — never directly.
- **Target users / use cases:** End users visiting published pages; content authors publish via `ISPOKE-09`.
- **Required Hub capabilities (already declared):** HUB-03 (Unified Asset Pipeline), HUB-02 (Distributed Cache), HUB-26 (Shared UI Component Library — Public Theme), HUB-08 (API Gateway & Public Surface), HUB-15 (Health Check & Service Discovery)
- **Transitive Core:** CORE-11/12 (SuperPHP Parser/Compiler), CORE-18 (Kernel & Lifecycle), CORE-06 (Router), CORE-14 (Filesystem)
- **Domain focus:** Content publishing & delivery (SEO, performance, structured data)
- **Feature gaps / future-work notes:** SEOEngine validates generated markup at publish time (in `ISPOKE-09`'s content-authoring workflow) — title length, meta description, canonical URL, structured-data schema validity. CMS itself is "render-only"; long-form authoring happens in ISPOKE-09, not ESPOKE-01.

### 3.2 ESPOKE-02: Sovereign Connect (REST API)

- **Component Name:** Sovereign Connect (API) — `SovereignStack\External\Connect`
- **Primary purpose:** Official public REST API: secure programmatic access to the platform's capabilities. Sits on top of `HUB-08`, enforcing rate limits, versioning, and developer-specific auth contexts.
- **Required Hub capabilities:** HUB-08, HUB-24 (Schema Registry), HUB-04 (Identity), HUB-06 (Auditor), HUB-28 (API Versioning), HUB-15
- **Domain focus:** Developer API surface (REST, JSON:API/Hal+JSON)
- **Feature gaps / future-work:** VersionController manages API versioning; Throttler does rate limits; ResponseTransformer formats responses; DocGenerator updates API docs from HUB-24 schemas. **Pure API surface — no UI, no content authoring.**

### 3.3 ESPOKE-03: Sovereign Account (Auth Portal)

- **Component Name:** Sovereign Account (Auth) — `SovereignStack\External\Account`
- **Primary purpose:** Public-facing authentication and account-management portal: customer registration, login, password resets, profile management. Interfaces with `HUB-04` through `BRIDGE-01` to manage customer identities.
- **Required Hub capabilities:** HUB-04 (Identity), HUB-05 (RBAC), HUB-26 (UI Library), HUB-06 (Auditor), HUB-08, HUB-15
- **Domain focus:** Identity & account management
- **Feature gaps / future-work:** Architectural design includes **SecurityCenter — UI for customers to view active sessions and security logs.** This is the closest existing analog to ISPOKE-E13's privacy dashboard. GDPR erasure test (cascading delete) is a verified property.

### 3.4 ESPOKE-04: Sovereign Discovery (Search)

- **Component Name:** Sovereign Discovery (Search) — `SovereignStack\External\Discovery`
- **Primary purpose:** High-performance search/discovery interface: search across the Public CMS, Products, and Documentation. Uses `HUB-14` through `BRIDGE-01` for filtered, public-safe search results.
- **Required Hub capabilities:** HUB-14 (Search Abstraction Layer), HUB-26, HUB-08, HUB-02, HUB-15
- **Domain focus:** Public search & discovery
- **Feature gaps / future-work:** SearchClient executes public-safe search queries against HUB-14 via the Bridge; FacetManager handles dynamic filters; AutoSuggest does type-ahead; ResultStyler renders snippets. **Pure search UI — does not own content.**

### 3.5 ESPOKE-05: Sovereign Growth (Marketing)

- **Component Name:** Sovereign Growth (Marketing) — `SovereignStack\External\Growth`
- **Primary purpose:** Engine for building, deploying, and optimizing marketing landing pages: block-based editor (via `HUB-26`), A/B testing (via `ISPOKE-12`), marketing analytics integration.
- **Required Hub capabilities:** HUB-03, HUB-01 (Search — possibly mislabeled in source), HUB-26, HUB-31 (Real-Time Analytics — pending), HUB-08, HUB-15
- **Domain focus:** Marketing & landing pages
- **Feature gaps / future-work:** **BlockEngine — conversion-optimized UI blocks (Hero, Features, Pricing, Testimonials)** is the only ESPOKE in the 18 that explicitly has a block-based content editor. CampaignManager handles page variations, UTM tracking, conversion goals. LandingPageRenderer optimized for sub-100ms LCP. This is the **closest existing analog to a "writing app"** in the catalog — marketers author landing page copy.

### 3.6 ESPOKE-06: Sovereign Relay (External Notifications)

- **Component Name:** Sovereign Relay (External) — `SovereignStack\External\Relay`
- **Primary purpose:** Manages all communications with end-customers: transactional emails, push notifications, in-app alerts. Consumes the `ISPOKE-07` messaging infrastructure through a strict `BRIDGE-01` policy.
- **Required Hub capabilities:** HUB-09 (Event Bus / Message Broker), HUB-12 (Notification Service), HUB-10 (Queue & Job Dispatcher), HUB-26, HUB-08, HUB-06, HUB-15
- **Domain focus:** Customer communication / notifications
- **Feature gaps / future-work:** CustomerPreferences for opt-in/opt-out; PublicRelay triggers customer notifications from internal events; ChannelManager integrates with public providers (SendGrid, Twilio, Firebase) through HUB-12. NotificationArchive is customer-viewable history within the Account Portal. **Notifications are short-form; no long-form content.**

### 3.7 ESPOKE-07: Sovereign Nexus (GraphQL API)

- **Component Name:** Sovereign Nexus (GraphQL API) — `SovereignStack\External\Nexus`
- **Primary purpose:** Performant, unified GraphQL API surface for public consumption: a consumer-facing projection of the Sovereign Stack data model, exposing only "Public-Safe" types and fields via `HUB-24`, enforcing `BRIDGE-01`'s boundary rules.
- **Required Hub capabilities:** HUB-24 (Schema Registry), HUB-08, HUB-04, HUB-05 (RBAC), HUB-15
- **Domain focus:** Developer API surface (GraphQL)
- **Feature gaps / future-work:** NexusSchemaManager aggregates types from the Bridge; PublicResolverEngine executes resolvers; ComplexityController prevents DoS; TypeProjectionLayer maps internal DTOs to GraphQL types. **Pure typed API surface — no UI, no content.**

### 3.8 ESPOKE-08: Sovereign Prism (Media Delivery)

- **Component Name:** Sovereign Prism (Media Delivery) — `SovereignStack\External\Prism`
- **Primary purpose:** Delivery service for public media/static assets: image transformation, video streaming metadata, asset optimization. Public entry point for media stored in the Internal sub-tier, enforcing privacy/delivery rules via the Bridge.
- **Required Hub capabilities:** HUB-03, HUB-02, HUB-08, HUB-15
- **Domain focus:** Media / asset delivery
- **Feature gaps / future-work:** TransformationEngine (GD/Imagick wrapper); CacheLayer via HUB-02; SecurityProxy validates request signatures via CORE-16; PrismRouter maps URLs to Bridge-requested internal media IDs. **No public UI — signed-URL based media delivery.**

### 3.9 ESPOKE-09: Sovereign Market (Checkout)

- **Component Name:** Sovereign Market (Checkout) — `SovereignStack\External\Market`
- **Primary purpose:** Secure, high-conversion e-commerce/checkout application: shopping carts, tax/shipping calculation via the Bridge, payment processing coordination through an abstraction layer.
- **Required Hub capabilities:** HUB-26, HUB-04, HUB-08, HUB-06, HUB-02
- **Domain focus:** E-commerce / checkout
- **Feature gaps / future-work:** CartManager (cache-backed shopping-session state); PaymentAbstractionLayer (Stripe/PayPal — no direct SDK coupling); OrderWorkflowEngine (Cart→Pending→Complete); CheckoutPresenter (multi-step UI). **PaymentProviderInterface is platform-owned, not user-BYOK** — merchants don't bring their own Stripe keys per this blueprint.

### 3.10 ESPOKE-10: Sovereign Pulse (Billing Portal)

- **Component Name:** Sovereign Pulse (Billing) — `SovereignStack\External\Pulse`
- **Primary purpose:** Portal for customers to manage subscriptions, view invoices, update billing methods — "Self-Service Billing," interacting with the Internal Spoke sub-tier via the Bridge to keep sensitive financial/plan data protected.
- **Required Hub capabilities:** HUB-04, HUB-26, HUB-08, HUB-15
- **Domain focus:** Billing / subscription management
- **Feature gaps / future-work:** SubscriptionManager (plan transitions); **InvoiceEngine — public-safe invoice data, downloadable PDFs via the Bridge**; PaymentMethodVault for saved payment tokens; PulseUI customer-facing dashboard. The InvoiceEngine explicitly generates downloadable PDFs — this is a clear consumer for ISPOKE-E11 Manuscript Exporter's PDF capability.

### 3.11 ESPOKE-11: Sovereign Beacon (Support Centre)

- **Component Name:** Sovereign Beacon (Support) — `SovereignStack\External\Beacon`
- **Primary purpose:** Unified self-service portal and support-ticket system: customers find answers via the public knowledge base and interact with support staff via tickets, without direct access to internal staff-only support tools.
- **Required Hub capabilities:** HUB-26, HUB-08, HUB-04, HUB-15
- **Domain focus:** Customer self-service / support / knowledge base
- **Feature gaps / future-work:** KnowledgeBaseConsumer fetches/renders public-safe articles via the Bridge, reading `ISPOKE-09`'s `isPublic()` flag — same mechanism as ESPOKE-01. **TicketWorkflowEngine** manages the public lifecycle of a support ticket (Open, Replied, Resolved). AttachmentProxy handles secure file uploads. BeaconPresenter is the support dashboard. This is the **strongest non-Eloq consumer** — Beacon's knowledge base + ticket workflow is the closest existing analog to Eloq's document model. Note that the blueprint flags that no Internal Spoke covering staff-facing ticket management exists yet (the `ISPOKE-11: Support Engine` reference in the diagram is a known error — actual `ISPOKE-11` is Sandbox).

### 3.12 ESPOKE-12: Sovereign Forge (Developer Portal)

- **Component Name:** Sovereign Forge (Dev Portal) — `SovereignStack\External\Forge`
- **Primary purpose:** Primary destination for developers building on the Sovereign Stack: hosts documentation for both the Public REST API (`ESPOKE-02`) and GraphQL API (`ESPOKE-07`), interactive "Try it now" environments, SDK links, API key management.
- **Required Hub capabilities:** HUB-24, HUB-08, HUB-26, HUB-04, HUB-15
- **Domain focus:** Developer relations / documentation portal
- **Feature gaps / future-work:** SchemaIntrospector fetches unified GraphQL schema from ESPOKE-07 and OpenAPI spec from ESPOKE-02; DocGenerator transforms schemas/Markdown into a searchable documentation site; **SandboxManager — interactive UI for executing authenticated requests against the public APIs** (uses developer's actual API keys via HUB-04 session for real Gateway calls); DeveloperConsole dashboard for managing public API keys/webhooks. **Forge is the most "ISPOKE-curious" ESPOKE** — every consumer cell is at least MAYBE.

### 3.13 ESPOKE-13: Sovereign Bridgehead (Partner Gateway)

- **Component Name:** Sovereign Bridgehead (Partner Gateway) — `SovereignStack\External\Bridgehead`
- **Primary purpose:** Specialized gateway for high-priority partner integrations and third-party webhooks: dedicated endpoints, custom auth for legacy partner systems, outbound webhook dispatch for Sovereign Stack events.
- **Required Hub capabilities:** HUB-08, HUB-06, HUB-10, HUB-04, HUB-15
- **Domain focus:** B2B partner integration / webhook dispatch
- **Feature gaps / future-work:** PartnerAuthManager uses specialized auth strategies (mTLS, custom header signatures) — **not API keys**, so BYOK Vault is not a fit. WebhookDispatcher consumes CORE-03 events and pushes to partner URLs via HUB-10. PayloadTransformer normalizes incoming partner data. CircuitBreaker protects against slow/failing partner endpoints. **No public UI — webhook gateway only.**

### 3.14 ESPOKE-14: Sovereign Lens (Analytics)

- **Component Name:** Sovereign Lens (Analytics) — `SovereignStack\External\Lens`
- **Primary purpose:** Dual-purpose analytics engine: an ingestion endpoint for public client events (clicks, views, conversions) and a reporting interface for authenticated customers to view their own performance metrics.
- **Required Hub capabilities:** HUB-08, HUB-02, HUB-26, HUB-06, HUB-15
- **Domain focus:** Public analytics & reporting
- **Feature gaps / future-work:** EventIngestor (high-throughput endpoint); AnonymizationLayer (strips PII, salts identifiers using CORE-16); MetricAggregator (real-time counters in HUB-02); **ReportingPresenter — customer-facing dashboard via HUB-26 visualization components**. Reports are typically interactive dashboards, not PDFs — ISPOKE-E11 fit is weak. Theme management fits the dashboard.

### 3.15 ESPOKE-15: Sovereign Sentinel (External Orchestrator)

- **Component Name:** Sovereign Sentinel (External Orchestrator) — `SovereignStack\External\Sentinel`
- **Primary purpose:** Internal orchestration and health-reporting layer governing the entire public-facing sub-tier — monitors lifecycle, deployment state, and operational health of all External Spokes (ESPOKE-01–14), reporting to CORE-01 and HUB-16.
- **Required Hub capabilities:** HUB-16 (Hooks), HUB-15 (Health), HUB-08, HUB-06
- **Domain focus:** Operational orchestration / health monitoring
- **Feature gaps / future-work:** SubTierController (Blue/Green, Canary deployments); HealthAggregator (Fleet Health); TrafficGovernor (load shedding); SentinelBridgeAgent (cross-tier contract monitoring). **No public UI — orchestrator only.** Theme Manager does not fit.

### 3.16 ESPOKE-16: Sovereign Booking Portal (Guest Booking)

- **Component Name:** Sovereign Booking Portal — `SovereignStack\External\BookingPortal`
- **Primary purpose:** Guest-facing booking surface for hospitality-tenant properties: branded availability search, pricing display, OTA widget embed, and the guest-side of the booking Pulse that hands off to `ISPOKE-26` (Reservations) to hold inventory.
- **Required Hub capabilities:** HUB-08, HUB-07 (Throttle), HUB-21 (Nexus — tenant resolution), HUB-22 (Ledger — pricing), HUB-02, HUB-12 (Notify), HUB-09 (Signal)
- **Domain focus:** Hospitality vertical / guest booking
- **Feature gaps / future-work:** SearchRequest, AvailabilitySearch (cache-first), HoldInitiator (delegates write to ISPOKE-26), **TenantBrandingRenderer (resolves tenant branding — logo, theme, contact — from HUB-21 config)**, BookingConfirmationListener. The portal is **intentionally thin and stateless** — it does not own the booking state machine (ISPOKE-26 does), does not compute final pricing (HUB-22 does), and does not persist guest identity beyond the session (HUB-04 at check-in). **PCI minimization is enforced — portal never sees card numbers.** Theme management fits (per-tenant branding via HUB-21 already covers most of this).

### 3.17 ESPOKE-17: Sovereign Concierge (AI Concierge)

- **Component Name:** Sovereign Concierge — `SovereignStack\External\Concierge`
- **Primary purpose:** Guest-facing AI concierge surface for hospitality-tenant properties: an intent-classifying chatbot that handles FAQs (wifi, breakfast, checkout time) inline and routes complex queries (room upgrade request, late checkout, complaint) to a human agent via `ISPOKE-08` (Support Desk extension).
- **Required Hub capabilities:** HUB-08, HUB-07, HUB-21, HUB-14 (Search — FAQ knowledge base lookup), HUB-12, HUB-09, HUB-11 (Cloud Storage — transcript persistence), HUB-06
- **Domain focus:** Hospitality vertical / AI chatbot / customer service
- **Feature gaps / future-work:** **IntentClassifierInterface is pluggable — the default RulesBasedClassifier (regex + keyword matching) handles ~70% of inbound queries; the rest are routed to a configured LLM provider (OpenAI / Anthropic / local model) via an adapter interface**. The classifier never decides — it only routes. HandoffRouter opens tickets in ISPOKE-08 with the transcript. ConversationState persisted in HUB-02 cache (24h TTL), promoted to HUB-11 on close. **The LLM adapter is the critical gap — `LlmProviderInterface` (OpenAI / Anthropic / local) is exactly the multi-provider AI routing that ISPOKE-E3→HUB-32 provides.** The Concierge is the **single clear consumer of ISPOKE-E8 BYOK Vault** — tenants bringing their own LLM API keys (per-tenant cost isolation) is a natural fit for a multi-tenant AI concierge.

### 3.18 ESPOKE-18: Sovereign Mobile Check-in (Guest Mobile Check-in)

- **Component Name:** Sovereign Mobile Check-in — `SovereignStack\External\MobileCheckIn`
- **Primary purpose:** Guest-side mobile web flow for contactless check-in at hospitality-tenant properties: registration form, ID document upload, e-signature capture, room assignment confirmation, and digital key issuance.
- **Required Hub capabilities:** HUB-08, HUB-07, HUB-04 (Identity — guest session, SMS OTP), HUB-21, HUB-12, HUB-09, **HUB-20 (Vault — ID document tokenization)**, HUB-11 (Cloud Storage — signed registration-card PDF + signature persistence, 7-year retention), HUB-06, HUB-02
- **Domain focus:** Hospitality vertical / guest mobile check-in / identity
- **Feature gaps / future-work:** CheckInSession state machine; **IdDocumentUploader streams upload to HUB-20 Vault for tokenization — discards original from memory before responding**; ESignatureCapture (SVG path + PDF-content hash); **RegistrationCardPdf — server-side PDF generator from form data**; RoomKeyIssuer (pluggable RoomAccessVendorInterface — Assa Abloy, Salto, dormakaba); CheckInCompletionListener. The spoke is the **privacy-critical surface** of the hospitality vertical. ID documents are PCI-DSS-equivalent PII. **The blueprint already has its own RegistrationCardPdf class** — ISPOKE-E11 Manuscript Exporter's multi-format capability could consolidate this, but is overkill for the single PDF use case. The blueprint's security properties explicitly mention audited retrievals and watermarked documents — fits the slim ISPOKE-E13's privacy dashboard aspect.

---

## 4. Consumer Matrix

**Rows:** 10 reusable ISPOKEs (E4, E5, E6, E7, E8, E9, E10, E11, E13, E15)
**Columns:** 18 existing ESPOKEs (ESPOKE-01 through ESPOKE-18)
**Cells:** `YES` / `MAYBE` / `NO` with one-sentence reason

**Decision criteria:**
- **YES** = the ESPOKE's purpose clearly benefits from this ISPOKE's capability.
- **MAYBE** = the ESPOKE's purpose *could* benefit but it's not obvious without more context.
- **NO** = the ESPOKE's purpose clearly does not benefit.

### 4.1 ISPOKE-E4: Paraphrase Engine — Consumer Matrix

| ESPOKE | Cell | Reason |
|---|---|---|
| 01 Canvas (CMS) | MAYBE | Content variation for SEO is plausible, but CMS itself is render-only — authoring happens in ISPOKE-09, not ESPOKE-01. |
| 02 Connect (REST API) | NO | Pure API surface — no content authoring. |
| 03 Account (Auth) | NO | Identity portal — no long-form content. |
| 04 Discovery (Search) | NO | Search interface — no content authoring. |
| 05 Growth (Marketing) | MAYBE | Landing page copy variation for A/B testing is a known use case, but the blueprint uses ISPOKE-12 (Feature Flags) for A/B variations, not paraphrase. |
| 06 Relay (Notifications) | NO | Notifications are short-form and ephemeral. |
| 07 Nexus (GraphQL) | NO | Pure typed API surface — no content. |
| 08 Prism (Media) | NO | Signed-URL media delivery — no content. |
| 09 Market (Checkout) | NO | E-commerce transactional flow — no content authoring. |
| 10 Pulse (Billing) | NO | Self-service billing — no content authoring. |
| 11 Beacon (Support) | MAYBE | Support articles could be paraphrased for different reading levels, but Beacon consumes articles from ISPOKE-09 rather than authoring them. |
| 12 Forge (Dev Portal) | MAYBE | Developer docs could be paraphrased for different audiences (beginner vs. expert); the TechDocs register exists in E4's 22-type taxonomy. |
| 13 Bridgehead (Partner Gateway) | NO | Webhook gateway — no content. |
| 14 Lens (Analytics) | NO | Analytics ingestion/reporting — no content. |
| 15 Sentinel (Orchestrator) | NO | Operational orchestration — no UI/content. |
| 16 Booking Portal | NO | Stateless guest booking surface. |
| 17 Concierge (AI Chatbot) | MAYBE | FAQ answers could be paraphrased for tone (formal/casual), but ELQ's 22 doc types (fiction/academic/legal) don't match the FAQ register. |
| 18 Mobile Check-in | NO | Identity/PCI flow — no content authoring. |

### 4.2 ISPOKE-E5: Linguix Quality Reviewer — Consumer Matrix

| ESPOKE | Cell | Reason |
|---|---|---|
| 01 Canvas (CMS) | MAYBE | Content authors could benefit from grammar review, but authoring happens in ISPOKE-09; CMS only renders. |
| 02 Connect (REST API) | NO | Pure API — no text authoring UI. |
| 03 Account (Auth) | NO | Identity portal — no long-form text. |
| 04 Discovery (Search) | NO | Search interface — no authoring. |
| 05 Growth (Marketing) | YES | BlockEngine authors landing page copy — Linguix-style inline grammar review is a natural fit (matches the original §6 prediction of "Showcase ESPOKE for product copy QA"). |
| 06 Relay (Notifications) | NO | Notifications are short-form. |
| 07 Nexus (GraphQL) | NO | Pure API surface. |
| 08 Prism (Media) | NO | Media delivery — no UI. |
| 09 Market (Checkout) | MAYBE | Checkout copy review is plausible (reduces cart abandonment from unclear copy), but checkout is mostly UI components, not long-form text. |
| 10 Pulse (Billing) | MAYBE | Invoice description review is a stretch — invoice text is mostly templated. |
| 11 Beacon (Support) | MAYBE | Knowledge base article review at authoring time is a fit, but Beacon doesn't author articles — that happens in ISPOKE-09. |
| 12 Forge (Dev Portal) | MAYBE | Developer doc review at authoring time is plausible, but Forge transforms Markdown schemas rather than authoring inline. |
| 13 Bridgehead | NO | Webhook gateway — no UI. |
| 14 Lens (Analytics) | NO | Analytics dashboards — no long-form text. |
| 15 Sentinel | NO | Orchestrator — no public UI. |
| 16 Booking Portal | NO | Stateless booking surface. |
| 17 Concierge | MAYBE | Chat response review is a stretch — chat is short-form and ephemeral; the classifier already routes rather than authors. |
| 18 Mobile Check-in | NO | Identity/PCI flow — no content. |

### 4.3 ISPOKE-E6: Readability Auditor — Consumer Matrix

| ESPOKE | Cell | Reason |
|---|---|---|
| 01 Canvas (CMS) | MAYBE | CMS could surface readability scores on rendered pages for end-user guidance, but the rendering path itself doesn't need it. |
| 02 Connect (REST API) | NO | Pure API — no content display. |
| 03 Account (Auth) | NO | Identity portal — no long-form text. |
| 04 Discovery (Search) | NO | Search interface — no content display beyond snippets. |
| 05 Growth (Marketing) | YES | Landing page copy authored via BlockEngine — readability audit at authoring time is a clear fit (conversion correlates with readability). |
| 06 Relay (Notifications) | NO | Notifications are short-form. |
| 07 Nexus (GraphQL) | NO | Pure API surface. |
| 08 Prism (Media) | NO | Media delivery — no text display. |
| 09 Market (Checkout) | MAYBE | Checkout copy readability tuning could reduce cart abandonment, but checkout is mostly UI components. |
| 10 Pulse (Billing) | MAYBE | Invoice description readability is a stretch — mostly templated text. |
| 11 Beacon (Support) | YES | Support knowledge base can surface readability scores to users picking articles (e.g., "Reading Level: 8th Grade" badge). |
| 12 Forge (Dev Portal) | YES | Developer documentation can surface readability to readers — technical content has high ARI by default; audit could flag overly dense docs. |
| 13 Bridgehead | NO | Webhook gateway — no UI. |
| 14 Lens (Analytics) | NO | Analytics dashboards — no long-form text. |
| 15 Sentinel | NO | Orchestrator — no public UI. |
| 16 Booking Portal | NO | Stateless booking surface — minimal text. |
| 17 Concierge | MAYBE | FAQ answer readability tuning is plausible, but FAQs are short-form. |
| 18 Mobile Check-in | NO | Identity/PCI flow — no long-form text. |

### 4.4 ISPOKE-E7: RAG Retriever — Consumer Matrix

| ESPOKE | Cell | Reason |
|---|---|---|
| 01 Canvas (CMS) | MAYBE | Published content could be RAG-indexed for "related articles" features, but CMS itself doesn't own the content (ISPOKE-09 does). |
| 02 Connect (REST API) | NO | Pure API surface. |
| 03 Account (Auth) | NO | Identity portal. |
| 04 Discovery (Search) | NO | Search already uses HUB-14 directly via Bridge — RAG Retriever would be redundant unless positioned as a semantic-search layer above HUB-14. |
| 05 Growth (Marketing) | NO | Landing pages don't have a corpus to RAG-index. |
| 06 Relay (Notifications) | NO | Notifications don't have searchable content. |
| 07 Nexus (GraphQL) | NO | Pure API surface. |
| 08 Prism (Media) | NO | Media delivery — no text corpus. |
| 09 Market (Checkout) | NO | E-commerce transactional flow. |
| 10 Pulse (Billing) | NO | Billing portal. |
| 11 Beacon (Support) | YES | Support knowledge base RAG is a strong fit — find semantically relevant articles for a customer's support question, beyond keyword search. |
| 12 Forge (Dev Portal) | MAYBE | Developer docs RAG for "smart search" is plausible, but Forge already has SchemaIntrospector + DocGenerator; the boundary with HUB-14 needs definition. |
| 13 Bridgehead | NO | Webhook gateway. |
| 14 Lens (Analytics) | NO | Analytics events are time-series, not RAG-indexable text. |
| 15 Sentinel | NO | Orchestrator. |
| 16 Booking Portal | NO | Stateless booking surface. |
| 17 Concierge | MAYBE | Concierge uses HUB-14 for FAQ lookup; RAG could upgrade semantic-match quality, but HUB-14 may already cover this — boundary needs definition. |
| 18 Mobile Check-in | NO | Identity/PCI flow. |

### 4.5 ISPOKE-E8: BYOK Vault — Consumer Matrix

| ESPOKE | Cell | Reason |
|---|---|---|
| 01 Canvas (CMS) | NO | CMS doesn't call external APIs on behalf of users. |
| 02 Connect (REST API) | NO | REST API is consumed, not a consumer of external APIs. |
| 03 Account (Auth) | NO | Identity is platform-owned via HUB-04. |
| 04 Discovery (Search) | NO | Search uses HUB-14 (platform-owned). |
| 05 Growth (Marketing) | NO | Marketing consumes ISPOKE-12 (feature flags) and HUB-31 (analytics) — both platform-owned. |
| 06 Relay (Notifications) | NO | Uses HUB-12 channels (SendGrid/Twilio/Firebase) — platform-level, not per-user. |
| 07 Nexus (GraphQL) | NO | Pure API surface. |
| 08 Prism (Media) | NO | Media delivery uses HUB-03/HUB-02 (platform-owned). |
| 09 Market (Checkout) | NO | PaymentProviderInterface (Stripe/PayPal) is platform-owned abstraction, not per-user BYOK. |
| 10 Pulse (Billing) | NO | Same as Checkout — platform-owned payment abstraction. |
| 11 Beacon (Support) | NO | Beacon uses HUB-26/HUB-08/HUB-04 (platform-owned). |
| 12 Forge (Dev Portal) | MAYBE | Sandbox "uses the developer's actual API keys (via HUB-04 session) for real Gateway calls" — BYOK could enable devs to bring their own external API keys (e.g., their own LLM endpoint) for sandbox testing, but the blueprint doesn't currently describe this. |
| 13 Bridgehead | NO | Partner auth uses mTLS / custom header signatures, not API keys. |
| 14 Lens (Analytics) | NO | No external API calls on behalf of users. |
| 15 Sentinel | NO | Orchestrator. |
| 16 Booking Portal | NO | Stateless — uses HUB-22 for pricing (platform-owned). |
| 17 Concierge | YES | LlmClassifier adapter calls OpenAI/Anthropic/local — per-tenant BYOK key isolation fits naturally (tenants bringing their own LLM API keys for cost isolation is a clear concierge use case). |
| 18 Mobile Check-in | NO | Uses HUB-20 Vault for ID tokenization (platform-owned), not user API keys. |

### 4.6 ISPOKE-E9: Remote Backup Orchestrator — Consumer Matrix

| ESPOKE | Cell | Reason |
|---|---|---|
| 01 Canvas (CMS) | NO | CMS content lives in ISPOKE-09 (Internal Knowledge Base); CMS itself doesn't own user content. |
| 02 Connect (REST API) | NO | Pure API surface. |
| 03 Account (Auth) | YES | Account portal can offer "export my data" to GitHub/WebDAV — GDPR-friendly user-initiated backup is a common Account-portal feature. |
| 04 Discovery (Search) | NO | Search interface — no user content. |
| 05 Growth (Marketing) | MAYBE | Landing page backup to GitHub (version control for marketers) is plausible, but the blueprint doesn't describe it. |
| 06 Relay (Notifications) | NO | Notifications are ephemeral. |
| 07 Nexus (GraphQL) | NO | Pure API surface. |
| 08 Prism (Media) | NO | Media is in HUB-03 (platform-owned). |
| 09 Market (Checkout) | NO | Orders are recorded in ISPOKE-13 (Ledger), not in ESPOKE-09. |
| 10 Pulse (Billing) | NO | Billing records are in ISPOKE-13 (Ledger). |
| 11 Beacon (Support) | YES | Support ticket history export for compliance is a clear fit — customer-initiated backup of their own support interactions. |
| 12 Forge (Dev Portal) | MAYBE | Sandbox API usage logs could be exported to remote target, but the blueprint doesn't describe this. |
| 13 Bridgehead | NO | Webhook gateway. |
| 14 Lens (Analytics) | NO | Analytics events are server-side. |
| 15 Sentinel | NO | Orchestrator. |
| 16 Booking Portal | NO | Blueprint explicitly states the portal is stateless and writes nothing. |
| 17 Concierge | MAYBE | Guest conversation transcript export for compliance is plausible, but transcripts are already in HUB-11 with 90-day retention — remote backup is duplicative unless longer retention is needed. |
| 18 Mobile Check-in | MAYBE | Signed registration-card PDFs are already in HUB-11 with 7-year retention — remote backup to S3 is duplicative unless cross-region durability is required. |

### 4.7 ISPOKE-E10: EPUB Importer — Consumer Matrix

| ESPOKE | Cell | Reason |
|---|---|---|
| 01 Canvas (CMS) | MAYBE | Could ingest EPUB content as CMS articles, but content authoring happens in ISPOKE-09 (Internal Knowledge Base), not ESPOKE-01. |
| 02 Connect (REST API) | NO | Pure API surface. |
| 03 Account (Auth) | NO | Identity portal. |
| 04 Discovery (Search) | NO | Search interface. |
| 05 Growth (Marketing) | NO | Landing pages aren't EPUB-sourced. |
| 06 Relay (Notifications) | NO | Notifications. |
| 07 Nexus (GraphQL) | NO | Pure API surface. |
| 08 Prism (Media) | NO | Media delivery. |
| 09 Market (Checkout) | NO | E-commerce. |
| 10 Pulse (Billing) | NO | Billing. |
| 11 Beacon (Support) | YES | Support knowledge base can ingest EPUB manuals — vendor documentation commonly ships as EPUB, and Beacon's KnowledgeBaseConsumer could consume EPUB-ingested articles. |
| 12 Forge (Dev Portal) | MAYBE | Developer docs could be ingested from EPUB, but doc portals typically author docs natively in Markdown; EPUB ingestion is rare. |
| 13 Bridgehead | NO | Webhook gateway. |
| 14 Lens (Analytics) | NO | Analytics. |
| 15 Sentinel | NO | Orchestrator. |
| 16 Booking Portal | NO | Stateless. |
| 17 Concierge | NO | Chatbot. |
| 18 Mobile Check-in | NO | Identity/PCI flow. |

### 4.8 ISPOKE-E11: Manuscript Exporter — Consumer Matrix

**Critical:** E11 consumes ISPOKE-E12 (Censorship & Redaction Engine, private). For cross-ESPOKE consumption, E11's redaction-aware feature must be made optional/configurable. See §8 Open Question #1.

| ESPOKE | Cell | Reason |
|---|---|---|
| 01 Canvas (CMS) | MAYBE | CMS pages could be exported to PDF/HTML for offline reading, but content lives in ISPOKE-09; CMS is HTML-native already. |
| 02 Connect (REST API) | NO | Pure API surface. |
| 03 Account (Auth) | MAYBE | Account data export to PDF (statements) is plausible, but Account portal is mostly identity management. |
| 04 Discovery (Search) | NO | Search interface. |
| 05 Growth (Marketing) | NO | Landing pages are live HTML, not export artifacts. |
| 06 Relay (Notifications) | NO | Notifications are ephemeral. |
| 07 Nexus (GraphQL) | NO | Pure API surface. |
| 08 Prism (Media) | NO | Media delivery. |
| 09 Market (Checkout) | MAYBE | Receipt PDF generation is in checkout, but PaymentProviderInterface issues tokens and receipts are simple — E11 is overkill. |
| 10 Pulse (Billing) | YES | InvoiceEngine explicitly generates "downloadable PDFs via the Bridge" — E11's PDF export with running headers/footers and page numbers is a strong fit for invoice PDFs. |
| 11 Beacon (Support) | YES | Support articles could be exported to PDF/Markdown for offline reference — strong fit for compliance and customer self-service. |
| 12 Forge (Dev Portal) | YES | Developer documentation export to PDF/EPUB is a strong fit — developers commonly want offline docs. |
| 13 Bridgehead | NO | Webhook gateway. |
| 14 Lens (Analytics) | MAYBE | Analytics reports could be exported to PDF, but dashboards are typically interactive, not PDF. |
| 15 Sentinel | NO | Orchestrator. |
| 16 Booking Portal | NO | Stateless. |
| 17 Concierge | NO | Chat is ephemeral. |
| 18 Mobile Check-in | MAYBE | RegistrationCardPdf already exists as a server-side PDF generator — E11's multi-format capability could consolidate, but is overkill for the single PDF use case. |

### 4.9 ISPOKE-E13: Privacy & Audit Ledger (slim, post-split) — Consumer Matrix

**Note:** Per ELQ-ANALYSIS.md §6.4.3, E13 is split: the audit log *mechanism* is absorbed into HUB-06 Auditor (already shipped); the 5 Eloq-specific privacy *policies* + dashboard remain as a slim ISPOKE. This matrix assesses the slim version (policy labels + privacy dashboard), not the mechanism.

| ESPOKE | Cell | Reason |
|---|---|---|
| 01 Canvas (CMS) | NO | CMS already uses HUB-06 for audit; the privacy-policy dashboard aspect doesn't fit a public CMS. |
| 02 Connect (REST API) | NO | REST API already uses HUB-06 for "every public API call logged in HUB-06 with developer attribution" — no separate privacy dashboard needed. |
| 03 Account (Auth) | YES | SecurityCenter is explicitly "UI for customers to view active sessions and security logs" — this is the privacy dashboard aspect of slim E13. |
| 04 Discovery (Search) | NO | Search interface — no privacy dashboard. |
| 05 Growth (Marketing) | NO | Marketing doesn't surface audit logs to users. |
| 06 Relay (Notifications) | NO | Notifications already use HUB-06; no user-facing privacy dashboard. |
| 07 Nexus (GraphQL) | NO | Pure API surface. |
| 08 Prism (Media) | NO | Media delivery. |
| 09 Market (Checkout) | NO | Checkout doesn't surface audit logs to users. |
| 10 Pulse (Billing) | MAYBE | Billing portal could show audit log of billing events (plan changes, invoice access), but the blueprint doesn't describe this. |
| 11 Beacon (Support) | NO | Beacon doesn't surface audit logs to customers. |
| 12 Forge (Dev Portal) | MAYBE | DeveloperConsole dashboard for managing API keys/webhooks — could include audit log of API key usage; blueprint mentions developer attribution but not a user-facing dashboard. |
| 13 Bridgehead | NO | Webhook gateway — no user-facing UI. |
| 14 Lens (Analytics) | NO | Analytics IS the audit — doesn't need another. |
| 15 Sentinel | NO | Orchestrator — no public UI. |
| 16 Booking Portal | NO | Stateless. |
| 17 Concierge | MAYBE | Guest could view audit log of classifier decisions and transcript access, but the blueprint already audits every classifier decision to HUB-06 — a guest-facing dashboard is not currently described. |
| 18 Mobile Check-in | YES | Guest privacy dashboard showing who accessed their ID document — the blueprint explicitly mentions "every retrieval is audited and watermarked" and could surface this to the guest. |

### 4.10 ISPOKE-E15: Theme Manager — Consumer Matrix

| ESPOKE | Cell | Reason |
|---|---|---|
| 01 Canvas (CMS) | YES | Public-facing website needs light/dark/system theme management. |
| 02 Connect (REST API) | NO | Pure API surface — no UI. |
| 03 Account (Auth) | YES | Account portal is UI-bearing — theme management fits. |
| 04 Discovery (Search) | YES | Search UI needs theme. |
| 05 Growth (Marketing) | YES | Landing pages need theme, though brand-specific themes may override; light/dark is still standard. |
| 06 Relay (Notifications) | MAYBE | NotificationArchive within Account Portal is UI-bearing — theme fits, but the notifications themselves are emails/push (no theme). |
| 07 Nexus (GraphQL) | NO | Pure API surface. |
| 08 Prism (Media) | NO | Signed-URL media delivery — no UI. |
| 09 Market (Checkout) | YES | Checkout UI needs theme. |
| 10 Pulse (Billing) | YES | Billing dashboard needs theme. |
| 11 Beacon (Support) | YES | Support portal needs theme. |
| 12 Forge (Dev Portal) | YES | Dev portal needs theme. |
| 13 Bridgehead | NO | Webhook gateway — no public UI. |
| 14 Lens (Analytics) | YES | Analytics dashboard needs theme. |
| 15 Sentinel | NO | Orchestrator — no public UI. |
| 16 Booking Portal | YES | Guest booking page needs theme (per-tenant branding via HUB-21 already covers branding, but light/dark is still useful). |
| 17 Concierge | MAYBE | Chat widget embedded in tenant pages — HUB-21 tenant branding may already cover this; theme management could be redundant. |
| 18 Mobile Check-in | YES | Mobile UI needs theme. |

---

## 5. Per-ISPOKE Consumer Summary

| ISPOKE | YES | MAYBE | NO | YES % | Top 3 consumers by fit | Crosses 50% threshold? |
|---|---|---|---|---|---|---|
| E4 Paraphrase Engine | 0 | 5 | 13 | 0% | (none) — top MAYBEs: E12 Forge, E11 Beacon, E05 Marketing, E17 Concierge, E01 CMS | NO |
| E5 Linguix Quality Reviewer | 1 | 5 | 12 | 5.6% | E05 Marketing (clear); MAYBEs: E11 Beacon, E12 Forge, E01 CMS, E09 Checkout, E10 Billing, E17 Concierge | NO |
| E6 Readability Auditor | 3 | 4 | 11 | 16.7% | E05 Marketing, E11 Beacon, E12 Forge; MAYBEs: E01 CMS, E09 Checkout, E10 Billing, E17 Concierge | NO |
| E7 RAG Retriever | 1 | 3 | 14 | 5.6% | E11 Beacon (clear); MAYBEs: E12 Forge, E17 Concierge, E01 CMS | NO |
| E8 BYOK Vault | 1 | 1 | 16 | 5.6% | E17 Concierge (clear); MAYBE: E12 Forge | NO |
| E9 Remote Backup Orchestrator | 2 | 4 | 12 | 11.1% | E03 Account, E11 Beacon; MAYBEs: E05 Marketing, E12 Forge, E17 Concierge, E18 Mobile Check-in | NO |
| E10 EPUB Importer | 1 | 2 | 15 | 5.6% | E11 Beacon (clear); MAYBEs: E01 CMS, E12 Forge | NO |
| E11 Manuscript Exporter | 3 | 5 | 10 | 16.7% | E10 Billing, E11 Beacon, E12 Forge; MAYBEs: E01 CMS, E03 Account, E09 Checkout, E14 Lens, E18 Mobile Check-in | NO |
| E13 Privacy & Audit Ledger (slim) | 2 | 3 | 13 | 11.1% | E03 Account, E18 Mobile Check-in; MAYBEs: E10 Billing, E12 Forge, E17 Concierge | NO |
| E15 Theme Manager | 11 | 2 | 5 | 61.1% | E01 CMS, E03 Account, E04 Discovery, E05 Marketing, E09 Checkout, E10 Billing, E11 Beacon, E12 Forge, E14 Lens, E16 Booking Portal, E18 Mobile Check-in; MAYBEs: E06 Relay, E17 Concierge | **YES** |

**Cell distribution totals:** YES=25 (13.9%), MAYBE=34 (18.9%), NO=121 (67.2%). Sums to 180 ✓.

**Per-ESPOKE YES counts** (for cross-reference):

| ESPOKE | YES | MAYBE | NO |
|---|---|---|---|
| 01 Canvas (CMS) | 1 (E15) | 6 | 3 |
| 02 Connect (REST API) | 0 | 0 | 10 |
| 03 Account (Auth) | 3 (E9, E13, E15) | 1 | 6 |
| 04 Discovery (Search) | 1 (E15) | 0 | 9 |
| 05 Growth (Marketing) | 3 (E5, E6, E15) | 2 | 5 |
| 06 Relay (Notifications) | 0 | 1 | 9 |
| 07 Nexus (GraphQL) | 0 | 0 | 10 |
| 08 Prism (Media) | 0 | 0 | 10 |
| 09 Market (Checkout) | 1 (E15) | 3 | 6 |
| 10 Pulse (Billing) | 2 (E11, E15) | 4 | 4 |
| 11 Beacon (Support) | 6 (E6, E7, E9, E10, E11, E15) | 2 | 2 |
| 12 Forge (Dev Portal) | 3 (E6, E11, E15) | 7 | 0 |
| 13 Bridgehead | 0 | 0 | 10 |
| 14 Lens (Analytics) | 1 (E15) | 1 | 8 |
| 15 Sentinel | 0 | 0 | 10 |
| 16 Booking Portal | 1 (E15) | 0 | 9 |
| 17 Concierge | 1 (E8) | 7 | 2 |
| 18 Mobile Check-in | 2 (E13, E15) | 2 | 6 |

---

## 6. Hub-Promotion-Candidate Reassessment

Per APP-MODEL-REFINEMENT-5 extension #2, the Hub-promotion-candidate threshold is **≥50% of ESPOKEs declaring YES consumption**. Of the 18 existing ESPOKEs, 50% = 9.0, so an ISPOKE with ≥9 YES votes crosses the threshold.

### 6.1 NEW Hub-promotion candidate identified

**ISPOKE-E15 Theme Manager: 11 YES (61.1%) — CROSSES the threshold.**

This is the single ISPOKE in the matrix that crosses the 50% threshold. However, ELQ-ANALYSIS.md §6.4.4 already anticipated this and proposed that E15 may be **absorbed into HUB-26 UI Elements** rather than promoted to a new Hub. The consumer matrix strongly validates that anticipation: theme management is small (91 LOC), generic across all UI-bearing DGLab apps, and HUB-26 UI Elements is the natural home.

**Recommendation:** Do NOT create a new Hub (e.g., HUB-33 Theme Manager). Instead, **ratify immediate absorption of E15's capability into HUB-26 UI Elements when HUB-26 ships**. HUB-26 is in the SDLC-AUDIT-1 NONE-dependency tier (pure library, can proceed without runtime substrate decision) — there is no sequencing blocker. This is a HUB-absorption candidate, not a new-Hub-creation candidate.

Until HUB-26 ships, E15 should remain in `packages/spoke/internal/eloq-theme/` and be shared via composition. When HUB-26 lands, the package moves to `packages/hub/ui-elements/Theme/` (or similar), the Eloq Application Manifest updates to consume HUB-26 (not the ISPOKE), and the 11 consumers identified here can adopt HUB-26 directly.

### 6.2 Original Hub-promotion candidates reassessed

#### 6.2.1 ISPOKE-E8 BYOK Vault — DEMOTED (was DEFER, now DEMOTE)

- **Original verdict (ELQ-ANALYSIS.md §6.4.1):** DEFER promotion until second consumer declares consumption.
- **Reassessment:** 1 YES out of 18 = 5.6%. The only consumer is ESPOKE-17 Concierge. ESPOKE-12 Forge is MAYBE (sandbox API keys). No other ESPOKE in the existing 18 has a per-user external-API-key use case.
- **New verdict:** **DEMOTE — ISPOKE-E8 remains an ISPOKE with `reusable: true`.** The 50% threshold is not even close to being met. Per APP-MODEL-REFINEMENT-5 deferred-promotion rule, E8 should remain in `packages/spoke/internal/eloq-byok-vault/` until either (a) a new content-creation ESPOKE is added that needs per-user API keys, or (b) the planned ESPOKE-17 Concierge is implemented and declares E8 in its Application Manifest — at which point the contract reusability audit triggers a Hub-promotion proposal (now with 2 consumers, still far below 50%).
- **Action:** No immediate Hub ratification. Build E8 as ISPOKE when Concierge implementation lands.

#### 6.2.2 ISPOKE-E9 Remote Backup Orchestrator — DEMOTED (was DEFER, now DEMOTE)

- **Original verdict (ELQ-ANALYSIS.md §6.4.2):** DEFER promotion; overlap with HUB-11 Cloud Storage (S3/R2/MinIO target) noted.
- **Reassessment:** 2 YES out of 18 = 11.1%. Consumers are ESPOKE-03 Account and ESPOKE-11 Beacon. 4 MAYBEs (E5 Marketing, E12 Forge, E17 Concierge, E18 Mobile Check-in) are mostly duplicative with HUB-11 Cloud Storage, which already provides durable cloud storage for several of these apps.
- **New verdict:** **DEMOTE — ISPOKE-E9 remains an ISPOKE with `reusable: true`.** The 50% threshold is not met. The HUB-11 overlap is significant: E9's S3 target is redundant with HUB-11, leaving GitHub/WebDAV/webhook as the differentiating targets. The right move is to position E9 as a **user-initiated multi-target backup dispatcher** that delegates its S3 calls to HUB-11, while keeping its GitHub/WebDAV/webhook targets as the differentiating capability.
- **Action:** No immediate Hub ratification. When E9 is built, scope it to delegate S3 calls to HUB-11 rather than re-implementing AWS SigV4.

#### 6.2.3 ISPOKE-E13 Privacy & Audit Ledger — REAFFIRMED SPLIT (was PARTIAL, stays PARTIAL)

- **Original verdict (ELQ-ANALYSIS.md §6.4.3):** SPLIT — mechanism → HUB-06 Auditor (already shipped); policy labels → slim ISPOKE.
- **Reassessment:** 2 YES out of 18 = 11.1% for the slim (post-split) version. Consumers are ESPOKE-03 Account (SecurityCenter explicitly surfaces security logs) and ESPOKE-18 Mobile Check-in (guest privacy dashboard for ID document access). 3 MAYBEs (E10 Billing, E12 Forge, E17 Concierge).
- **New verdict:** **REAFFIRM THE SPLIT.** The mechanism belongs to HUB-06 Auditor (already shipped per PR #256). The slim policy-label ISPOKE remains — it provides the privacy dashboard aspect that HUB-06 doesn't (HUB-06 records; the slim E13 displays to users with policy labels). The consumer count (2 YES) is far below the 50% threshold, so the slim E13 stays as an ISPOKE.
- **Action:** No immediate Hub ratification. When E13 is built, build the slim version (5 Eloq-specific privacy policies as decorators on HUB-06 events), not the original thick version.

### 6.3 Should any ISPOKE be IMMEDIATELY promoted to Hub (like E3 was)?

**No.** None of the 10 reusable ISPOKEs reaches near-universal consumer fit requiring immediate Hub ratification.

- E15 (Theme Manager) is the closest at 61.1%, but is better absorbed into HUB-26 than promoted to a new Hub (per §6.1 above).
- All other ISPOKEs are below 20% YES — far below the "near-universal" bar that justified E3's immediate ratification (E3 was the multi-provider AI router, which is foundational infrastructure analogous to Identity or Audit).

The E3→HUB-32 immediate ratification was justified by the foundational nature of LLM invocation (the tech lead judged it as universally needed as Identity/Audit). None of the remaining 10 ISPOKEs has that foundational character — they're domain-specific capabilities (writing-app paraphrasing, content export, EPUB import, etc.) that benefit a subset of apps.

### 6.4 Hub-promotion reassessment summary

| ISPOKE | Original status | New YES % | New status | Change |
|---|---|---|---|---|
| E4 Paraphrase | (not a candidate) | 0% | ISPOKE | no change |
| E5 Linguix | (not a candidate) | 5.6% | ISPOKE | no change |
| E6 Readability | (not a candidate) | 16.7% | ISPOKE | no change |
| E7 RAG | (not a candidate) | 5.6% | ISPOKE | no change |
| E8 BYOK Vault | DEFER candidate | 5.6% | ISPOKE (DEMOTED) | demoted |
| E9 Remote Backup | DEFER candidate | 11.1% | ISPOKE (DEMOTED) | demoted |
| E10 EPUB Importer | (not a candidate) | 5.6% | ISPOKE | no change |
| E11 Manuscript Exporter | (not a candidate) | 16.7% | ISPOKE | no change |
| E13 Privacy & Audit | PARTIAL (split) candidate | 11.1% | ISPOKE slim (REAFFIRMED SPLIT) | reaffirmed |
| E15 Theme Manager | PARTIAL (HUB-26 absorption) candidate | 61.1% | HUB-26 ABSORPTION CANDIDATE (NEW FULL) | promoted to full candidate |

**Tally:**
- NEW Hub-promotion candidates identified: **1** (E15, with HUB-26 absorption as the recommended path)
- Original Hub-promotion candidates reaffirmed: **1** (E13, with the original split reaffirmed — mechanism to HUB-06, slim ISPOKE stays)
- Original Hub-promotion candidates demoted: **2** (E8 and E9 — neither crosses the 50% threshold; both remain ISPOKEs)
- ISPOKEs immediately promoted to new Hub (like E3 was): **0**

---

## 7. Consumption-Order Recommendation

Per ELQ-ANALYSIS.md §7.1 (open question, now answered by this matrix): "order of consumption determines which ISPOKEs become Hub-promotion candidates first." The matrix provides the data to recommend an admission order for the SDLC.

### 7.1 ISPOKEs to build first (by consumer demand)

Priority is determined by: (a) YES count, (b) Hub dependencies ready (or already shipped), (c) implementation effort (build-units from ELQ-ANALYSIS.md §8), (d) whether the ISPOKE blocks a Hub-absorption decision.

| Priority | ISPOKE | YES | Build-units | Hub deps | Rationale |
|---|---|---|---|---|---|
| 1 | E6 Readability Auditor | 3 | 1.0 | none (pure library) | Pure library, no Hub deps, 3 clear consumers (Marketing, Beacon, Forge). Fastest ship — line-by-line PHP port of `readability.service.ts`. **First to ship.** |
| 2 | E15 Theme Manager | 11 | 0.5 | none (pure library) | Pure library, 11 consumers waiting. But: don't ship as standalone ISPOKE — instead, fold into HUB-26 UI Elements when HUB-26 ships. Until then, share via composition from `packages/spoke/internal/eloq-theme/`. **Build now, absorb into HUB-26 later.** |
| 3 | E5 Linguix Quality Reviewer | 1 | 1.0 | HUB-32 (shipped via immediate ratification) | 1 clear consumer (Marketing) + 5 MAYBEs. Small build. Depends on HUB-32 which is already ratified. **Third to ship.** |
| 4 | E11 Manuscript Exporter | 3 | 2.5 | none (but consumes private E12) | 3 clear consumers (Billing, Beacon, Forge) + 5 MAYBEs. **Blocked on resolving E12 private dependency** (see §8 Open Question #1). Once E12's redaction feature is made optional, ship E11. |
| 5 | E13 Privacy & Audit Ledger (slim) | 2 | 0.5 | HUB-06 (already shipped) | 2 clear consumers (Account, Mobile Check-in) + 3 MAYBEs. Build the slim version (policy labels decorating HUB-06 events), not the original thick version. **Fifth to ship.** |
| 6 | E8 BYOK Vault | 1 | 1.5 | HUB-20 Vault, HUB-04 Identity | 1 clear consumer (Concierge) + 1 MAYBE (Forge). Build when Concierge implementation lands; the contract reusability audit at that point will reaffirm or revise the ISPOKE boundary. |
| 7 | E10 EPUB Importer | 1 | 2.0 | none | 1 clear consumer (Beacon) + 2 MAYBEs. Pure porting effort (ZipArchive + Masterminds/HTML5). |
| 8 | E7 RAG Retriever | 1 | 2.0 | HUB-14, HUB-10, HUB-32 | 1 clear consumer (Beacon) + 3 MAYBEs. **Blocked on boundary clarification with HUB-14** (see §8 Open Question #5). Build after HUB-14 ships and the boundary is defined. |
| 9 | E9 Remote Backup Orchestrator | 2 | 4.0 | HUB-10, HUB-25 (pending), HUB-11, HUB-06 | 2 clear consumers (Account, Beacon) + 4 MAYBEs. **Largest effort (4 build-units).** Multiple Hub deps including HUB-25 (pending per HUB-FOUNDATION-SWEEP-2). Build last among the multi-consumer ISPOKEs. |
| 10 | E4 Paraphrase Engine | 0 | 2.0 | HUB-32 | 0 YES consumers; 5 MAYBEs. No existing ESPOKE produces long-form content that needs paraphrasing. **Defer build until either Eloq ESPOKE-19 ships or a new content-creation ESPOKE is added.** |

### 7.2 First non-Eloq consumers (cross-app composition test targets)

The tech lead should pick ESPOKEs that exercise the broadest range of ELQ ISPOKE capabilities as the first cross-app composition tests. Ranked by YES count and MAYBE breadth:

| Rank | ESPOKE | YES | MAYBE | ISPOKEs it would consume | Rationale |
|---|---|---|---|---|---|
| 1 | **ESPOKE-11 Beacon (Support)** | 6 | 2 | E6 Readability, E7 RAG, E9 Remote Backup, E10 EPUB Importer, E11 Manuscript Exporter, E15 Theme Manager | Strongest consumer — Beacon's knowledge base + ticket workflow is the closest existing analog to Eloq's document model. Exercises 6 of 10 ISPOKEs across all 4 porting phases (foundation, infrastructure, I/O, UX). **Recommended as first cross-app composition test.** |
| 2 | **ESPOKE-12 Forge (Dev Portal)** | 3 | 7 | E6 Readability, E11 Manuscript Exporter, E15 Theme Manager (+ 7 MAYBEs including E4, E5, E7, E8, E9, E10, E13) | Most "ISPOKE-curious" — every cell is at least MAYBE. Forge's doc-portal + sandbox model is the second-closest analog to a content app. The 7 MAYBEs make Forge a natural "stress test" for ISPOKE contracts — if Forge upgrades MAYBEs to YES, the consumer matrix shifts significantly. **Recommended as second cross-app composition test.** |
| 3 | **ESPOKE-03 Account (Auth)** | 3 | 1 | E9 Remote Backup, E13 Privacy & Audit, E15 Theme Manager | Account portal is a fundamental ESPOKE (auth is required by many others). Its 3 YES consumers span infrastructure (E9 backup), UX (E13 privacy dashboard), and foundation (E15 theme). **Recommended as third cross-app composition test — but note that Account is blocked on HUB-04, HUB-05, HUB-26 (none implemented yet).** |
| 3 (tied) | **ESPOKE-05 Growth (Marketing)** | 3 | 2 | E5 Linguix, E6 Readability, E15 Theme Manager | Marketing's BlockEngine is the only ESPOKE in the 18 with a block-based content editor — making it the closest existing analog to Eloq's writing surface. Consumes the writing-app ISPOKEs (E5, E6) directly. **Recommended as alternative third cross-app composition test, especially if the tech lead wants to validate E5+E6+E15 composition with a content-creation app.** |

### 7.3 Recommended SDLC admission order

Combining the ISPOKE build order (§7.1) with the cross-app composition test targets (§7.2), the recommended SDLC admission order is:

**Phase A — Foundation ISPOKEs (no Hub deps, ship first):**
1. ISPOKE-E6 Readability Auditor (1 build-unit) — pure library, 3 consumers waiting
2. ISPOKE-E15 Theme Manager (0.5 build-units) — pure library; build now, absorb into HUB-26 later when HUB-26 ships

**Phase B — First cross-app composition test (target: ESPOKE-11 Beacon):**
3. Ship Beacon's required Hub deps first (HUB-14 Search, HUB-04 Identity, HUB-26 UI — all already in SDLC pipeline)
4. ISPOKE-E10 EPUB Importer (2 build-units) — Beacon consumes for EPUB manual ingestion
5. ISPOKE-E11 Manuscript Exporter (2.5 build-units) — Beacon consumes for article PDF/Markdown export. **Must resolve E12 private dependency first** (see §8).
6. ISPOKE-E7 RAG Retriever (2 build-units) — Beacon consumes for semantic FAQ/article lookup. **Must clarify HUB-14 boundary first** (see §8).
7. ISPOKE-E9 Remote Backup Orchestrator (4 build-units) — Beacon consumes for ticket history export

**Phase C — Linguix + Privacy (parallel):**
8. ISPOKE-E5 Linguix Quality Reviewer (1 build-unit) — Marketing consumes (depends HUB-32, shipped)
9. ISPOKE-E13 Privacy & Audit Ledger slim (0.5 build-units) — Account + Mobile Check-in consume (depends HUB-06, shipped)

**Phase D — Hospitality vertical (target: ESPOKE-17 Concierge):**
10. ISPOKE-E8 BYOK Vault (1.5 build-units) — Concierge consumes for per-tenant LLM key isolation (depends HUB-20 Vault)

**Phase E — Defer:**
11. ISPOKE-E4 Paraphrase Engine (2 build-units) — 0 consumers in existing 18 ESPOKEs. Defer until Eloq ESPOKE-19 ships or a new content-creation ESPOKE is added.

**Estimated total:** ~15 build-units for Phases A-D (excluding deferred E4). At 2.5 build-units/day, this is ~6 working days — assuming all Hub deps are shipped. If Hub deps are not yet shipped, add the Hub shipping effort first per HUB-FOUNDATION-SWEEP-2.

---

## 8. Open Questions Discovered

The following questions emerged during the consumer-matrix analysis and require tech-lead decision.

### 8.1 ISPOKE-E11 (Manuscript Exporter) consumes the private ISPOKE-E12 (Censorship & Redaction Engine) — should E11's redaction feature be made optional?

**Context:** ELQ-ANALYSIS.md §6.1.11 states: "Consumes (ISPOKE): ISPOKE-E12 (Censorship Engine — for redaction-aware content generation)." E12 is `reusable: false` (private to Eloq ESPOKE). For E11 to be reusable by other ESPOKEs (the matrix shows 3 YES consumers: Billing, Beacon, Forge), the redaction-aware feature must be either optional or substitutable.

**Options:**
(a) Make redaction optional in E11 — if no CensorshipConfig is provided, skip redaction (pass-through). This is the simplest fix and matches how most apps would consume E11.
(b) Split E12 into a generic redaction capability (reusable: true) + an Eloq-specific policy layer (reusable: false). More work but cleaner separation.
(c) Keep E12 fully private; E11 is only reusable in its non-redaction-aware form. Other ESPOKEs use E11 without redaction; Eloq uses E11 with E12 composed in.

**Recommendation:** Option (a) is the lowest-effort path and matches typical consumer expectations (Billing/Beacon/Forge don't need redaction). The redaction-aware path becomes an Eloq-specific decoration.

**Question for tech lead:** ratify option (a), (b), or (c)?

### 8.2 ISPOKE-E15 (Theme Manager) — when should it be absorbed into HUB-26 UI Elements?

**Context:** ELQ-ANALYSIS.md §6.4.4 deferred this decision: "Decision: DEFER until HUB-26 ships. When HUB-26 ships, evaluate whether its scope includes theme management or whether a separate small ISPOKE is warranted." The consumer matrix (61.1% YES) provides the data to make the decision now: E15 should be absorbed into HUB-26 when HUB-26 ships.

**Question for tech lead:** ratify that E15 is a HUB-26 absorption candidate (not a new Hub) — and that the Eloq Application Manifest should reference HUB-26 (not ISPOKE-E15) for theme, once HUB-26 ships?

### 8.3 Should the SDLC admission order prioritize ESPOKE-11 Beacon as the first cross-app composition test?

**Context:** Beacon consumes 6 of 10 reusable ISPOKEs (the most of any ESPOKE). Its knowledge-base + ticket workflow is the closest existing analog to Eloq's document model. Building the ISPOKEs Beacon needs first (E6, E10, E11, E7, E9, E15) would simultaneously validate the consumer-side composition model AND deliver the broadest test coverage.

**Question for tech lead:** confirm that the SDLC admission order in §7.3 (Phases A-B targeting Beacon) is the right sequencing, or revise to target a different ESPOKE first?

### 8.4 The "Codex" and "Showcase" ESPOKEs referenced in the original ELQ-ANALYSIS.md §6 as natural consumers DO NOT EXIST in the 18 existing ESPOKEs.

**Context:** ELQ-ANALYSIS.md §6.1.3 says "Potential consumers: Codex ESPOKE (document Q&A), LMS ESPOKE (adaptive course content), Showcase ESPOKE (product description generation)." None of these ESPOKEs exist in the current 18-blueprint catalog. The closest existing analogs are:
- "Codex" → ESPOKE-11 Beacon (Support knowledge base is the closest document-Q&A surface)
- "LMS" → no analog (no learning-management ESPOKE exists)
- "Showcase" → ESPOKE-05 Marketing (landing page BlockEngine is the closest content-authoring surface)

**Implication:** The original ELQ analysis assumed consumers that aren't in the catalog. The consumer matrix above uses the actual 18. If the tech lead intends to add Codex/LMS/Showcase as new ESPOKEs (20, 21, 22+), the matrix should be re-run after those land — they would significantly boost the YES counts for E4 (Paraphrase), E5 (Linguix), E6 (Readability), E7 (RAG).

**Question for tech lead:** are Codex/LMS/Showcase planned as new ESPOKEs in a future SDLC batch? If so, the ELQ ISPOKE investment has stronger justification than the current 18-ESPOKE matrix suggests.

### 8.5 ISPOKE-E7 (RAG Retriever) overlaps with HUB-14 (Search Abstraction Layer) — what's the boundary?

**Context:** ESPOKE-04 Discovery and ESPOKE-17 Concierge both already consume HUB-14 for search. E7 provides chunking + embeddings + cosine similarity + BM25 fallback — a more sophisticated semantic-search layer. The boundary is unclear:
- Is E7 a layer above HUB-14 (semantic search using HUB-14 as the keyword fallback)?
- Is E7 a competing capability (replaces HUB-14 for ISPOKE consumers)?
- Should E7's capability be absorbed into HUB-14 (vector store + BM25 fallback as HUB-14 features)?

**Recommendation:** Position E7 as a layer above HUB-14 — E7 does chunking + embeddings + cosine similarity, with HUB-14's keyword search as the fallback when embeddings fail. E7's HUB-14 dependency is "fallback keyword search", not "competitor". When HUB-14 ships, evaluate whether HUB-14's API should grow a vector-search mode that subsumes E7 (similar to the E15→HUB-26 absorption pattern).

**Question for tech lead:** ratify the "E7 above HUB-14" boundary, or propose a different split?

### 8.6 None of the 18 existing ESPOKEs is a long-form content-creation app (like Eloq) — should the SDLC prioritize new content-creation ESPOKEs to justify the ELQ ISPOKE investment?

**Context:** The matrix shows weak consumer fit for the writing-app ISPOKEs (E4, E5, E6) because no existing ESPOKE produces long-form user-authored content. Only ESPOKE-05 Marketing's BlockEngine and ESPOKE-11 Beacon's knowledge base consumer are even partial analogs. The other 16 ESPOKEs are transactional/operational.

**Implication:** The ELQ ISPOKEs (especially E4 Paraphrase at 0 YES, E5 Linguix at 1 YES) have weak justification in the existing catalog. Their value materializes only if new content-creation ESPOKEs (Codex, LMS, Showcase) are added — or if Eloq itself is built.

**Question for tech lead:** is the ELQ ISPOKE investment justified by the existing 18 ESPOKEs alone, or does justification require committing to add Codex/LMS/Showcase (or similar content-creation ESPOKEs) in a future SDLC batch?

### 8.7 ISPOKE-E13 (Privacy & Audit Ledger) — what's the contract between HUB-06 (mechanism) and slim E13 (policy labels)?

**Context:** Per ELQ-ANALYSIS.md §6.4.3, E13 is split: HUB-06 owns the audit log mechanism; slim E13 decorates HUB-06 events with the 5 Eloq-specific privacy policy labels (zero retention, local encryption, no training, BYOK isolation, unbiased neutrality). The contract between HUB-06 and slim E13 is not yet defined:
- Does slim E13 subscribe to HUB-06 events via CORE-03 EventDispatcher and add policy labels?
- Or does slim E13 expose a `recordWithPolicy()` method that callers use instead of HUB-06's `record()`?
- Or does slim E13 provide a `PolicyLabeler` value object that HUB-06 callers pass to `record()`?

**Question for tech lead:** ratify the contract shape (subscriber, wrapper, or value-object pattern) for the slim E13↔HUB-06 boundary.

### 8.8 ISPOKE-E9 (Remote Backup Orchestrator) — should the S3 target delegate to HUB-11 Cloud Storage?

**Context:** ELQ-ANALYSIS.md §6.4.2 notes E9 overlaps with HUB-11 Cloud Storage (S3/R2/MinIO target). The matrix shows 2 YES + 4 MAYBE consumers — many of which already use HUB-11 for cloud storage (e.g., ESPOKE-17 Concierge's transcripts, ESPOKE-18 Mobile Check-in's PDFs).

**Recommendation:** E9 should delegate its S3 target calls to HUB-11 rather than re-implementing AWS SigV4 (replacing ELQ's hand-rolled `server-api.cjs:1356-1370` with `aws-sdk-php` is the right move, but going further: E9 should call `HUB-11.putObject()` rather than `S3Client::putObject()` directly). E9's GitHub/WebDAV/webhook targets are the differentiating capability that HUB-11 doesn't cover.

**Question for tech lead:** ratify that E9's S3 target delegates to HUB-11, or keep E9's S3 implementation independent?

---

*End of analysis. This document is self-contained. All ESPOKE blueprint content quoted verbatim from `/home/z/my-project/Architecture/Spoke/External/ESPOKE-{01..18}.md`. All ISPOKE content quoted from `/home/z/my-project/download/ELQ-ANALYSIS.md` §6. The tech lead can verify consumer-fit judgments against the quoted blueprint content directly.*
