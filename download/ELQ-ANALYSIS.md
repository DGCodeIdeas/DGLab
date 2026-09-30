# ELQ → DGLab Spoke Migration Analysis

**Source repo:** https://github.com/DGCodeIdeas/ELQ
**Analysis date:** 2026-09-30
**Status:** Analysis complete. Code implementation deferred per user instruction.
**Analyst task ID:** ELQ-ANALYSIS-6

---

## 1. Executive Summary

ELQ (publicly named **Eloqui** in `metadata.json`) is a TypeScript/Angular 21 single-page application that markets itself as "a Notion-style block editor with Linguix-style quality inspector, document-aware AI paraphrasing across fiction (SFW/NSFW), academic, and legal registers, BYOK zero-knowledge privacy, and writing productivity analytics." It is a single-developer prototype (~17,400 LOC across 27 TypeScript source files plus a 1,749-line Express server shim) that has been iterated through six commits over a short period, with **no README, no LICENSE, no docs/ directory, and ~45 throwaway `test-*.js`/`test-*.cjs` Puppeteer smoke scripts left at the repository root.** Its primary language is TypeScript 5.8 on Angular 21 (zoneless, signals-first, OnPush throughout); its backend is a single CommonJS Express router (`server-api.cjs`) that proxies all AI calls through Google's `@google/genai` SDK plus an OpenAI-compatible fetch fallback layer; its persistence is **client-side IndexedDB with at-rest AES-GCM-256 encryption**, with no server-side storage of user documents. The codebase is sophisticated in domain logic (paraphrase register taxonomy, readability metrics, AO3 EPUB ingestion) but architecturally single-tier: everything lives behind one Angular DI tree with no abstraction boundaries between domain, infrastructure, and UI.

What's worth keeping from ELQ is **almost all of its domain logic and its clever multi-provider AI failover pattern**, but **almost none of its infrastructure** — Angular DI, RxJS, IndexedDB, server-side `server-api.cjs`, and WebCrypto-in-browser patterns all need either re-platforming to PHP 8.4 or replacement by existing DGLab Hub/Core packages. The three highest-value cherry-picks are: (1) the **22 document-type × 10 paraphrase-style taxonomy** with its surrounding-context-aware prompt assembly (`src/services/paraphrase.types.ts` + `server-api.cjs:298-396`); (2) the **multi-provider AI router with hierarchical failover** (Gemini SDK → OpenAI-compatible fetch → Pollinations zero-key → rule-based local fallback) (`server-api.cjs:39-296` + `src/services/ai.service.ts`); and (3) the **readability metrics engine** (5 formulae + 6 audience profiles + syllable counter with abbrevation-aware sentence splitter) (`src/services/readability.service.ts`).

The proposed Spoke decomposition is **one ESPOKE ("Eloq") consuming 16 ISPOKEs** (9 Abstractions, 7 Features), of which **6 ISPOKEs are flagged `reusable: true`** (AI Inference Hub, Paraphrase Engine, Linguix Reviewer, Readability Auditor, RAG Retriever, Remote Backup Orchestrator, EPUB Importer, Manuscript Exporter, BYOK Vault, Privacy Ledger, Theme Manager) and **4 ISPOKEs are flagged `reusable: false`** (Document Vault, Block Editor Surface, Censorship Engine, Productivity Metrics). Three capabilities surface as **Hub-promotion candidates** (BYOK Vault → potential generic secret-vault Hub; Remote Backup Orchestrator → potential generic backup-dispatch Hub; Privacy & Audit Ledger → potential generic audit Hub) — all three should be **deferred until a second consumer appears** per the consumer-side composition rule. The Auth & RBAC Stub is rejected as a standalone ISPOKE and absorbed into existing HUB-04 Identity.

---

## 2. ELQ Repository Overview

### 2.1 Primary language & framework

| Property | Value | Source |
|---|---|---|
| Primary language | TypeScript 5.8 (`~5.8.2`) | `package.json:31` |
| UI framework | Angular 21.1 (standalone components, signals, zoneless) | `package.json:13-20`; `index.tsx:4` uses `provideZonelessChangeDetection()` |
| CSS framework | Tailwind CSS (`latest`, loaded via CDN script in `index.html:12`) | `package.json:26` |
| Server runtime | Node.js + Express + CommonJS (`server.cjs`, `server-api.cjs`) | `server.cjs:1-21` |
| AI SDK | `@google/genai` `^1.37.0` | `package.json:21` |
| Build tooling | `@angular/build:application` (esbuild-based); `vite ^6.2.0` declared as devDep but unused | `angular.json:13`; `package.json:32` |
| Test tooling | `puppeteer ^25.10.0` only — **no unit-test runner declared** (no Jest/Vitest/Karma) | `package.json:30`; see `test-*.js` files at repo root |

### 2.2 Primary purpose

Per `metadata.json:1-6`:

> **Eloqui — Private AI Writing Assistant & Block Editor.** A Notion-style block editor with Linguix-style quality inspector, document-aware AI paraphrasing across fiction (SFW/NSFW), academic, and legal registers, BYOK zero-knowledge privacy, and writing productivity analytics.

The user-visible surface is a single-page web app (served at `/` by `server.cjs`) that exposes a three-pane writing environment: left sidebar = library of manuscripts/chapters; center = contenteditable block editor; right sidebar = AI chat with chapter/book/dictionary/search/unfiltered modes. Modals (auth, BYOK, models, privacy, metrics, export, backup) overlay the editor. The Express server is a thin API router for `/api/ai/*` and `/api/backup/remote/*` — **no document persistence server-side**, no user account database, no server-side sessions. All document state lives in the browser IndexedDB encrypted with an AES-GCM-256 master key stored in `localStorage`.

### 2.3 Top-level directory inventory

```
/home/z/my-project/external/ELQ/
├── .angular/              (Angular CLI cache, gitignored)
├── .git/                  (6 commits)
├── public/sample.epub     (AO3-style sample EPUB fixture)
├── src/
│   ├── app.component.{ts,html}     (root component, 955-line HTML template)
│   ├── components/                 (11 Angular standalone components)
│   │   ├── auth-modal/             (192 LOC — localStorage-only auth stub)
│   │   ├── backup-modal/           (1335 LOC — the largest component)
│   │   ├── byok-modal/             (302 LOC — BYOK API key manager)
│   │   ├── chat/                   (503 LOC — right sidebar AI chat)
│   │   ├── editor/                 (editor.component.ts 471 LOC + block.component.ts 432 LOC + 2 HTML templates)
│   │   ├── export-modal/           (613 LOC — PDF/MD/TXT/HTML/EPUB export)
│   │   ├── metrics-modal/          (709 LOC — readability + word count + goals)
│   │   ├── models-modal/           (972 LOC — model hub UI for probe/test/curated catalog)
│   │   ├── paraphrase/             (879 LOC — paraphrase modal with diff view)
│   │   └── privacy-modal/          (225 LOC — privacy policy dashboard)
│   └── services/                   (14 services + 1 types file)
│       ├── ai.service.ts          (437 LOC — Gemini + multi-provider router)
│       ├── auth.service.ts         (215 LOC — localStorage auth + RBAC + reset tokens)
│       ├── backup.service.ts       (1397 LOC — the largest service)
│       ├── block.service.ts        (1047 LOC — central document/chapter state)
│       ├── crypto.service.ts       (204 LOC — AES-GCM-256 + BYOK key vault)
│       ├── export.service.ts       (755 LOC — PDF/MD/TXT/HTML/EPUB generation)
│       ├── import.service.ts       (382 LOC — EPUB + AO3 ingestion)
│       ├── model.service.ts        (823 LOC — curated model catalog + custom models)
│       ├── paraphrase.types.ts     (309 LOC — 22 doc types + 10 styles)
│       ├── privacy.service.ts      (134 LOC — audit log + policies)
│       ├── rag.service.ts          (157 LOC — in-memory vector store + BM25 fallback)
│       ├── readability.service.ts  (604 LOC — 5 readability formulae + 6 audience profiles)
│       ├── storage.service.ts      (242 LOC — IndexedDB + chapter encryption)
│       └── theme.service.ts        (91 LOC — light/dark/system)
├── server.cjs                (21 LOC — bare Express static + /api mount)
├── server-api.cjs            (1749 LOC — all 12 API endpoints + AI provider router)
├── proxy.conf.cjs            (37 LOC — Angular dev-server proxy to localhost:3001)
├── angular.json              (66 LOC)
├── index.html                (271 LOC — Tailwind CDN + index.tsx entry)
├── index.tsx                 (12 LOC — Angular bootstrap)
├── package.json              (34 LOC)
├── metadata.json             (6 LOC — AI Studio app descriptor)
├── bun.lock + package-lock.json
├── Isekaied_to_Save_the.epub (fixture)
├── screenshot.png + test-final-verification.png
├── tsconfig.json + tsconfig.temp.json + tsconfig.temp2.json
└── test-*.js / test-*.cjs × 28 files (Puppeteer smoke scripts, no test runner)
```

### 2.4 Approximate size

| Metric | Count |
|---|---|
| TypeScript source files | 27 (14 services + 12 components + 1 bootstrap) |
| Total TypeScript LOC (services) | 7,056 |
| Total TypeScript LOC (components) | 6,633 |
| HTML template LOC | 1,591 (1 root + 2 editor) |
| Express server LOC | 1,749 (`server-api.cjs`) + 21 (`server.cjs`) + 37 (`proxy.conf.cjs`) |
| Config/HTML/TSX LOC | ~400 (`angular.json` + `index.html` + `index.tsx` + 3 tsconfigs) |
| **Total project LOC (excl. test scripts)** | **~17,450** |
| Test scripts (Puppeteer smoke) | 28 files, ~12,000 LOC (not part of porting target) |
| Production dependencies | 11 (Angular ×6, genai, jspdf, jszip, marked, rxjs, tailwindcss) |
| Dev dependencies | 4 (puppeteer, typescript, vite, @types/node) |
| Git commits | 6 (`788c108` Initial → `8544bdf` dark mode) |

### 2.5 License

**No LICENSE file exists at the repo root.** The `.gitignore` (`/home/z/my-project/external/ELQ/.gitignore:1-24`) does not mention licensing. `package.json:3` declares `"private": true` — meaning the package is not intended for npm distribution but does **not** by itself grant any rights. The repository is published on GitHub under `DGCodeIdeas/ELQ`, suggesting the author intends some form of open sharing, but the **absence of a LICENSE file means default "All Rights Reserved" applies under both US and EU copyright law.** This is a **flag-red cherry-picking risk** (see §4.5 below): any direct code lift would technically require explicit permission from the rightsholder.

The 22-document-type and 10-style prompt taxonomy, the readability formulae (which are public-domain algorithms), and the architectural patterns (which are uncopyrightable ideas) can be reimplemented freely. **Direct verbatim source porting of TypeScript → PHP should not proceed without an explicit license grant from the ELQ author.** This analysis assumes the user has or will obtain such permission, or will treat this document as the boundary (i.e., re-implement from analysis, not from source).

---

## 3. Architectural Pattern Audit

This section is organized by the **14 Angular services** + the **Express backend** + the **11 components** + the **client persistence layer** + the **AI provider router**, because that is how ELQ is actually structured (services are the seam between UI and infrastructure).

### 3.1 Central reactive state: `BlockService` (`src/services/block.service.ts`, 1047 LOC)

- **What it does.** Holds the entire client-side state of the application: documents array, current document, active chapter, active chat session, derived metrics (word count, reading time, sentence-length distribution), censorship report, paraphrase modal context, RAG index trigger, autosave debounce timer.
- **Structure.** Angular `@Injectable({providedIn: 'root'})` singleton. Reactive primitives: 17 `signal()` properties + 28 `computed()` derived values + 2 `effect()` side-effect pipelines (autosave + RAG reindex). Uses `inject()` for constructor-less DI.
- **External deps.** `StorageService` (IndexedDB layer), `RagService`, `ReadabilityService`. At module load also imports `JSZip` for export.
- **Internal deps.** Consumed by every component and most other services.
- **State management.** In-memory only (lost on reload unless `StorageService.saveDocument()` flushes to IndexedDB). Debounced autosave at 1200ms (`block.service.ts:277`). RAG reindex trigger at 5s idle (`block.service.ts:296`).
- **API surface.** No HTTP endpoints — internal Angular service consumed via `inject()`.
- **Verdict.** Tightly coupled to Angular's signal/computed/effect primitives. State shape (Document/Chapter/ChatSession/Goals) is portable; the reactive harness is not.

### 3.2 Client persistence: `StorageService` (`src/services/storage.service.ts`, 242 LOC)

- **What it does.** IndexedDB wrapper. Stores one document per record (`{keyPath: 'id'}`). Encrypts chapter content via `CryptoService.encryptText()` before `put()`; decrypts on `get()`. Adds legacy migration defaults on read (empty `chapters` → seed chapter; missing `wordCountHistory` → `[]`; missing `goals` → defaults).
- **Structure.** Plain IndexedDB promise-based API (no Dexie, no idb-keyval).
- **External deps.** `CryptoService`, `PrivacyService`. Browser `indexedDB`, `crypto.randomUUID()`.
- **Internal deps.** Consumed by `BlockService`, `BackupService`, `MetricsModalComponent`.
- **State management.** IndexedDB `EloquiDB` v4, object store `documents`.
- **API surface.** `saveDocument(doc)`, `getDocument(id)`, `getAllDocuments()`, `deleteDocument(id)`.
- **Verdict.** IndexedDB is browser-only. PHP port replaces this with a MySQL-backed repository (DGLab `core/dbal` pattern, ADR-013) and a separate at-rest envelope (DGLab `core/crypto`). The Document/Chapter value-object shapes (defined at `storage.service.ts:5-60`) are directly portable as PHP DTOs.

### 3.3 At-rest crypto + BYOK vault: `CryptoService` (`src/services/crypto.service.ts`, 204 LOC)

- **What it does.** Two responsibilities conflated: (a) **per-document AES-GCM-256 at-rest encryption** of chapter content using a 256-bit master key stored as hex in `localStorage` under `eloqui_master_key_raw`; (b) **BYOK API key vault** — a `ApiKeyConfig[]` list of provider-key pairs persisted in `localStorage` under `eloqui_byok_api_keys`, with `getActiveKeyForProvider(provider)` lookup.
- **Structure.** Singleton; uses native `window.crypto.subtle` for AES-GCM + PBKDF2 + SHA-256 fingerprinting. Encryption marker `__ELOQUI_ENC_V1__:` prefix on ciphertext.
- **External deps.** Native WebCrypto only.
- **Internal deps.** Consumed by `StorageService` (encrypt/decrypt chapters), `AiService` (BYOK key resolution per provider).
- **State management.** Two `localStorage` keys + one in-memory `CryptoKey` handle + 4 `signal()`s for reactive UI.
- **API surface.** `encryptText(plaintext)`/`decryptText(payload)`, `setRawKey(hex)`, `generateNewMasterKey()`, `toggleAtRestEncryption(bool)`, `saveApiKey(config)`, `removeApiKey(name)`, `getActiveKeyForProvider(provider)`.
- **Verdict.** AES-GCM-256 with a 12-byte IV prepended to ciphertext + base64-encoded + magic-string marker is a sound but ELQ-specific envelope format. The DGLab `core/crypto` `Envelope` (PR #260) already implements a versioned JSON envelope with `kid` (key id), `iv`, `ciphertext`, and `sodium_memzero` — **the DGLab envelope is strictly superior and supersedes ELQ's format.** The BYOK vault is a separable capability worth promoting to a Hub (see §6.4).

### 3.4 AI provider router + Linguix reviewer: `AiService` (`src/services/ai.service.ts`, 437 LOC) + `server-api.cjs:39-296`

- **What it does.** Unified multi-provider AI invocation layer. Routes prompts to one of: (a) Google Gemini via `@google/genai` SDK; (b) custom OpenAI-compatible endpoint (Ollama, LM Studio, vLLM, private HTTP) via fetch; (c) Pollinations zero-key free public router; (d) Groq; (e) OpenRouter; (f) Cerebras. Implements 6 prompt modes (`fast`, `dictionary`, `uncensored`, `shorten`, `expand`, `formal`, `casual`, `simplify`, `active_voice`, etc.) and 5 task surfaces (`generateText`, `reviewText`, `paraphraseText`, `chatStream`, `getEmbedding`).
- **Structure.** Client-side `AiService` is a thin fetch wrapper that delegates model/key resolution to `ModelService` and provider-specific path construction to the server. Server-side `executeUnifiedModelPrompt()` (`server-api.cjs:60-296`) is a 240-line router with explicit branching by `modelId` prefix (e.g. `pollinations-`, `groq/`, `openrouter/`, `cerebras/`, gemini default) and SSE streaming with manual word-chunking for non-Gemini providers (lines 868-893).
- **External deps.** `@google/genai` server-side only; native `fetch` everywhere else; `AbortSignal.timeout(10000)` for custom endpoints.
- **Internal deps.** Consumed by `BlockService` (for editor tools), `ChatComponent` (streaming chat), `EditorComponent` (Linguix review), `ParaphraseModalComponent`.
- **State management.** Stateless on server (each request is independent). Client `AiService` holds a `linguixScore` signal + a `localLinguisticReview` rule-based fallback (duplicate words, common typos, filler phrases — `ai.service.ts:65-131`).
- **API surface.** Five Express endpoints (all POST):
  - `POST /api/ai/test-model` (`server-api.cjs:678`) — latency probe for a curated/custom model
  - `POST /api/ai/generate` (`server-api.cjs:713`) — text generation with 10 mode-specific prompt templates
  - `POST /api/ai/chat` (`server-api.cjs:807`) — streaming SSE chat with 5 history roles
  - `POST /api/ai/review` (`server-api.cjs:920`) — Linguix-style grammar review, returns structured JSON via Gemini's `responseSchema`
  - `POST /api/ai/paraphrase` (`server-api.cjs:1022`) — document-type/style-aware paraphrasing with 4 alternatives + fitScore
  - `POST /api/ai/embed` (`server-api.cjs:1229`) — embeddings with 3-model failover list (`gemini-embedding-001`, `-2`, `-2-preview`)
- **Verdict.** The hierarchical failover (Gemini native SDK → OpenAI-compatible fetch with `AbortSignal.timeout(10s)` → Pollinations zero-key → rule-based local fallback) is the most valuable architectural pattern in the entire repo. Worth extracting verbatim into a reusable ISPOKE.

### 3.5 Paraphrase taxonomy + prompt assembly: `paraphrase.types.ts` (309 LOC) + `server-api.cjs:298-396`

- **What it does.** Defines 22 document types across 5 categories (Fiction & Creative ×9 incl. NSFW/Horror/AO3; Academic & Research ×5; Legal & Regulatory ×3; Professional & Business ×3; Specialized & Media ×2). Each type has an `id`, `name`, `description`, `badge`, `icon` (Material Symbols), and a long-form prompt fragment stored server-side in `DOCUMENT_TYPE_DESCRIPTIONS` (`server-api.cjs:298-383`). Also defines 10 paraphrase styles (natural, closer, vivid, concise, dramatic, formal, simplified, active, lyrical, dialogue) with parallel client-side metadata and server-side `PARAPHRASE_STYLE_GUIDES` (`server-api.cjs:385-396`).
- **Structure.** Pure TypeScript type definitions + two const arrays + two server-side prompt dictionaries. **No logic** — just data.
- **Verdict.** Highest-value cherry-pick in the repo. The taxonomy is the product. The client/server split is suboptimal (style metadata duplicated) but the content is gold.

### 3.6 Local fallback paraphraser: `localParaphraseFallback` (`server-api.cjs:398-670`)

- **What it does.** When all AI providers fail, returns hardcoded alternatives for: (a) single words (`said` → `murmured`/`stated`/`whispered`/`declared`; `walked`/`looked`/`important` similarly); (b) legal documents (operative covenant, express disclaimer, statutory alignment templates); (c) academic documents (empirical hedging, dialectical synthesis, methodological qualifier templates); (d) NSFW documents (somatic intensity, atmospheric immersion, sensory texture templates); (e) general fiction (atmospheric depth, operative rhythm, sensory & visceral templates).
- **Structure.** Pure function with 270 lines of branching.
- **Verdict.** Useful for graceful degradation but very ELQ-specific. Reimplement selectively if at all — most of this is just sample alternatives that the LLM should produce.

### 3.7 Local Linguix fallback: `localLinguisticReview` (`ai.service.ts:65-131` + `server-api.cjs:672+`)

- **What it does.** Rule-based grammar review returning structured `Suggestion[]` (original, suggestion, type, explanation). Three rule families: (1) duplicate consecutive words (`/\b([a-zA-Z]{2,})\s+\1\b/gi`); (2) 10 common typos (`teh`/`recieve`/`seperate`/`definately`/`occured`/`untill`/`truely`/`wierd`/`accomodate`/`goverment`); (3) 5 filler phrases (`in order to`/`at the present time`/`due to the fact that`/`in the event that`/`for the purpose of`).
- **Verdict.** Trivial to port. Useful as zero-AI-cost prefilter to avoid wasting LLM calls on obvious errors.

### 3.8 RAG retriever: `RagService` (`src/services/rag.service.ts`, 157 LOC)

- **What it does.** Chapter chunking (500 words with 50-word overlap, capped at 50 chunks total) → batch embeddings via `AiService.getBatchEmbeddings()` (concurrency 3) → in-memory cosine similarity retrieval with BM25 keyword fallback when embeddings fail.
- **Structure.** Singleton; in-memory `Chunk[]` array (no persistence); progress signal for UI spinner.
- **External deps.** `AiService.getEmbedding()` (which calls `/api/ai/embed`). Native `crypto.randomUUID()` for chunk IDs.
- **Internal deps.** Consumed by `ChatComponent` (book mode), `BlockService` (reindex trigger).
- **Verdict.** Functional but minimal. The 50-chunk cap and `setTimeout(25)` yield to main thread are browser-event-loop-specific. PHP port uses `HUB-14 Search` or `HUB-31 Real-Time Analytics` for vector storage; HUB-10 Queue for batch embedding.

### 3.9 Readability engine: `ReadabilityService` (`src/services/readability.service.ts`, 604 LOC)

- **What it does.** Computes 5 readability formulae (Flesch Reading Ease, Flesch-Kincaid Grade Level, Gunning Fog, Coleman-Liau, Automated Readability Index) + sentence-length distribution + 6 audience profiles (Middle Grade, Young Adult, General Fiction, Literary Fiction, Academic/Technical, Casual/Web) + audience-match feedback with 5 status levels (optimal/slightly_dense/too_dense/slightly_simple/too_simple).
- **Structure.** Singleton; pure functions (`analyzeText`, `evaluateAudienceMatch`, `countSyllables`, `splitIntoSentences`). Robust English syllable counter with heuristics for silent 'e', 'le' syllable, trailing 'ed'/'es' patterns, vowel-group counting, and special-case adjustment for `ia|io|iu|eo|ua|uo` diphthongs (`readability.service.ts:465-508`). Abbreviation-aware sentence splitter protects `Mr.`/`Mrs.`/`Dr.`/`Prof.`/`St.`/`Sr.`/`Jr.`/`e.g.`/`i.e.`/`etc.`/`vs.`/`approx.`/`No.`/decimal numbers/ellipses (`readability.service.ts:412-452`).
- **External deps.** None.
- **Internal deps.** Consumed by `BlockService` (active chapter readability signals + audience match signals), `MetricsModalComponent`.
- **Verdict.** **Pure library code, no Angular-specific primitives, no I/O.** The single most directly portable service in the repo. PHP port is line-by-line translation.

### 3.10 Backup orchestrator: `BackupService` (`src/services/backup.service.ts`, 1397 LOC) + `server-api.cjs:1271-1750`

- **What it does.** Six backup methods × five remote targets. **Methods**: `encrypted_vault` (AES-GCM-256 with PBKDF2-SHA256 100k iterations, JSON envelope with `format`/`version`/`kdf`/`cipher`/`salt`/`iv`/`ciphertext`/`checksumSha256`), `json_vault` (plain JSON), `zip_bundle` (multi-folder markdown ZIP with Manifest.json + Manuscripts/ + ChatHistory/ + Analytics/wordcount-history.csv), `incremental` (delta since last backup timestamp), `active_doc` (single doc only), `disaster_html` (single-file self-contained HTML reader for catastrophic recovery — `backup.service.ts:938-1129`). **Remotes**: `download` (browser `<a download>`), `local_dir` (FileSystemDirectoryHandle via File System Access API), `github` (REST API PUT to repo contents or Gist), `s3` (hand-rolled AWS SigV4 with `crypto.createHmac`), `webdav` (PROPFIND/OPTIONS), `webhook` (POST/PUT with HMAC-SHA256 secret).
- **Structure.** Singleton with separate orchestration (`executeBackup(method, remoteId, customPassword)`) and dispatch (`downloadFileLocally`, `writeToLocalDirectory`, fetch to `/api/backup/remote/{github,s3,webdav,webhook}`). Auto-backup scheduler with localStorage-persisted settings + interval timer.
- **External deps.** `JSZip` (zip generation); `BackupService.computeSha256()` via `window.crypto.subtle.digest`. Server-side uses native `crypto` for AWS SigV4 signing + GitHub token verification.
- **Internal deps.** Consumed by `BlockService` (restore), `BackupModalComponent`.
- **API surface.** 5 Express endpoints (all POST, all under `/api/backup/remote/`): `test-connection`, `github`, `s3`, `webdav`, `webhook`.
- **State management.** 5 localStorage keys (`REMOTES_STORAGE_KEY`, `LOGS_STORAGE_KEY`, `SETTINGS_STORAGE_KEY`, `LAST_BACKUP_TIME_KEY`, plus per-user reset tokens in AuthService).
- **Verdict.** The most over-engineered service in the repo. 1397 LOC for what is essentially "save the docs to N places." The disaster-HTML generator (193 LOC, `backup.service.ts:938-1129`) is a clever touch worth keeping as a UX pattern. The multi-target dispatch is generic enough to be a Hub candidate.

### 3.11 EPUB importer: `ImportService` (`src/services/import.service.ts`, 382 LOC)

- **What it does.** Parses `.epub` (a ZIP) and `.txt` files into `Chapter[]`. EPUB pipeline: read `META-INF/container.xml` → find OPF rootfile → read OPF manifest + spine + TOC (NCX or XHMTL) → for each spine item, parse the HTML and either detect AO3 metadata (rating/warnings/categories/fandom/relationships/tags/stats from `dl.tags`/`.byline`/`blockquote.userstuff` selectors) and build a preface chapter, or extract paragraphs/quotes/hr as content. Aggressive attribute sanitization (`on*`/`mso-*`/`style`/`class`/`id` stripped).
- **Structure.** Stateless service; two methods (`importTxt`, `importEpub`).
- **External deps.** `JSZip` for unzip; `DOMParser` for XML/HTML parsing; `crypto.randomUUID()`.
- **Verdict.** Highly valuable. The AO3-specific metadata extraction is a niche feature but the EPUB→Chapters pipeline is generic. PHP port replaces `DOMParser` with `DOMDocument` or `Masterminds/HTML5` + `zip_archive` for unzip.

### 3.12 Manuscript exporter: `ExportService` (`src/services/export.service.ts`, 755 LOC)

- **What it does.** Five export formats: `pdf` (jsPDF with running headers/footers, redaction-aware, page breaks between chapters), `markdown`, `txt`, `html`, `epub` (JSZip with `mimetype`/`OEBPS/`/`META-INF/container.xml`). Redaction-aware (respects per-doc `censorshipConfig` + 6 redaction styles). Optional metadata banner (export date, chapter count, redaction note).
- **Structure.** Stateless service; `generatePreview(options)` returns stats; `exportDocument(options)` triggers browser download.
- **External deps.** `jsPDF`, `JSZip`, `BlockService` (current doc state).
- **Verdict.** Most of jsPDF's complexity (font metrics, page breaks, running headers) maps to `tcpdf` or `mpdf` in PHP. The EPUB generation (JSZip) maps to `zip_archive`. Markdown/TXT/HTML are trivial string assembly. The redaction-aware content generation is the valuable seam.

### 3.13 Censorship & redaction engine: inline in `BlockService` (`block.service.ts:431-498`)

- **What it does.** Maintains a per-document `CensorshipConfig { redactionStyle: 'blackout'|'blackbar'|'blur'|'spoiler'|'asterisks'|'redact_pill', customBlacklist: string[] }`. Detects sensitive terms (default list of 21 profanities + custom blacklist) via word-boundary regex. Renders HTML with sensitive terms wrapped in CSS-styled `<span>` elements per style. Toggleable per-doc (`isUncensored` flag) for "unfiltered mode."
- **Structure.** Pure functions on `BlockService`. CSS classes defined in `index.html:197-245`.
- **Verdict.** Content-filtering UX pattern. The "neutral parity" framing (filtered vs. unfiltered as a per-doc toggle rather than a global policy) is a deliberate design choice that should be preserved in the port.

### 3.14 Privacy & audit ledger: `PrivacyService` (`src/services/privacy.service.ts`, 134 LOC)

- **What it does.** Client-side audit log of 5 event categories (document, encryption, ai_inference, byok, auth) with 4 statuses (allowed, encrypted, revoked, processed). Capped at 100 entries, persisted in `localStorage` under `eloqui_privacy_audit_log`. Defines 5 privacy policy items (zero retention, local encryption, no training, BYOK isolation, unbiased neutrality) as a static array. Zero-telemetry toggle persisted under `eloqui_telemetry_disabled`.
- **Verdict.** Useful pattern. The "privacy policy as a static array of guarantees" is a good UX pattern. Maps to a Hub audit capability.

### 3.15 Auth & RBAC stub: `AuthService` (`src/services/auth.service.ts`, 215 LOC)

- **What it does.** localStorage-based user accounts with 3 roles (admin, writer, reviewer) and 4 role-derived permission computeds (`canEdit`, `canManageModels`, `canManageBYOK`, `canAccessPrivacy`). Cryptographically-secure 24-byte password reset tokens (15-min expiry, stored in `localStorage` under `eloqui_password_resets`). No real authentication — `login(email)` just looks up the email in the local users list and sets `isAuthenticated` to true.
- **Verdict.** A demo stub. Not production-ready. **Reject as standalone ISPOKE.** Map directly onto DGLab HUB-04 Identity (already shipped, PR #268) — its `User` entity, `UserId`/`Email`/`RoleIdentifier` value objects, JWT (ES256), and `AuthMiddleware` (PSR-15) cover all of ELQ's auth surface.

### 3.16 Theme manager: `ThemeService` (`src/services/theme.service.ts`, 91 LOC)

- **What it does.** Three-state theme mode (light/dark/system). Listens to `prefers-color-scheme: dark` media query. Synchronizes DOM `<html>` class + `color-scheme` CSS property. Anti-flash pre-hydration script in `index.html:48-60` reads `localStorage` before Angular boots.
- **Verdict.** Trivial. The anti-flash pattern is the only interesting bit — it's a useful pattern for any server-rendered app that supports dark mode.

### 3.17 Model catalog: `ModelService` (`src/services/model.service.ts`, 823 LOC)

- **What it does.** Curated catalog of ~20 free LLMs across 6 categories (instant-free Pollinations, groq, openrouter, cerebras, local Ollama, gemini). Each curated entry has 14 metadata fields (architecture, version, contextWindow, speedTokPerSec, latencyTier, isFree, keyRequirement, freeTierDetails, keySetupUrl, privacyTier, capabilities[], limitations[], recommendedFor, testedLatencyMs, testStatus, lastTestedSnippet). Custom-model support (user-defined OpenAI-compatible endpoints with temperature + systemPrompt + per-task assignment).
- **Structure.** Singleton; signal-based state; localStorage persistence for custom models + task-role assignments.
- **Verdict.** The catalog is essentially a static data table + a UI for managing user-defined endpoints. The catalog itself is ephemeral (model names change monthly); the pattern of "task-role assignment" (one model for grammar, another for drafting, another for chat) is worth preserving.

### 3.18 Express backend: `server-api.cjs` (1749 LOC)

- **What it does.** Single CommonJS file holding: (1) Gemini client factory + safety settings factory; (2) `generateContentWithFailover` (tries `gemini-2.5-flash` → `2.0-flash` → `1.5-flash` → `1.5-flash-8b`); (3) `executeUnifiedModelPrompt` (the 240-line multi-provider router, lines 60-296); (4) the two prompt dictionaries (`DOCUMENT_TYPE_DESCRIPTIONS`, `PARAPHRASE_STYLE_GUIDES`); (5) `localParaphraseFallback` (270 LOC); (6) 12 Express routes (1 health, 5 AI, 1 embed, 5 backup). Uses native `fetch` + native `crypto` (for AWS SigV4) + native `Buffer`.
- **Verdict.** This file is the spine of ELQ's AI surface. It needs to be split across multiple PHP ISPOKEs (AI provider router ISPOKE, paraphrase taxonomy ISPOKE, backup dispatch ISPOKE) — none of which should remain a 1749-line monolith.

### 3.19 Component layer (11 components, 6633 LOC total)

| Component | LOC | Key responsibility | Reactive primitives |
|---|---|---|---|
| `app.component` | 259 (ts) + 955 (html) | Root layout: 3 panes + modal router + keyboard shortcuts + import/drag-drop | 8 signals |
| `editor.editor` | 471 | ContentEditable host, paste sanitizer, Linguix scan, paraphrase selection range tracking | 10 signals + 1 effect |
| `editor.block` | 432 | Single block (paragraph/h1/h2/bullet/code/quote/divider), slash-menu, inline AI tools | 5 signals |
| `chat` | 503 | Streaming chat with 5 modes (chapter/book/dictionary/search/uncensored), markdown render via `marked`, RAG retrieval | 5 signals + 1 computed |
| `metrics-modal` | 709 | Readability dashboard, audience selector, word count history sparkline, goals | 0 own state (reads from BlockService) |
| `models-modal` | 972 | Model catalog UI, latency probe, custom model CRUD | 8 signals |
| `paraphrase-modal` | 879 | Document-type picker, style picker, surrounding-context toggle, diff view, 4 alternatives | 9 signals + 2 computed |
| `export-modal` | 613 | Format picker, redaction toggle, preview, scope picker | 4 signals |
| `backup-modal` | 1335 | Remote config CRUD, backup method picker, restore inspection, auto-backup scheduler UI | 12 signals |
| `byok-modal` | 302 | BYOK key CRUD per provider | 0 (delegates to CryptoService) |
| `privacy-modal` | 225 | Policy dashboard, audit log viewer, telemetry toggle | 0 (delegates to PrivacyService) |
| `auth-modal` | 192 | Login/signup/role-switch/reset-password forms | 0 (delegates to AuthService) |

- **Pattern.** Every component is `standalone: true`, `changeDetection: ChangeDetectionStrategy.OnPush`, signals-first. Templates are inline (with two exceptions: `editor.component.html` and `block.component.html`). Heavy Tailwind class usage in templates.
- **Verdict.** Angular component layer does not port to PHP — it must be **completely re-platformed to SuperPHP templates** (per ADR-005). The UX flows (modals, slash menus, selection range tracking, streaming chat) are the cherry-pickable patterns, not the components themselves.

### 3.20 Build & dev infrastructure

- `angular.json` (66 LOC) — standard Angular 21 build config; production hashing; dev server on `0.0.0.0:3000`; proxy to `127.0.0.1:3001` for `/api/*`; `allowedHosts` includes Google AI Studio Cloud Run preview hosts.
- `proxy.conf.cjs` (37 LOC) — Express-based proxy server that mounts `apiRouter` at `/api` and listens on `127.0.0.1:3001`. Permissive CORS (`*`).
- `index.tsx` (12 LOC) — Angular bootstrap with `provideZonelessChangeDetection()`.
- `index.html` (271 LOC) — Tailwind via CDN script; inline `tailwind.config`; inline custom CSS for review highlights, censorship styles, markdown body, scrollbar; import-map for esm.sh dependencies.
- `tsconfig.json` + 2 temp variants — standard Angular strict mode.
- 28 `test-*.js`/`test-*.cjs` Puppeteer smoke scripts — ad-hoc functional tests, no test runner.

**Verdict.** The Angular/Vite/CDN-Tailwind toolchain does not map to DGLab. The dev-server-as-proxy pattern is interesting but suboptimal — DGLab uses FrankenPHP workers (ADR-017) for HTTP serving, not an Express dev shim.

---

## 4. Cherry-Pick List

### 4.1 Architectural Patterns

#### 4.1.1 Hierarchical AI provider failover

- **What.** The pattern of trying Gemini SDK first, then a sequence of fallback Gemini models (`2.5-flash` → `2.0-flash` → `1.5-flash` → `1.5-flash-8b`), then an OpenAI-compatible fetch, then a zero-key public router (Pollinations), then a rule-based local heuristic. Each layer catches its own errors and falls through to the next.
- **Why it's valuable.** AI providers rate-limit, deprecate models, and have regional outages. A hierarchical failover that degrades gracefully (cloud → zero-key public → local rule-based) is the difference between a working product and a 502 page. DGLab has no equivalent pattern today — each Hub/Spoke that talks to an LLM would benefit.
- **How to extract.** `server-api.cjs:39-296` (the `executeUnifiedModelPrompt` router + `generateContentWithFailover` Gemini cascade + Pollinations branch). Depends on: native `fetch`, native `crypto` for any HMAC signing, optionally `@google/genai` SDK (PHP port replaces with PSR-18 HTTP client calling Gemini REST directly).
- **Risks.** The Gemini SDK call shape (`client.models.generateContent({model, contents, config})`) is JS-specific; PHP port must use Gemini's REST API directly. Pollinations is a free public service with no SLA — relying on it for production is fragile.
- **Verdict.** **KEEP-WITH-MODIFICATION** — pattern is gold, but each provider branch must be reimplemented for PHP.

#### 4.1.2 SSE streaming for non-Gemini providers

- **What.** For Gemini, true token-streaming via `client.models.generateContentStream()` writing `data: {text: chunk}` SSE events. For non-Gemini providers that don't natively stream, the server issues a single non-streaming request and then artificially word-chunks the response (split on `/(\s+)/`, emit 2 tokens at a time) to give the UI the feel of streaming.
- **Why it's valuable.** Real streaming is critical for chat UX (perceived latency drops 5-10×). The fake-streaming fallback is a pragmatic compromise for providers that don't support SSE.
- **How to extract.** `server-api.cjs:837-916` (the `/api/ai/chat` endpoint).
- **Risks.** PHP 8.4 + FrankenPHP supports SSE natively; the fake-streaming approach is technically dishonest (the model has already returned all text) but UX-valuable.
- **Verdict.** **KEEP-WITH-MODIFICATION** — implement true streaming where available, fake-streaming only when the provider lacks it.

#### 4.1.3 Document-type × style taxonomy as a static data table

- **What.** 22 document types × 10 paraphrase styles, each with a long-form prompt fragment that primes the LLM for the appropriate register. Two parallel arrays: client-side metadata (id/name/description/badge/icon/category) and server-side prompt fragments (`DOCUMENT_TYPE_DESCRIPTIONS[docType].prompt`, `PARAPHRASE_STYLE_GUIDES[style]`).
- **Why it's valuable.** This is the **core IP of the product**. The taxonomy encodes domain knowledge (the difference between "operative covenant" legal drafting and "somatic intensity" NSFW prose) that takes significant domain expertise to produce.
- **How to extract.** `src/services/paraphrase.types.ts:49-309` (client metadata) + `server-api.cjs:298-396` (prompt fragments). The two should be **unified into a single source of truth** in the PHP port.
- **Risks.** The prompts are LLM-output-quality-dependent and may need tuning for different models. The "NSFW" / "uncensored" prompts carry content-policy risk depending on the chosen provider's safety settings.
- **Verdict.** **KEEP** — directly portable as a PHP `enum` + prompt-builder class.

#### 4.1.4 BYOK per-provider routing

- **What.** User-supplied API keys (`ApiKeyConfig[]` with `provider`/`name`/`key`/`endpointUrl`/`isActive`) stored client-side; resolution logic picks the right key based on the model's `providerType` (gemini vs. groq vs. openrouter vs. cerebras vs. custom endpoint).
- **Why it's valuable.** BYOK is a competitive feature for privacy-conscious users and a cost-shifting mechanism (user pays their own LLM bill).
- **How to extract.** `src/services/crypto.service.ts:186-204` (the BYOK vault methods) + `src/services/ai.service.ts:31-62` (`resolveModelPayload`).
- **Risks.** Storing API keys in `localStorage` is **insecure** (any XSS = total key compromise). PHP port should use the DGLab `core/crypto` `Encrypter` + `KeyRegistry` (PR #260) with `SensitiveParameterValue` redaction in logs and `sodium_memzero` after use.
- **Verdict.** **KEEP-WITH-MODIFICATION** — pattern is valuable, storage must move to encrypted server-side vault.

#### 4.1.5 Anti-flash dark-mode pre-hydration script

- **What.** Inline `<script>` in `index.html:48-60` runs synchronously before Angular boots, reads `localStorage.getItem('eloqui_theme')`, and applies the `dark` class to `<html>` before the first paint. Prevents the flash-of-wrong-theme that occurs when SSR/CSR happens before the theme signal is read.
- **Why it's valuable.** Trivial but improves perceived quality. Critical for any DGLab app that supports user-themeable dark mode.
- **How to extract.** `index.html:48-60` — 13 lines of plain JS.
- **Verdict.** **KEEP** — direct copy, even in SuperPHP templates the inline script pattern works.

#### 4.1.6 Encrypted-vault backup envelope format

- **What.** JSON envelope `{format: 'eloqui-vault-encrypted', version: 1, kdf: 'PBKDF2-SHA256', iterations: 100000, cipher: 'AES-GCM-256', salt, iv, ciphertext, timestamp, checksumSha256}`. PBKDF2-SHA256 with 100k iterations to derive the AES key from a password.
- **Why it's valuable.** A self-describing, versioned, password-derivable encryption envelope for portable backups.
- **How to extract.** `src/services/backup.service.ts:846-894`.
- **Risks.** DGLab `core/crypto` `Envelope` (PR #260) already implements a **strictly superior** envelope: versioned, `kid` (key id) for multi-key rotation, `sodium_memzero` after use. **Do not adopt ELQ's envelope** — adopt DGLab's, and write a one-time importer for ELQ backups if migration is needed.
- **Verdict.** **REJECT** as a target format. **KEEP-WITH-MODIFICATION** as a migration concern (one-time importer for existing ELQ user backups).

#### 4.1.7 Multi-target backup dispatch

- **What.** Five remote targets (download, local_dir, GitHub repo+gist, S3+R2+MinIO, WebDAV, webhook) with a unified `RemoteConfig` interface. Each target implements: `testConnection()` (verify credentials), `executeBackup()` (upload payload), and (implicitly) `restore()` (download payload).
- **Why it's valuable.** Generic across any DGLab app that has user-generated content (LMS course exports, Showcase portfolios, Codex knowledge bases). The "save to my GitHub repo" pattern is especially compelling for technical users.
- **How to extract.** `src/services/backup.service.ts:121-239` (remote config + dispatch) + `server-api.cjs:1271-1750` (server-side handlers for GitHub REST API, AWS SigV4, WebDAV PROPFIND, webhook POST).
- **Risks.** The AWS SigV4 hand-rolled implementation (`server-api.cjs:1356-1370`) is fragile — use the official `aws-sdk-php` library instead. The GitHub REST API calls are stable but rate-limited (5000 req/hour per token).
- **Verdict.** **KEEP-WITH-MODIFICATION** — promote to Hub candidate (see §6.4).

#### 4.1.8 Disaster-recovery HTML reader

- **What.** A 193-line generator that emits a single self-contained HTML file with embedded CSS + JS that can render all manuscripts, chapters, and chat history offline in any browser — even if the original app is gone. Designed for catastrophic-recovery scenarios (lost IndexedDB, lost encryption key, but you have this one HTML file on a USB stick).
- **Why it's valuable.** A genuinely thoughtful UX pattern for any document-creation app. Demonstrates a commitment to user data sovereignty.
- **How to extract.** `src/services/backup.service.ts:938-1129`.
- **Risks.** The generated HTML is large (full content inline). PHP port should offer this as one of several backup methods.
- **Verdict.** **KEEP** — distinctive UX feature worth preserving.

### 4.2 Domain Logic

#### 4.2.1 Readability formulae + audience profiles

- **What.** Five readability formulae (Flesch Reading Ease, Flesch-Kincaid Grade Level, Gunning Fog, Coleman-Liau, Automated Readability Index) computed from raw HTML. Six audience profiles with target grade ranges (Middle Grade 4.0-6.5, Young Adult 6.0-8.5, General Fiction 7.0-9.5, Literary Fiction 9.5-13.0, Academic/Technical 12.0-18.0, Casual/Web 5.0-7.5) and benchmark authors (Percy Jackson, Hunger Games, Stephen King, Toni Morrison, scientific papers, Royal Road).
- **Why it's valuable.** Readability metrics are universal writing aids. The audience-profile-matching UX (show a green/amber/red badge indicating whether your prose matches your target reader) is a distinctive product feature.
- **How to extract.** `src/services/readability.service.ts:1-604` — pure TypeScript, no Angular primitives, no I/O. The 6 audience profiles at lines 70-143 are static data.
- **Risks.** The syllable counter is English-only. The abbreviation-aware sentence splitter handles 13 English abbreviations; multi-language support would need expansion.
- **Verdict.** **KEEP** — direct line-by-line port to PHP.

#### 4.2.2 AO3 EPUB metadata extraction

- **What.** When importing an EPUB, detect if the source was Archive of Our Own by looking for `archiveofourown.org` URLs or `.userstuff`/`.userstuff1`/`.userstuff2`/`dl.tags` selectors. If AO3, extract structured metadata (Rating, Archive Warning, Categories, Fandom, Relationships, Additional Tags, Stats) from `dl.tags`, byline from `.byline`, summary from `blockquote.userstuff`. Generate a formatted "Work Info & Preface" chapter with Tailwind-styled badges.
- **Why it's valuable.** AO3 is the largest fanfiction archive; this importer is a one-way door for fanfic authors migrating to ELQ. The metadata extraction is AO3-specific but the pattern (detect-source-then-extract-structured-metadata) is generalizable.
- **How to extract.** `src/services/import.service.ts:113-153` (detection + extraction) + `:177-275` (preface HTML generation).
- **Risks.** AO3's HTML structure is stable but undocumented; future AO3 redesigns could break selectors.
- **Verdict.** **KEEP** — distinctive onboarding feature for the fanfic use case.

#### 4.2.3 EPUB spine+manifest+TOC parser

- **What.** Standard EPUB-2 parser: reads `META-INF/container.xml` for the OPF rootfile path, reads OPF for `<manifest>` (id→href map) and `<spine>` (itemref order), reads NCX or XHTML TOC for chapter titles. Handles root-directory paths, href URL-encoding, missing TOC files.
- **Why it's valuable.** Generic EPUB ingestion. Any DGLab app that needs to import EPUB (LMS course content, Codex document corpus, Showcase portfolio) can consume this.
- **How to extract.** `src/services/import.service.ts:40-103`.
- **Risks.** EPUB-3 (which uses nav.xhtml instead of toc.ncx) is partially supported. PHP port should use a proper EPUB library.
- **Verdict.** **KEEP-WITH-MODIFICATION** — port to PHP with proper EPUB-3 support.

#### 4.2.4 Censorship redaction with 6 visual styles

- **What.** Per-document `CensorshipConfig { redactionStyle: 6-style enum, customBlacklist: string[] }`. Word-boundary regex detects sensitive terms (default 21 profanities + user blacklist). 6 visual styles: `blackout` (█ chars), `blackbar` (black span), `blur` (CSS blur filter), `spoiler` (gray box that reveals on click), `asterisks` (***), `redact_pill` ([REDACTED] pill). Per-doc toggle `isUncensored` bypasses entirely.
- **Why it's valuable.** Allows the same document to render in two modes (filtered for sharing, unfiltered for author). The "neutral parity" framing — filtered vs. unfiltered as a deliberate user choice rather than a global platform policy — is a strong design principle.
- **How to extract.** `src/services/block.service.ts:429-498` (`detectSensitiveTerms` + `redactHtml`).
- **Risks.** The default sensitive-terms list is hand-curated and English-only. PHP port should make this configurable.
- **Verdict.** **KEEP** — distinctive UX feature.

#### 4.2.5 Local Linguix rule-based fallback

- **What.** Rule-based grammar review: duplicate consecutive word detection, 10 common typo corrections, 5 filler-phrase simplifications. Returns structured `Suggestion[]`.
- **Why it's valuable.** Zero-AI-cost prefilter for obvious errors. Saves LLM calls.
- **How to extract.** `src/services/ai.service.ts:65-131` (client-side) + `server-api.cjs:672+` (server-side mirror).
- **Risks.** Trivial — easy to expand with more rules.
- **Verdict.** **KEEP** — trivial to port, useful as a free prefilter.

#### 4.2.6 Word-count history + writing goals + streaks

- **What.** Per-document `WordCountHistoryPoint[]` (timestamp, wordCount, chapterTitle). Per-document `WritingGoals { documentTarget, dailyTarget, wordsWrittenToday, lastActiveDate, currentStreak }`. Computed progress percentages and streak maintenance logic.
- **Why it's valuable.** Writing-productivity gamification. Distinctive authorial UX (NaNoWriMo-style goal tracking).
- **How to extract.** `src/services/storage.service.ts:30-42` (types) + `src/services/block.service.ts:159-173` (computeds).
- **Verdict.** **KEEP** — small but valuable domain logic.

### 4.3 Infrastructure

#### 4.3.1 IndexedDB document store with per-chapter encryption

- **What.** IndexedDB object store with one record per document; each chapter's content is encrypted via AES-GCM-256 before `put()` and decrypted on `get()`. Master key persisted in `localStorage` as hex.
- **Why it's valuable.** Demonstrates at-rest encryption at the field level (per-chapter) rather than the database level. Useful pattern for high-sensitivity fields in a larger DB.
- **How to extract.** `src/services/storage.service.ts:97-145`.
- **Risks.** localStorage-as-key-storage is XSS-vulnerable (see §4.1.4). Browser-only.
- **Verdict.** **REJECT** as a porting target — replaced by DGLab `core/dbal` + `core/crypto` server-side. **KEEP** the per-chapter encryption idea as an ADR consideration for DGLab's "document" domain.

#### 4.3.2 Debounced autosave + throttled RAG reindex

- **What.** `effect()` watches the current document signal; on any change, sets a 1200ms debounce timer to flush to IndexedDB; separately, a 5s idle timer triggers RAG reindex if the chapter signature changed. Both timers are canceled if a new change arrives.
- **Why it's valuable.** Saves writes (IndexedDB writes are expensive on Firefox). Avoids reindexing on every keystroke.
- **How to extract.** `src/services/block.service.ts:268-303`.
- **Risks.** Browser event loop only. PHP port: autosave becomes a server-side `flush()` after `POST /chapters/{id}` with coalescing; RAG reindex becomes a queue job triggered on chapter save.
- **Verdict.** **KEEP-WITH-MODIFICATION** — pattern translates to server-side write coalescing + queue-side reindex trigger.

#### 4.3.3 Vector store with cosine similarity + BM25 fallback

- **What.** In-memory `Chunk[]` with cosine similarity scoring. BM25-ish keyword fallback (custom scoring with `(matches / (matches + 1.5)) * (term.length > 4 ? 2.0 : 1.0)` — not standard BM25, just a heuristic).
- **Why it's valuable.** Demonstrates the embedding→similarity→keyword fallback chain. The pattern is sound.
- **How to extract.** `src/services/rag.service.ts:1-157`.
- **Risks.** In-memory only (lost on reload). 50-chunk hard cap. BM25 implementation is non-standard.
- **Verdict.** **REJECT** as direct port — replaced by DGLab HUB-14 Search (vector store) + HUB-31 Real-Time Analytics. **KEEP** the cosine similarity + keyword fallback pattern as a design reference.

### 4.4 UX Patterns

#### 4.4.1 Notion-style slash-menu block editor

- **What.** `contentEditable` host with a 7-block-type vocabulary (paragraph/h1/h2/bullet/code/quote/divider). Slash-menu opens when user types `/`. Paste sanitizer strips dangerous tags (`script`/`style`/`iframe`/etc.) and attributes (`on*`/`data-*`/`mso-*`/`v:`/`o:`/`style`/`class`/`id`). Selection range tracking with `preRange`/`postRange` to capture surrounding context for paraphrase.
- **Why it's valuable.** The block-editor UX is the central editor surface. Selection-range tracking for context-aware paraphrase is the cleverest UX touch in the repo.
- **How to extract.** `src/components/editor/editor.component.ts:79-470` + `src/components/editor/block.component.ts:1-432`.
- **Risks.** `document.execCommand('insertHTML')` is deprecated. Modern editors (ProseMirror, Tipap, Lexical) use their own DOM abstraction. PHP/SSR port would need a different approach (HTMX-based or a JS editor like Tipap).
- **Verdict.** **KEEP-WITH-MODIFICATION** — UX pattern is valuable, but the `execCommand`-based implementation must be replaced with a modern editor framework.

#### 4.4.2 Floating-pill paraphrase trigger on selection

- **What.** On `document.selectionchange`, if the selection is non-empty and inside the editor, position a floating pill above the selection (top = rect.top - 44, left = rect.left + rect.width / 2). Pill contains a "Paraphrase" button that opens the paraphrase modal pre-populated with the selected text + 800-char context before + 800-char context after.
- **Why it's valuable.** Discoverable paraphrase UX — no need to highlight-then-navigate-menu.
- **How to extract.** `src/components/editor/editor.component.ts:305-410`.
- **Verdict.** **KEEP** — UX pattern.

#### 4.4.3 Review-mode inline suggestion highlights

- **What.** Linguix/Grammarly-style inline highlights with 4 category colors: red (Grammar/Spelling), purple (Style/Tone), blue (Clarity), emerald (Alternative). Selected suggestion shows amber highlight. CSS classes `.review-error`/`.review-style`/`.review-clarity`/`.review-alternative`/`.review-active` defined in `index.html:108-148`.
- **Why it's valuable.** Industry-standard review UX.
- **How to extract.** `index.html:108-148` (CSS) + `src/components/editor/editor.component.ts:239-294` (linguix scan + accept/dismiss/applyAll).
- **Verdict.** **KEEP** — direct port.

#### 4.4.4 Modal-via-signal pattern

- **What.** Each modal has a `show*Modal = signal(false)` on the root component. `openModal('metrics'|'models'|...)` sets the appropriate signal. ESC key closes topmost modal. Keyboard shortcuts (Ctrl+Shift+B = backup, Ctrl+E = export, Ctrl+\ = left sidebar, Ctrl+J = right sidebar, Ctrl+Shift+D = theme toggle) wired via `(window:keydown)` host listener.
- **Why it's valuable.** Clean modal management for SPAs. Keyboard shortcut discoverability.
- **How to extract.** `src/app.component.ts:64-91` (modal signals) + `:117-166` (keyboard shortcuts).
- **Risks.** PHP SSR doesn't have "signals" — but the pattern of "named modal open/close via centralized router" maps to Livewire/SuperPHP actions.
- **Verdict.** **KEEP-WITH-MODIFICATION** — pattern translates; primitive changes.

### 4.5 Cherry-pick verdict tally

| Verdict | Count |
|---|---|
| **KEEP** | 9 (4.1.3, 4.1.5, 4.1.8, 4.2.1, 4.2.2, 4.2.4, 4.2.5, 4.2.6, 4.4.3) |
| **KEEP-WITH-MODIFICATION** | 9 (4.1.1, 4.1.2, 4.1.4, 4.1.7, 4.2.3, 4.3.2, 4.4.1, 4.4.4, 4.1.6-as-migration) |
| **REJECT** | 3 (4.1.6 as target format, 4.3.1 IndexedDB, 4.3.3 in-memory vector store) |
| **DEFER** | 0 |

---

## 5. PHP Porting Plan

### 5.1 Language-feature mapping

| ELQ (TypeScript) construct | PHP 8.4 equivalent | Notes |
|---|---|---|
| `signal(value)` / `computed(() => ...)` / `effect(() => ...)` | State held in service object; computeds are methods; effects are removed | Angular signals are runtime reactivity primitives; PHP is request-scoped. For SSE/streaming, use Fibers (ADR-017). For long-lived state, use session or DB. |
| `@Injectable({providedIn: 'root'})` | PSR-11 container singleton registration | DGLab `core/container` (PR-shipped) already supports this. |
| `inject(Service)` | Constructor injection | DGLab lint rule forbids `$container->resolve(` in non-container callers (see worklog Task 59). |
| `async/await` | PHP Fibers (ADR-017) or sync code | Native async for HTTP calls: use `ext-curl` Multi or `Revolt` event loop. For typical request-scoped code, sync `file_get_contents` + `ext-curl` is fine. |
| `AsyncGenerator<string>` | Generator (`yield`) inside a Fiber | `chatStream()` (`ai.service.ts:319`) returns an async generator that yields chunks. PHP equivalent: `function chatStream(...): \Generator` yielding strings, with the underlying HTTP request running inside a Fiber for non-blocking SSE. |
| TypeScript interfaces (`interface Document`, `interface Chapter`) | PHP interfaces or readonly classes | Use `readonly class` + `public readonly string $id` for value objects. Use interfaces for repositories. |
| TypeScript discriminated unions (`type AiMode = 'fast' \| 'uncensored' \| ...`) | PHP enum (`enum AiMode: string { case Fast = 'fast'; ... }`) | PHP 8.1+ backed enums are strictly superior to string-union types. |
| TypeScript literal types (`'paragraph' \| 'h1' \| 'h2' \| ...`) | PHP enum (backed) | Same as above. |
| `CryptoKey` (WebCrypto) | `OpenSSL` extension or `sodium` extension | DGLab `core/crypto` `Encrypter` already wraps `openssl_encrypt` with AES-256-GCM + `sodium_memzero`. Use it. |
| `window.crypto.subtle.encrypt({name: 'AES-GCM', iv}, key, data)` | `\DGLab\Core\Crypto\Encrypter::encrypt($plaintext, $kid)` | Drop-in replacement. The DGLab envelope is JSON-serialized with version, kid, iv, ciphertext. |
| `window.crypto.subtle.digest('SHA-256', data)` | `hash('sha256', $data, false)` | Native PHP. |
| `window.crypto.subtle.deriveKey({name: 'PBKDF2', salt, iterations: 100000, hash: 'SHA-256'}, ...)` | `hash_pbkdf2('sha256', $password, $salt, 100000, 32, true)` | DGLab `core/crypto` `Hasher` already does HKDF-SHA256; for PBKDF2 use `hash_pbkdf2` directly. |
| `window.crypto.getRandomValues(new Uint8Array(N))` | `random_bytes(N)` | Native PHP. |
| `indexedDB.open(name, version)` + `onupgradeneeded` + `transaction` + `objectStore` | Skip entirely; use MySQL via `core/dbal` | IndexedDB is browser-only. PHP port replaces with server-side persistence. |
| `localStorage.getItem(key)` / `setItem(key, value)` | Session (`$_SESSION`) or DB-backed user preferences | localStorage is browser-only. For server-side per-user storage, use MySQL `user_preferences` table; for ephemeral UI state, use sessions. |
| `DOMParser.parseFromString(html, 'text/html')` | `DOMDocument` or `Masterminds/HTML5` | Native `DOMDocument` is HTML4-lenient; for HTML5, use `Masterminds/HTML5` package. For XML (EPUB OPF), `simplexml_load_string` or `DOMDocument`. |
| `JSZip.loadAsync(file)` | `ZipArchive::open()` + `getFromName()` | Native PHP extension. |
| `jsPDF` | `tcpdf` or `mpdf` or `dompdf` | All three are mature. `tcpdf` is most feature-complete; `mpdf` is easiest for HTML→PDF; `dompdf` is fastest for HTML→PDF but less accurate. |
| `marked.parse(markdownText)` | `league/commonmark` or `cebe/markdown` | Both are PSR-15-friendly. `league/commonmark` is more configurable. |
| `@angular/forms` (FormControl, FormGroup) | Native PHP form handling or `laminas-form` | For SuperPHP SSR, forms are just POST handlers. |
| `@angular/common` (ngFor, ngIf) | SuperPHP templates (per ADR-005) | ADR-005 ratified SuperPHP as the template language. Angular control-flow syntax (`@if`/`@for`) maps closely to SuperPHP's control flow. |
| RxJS `Observable`/`Subject` | PSR-14 EventDispatcher + Fibers | DGLab `core/event-dispatcher` ships PSR-14. For long-running streams, use Fibers + Generator. |
| Express middleware chain | PSR-15 middleware pipeline | DGLab `core/middleware` ships `MiddlewarePipeline`. |
| `app.use('/api', apiRouter)` | Route registration on `core/router` | DGLab `core/router` supports attribute-loaded routes (`#[Route('/api/ai/chat', methods: ['POST'])]`). |
| `express.json({limit: '15mb'})` body parser | PSR-7 `ServerRequest::getParsedBody()` | Body size limit configured at FrankenPHP worker level, not middleware. |
| `fetch(url, {method, headers, body, signal: AbortSignal.timeout(10000)})` | PSR-18 `ClientInterface::sendRequest($request)` + `GuzzleHttp\Exception\ConnectException` for timeout | DGLab standard is PSR-18. For timeout, set `Guzzle`'s `timeout` option. |
| `res.setHeader('Content-Type', 'text/event-stream')` + `res.write('data: ...')` | `header('Content-Type: text/event-stream')` + `echo 'data: ...'` + `flush()` | FrankenPHP supports SSE natively. Use `connection: keep-alive`. |
| `process.env.GEMINI_API_KEY` | `getenv('GEMINI_API_KEY')` or DGLab `core/config` env loader | DGLab `core/config` ships `EnvLoader`. |
| `Buffer.from(str).toString('base64')` | `base64_encode($str)` | Native. |
| `crypto.createHash('sha256').update(data).digest('hex')` | `hash('sha256', $data)` | Native. |
| `crypto.createHmac('sha256', key).update(data).digest('hex')` | `hash_hmac('sha256', $data, $key)` | Native. Used for AWS SigV4 in `server-api.cjs:1356-1370`. |
| `new Date().toISOString()` | `(new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)` | Use `DateTimeImmutable` for value semantics. |
| `Math.max(a, b)` / `Math.min(a, b)` | `max($a, $b)` / `min($a, $b)` | Native. |
| `Math.round(x * 10) / 10` | `round($x, 1)` | Native. |
| `Math.sqrt(x)` | `sqrt($x)` | Native. |
| `Array.from(set)` | `array_values(iterator_to_array($set))` or `array_unique($arr)` | Native. |
| `string.match(regex)` with named groups | `preg_match($regex, $subject, $matches)` | PHP regex syntax differs slightly (delimiters required). |
| `string.replace(regex, callback)` | `preg_replace_callback($regex, $callback, $subject)` | Native. Used for censorship redaction (`block.service.ts:470`). |
| `new Uint8Array(N).map(...)` | `str_repeat('\0', N)` then unpack, or `array_fill(0, N, 0)` | Prefer `random_bytes(N)` for crypto. |
| TypeScript strict null checks | PHPStan level 6 + nullable type declarations | DGLab enforces PHPStan `^2.2`. |
| `import { X } from 'path'` | `use DGLab\...\X;` + PSR-4 autoload | Standard. |

### 5.2 Framework-feature mapping

| ELQ framework feature | PHP equivalent | Migration notes |
|---|---|---|
| Angular DI (`@Injectable`, `inject()`) | PSR-11 container (`core/container`) | Constructor injection everywhere. No property injection. Service definitions in `ApplicationFactory`. |
| Angular standalone components | SuperPHP templates (per ADR-005) | Templates are `.phtml` files; component logic becomes a Presenter class. |
| Angular `OnPush` change detection | Not applicable (PHP is request-scoped) | For SSE, use Fibers + manual flush. |
| Angular signals/computed/effect | Service state + computed methods | No runtime reactivity in PHP. UI updates require full request/response or SSE push. |
| Angular `@ViewChild` | DI (inject the presenter/template data) | Different paradigm: in SSR, you don't query the DOM; you compute the data before render. |
| Angular `@Output` event emitters | Form POST actions or AJAX endpoints | SuperPHP forms submit to PHP endpoints; the response re-renders. |
| Angular `Router` | `core/router` with attribute routes | `#[Route('/api/ai/chat', methods: ['POST'])]` on a controller method. |
| Angular `HttpClient` | PSR-18 `ClientInterface` | Use `GuzzleHttp\Client` or `nyholm/psr7` + `kriswallsmith/buzz`. |
| Angular `FormControl` / `FormGroup` | Plain HTML forms + server-side validation | Use `laminas-validator` for server-side rules. |
| Tailwind via CDN | Tailwind compiled at build time | DGLab should compile Tailwind at build time (not CDN) for production. |
| `zoneless` change detection | N/A | No change detection in SSR. |
| Express app + Router | FrankenPHP + `core/router` | Routes registered in `ApplicationFactory`. |
| Express `express.json()` body parser | PSR-7 `ServerRequestInterface::getParsedBody()` | Body parsing is PSR-7 standard. |
| Express middleware | PSR-15 middleware (`core/middleware`) | `MiddlewarePipeline` ships with `CallableMiddlewareAdapter`. |
| `bun.lock` / `package-lock.json` | `composer.lock` | Standard. |
| `puppeteer` smoke tests | PHPUnit + Panther or Cypress | ELQ's 28 test scripts are ad-hoc; DGLab standard is PHPUnit. For E2E, use `behat/mink-selenium2-driver` or Cypress. |

### 5.3 Standard-library mapping

| ELQ JS library | PHP equivalent | Migration notes |
|---|---|---|
| `jszip` (EPUB/backup zip generation) | `ZipArchive` (ext-zip) | Native. For EPUB generation, `ZipArchive` + manual `mimetype` first entry (stored, not deflated). |
| `jspdf` | `tcpdf` or `mpdf` or `dompdf` | `mpdf` is closest to ELQ's usage (HTML→PDF with running headers/footers). |
| `marked` (Markdown rendering) | `league/commonmark` | Configure with `GithubFlavoredMarkdownExtension` for tables/strikethrough. |
| `@google/genai` (Gemini SDK) | PSR-18 HTTP client + Google AI REST API | The `@google/genai` SDK is a thin wrapper around the REST API. Use `guzzlehttp/psr7` + `google/apiclient` or roll a thin client. |
| RxJS Observables | PSR-14 events + Fibers (for streams) | For `chatStream()`, use `Generator<string>` yielding SSE chunks inside a Fiber. |
| `fetch()` | PSR-18 `ClientInterface` | Standardize on `GuzzleHttp\Client` with `extended-cache-mode` for connection pooling. |
| `Buffer` (Node) | Native PHP strings | PHP strings are byte-strings. No `Buffer` type needed. |
| `crypto` (Node) | `ext-openssl` + `ext-sodium` + `hash_*` functions | DGLab `core/crypto` already wraps this. |
| `DOMParser` (browser) | `DOMDocument` (ext-dom) or `Masterminds/HTML5` | For HTML5, prefer `Masterminds/HTML5` (DOMDocument is HTML4). |
| `crypto.randomUUID()` | ` Ramsey\Uuid\Uuid::uuid4()` or `Symfony\Component\Uid\Uuid::v4()` | DGLab ADR-009 ratifies ULID over UUID — use `symfony/uid` Ulid. |
| `window.matchMedia('(prefers-color-scheme: dark)')` | Server-side: read `Sec-CH-Prefers-Color-Scheme` header (Chrome) | For non-supporting browsers, fall back to localStorage or a server-side default. |
| `navigator.clipboard.writeText(text)` | Browser feature only — PHP cannot write to clipboard | UI feature; not a porting concern. |
| `File` API (browser) | `Psr\Http\Message\UploadedFileInterface` | Server-side file uploads. |
| `FileSystemDirectoryHandle` (File System Access API) | Not portable — browser-only | Replace with server-side filesystem (`core/filesystem`, already shipped). |

### 5.4 Tooling mapping

| ELQ tool | PHP equivalent | Migration notes |
|---|---|---|
| TypeScript compiler (`tsc`) | PHPStan `^2.2` (already in DGLab) | Strict mode level 6. |
| ESLint | PHPStan + PSalter + `php-cs-fixer` | DGLab standard. |
| `@angular/cli` (`ng serve`, `ng build`) | `composer` + `core/dev-cli` (CORE-20, not yet shipped) | ADR-014 ratifies SDLC as canonical. |
| Vite (declared but unused) | Not needed — PHP doesn't bundle by default | For Tailwind, use `tailwindcss/tailwindcss` standalone binary or `aurorawp/tailwindcss` npm package in a build step. |
| `puppeteer` (smoke tests) | PHPUnit + Panther for E2E | ELQ's 28 test scripts are throwaway; DGLab standard is PHPUnit. |
| `bun.lock` | `composer.lock` | Standard. |
| `package.json` scripts (`dev`, `build`, `start`, `preview`) | `composer scripts` | Standard. |
| `tsconfig.json` strict mode | PHPStan level 6 | Standard. |

### 5.5 Non-trivial port migration notes

**SSE streaming for AI chat.** ELQ's `chatStream()` (`ai.service.ts:319-389`) is an `AsyncGenerator<string>` that fetches an SSE endpoint, reads chunks via `ReadableStream.getReader()`, decodes via `TextDecoder`, and yields parsed `data: {text: ...}` events. PHP 8.4 + FrankenPHP supports SSE natively via `header('Content-Type: text/event-stream')` + `echo` + `flush()`. The server-side endpoint (`server-api.cjs:807-917`) uses Gemini's `generateContentStream()` for true streaming or fake word-chunks the response for non-Gemini. PHP port: server endpoint uses `GuzzleHttp\Client->requestAsync()` with `'stream' => true` + ` Psr\Http\Message\StreamInterface::read()` in a Fiber-yielding loop. Client-side, fetch + ReadableStream works unchanged.

**IndexedDB → MySQL persistence.** ELQ stores Documents in IndexedDB (`storage.service.ts:78-95`), encrypting chapter content before `put()`. The PHP port stores Documents in MySQL via DGLab `core/dbal`, with chapter content encrypted via DGLab `core/crypto` `Encrypter::encrypt()` using a per-user key from `KeyRegistry`. The "per-chapter encryption" granularity is preserved by encrypting the `content` column of the `chapters` table, not the entire `documents` row. Migration: write a one-time importer that reads ELQ's IndexedDB export (via a JSON backup file the user generates through the Backup modal) and inserts into MySQL.

**EPUB import.** ELQ's `importEpub()` uses `JSZip.loadAsync(file)` then `DOMParser.parseFromString(content, 'application/xml')` for OPF/NCX and `'text/html'` for chapter content. PHP port: `ZipArchive::open($file->getStreamPath())` + `getFromName('META-INF/container.xml')` + `simplexml_load_string()` for XML/OPF/NCX, and `Masterminds/HTML5::loadHTML($html)` for chapter content (DOMDocument is HTML4-lenient). The AO3-specific selectors (`.userstuff`, `dl.tags`, `.byline`) work identically in `Masterminds/HTML5` via `XPath` or CSS selectors (`Symfony\Component\CssSelector`).

**Multi-provider AI router.** ELQ's `executeUnifiedModelPrompt()` (`server-api.cjs:60-296`) branches on `modelId` prefix: `pollinations-`, `groq/`, `openrouter/`, `cerebras/`, custom endpoint URL, or default Gemini. PHP port: an `AiProviderRouter` class with a chain of `AiProviderStrategy` implementations, each with a `supports(string $modelId): bool` and `invoke(Prompt $prompt): Response` method. The Gemini native SDK call becomes a direct REST call to `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent` with the `x-goog-api-key` header. The OpenAI-compatible strategy is a single PSR-18 client call to `{endpoint}/chat/completions`.

**Paraphrase taxonomy as enum + builder.** ELQ stores the 22 document types as a const array of objects in `paraphrase.types.ts:49-236` and the prompt fragments as a separate `DOCUMENT_TYPE_DESCRIPTIONS` dict in `server-api.cjs:298-383`. PHP port unifies these: `enum DocumentType: string { case NovelSfw = 'novel_sfw'; ... }` with a `promptFragment(): string` method on each case (PHP enum methods). Same for `enum ParaphraseStyle`. A `ParaphrasePromptBuilder` service assembles the final prompt from `{docType, style, customInstruction, surroundingContext, contextBefore, contextAfter, selectionType}`.

**Censorship redaction regex callback.** ELQ's `redactHtml()` (`block.service.ts:451-486`) uses a complex regex callback to wrap sensitive terms in styled spans while preserving HTML tag boundaries (`/(>|^)([^<]+)(<|$)/g` then `text.replace(/\b(term1|term2|...)\b/gi, callback)`). PHP port: `preg_replace_callback('/(>|^)([^<]+)(<|$)/', function($m) use ($terms, $style) { return $m[1] . preg_replace_callback('/\b(' . implode('|', $terms) . ')\b/i', fn($w) => $this->renderSpan($w[0], $style), $m[2]) . $m[3]; }, $html)`. Identical algorithm; PHP closures + `use` capture.

**Backup envelope format migration.** ELQ's encrypted-vault format (`backup.service.ts:846-894`) uses `{format, version, kdf, iterations, cipher, salt, iv, ciphertext, checksumSha256}`. DGLab `core/crypto` `Envelope` uses `{v, kid, iv, ciphertext, ...}`. To migrate existing ELQ user backups: a one-time `EloquiBackupImporter` reads the ELQ envelope, derives the AES key via `hash_pbkdf2('sha256', $password, $salt, 100000, 32, true)`, decrypts with `openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv)`, and re-encrypts with DGLab `Encrypter::encrypt($plaintext, $newKid)` for storage in the new system. The user must supply their password once.

**AWS SigV4 hand-rolled → aws-sdk-php.** ELQ's S3 backup endpoint (`server-api.cjs:1347-1380`) hand-rolls AWS SigV4 signing with native `crypto` module. PHP port: use `aws-sdk-php`'s `S3Client::putObject()` directly. The hand-rolled signing is fragile (canonical header ordering, query string encoding) and should not be reproduced.

---

## 6. Spoke Decomposition

This section applies the **ESPOKE/ISPOKE model** as refined in APP-MODEL-REFINEMENT-5:

> **ESPOKE** = singular external identity of an application. One ESPOKE per app. Owns public surface + composition policy + entry orchestration. Does NOT do domain logic, business rules, direct Hub calls, state, or long-running work. Lint rule restricts ESPOKE imports to `Spoke\*` and `Application\*` namespaces.
>
> **ISPOKE** = internal composable capability. Many per app. May be shared (many-to-many) via consumer-side composition policy (no bilateral consent).
>
> **Hub** = generic platform capability reusable across all DGLab apps. If a capability is useful to *every* DGLab app, it's a Hub, not an ISPOKE.
>
> **Classification** = `abstraction` (reusable infrastructure worker) or `feature` (app-specific domain logic) — metadata, not type.
>
> **`reusable: true`** = other ESPOKEs may consume via their Application Manifest; `reusable: false` = private, lint-enforced.

### 6.1 Proposed ISPOKE inventory

The proposed Eloq ESPOKE consumes 16 ISPOKEs. Placeholder IDs are used; actual IDs assigned during admission per the SDLC.

---

#### 6.1.1 ISPOKE-E1: Document Vault

```
Capability: Document/Chapter persistence + at-rest encryption + autosave
ELQ source: src/services/block.service.ts (1047 LOC), src/services/storage.service.ts (242 LOC), src/services/block.service.ts:80-303 (state + autosave effect)
Proposed ISPOKE ID: ISPOKE-E1 (placeholder)
Classification: feature
Reusable: false
Consumes (Hub capabilities): HUB-04 Identity (user ownership), HUB-06 Auditor (save events), HUB-20 Vault (key material if server-side key vault desired)
Consumes (Core): CORE-02 DBAL, CORE-16 Crypto (Envelope for at-rest encryption), CORE-03 EventDispatcher (autosave events), CORE-14 Filesystem (disaster HTML if applicable)
Description: Per-user document/chapter repository with field-level AES-GCM-256 encryption of chapter content, debounced autosave coalescing, and per-chapter save events. Stores Document {id, title, lastModified, chapters[], chatSessions[], wordCountHistory[], goals, isUncensored, censorshipConfig, documentType} and Chapter {id, title, content (encrypted), lastModified}. The Document and Chapter value-object shapes defined at storage.service.ts:5-60 are directly portable as PHP readonly classes.
Migration notes: ELQ uses IndexedDB with a single-object-store model and per-chapter encryption before put(). PHP port uses MySQL `documents` and `chapters` tables via CORE-02 DBAL, with the `content` column encrypted via CORE-16 Crypto Encrypter using a per-user KEK from HUB-20 Vault. Autosave debounce (1200ms in ELQ) becomes a server-side write-coalescing pattern: client sends PATCH /chapters/{id} with content; server debounces by flushing on a 1s timer or on session-end. The "Document" aggregate root (with its 9 optional fields and legacy migration defaults at storage.service.ts:155-191) is a value object worth defining once and reusing everywhere.
Why this classification: feature — this is the core domain aggregate root of the Eloq application. Document/Chapter/ChatSession/Goals are Eloq-specific shapes, not generic infrastructure. Other apps (LMS Course, Showcase Product, Codex Knowledge Base) have different aggregate roots. NOT reusable because the Document schema is Eloq-specific.
Potential consumers: none (private to Eloq ESPOKE).
```

---

#### 6.1.2 ISPOKE-E2: Block Editor Surface

```
Capability: Notion-style contentEditable block editor with 7 block types, slash-menu, paste sanitizer, selection-range tracking
ELQ source: src/components/editor/editor.component.ts (471 LOC), src/components/editor/block.component.ts (432 LOC), src/components/editor/*.html (636 LOC), index.html:84-91 (empty-block CSS), src/services/block.service.ts:17-78 (htmlToBlocks/blocksToHtml)
Proposed ISPOKE ID: ISPOKE-E2 (placeholder)
Classification: feature
Reusable: false
Consumes (Hub capabilities): HUB-26 UI Elements (for shared primitives)
Consumes (Core): CORE-07 SuperPHP Templates (ADR-005), CORE-11 SuperPHP Compiler, CORE-12 SuperPHP Runtime
Description: Authoring surface for chapter content. 7 block types (paragraph, h1, h2, bullet, code, quote, divider). Slash-menu opens on `/` keystroke. Paste sanitizer strips forbidden tags (script/style/iframe/object/embed/link/meta/svg/canvas/form/input) and attributes (on*/data-*/mso-*/v:*/o:*/style/class/id). Selection-range tracking with preRange/postRange for context-aware paraphrase (800 chars before/after). Floating-pill paraphrase trigger above selection.
Migration notes: Angular component layer does NOT port directly. The contentEditable host + document.execCommand('insertHTML') approach is deprecated and fragile. Recommended PHP-port approach: use a modern JS editor (Tipap, ProseMirror, or Lexical) loaded as an ES module on the client; the PHP SSR layer renders the initial HTML and exposes REST endpoints for content updates (/chapters/{id}/content PATCH). The paste sanitizer logic (editor.component.ts:163-213) ports to PHP for server-side validation of submitted HTML. The htmlToBlocks/blocksToHtml converters (block.service.ts:17-78) port directly to PHP.
Why this classification: feature — the editor surface IS the Eloq product. Block vocabulary (paragraph/h1/h2/bullet/code/quote/divider) is writing-app-specific. NOT reusable because other apps have different content models.
Potential consumers: none (private to Eloq ESPOKE).
```

---

#### 6.1.3 ISPOKE-E3: AI Inference Hub

```
Capability: Multi-provider AI invocation router with hierarchical failover
ELQ source: server-api.cjs:39-296 (executeUnifiedModelPrompt + generateContentWithFailover), src/services/ai.service.ts:31-62 (resolveModelPayload), src/services/model.service.ts (curated model catalog + custom endpoints)
Proposed ISPOKE ID: ISPOKE-E3 (placeholder)
Classification: abstraction
Reusable: true
Consumes (Hub capabilities): HUB-20 Vault (BYOK key storage, SensitiveParameterValue for keys)
Consumes (Core): CORE-18 Kernel (HTTP entry), CORE-02 DBAL (model catalog persistence if needed), CORE-03 EventDispatcher (request lifecycle events)
Description: Provider-agnostic AI invocation layer. Routes prompts by modelId prefix to one of: (a) Google Gemini REST API; (b) OpenAI-compatible endpoint (Ollama, LM Studio, vLLM, custom HTTP); (c) Pollinations zero-key public router; (d) Groq; (e) OpenRouter; (f) Cerebras. Hierarchical failover: Gemini 2.5-flash → 2.0-flash → 1.5-flash → 1.5-flash-8b → OpenAI-compatible → Pollinations → rule-based local. SSE streaming for true streaming providers; fake word-chunked streaming for non-streaming providers. Unified model payload resolution (modelId + customKey + customModel config).
Migration notes: The Gemini SDK (@google/genai) is JS-only; PHP port calls the Gemini REST API directly via PSR-18 HTTP client. The OpenAI-compatible branch (server-api.cjs:73-119) ports line-by-line — just Guzzle POST to {endpoint}/chat/completions. The Pollinations branch (server-api.cjs:122-180) is a single Guzzle call. The hierarchical failover pattern (try → catch → try next model) is the same algorithm in any language. The "task-role assignment" pattern (one model for grammar, another for drafting) from model.service.ts:50-57 should be preserved as a TaskModelConfig value object.
Why this classification: abstraction — this is reusable infrastructure, not Eloq-specific domain logic. Any DGLab app that calls an LLM (Codex for document Q&A, LMS for adaptive content, Showcase for product copywriting) would consume this ISPOKE. REUSE because multi-provider AI routing is universally valuable.
Potential consumers: Codex ESPOKE (document Q&A), LMS ESPOKE (adaptive course content), Showcase ESPOKE (product description generation).
```

---

#### 6.1.4 ISPOKE-E4: Paraphrase Engine

```
Capability: Document-type × style-aware paraphrase prompt assembly + alternative generation
ELQ source: src/services/paraphrase.types.ts (309 LOC, 22 doc types + 10 styles), server-api.cjs:298-396 (DOCUMENT_TYPE_DESCRIPTIONS + PARAPHRASE_STYLE_GUIDES), server-api.cjs:1022-1226 (/api/ai/paraphrase endpoint), server-api.cjs:398-670 (localParaphraseFallback), src/components/paraphrase/paraphrase-modal.component.ts (879 LOC)
Proposed ISPOKE ID: ISPOKE-E4 (placeholder)
Classification: feature
Reusable: true
Consumes (Hub capabilities): none
Consumes (Core): CORE-03 EventDispatcher (paraphrase-applied event for audit), CORE-16 Crypto (no direct use)
Consumes (ISPOKE): ISPOKE-E3 (AI Inference Hub — invokes the LLM)
Description: Assembles paraphrase prompts from {documentType, style, selectionType, customInstruction, surroundingContext, contextBefore, contextAfter, useSurroundingContext, isDocUncensored}. Returns 4 alternatives each with {text, label, tone, explanation, fitScore}. 22 document types across 5 categories (Fiction SFW/NSFW/Speculative/Suspense/Horror/YA/Historical/Poetry/Fanfic × 9; Academic Research/Thesis/LitReview/STEM/Humanities × 5; Legal Contract/Brief/Compliance × 3; Business Memo/TechDocs/Marketing × 3; Journalism/Memoir × 2). 10 paraphrase styles (natural/closer/vivid/concise/dramatic/formal/simplified/active/lyrical/dialogue). Local fallback dictionary for common words (said/walked/looked/important) and register-specific templates (legal operative covenant, academic empirical hedging, NSFW somatic intensity).
Migration notes: The 22 doc types + 10 styles become PHP enums with method implementations (promptFragment(): string) on each case, unifying the client metadata (paraphrase.types.ts:49-309) and server prompt fragments (server-api.cjs:298-396) into a single source of truth. The prompt assembly logic (server-api.cjs:1065-1110) becomes a ParaphrasePromptBuilder service. The local fallback (server-api.cjs:398-670) is 270 LOC of hardcoded templates — port selectively, since the LLM should produce these dynamically.
Why this classification: feature — paraphrasing is a domain capability, not pure infrastructure. However, the paraphrase taxonomy is reusable beyond Eloq: Codex (technical doc paraphrasing), LMS (course content rewriting for different reading levels), Showcase (product description variation) could all consume this ISPOKE. The 22 doc types cover academic, legal, business, and journalism registers — not just fiction. REUSE because the taxonomy is broad enough to serve non-fiction apps.
Potential consumers: Codex ESPOKE (technical doc paraphrasing for different audiences), LMS ESPOKE (rewriting course content for different reading levels).
```

---

#### 6.1.5 ISPOKE-E5: Linguix Quality Reviewer

```
Capability: Grammar/spelling/clarity/style/tone review with structured suggestions + score
ELQ source: src/services/ai.service.ts:65-131 (localLinguisticReview), server-api.cjs:920-1019 (/api/ai/review endpoint with Gemini responseSchema), src/components/editor/editor.component.ts:239-294 (scan + accept/dismiss/applyAll), index.html:108-148 (review-highlight CSS)
Proposed ISPOKE ID: ISPOKE-E5 (placeholder)
Classification: feature
Reusable: true
Consumes (Hub capabilities): none
Consumes (Core): CORE-03 EventDispatcher (review-applied event for audit)
Consumes (ISPOKE): ISPOKE-E3 (AI Inference Hub)
Description: Linguix/Grammarly-style inline grammar review. Returns Suggestion[] = {original, suggestion, type: Spelling|Grammar|Clarity|Style|Tone, explanation}. Computes a Linguix score 0-100 from issue density. Local rule-based fallback: duplicate word detection, 10 common typo corrections, 5 filler-phrase simplifications. UI renders inline highlights with 4 category colors (red/purple/blue/emerald) + active suggestion in amber.
Migration notes: The Gemini responseSchema (server-api.cjs:940-952) ports to PHP as a structured JSON schema passed to the Gemini REST API's `generationConfig.responseSchema` field. The local rule-based reviewer (ai.service.ts:65-131) ports line-by-line — regex + dictionary lookup. The CSS classes (index.html:108-148) port unchanged. The accept/dismiss/applyAll UI flow (editor.component.ts:269-294) becomes SuperPHP form actions.
Why this classification: feature — quality review is a writing-app domain feature. REUSE because the Linguix-pattern (inline grammar highlights + accept/dismiss UX) applies to any document-creation app. Codex (code documentation review), LMS (student essay grading), Showcase (product copy QA) could all consume this ISPOKE.
Potential consumers: Codex ESPOKE (code documentation grammar review), LMS ESPOKE (student essay quality scoring).
```

---

#### 6.1.6 ISPOKE-E6: Readability Auditor

```
Capability: 5 readability formulae + 6 audience profiles + audience-match feedback
ELQ source: src/services/readability.service.ts (604 LOC — pure TypeScript, no Angular primitives)
Proposed ISPOKE ID: ISPOKE-E6 (placeholder)
Classification: abstraction
Reusable: true
Consumes (Hub capabilities): none
Consumes (Core): none (pure library)
Description: Computes Flesch Reading Ease, Flesch-Kincaid Grade Level, Gunning Fog Index, Coleman-Liau Index, Automated Readability Index from raw HTML input. Abbreviation-aware sentence splitter (13 abbreviations protected: Mr./Mrs./Ms./Dr./Prof./St./Sr./Jr./e.g./i.e./etc./vs./approx./No., plus decimals and ellipses). English syllable counter with heuristics for silent 'e', 'le' syllable, trailing 'ed'/'es' patterns, vowel-group counting, and special-case adjustment for ia|io|iu|eo|ua|uo diphthongs. 6 audience profiles (Middle Grade 4.0-6.5, Young Adult 6.0-8.5, General Fiction 7.0-9.5, Literary Fiction 9.5-13.0, Academic/Technical 12.0-18.0, Casual/Web 5.0-7.5) with benchmark authors. 5-status audience-match feedback (optimal/slightly_dense/too_dense/slightly_simple/too_simple) with actionable tips.
Migration notes: This is the single most directly portable service in the repo. Pure TypeScript, no Angular primitives, no I/O. PHP port is line-by-line: stripHtml() (readability.service.ts:394-407) ports with preg_replace; splitIntoSentences() (readability.service.ts:412-452) ports with preg_replace_callback + preg_split; countSyllables() (readability.service.ts:465-508) ports unchanged. The 6 audience profiles (lines 70-143) become a PHP enum or static array. The 5 formulae (lines 228-253) are public-domain math.
Why this classification: abstraction — readability formulae are infrastructure, not Eloq-specific. They are public-domain math (Flesch 1948, Flesch-Kincaid 1975, Gunning Fog 1952, Coleman-Liau 1975, ARI 1967). REUSE because every DGLab app that displays user-written text could benefit from a readability audit.
Potential consumers: Codex ESPOKE (technical doc readability), LMS ESPOKE (course content readability vs. student level), Showcase ESPOKE (product description readability), any future DGLab app with user-authored content.
```

---

#### 6.1.7 ISPOKE-E7: RAG Retriever

```
Capability: Document chunking + batch embeddings + cosine similarity + BM25 fallback
ELQ source: src/services/rag.service.ts (157 LOC), src/services/ai.service.ts:133-174 (getEmbedding + getBatchEmbeddings with concurrency=3)
Proposed ISPOKE ID: ISPOKE-E7 (placeholder)
Classification: abstraction
Reusable: true
Consumes (Hub capabilities): HUB-14 Search (vector store) or HUB-31 Real-Time Analytics (durable metrics), HUB-10 Queue (batch embedding jobs)
Consumes (Core): CORE-03 EventDispatcher (indexing events)
Consumes (ISPOKE): ISPOKE-E3 (AI Inference Hub — getEmbedding())
Description: Chapter chunking (500 words with 50-word overlap, capped at 50 chunks in ELQ — should be configurable in PHP port). Batch embedding via concurrent fetch (concurrency=3). In-memory vector store with cosine similarity search. BM25-ish keyword fallback when embeddings fail (custom scoring `(matches / (matches + 1.5)) * (term.length > 4 ? 2.0 : 1.0)` — note: this is NOT standard BM25, just a heuristic).
Migration notes: ELQ's in-memory `Chunk[]` array (lost on reload) is replaced by HUB-14 Search (persistent vector store). The 50-chunk cap is browser-memory-specific — PHP port removes it and uses HUB-14's pagination. The `setTimeout(25)` cooperative yield (rag.service.ts:86) becomes a HUB-10 Queue job for batch embedding. The cosine similarity (rag.service.ts:147-157) is 9 LOC of pure math — port unchanged. The BM25 fallback should be replaced with a proper BM25 implementation (e.g., `teamtnt/tntsearch` library).
Why this classification: abstraction — RAG retrieval is generic infrastructure, not Eloq-specific. REUSE because every DGLab app that has searchable content (Codex knowledge base, LMS course content, Showcase product catalog) could use this ISPOKE.
Potential consumers: Codex ESPOKE (knowledge base retrieval), LMS ESPOKE (course content Q&A), Showcase ESPOKE (product search).
```

---

#### 6.1.8 ISPOKE-E8: BYOK Vault

```
Capability: Multi-provider API key vault with per-provider routing + key fingerprinting
ELQ source: src/services/crypto.service.ts:186-204 (saveApiKey/removeApiKey/getActiveKeyForProvider), src/services/ai.service.ts:31-62 (resolveModelPayload routing), src/components/byok-modal/byok-modal.component.ts (302 LOC UI)
Proposed ISPOKE ID: ISPOKE-E8 (placeholder)
Classification: abstraction
Reusable: true
Consumes (Hub capabilities): HUB-20 Vault (server-side secret storage, SensitiveParameterValue), HUB-04 Identity (user ownership of keys)
Consumes (Core): CORE-16 Crypto (Encrypter for at-rest encryption of API keys, PasswordHasher for key derivation)
Description: Per-user API key vault. Each ApiKeyConfig = {provider: gemini|openai|groq|anthropic|openrouter|custom, name, key, endpointUrl?, isActive, addedAt}. Provider-specific routing: resolveModelPayload(modelId, customModel) picks the right key based on the model's providerType. SHA-256 fingerprint of the raw key for UI display (without revealing the key itself). Active key per provider.
Migration notes: ELQ stores keys in localStorage — XSS-vulnerable. PHP port uses HUB-20 Vault (MySQL `vault_secrets` table with AES-256-GCM-encrypted blobs). The Encrypter from CORE-16 wraps each key. SensitiveParameterValue (PHP 8.2+) is used in the ApiKeyConfig value object to prevent accidental var_dump/log leakage. The provider-routing logic (ai.service.ts:31-62) becomes a KeyResolver service. The fingerprint computation (crypto.service.ts:84-94) uses hash('sha256', $rawKey) truncated to 8 bytes for display.
Why this classification: abstraction — API key storage is generic infrastructure. REUSE because every DGLab app that calls external APIs on behalf of users (Codex calling OpenAI, LMS calling adaptive-content providers, Showcase calling image-generation APIs) needs this.
Potential consumers: Codex ESPOKE (LLM API keys), LMS ESPOKE (content-provider API keys), Showcase ESPOKE (image-generation API keys).
Hub-promotion candidate: YES — see §6.4.
```

---

#### 6.1.9 ISPOKE-E9: Remote Backup Orchestrator

```
Capability: 6 backup methods × 5 remote targets with auto-scheduler + restore
ELQ source: src/services/backup.service.ts (1397 LOC), server-api.cjs:1271-1750 (5 backup endpoints), src/components/backup-modal/backup-modal.component.ts (1335 LOC UI)
Proposed ISPOKE ID: ISPOKE-E9 (placeholder)
Classification: abstraction
Reusable: true
Consumes (Hub capabilities): HUB-10 Queue (auto-backup scheduler), HUB-25 Chronos (interval scheduling — pending per HUB-FOUNDATION-SWEEP-2), HUB-11 Cloud Storage (S3/R2/MinIO target), HUB-06 Auditor (backup event logging)
Consumes (Core): CORE-16 Crypto (envelope for encrypted_vault method), CORE-14 Filesystem (local_dir target), CORE-02 DBAL (backup log persistence)
Description: Six backup methods: encrypted_vault (AES-GCM-256 with PBKDF2-derived key, versioned JSON envelope), json_vault (plain JSON), zip_bundle (multi-folder markdown ZIP with Manifest.json + Manuscripts/ + ChatHistory/ + Analytics/wordcount-history.csv), incremental (delta since last timestamp), active_doc (single doc only), disaster_html (single-file self-contained HTML reader). Five remote targets: download (HTTP response), local_dir (server filesystem), github (REST API PUT to repo contents or Gist), s3 (AWS SigV4), webdav (PROPFIND/PUT), webhook (POST/PUT with HMAC-SHA256 secret). Auto-backup scheduler with interval-based trigger (10/30/60 min or on every save). Restore inspection + merge/replace modes.
Migration notes: ELQ's 1397-LOC backup.service.ts is over-engineered. PHP port splits into: BackupMethodStrategy enum + RemoteTargetStrategy enum + BackupOrchestrator service. The disaster_html generator (backup.service.ts:938-1129, 193 LOC) ports nearly verbatim — it's just HTML string assembly. The encrypted_vault envelope format should be REPLACED with DGLab's CORE-16 Envelope (strictly superior: versioned, kid, sodium_memzero). A one-time importer handles existing ELQ backups. The AWS SigV4 hand-rolled code (server-api.cjs:1356-1370) is replaced with aws-sdk-php's S3Client. The GitHub REST API calls port line-by-line via Guzzle.
Why this classification: abstraction — multi-target backup dispatch is generic infrastructure. REUSE because every DGLab app with user-generated content (LMS courses, Showcase portfolios, Codex knowledge bases) needs backup-to-remote capability.
Potential consumers: LMS ESPOKE (course backup to GitHub), Showcase ESPOKE (portfolio backup to S3), Codex ESPOKE (knowledge base backup to WebDAV).
Hub-promotion candidate: YES — see §6.4.
```

---

#### 6.1.10 ISPOKE-E10: EPUB Importer

```
Capability: EPUB-2/3 + AO3 metadata ingestion
ELQ source: src/services/import.service.ts (382 LOC)
Proposed ISPOKE ID: ISPOKE-E10 (placeholder)
Classification: abstraction
Reusable: true
Consumes (Hub capabilities): none
Consumes (Core): CORE-14 Filesystem (uploaded file handling), CORE-02 DBAL (chapter persistence)
Description: Parses .epub and .txt files into Chapter[]. EPUB pipeline: ZipArchive open → read META-INF/container.xml for OPF rootfile → parse OPF manifest (id→href map) + spine (itemref order) + TOC (NCX or XHTML) → for each spine item, parse HTML and either detect AO3 (URLs containing archiveofourown.org or .userstuff/.userstuff1/.userstuff2/dl.tags selectors) and extract structured metadata (Rating, Archive Warning, Categories, Fandom, Relationships, Additional Tags, Stats from dl.tags; byline from .byline; summary from blockquote.userstuff) to build a preface chapter, or extract paragraphs/quotes/hr as content. Aggressive attribute sanitization (on*/mso-*/style/class/id stripped).
Migration notes: JSZip → ZipArchive (ext-zip). DOMParser (browser) → Masterminds/HTML5 (better HTML5 support than DOMDocument). The AO3-specific CSS selectors (.userstuff, dl.tags, .byline) work in Masterminds/HTML5 via Symfony\Component\CssSelector. The preface HTML generation (import.service.ts:195-268) is 73 LOC of Tailwind-styled badge HTML — port directly with SuperPHP template partials. EPUB-3 nav.xhtml is partially supported in ELQ; PHP port should fully support both EPUB-2 (toc.ncx) and EPUB-3 (nav.xhtml).
Why this classification: abstraction — EPUB parsing is generic infrastructure. REUSE because every DGLab app that ingests EPUB (LMS course content import, Codex document corpus ingestion, Showcase portfolio import) could use this ISPOKE.
Potential consumers: LMS ESPOKE (EPUB course content import), Codex ESPOKE (EPUB document corpus ingestion), Showcase ESPOKE (EPUB portfolio import).
```

---

#### 6.1.11 ISPOKE-E11: Manuscript Exporter

```
Capability: PDF/Markdown/TXT/HTML/EPUB export with redaction awareness
ELQ source: src/services/export.service.ts (755 LOC), src/components/export-modal/export-modal.component.ts (613 LOC UI)
Proposed ISPOKE ID: ISPOKE-E11 (placeholder)
Classification: abstraction
Reusable: true
Consumes (Hub capabilities): none
Consumes (Core): CORE-14 Filesystem (export file generation), CORE-16 Crypto (no direct use)
Consumes (ISPOKE): ISPOKE-E12 (Censorship Engine — for redaction-aware content generation)
Description: Five export formats: pdf (running headers/footers, page numbers, redaction-aware, chapter page breaks, metadata banner), markdown (HTML→MD conversion + chapter headings), txt (plain text with paragraph breaks), html (self-contained with inline CSS), epub (proper EPUB-2 structure with mimetype/OEBPS/META-INF/container.xml). Redaction-aware: respects per-doc CensorshipConfig + 6 redaction styles when generating export content. Optional metadata banner (export date, chapter count, redaction note, "REDACTED COPY - CONFIDENTIAL" footer).
Migration notes: jsPDF → mpdf (closest to ELQ's HTML→PDF usage with running headers/footers). JSZip for EPUB generation → ZipArchive with mimetype as first entry stored (not deflated) per EPUB spec. Markdown generation → league/commonmark reverse (HTML→Markdown via league/html-to-markdown). HTML export is trivial string assembly. TXT export is HTML-stripped plain text. The redaction-aware content generation (export.service.ts:46-67) calls BlockService.redactHtml() — this dependency on ISPOKE-E12 means the export modal must compose both.
Why this classification: abstraction — multi-format export is generic infrastructure. REUSE because every DGLab app with user-generated content needs export (LMS course export to PDF, Codex knowledge base export to Markdown, Showcase portfolio export to HTML).
Potential consumers: LMS ESPOKE (course PDF export), Codex ESPOKE (knowledge base Markdown export), Showcase ESPOKE (portfolio HTML export).
```

---

#### 6.1.12 ISPOKE-E12: Censorship & Redaction Engine

```
Capability: Per-document sensitive-term redaction with 6 visual styles
ELQ source: src/services/block.service.ts:429-498 (detectSensitiveTerms + redactHtml + toggleDocumentCensorship), src/services/block.service.ts:7-15 (DEFAULT_SENSITIVE_TERMS list), index.html:197-245 (CSS for 6 redaction styles)
Proposed ISPOKE ID: ISPOKE-E12 (placeholder)
Classification: feature
Reusable: false
Consumes (Hub capabilities): none
Consumes (Core): CORE-03 EventDispatcher (redaction-toggled event for audit)
Description: Per-document CensorshipConfig {redactionStyle: blackout|blackbar|blur|spoiler|asterisks|redact_pill, customBlacklist: string[]}. Word-boundary regex detection of sensitive terms (default 21 profanities + user blacklist). HTML redaction: for each text node (between > and <), regex-replace sensitive terms with styled <span> wrappers. 6 visual styles: blackout (█ chars), blackbar (black span), blur (CSS blur filter), spoiler (gray box, click to reveal), asterisks (***), redact_pill ([REDACTED] pill). Per-doc isUncensored toggle bypasses entirely (neutral parity: filtered vs. unfiltered as deliberate user choice).
Migration notes: The DEFAULT_SENSITIVE_TERMS list (block.service.ts:7-15) should become a configurable PHP array (per-app or per-tenant). The redactHtml regex callback (block.service.ts:470-485) ports to PHP via preg_replace_callback with nested closures. The 6 CSS classes (index.html:197-245) port unchanged. The "neutral parity" design (filtered vs. unfiltered as a per-doc toggle, not a global policy) must be preserved — this is a deliberate design choice that distinguishes Eloq from typical content-filter platforms.
Why this classification: feature — censorship/redaction is Eloq-specific domain logic (the "neutral parity" framing is a product decision, not generic infrastructure). NOT REUSE because the sensitive-terms list and the "unfiltered mode" framing are writing-app-specific. Other apps have different content policies.
Potential consumers: none (private to Eloq ESPOKE).
```

---

#### 6.1.13 ISPOKE-E13: Privacy & Audit Ledger

```
Capability: Client-side audit log + privacy policy dashboard + zero-telemetry toggle
ELQ source: src/services/privacy.service.ts (134 LOC), src/components/privacy-modal/privacy-modal.component.ts (225 LOC UI)
Proposed ISPOKE ID: ISPOKE-E13 (placeholder)
Classification: abstraction
Reusable: true
Consumes (Hub capabilities): HUB-06 Auditor (server-side audit log infrastructure — this ISPOKE may be subsumed by HUB-06)
Consumes (Core): CORE-02 DBAL (audit log persistence), CORE-03 EventDispatcher (audit event subscription)
Description: 5-category audit log (document, encryption, ai_inference, byok, auth) with 4 statuses (allowed, encrypted, revoked, processed). Capped at 100 entries in ELQ (should be configurable in PHP port). JSON export. 5 privacy policy items as a static array (zero retention, local encryption, no training, BYOK isolation, unbiased neutrality) — UI dashboard displaying each with a status badge (guaranteed, enforced, user_controlled). Zero-telemetry toggle persisted per-user.
Migration notes: The AuditEvent value object (privacy.service.ts:3-11) becomes a PHP readonly class. The 5 policy items (privacy.service.ts:31-67) become a PHP enum or static array. The logEvent method (privacy.service.ts:99-110) becomes a thin wrapper around HUB-06 Auditor's record() method. The 100-entry cap is localStorage-specific — PHP port uses HUB-06's retention policy. The JSON export (privacy.service.ts:131-133) is trivial.
Why this classification: abstraction — audit logging is generic infrastructure. REUSE because every DGLab app that handles user data needs an audit log. NOTE: this ISPOKE may be entirely subsumed by HUB-06 Auditor if HUB-06's API is broad enough. The decision is whether the 5 Eloq-specific privacy policies (zero retention, local encryption, no training, BYOK isolation, unbiased neutrality) belong in a generic Hub or in an Eloq-specific ISPOKE. Recommendation: HUB-06 owns the audit log mechanism; the 5 policy items are an Eloq-specific ISPOKE that decorates HUB-06's events with policy labels.
Potential consumers: Codex ESPOKE (audit log for code-documentation access), LMS ESPOKE (audit log for student data access), Showcase ESPOKE (audit log for product catalog changes).
Hub-promotion candidate: PARTIAL — see §6.4. The mechanism belongs to HUB-06; the policy labels are Eloq-specific.
```

---

#### 6.1.14 ISPOKE-E14: Productivity Metrics

```
Capability: Word count history + writing goals + streaks + audience-match dashboard
ELQ source: src/services/block.service.ts:159-258 (goal signals + wordCountHistory computeds), src/components/metrics-modal/metrics-modal.component.ts (709 LOC UI)
Proposed ISPOKE ID: ISPOKE-E14 (placeholder)
Classification: feature
Reusable: false
Consumes (Hub capabilities): none
Consumes (Core): CORE-02 DBAL (word count history persistence), CORE-03 EventDispatcher (goal-completed events)
Consumes (ISPOKE): ISPOKE-E6 (Readability Auditor — for audience match), ISPOKE-E1 (Document Vault — for chapter content)
Description: Per-document WritingGoals {documentTarget, dailyTarget, wordsWrittenToday, lastActiveDate, currentStreak}. Per-document WordCountHistoryPoint[] (timestamp, wordCount, chapterTitle). Computed progress percentages (documentProgress, dailyProgress). Audience-match dashboard combining ReadabilityAuditor metrics with audience profile targets.
Migration notes: The WritingGoals and WordCountHistoryPoint value objects (storage.service.ts:30-42) become PHP readonly classes. The streak maintenance logic (block.service.ts:160-163 — reads lastActiveDate, increments or resets streak) ports to a small DomainService. The dashboard UI (metrics-modal.component.ts) becomes a SuperPHP template. The sparkline chart for word count history needs a charting library — Chart.js or a server-rendered SVG sparkline.
Why this classification: feature — writing-productivity gamification is Eloq-specific domain logic. NOT REUSE because "daily word count goals" and "writing streaks" are writing-app-specific. Other apps have different productivity metrics (LMS: study streaks; Codex: documentation coverage; Showcase: product launch countdown).
Potential consumers: none (private to Eloq ESPOKE).
```

---

#### 6.1.15 ISPOKE-E15: Theme Manager

```
Capability: Light/dark/system theme toggle with anti-flash pre-hydration
ELQ source: src/services/theme.service.ts (91 LOC), index.html:48-60 (anti-flash script)
Proposed ISPOKE ID: ISPOKE-E15 (placeholder)
Classification: abstraction
Reusable: true
Consumes (Hub capabilities): none
Consumes (Core): CORE-07 SuperPHP Templates (for SSR theme application)
Description: Three-state theme mode (light, dark, system-follows-OS). Listens to prefers-color-scheme: dark media query (browser-side). Synchronizes DOM <html> class + color-scheme CSS property. Anti-flash pre-hydration script reads localStorage before Angular boots (in PHP port: read Sec-CH-Prefers-Color-Scheme header or session).
Migration notes: ELQ's anti-flash script (index.html:48-60) is 13 lines of plain JS — direct copy. PHP port: read Sec-CH-Prefers-Color-Scheme client hint header (Chrome 111+) for SSR; fall back to session-stored preference. The ThemeMode enum (theme.service.ts:3) becomes a PHP backed enum. The system-dark detection (theme.service.ts:85-90) becomes client-side JS in the SSR template.
Why this classification: abstraction — theme management is generic infrastructure. REUSE because every DGLab app with a UI needs theme management. NOTE: this ISPOKE is small enough that it may belong in HUB-26 UI Elements instead of as a standalone ISPOKE. Decision: defer until HUB-26 ships.
Potential consumers: Codex ESPOKE, LMS ESPOKE, Showcase ESPOKE — all UI-bearing apps.
Hub-promotion candidate: PARTIAL — may be subsumed by HUB-26 UI Elements.
```

---

#### 6.1.16 ISPOKE-E16: Auth & RBAC (REJECTED as standalone ISPOKE)

```
Capability: localStorage auth stub + 3 roles + crypto-RNG reset tokens
ELQ source: src/services/auth.service.ts (215 LOC), src/components/auth-modal/auth-modal.component.ts (192 LOC UI)
Proposed ISPOKE ID: ISPOKE-E16 (placeholder — REJECTED)
Classification: feature
Reusable: true (would be if accepted)
Consumes (Hub capabilities): HUB-04 Identity (already shipped, PR #268)
Description: localStorage-based user accounts with 3 roles (admin, writer, reviewer) and 4 role-derived permission computeds. 24-byte crypto-RNG password reset tokens (15-min expiry). No real authentication — login is just email lookup in localStorage users list.
Migration notes: ELQ's AuthService is a demo stub, not production-ready. DGLab HUB-04 Identity (PR #268) already ships: User entity, UserId/Email/RoleIdentifier value objects, AuthenticatedUser, AuthMiddleware (PSR-15), JwtSigner/JwtVerifier (ES256 via openssl_sign/verify), UserRepositoryInterface, MySQLUserRepository, 3 migrations, password reset flow not yet shipped. ELQ's 3 roles (admin/writer/reviewer) map onto HUB-04's RoleIdentifier value object. ELQ's 4 permission computeds (canEdit, canManageModels, canManageBYOK, canAccessPrivacy) become a small RBAC layer on top of HUB-04.
Why this classification: REJECTED as standalone ISPOKE — the entire capability is subsumed by HUB-04 Identity. The 3 roles and 4 permission checks are 30 LOC of glue code on top of HUB-04, not a separate ISPOKE.
Potential consumers: N/A — absorbed into HUB-04.
```

---

### 6.2 Application Manifest draft (Eloq ESPOKE)

```yaml
# /home/z/my-project/Architecture/Applications/Eloq/APPLICATION.md
# Draft — actual file not yet created. Code implementation deferred per user instruction.
application: Eloq
espoke: ESPOKE-Eloq  # placeholder ID — actual ID assigned during admission

# Public surface (owned by ESPOKE — these are the URLs the ESPOKE publishes)
public_surface:
  - GET  /                  # Editor SPA shell
  - GET  /documents         # Document library
  - GET  /documents/{id}    # Document editor
  - POST /api/documents                # Create document
  - PATCH /api/documents/{id}          # Update document metadata
  - DELETE /api/documents/{id}         # Delete document
  - GET  /api/documents/{id}/chapters  # List chapters
  - PATCH /api/chapters/{id}           # Update chapter content (debounced)
  - POST /api/ai/generate              # Generate text (delegates to ISPOKE-E3)
  - POST /api/ai/chat                  # Streaming chat (delegates to ISPOKE-E3)
  - POST /api/ai/review                # Linguix review (delegates to ISPOKE-E5)
  - POST /api/ai/paraphrase            # Paraphrase (delegates to ISPOKE-E4)
  - POST /api/ai/embed                 # Embedding (delegates to ISPOKE-E3)
  - POST /api/ai/test-model            # Model probe (delegates to ISPOKE-E3)
  - POST /api/backup/remote/test-connection  # Backup probe (delegates to ISPOKE-E9)
  - POST /api/backup/remote/github           # Backup to GitHub (delegates to ISPOKE-E9)
  - POST /api/backup/remote/s3              # Backup to S3 (delegates to ISPOKE-E9)
  - POST /api/backup/remote/webdav          # Backup to WebDAV (delegates to ISPOKE-E9)
  - POST /api/backup/remote/webhook         # Backup to webhook (delegates to ISPOKE-E9)
  - POST /api/import/epub                   # EPUB import (delegates to ISPOKE-E10)
  - POST /api/export/{format}               # Export (delegates to ISPOKE-E11)

# Workers (ISPOKEs composed into this ESPOKE — consumer-side composition, no ownership)
workers:
  - ISPOKE-E1   # Document Vault (feature, reusable: false) — private
  - ISPOKE-E2   # Block Editor Surface (feature, reusable: false) — private
  - ISPOKE-E3   # AI Inference Hub (abstraction, reusable: true) — imported
  - ISPOKE-E4   # Paraphrase Engine (feature, reusable: true) — imported
  - ISPOKE-E5   # Linguix Quality Reviewer (feature, reusable: true) — imported
  - ISPOKE-E6   # Readability Auditor (abstraction, reusable: true) — imported
  - ISPOKE-E7   # RAG Retriever (abstraction, reusable: true) — imported
  - ISPOKE-E8   # BYOK Vault (abstraction, reusable: true) — imported; Hub-promotion candidate
  - ISPOKE-E9   # Remote Backup Orchestrator (abstraction, reusable: true) — imported; Hub-promotion candidate
  - ISPOKE-E10  # EPUB Importer (abstraction, reusable: true) — imported
  - ISPOKE-E11  # Manuscript Exporter (abstraction, reusable: true) — imported
  - ISPOKE-E12  # Censorship & Redaction Engine (feature, reusable: false) — private
  - ISPOKE-E13  # Privacy & Audit Ledger (abstraction, reusable: true) — imported; Hub-promotion candidate (partial)
  - ISPOKE-E14  # Productivity Metrics (feature, reusable: false) — private
  - ISPOKE-E15  # Theme Manager (abstraction, reusable: true) — imported; may merge into HUB-26

# Auth via HUB-04 Identity (composed, not an ISPOKE)
hub_consumption:
  - HUB-04 Identity        # User, roles, JWT, password reset
  - HUB-06 Auditor         # Audit log mechanism (ISPOKE-E13 decorates with policy labels)
  - HUB-10 Queue           # Batch embedding + auto-backup scheduler
  - HUB-14 Search          # Vector store for RAG (replaces in-memory Chunks[])
  - HUB-20 Vault           # BYOK key storage (server-side secret vault)
  - HUB-25 Chronos         # Auto-backup interval scheduling (pending HUB-FOUNDATION-SWEEP-2)
  - HUB-26 UI Elements     # Shared UI primitives (pending — may absorb ISPOKE-E15)
  - HUB-31 Real-Time Analytics  # Optional durable metrics (pending)

core_consumption:
  - CORE-02 DBAL              # Document/chapter persistence
  - CORE-03 EventDispatcher   # Autosave + audit + indexing events
  - CORE-07 SuperPHP Templates  # SSR templates (per ADR-005)
  - CORE-11 SuperPHP Compiler  # Template compilation
  - CORE-12 SuperPHP Runtime   # Template runtime
  - CORE-14 Filesystem       # Local backup target + EPUB upload handling
  - CORE-16 Crypto           # AES-GCM-256 at-rest encryption + Envelope
  - CORE-18 Kernel           # HTTP entry, lifecycle events, resource ceilings
  - CORE-19 Service Providers # Boot configuration (partially shipped)
```

### 6.3 ISPOKE classification tally

| ISPOKE | Capability | Classification | Reusable |
|---|---|---|---|
| ISPOKE-E1 | Document Vault | feature | false |
| ISPOKE-E2 | Block Editor Surface | feature | false |
| ISPOKE-E3 | AI Inference Hub | abstraction | true |
| ISPOKE-E4 | Paraphrase Engine | feature | true |
| ISPOKE-E5 | Linguix Quality Reviewer | feature | true |
| ISPOKE-E6 | Readability Auditor | abstraction | true |
| ISPOKE-E7 | RAG Retriever | abstraction | true |
| ISPOKE-E8 | BYOK Vault | abstraction | true |
| ISPOKE-E9 | Remote Backup Orchestrator | abstraction | true |
| ISPOKE-E10 | EPUB Importer | abstraction | true |
| ISPOKE-E11 | Manuscript Exporter | abstraction | true |
| ISPOKE-E12 | Censorship & Redaction Engine | feature | false |
| ISPOKE-E13 | Privacy & Audit Ledger | abstraction | true |
| ISPOKE-E14 | Productivity Metrics | feature | false |
| ISPOKE-E15 | Theme Manager | abstraction | true |
| ISPOKE-E16 | Auth & RBAC (REJECTED) | feature | absorbed by HUB-04 |

**Totals:** 15 accepted ISPOKEs (excluding the rejected ISPOKE-E16).
- **Classification:** 9 abstraction, 6 feature.
- **Reusability:** 11 reusable, 4 private.
- **Hub-promotion candidates:** 3 (ISPOKE-E8, ISPOKE-E9, ISPOKE-E13-partial).

### 6.4 Hub-promotion candidates

Per the consumer-side composition model (APP-MODEL-REFINEMENT-5), a capability becomes a Hub when it is useful to *every* DGLab app, not just a subset. Three ISPOKEs surface as Hub-promotion candidates:

#### 6.4.1 ISPOKE-E8 (BYOK Vault) → potential HUB-32 BYOK Vault

- **Why candidate.** Every DGLab app that calls an external API on behalf of users (Codex calling OpenAI, LMS calling adaptive-content providers, Showcase calling image-generation APIs) needs encrypted API key storage with per-provider routing. The capability is generic across all DGLab apps, not specific to a subset.
- **Decision.** **DEFER promotion.** Until a second consumer (Codex, LMS, or Showcase) actually needs this capability, it remains an ISPOKE with `reusable: true`. The first consumer is Eloq; if/when Codex or another app declares it in their Application Manifest, the contract reusability audit (APP-MODEL-REFINEMENT-5 extension #2) triggers a Hub promotion proposal. For now, ISPOKE-E8 lives in `packages/spoke/internal/eloq-byok-vault/` (or similar) and is shared via composition.
- **Promotion criteria.** (a) Second ESPOKE declares ISPOKE-E8 in their Application Manifest; (b) contract audit confirms the API surface is generic (no Eloq-specific assumptions); (c) ADR is written ratifying the promotion; (d) package moves from `spoke/internal/` to `hub/`.

#### 6.4.2 ISPOKE-E9 (Remote Backup Orchestrator) → potential HUB-33 Backup Dispatch

- **Why candidate.** Every DGLab app with user-generated content (LMS courses, Showcase portfolios, Codex knowledge bases) needs backup-to-remote-target capability. The 6 backup methods × 5 remote targets pattern is generic.
- **Decision.** **DEFER promotion.** Same rationale as ISPOKE-E8. Note: this overlaps with HUB-11 Cloud Storage (S3/R2/MinIO target) — when HUB-11 ships, ISPOKE-E9's S3 branch should delegate to HUB-11 rather than implement its own AWS SigV4. The GitHub/WebDAV/webhook targets are not covered by HUB-11 and may warrant a separate Hub.
- **Promotion criteria.** Same as ISPOKE-E8. Additional: confirm overlap with HUB-11 Cloud Storage and define the boundary (HUB-11 = storage primitive; HUB-33 = multi-target dispatch orchestrator).

#### 6.4.3 ISPOKE-E13 (Privacy & Audit Ledger) → absorbed by HUB-06 Auditor (PARTIAL)

- **Why candidate.** The audit log mechanism (5-category, 4-status, capped, JSON-exportable) is generic. The 5 Eloq-specific privacy policies (zero retention, local encryption, no training, BYOK isolation, unbiased neutrality) are NOT generic — they're Eloq product decisions.
- **Decision.** **SPLIT.** The audit log mechanism is absorbed into HUB-06 Auditor (already shipped, PR #256). The 5 privacy policies remain as an Eloq-specific ISPOKE that decorates HUB-06's events with policy labels. The Eloq ESPOKE composes HUB-06 for the mechanism and ISPOKE-E13 (slim) for the policy decoration.
- **Promotion criteria.** N/A — HUB-06 already exists. The decision is whether to split ISPOKE-E13 or leave it as a thick ISPOKE that calls HUB-06 internally. Recommendation: split for clarity.

#### 6.4.4 ISPOKE-E15 (Theme Manager) → potential absorption into HUB-26 UI Elements

- **Why candidate.** Theme management is small (91 LOC) and generic across all UI-bearing DGLab apps. HUB-26 UI Elements is the natural home.
- **Decision.** **DEFER until HUB-26 ships.** HUB-26 is in the SDLC-AUDIT-1 NONE-dependency tier (pure library, can proceed without runtime). When HUB-26 ships, evaluate whether its scope includes theme management or whether a separate small ISPOKE is warranted. For now, ISPOKE-E15 lives in `packages/spoke/internal/eloq-theme/` and is shared via composition.

### 6.5 ESPOKE boundary enforcement

Per APP-MODEL-REFINEMENT-5, the ESPOKE must NOT do domain logic, business rules, direct Hub calls, state, or long-running work. Lint rule restricts ESPOKE imports to `Spoke\*` and `Application\*` namespaces.

For the Eloq ESPOKE, this means:

- **ESPOKE owns:** Public surface (URLs listed in §6.2), composition policy (the `workers:` list in the Application Manifest), cross-cutting policy (e.g., "all AI calls must go through ISPOKE-E3"), entry orchestration (which ISPOKE handles which route).
- **ESPOKE does NOT own:** Document/Chapter value objects (owned by ISPOKE-E1), paraphrase taxonomy (owned by ISPOKE-E4), readability formulae (owned by ISPOKE-E6), etc.
- **ESPOKE imports:** Only `DGLab\Spoke\*` (ISPOKE interfaces) and `DGLab\Application\*` (Application Manifest, ApplicationFactory).
- **ESPOKE forbids:** Direct `DGLab\Hub\*` imports (must go through an ISPOKE), direct `DGLab\Core\*` imports (must go through an ISPOKE), direct DB access (must go through ISPOKE-E1 → CORE-02 DBAL).

This boundary ensures the Eloq ESPOKE is composable: another app could swap ISPOKE-E3 (AI Inference Hub) for a different AI provider ISPOKE without touching the ESPOKE.

---

## 7. Open Questions

These questions cannot be answered from the ELQ repo alone; they require user input.

### 7.1 Which DGLab apps beyond Eloq might consume ELQ-derived ISPOKEs?

The analysis identified 11 `reusable: true` ISPOKEs. The most likely consumers (per the analysis in §6) are Codex, LMS, and Showcase. **Question for the tech lead:** which of these apps are planned to consume ELQ-derived ISPOKEs, and in what order? This determines which ISPOKEs become Hub-promotion candidates first (per §6.4, a second consumer triggers the contract reusability audit).

### 7.2 Is the Eloq ESPOKE a new app or a rename of an existing planned app?

The current DGLab blueprint catalog (`Architecture/Spoke/External/ESPOKE-01.md` through `ESPOKE-15.md`) lists 15 planned ESPOKEs. **Question:** is "Eloq" (the writing-app ESPOKE proposed here) one of those 15, or a new 16th? If it's a rename of an existing planned app (e.g., the long-deferred "AI App Architect" mentioned in ARCHITECTURE-SDLC-FUSION.md), the Eloq Application Manifest should adopt that app's existing ID and update its scope.

### 7.3 Does ELQ have features that are explicitly out of scope for DGLab?

ELQ has several features that may not align with DGLab's product scope:

- **NSFW/uncensored mode** — ELQ explicitly supports "novel_nsfw" document type and "unfiltered" AI mode with `BLOCK_NONE` safety settings. **Question:** does DGLab want to preserve this content-policy-agnostic stance, or apply platform-wide content filtering?
- **Disaster-recovery HTML reader** (193 LOC, `backup.service.ts:938-1129`) — a thoughtful but unusual UX feature. **Question:** in scope or defer?
- **AO3-specific EPUB metadata extraction** — fanfic-specific. **Question:** in scope for DGLab's writing-app ESPOKE, or treated as a niche feature?
- **Custom LLM endpoint support (Ollama, LM Studio, vLLM)** — assumes users run local LLMs. **Question:** in scope, or limit to cloud providers?

### 7.4 Are there license considerations the user wants flagged?

**The ELQ repo has no LICENSE file.** Default copyright (All Rights Reserved) applies. Direct verbatim source porting of TypeScript → PHP requires explicit permission from the ELQ author. **Question:** has the user obtained permission from the ELQ author (DGCodeIdeas) to port this code into DGLab? If not, the analysis-only output (this document) is the boundary — code implementation would need to either (a) obtain permission, or (b) re-implement from the analysis (not the source) to avoid derivative-work concerns.

The 22-document-type taxonomy, the readability formulae (which are public-domain algorithms), and the architectural patterns (which are uncopyrightable ideas) can be reimplemented freely. The specific prompt fragments in `server-api.cjs:298-396` are creative content and may be copyrightable.

### 7.5 Any UI/UX patterns that must be preserved vs. redesigned?

ELQ's UI has several distinctive patterns: floating-pill paraphrase trigger on selection, Linguix-style inline review highlights with 4 category colors, slash-menu block editor, 6-style censorship redaction visualizations, disaster-recovery HTML reader, three-pane writing environment (library/editor/chat). **Question:** which of these must be preserved verbatim in the DGLab port, and which can be redesigned to fit DGLab's UI system (HUB-26 UI Elements, when shipped)?

### 7.6 Is the at-rest encryption model field-level (per-chapter) or row-level (per-document)?

ELQ encrypts each chapter's content individually before storing the document. This is field-level encryption at the chapter-content column. DGLab's `core/crypto` Envelope supports both field-level and row-level encryption. **Question:** does the tech lead want to preserve field-level encryption (more granular, more CPU cost per chapter read) or move to row-level encryption (encrypt the whole document JSON once)? The decision has performance implications: field-level means decrypting one chapter touches one Envelope; row-level means decrypting one chapter requires decrypting the entire document.

### 7.7 Does the multi-provider AI router belong in ISPOKE-E3 or in a future Hub?

ISPOKE-E3 (AI Inference Hub) is the highest-value reusable ISPOKE in this analysis. **Question:** given that LLM invocation is one of the most common infrastructure needs across DGLab apps, should ISPOKE-E3 be promoted to a Hub immediately (HUB-34 AI Inference Hub) rather than waiting for a second consumer? The deferred-promotion rule (APP-MODEL-REFINEMENT-5) says "wait for a second consumer," but if the tech lead considers LLM invocation as universally needed as Identity (HUB-04) or Audit (HUB-06), an immediate Hub ratification could be justified.

### 7.8 What is the migration path for existing ELQ users (if any)?

If there are existing ELQ users with IndexedDB-stored documents or generated backup files (encrypted_vault, json_vault, zip_bundle), the DGLab port needs a one-time importer. **Question:** are there existing ELQ users? If so, how many, and what is the priority of backward compatibility for their backup format?

---

## 8. Recommended Next Steps (deferred)

Code implementation is deferred per user instruction. When un-deferred, the recommended sequence is:

### 8.1 Phase 1: Foundation ISPOKEs (no Hub dependencies)

These are pure-library ISPOKEs with no Hub dependencies — they can ship immediately.

1. **ISPOKE-E6 (Readability Auditor)** — direct line-by-line port of `src/services/readability.service.ts` (604 LOC) to PHP. Pure library code. Estimated effort: 1 build-unit.
2. **ISPOKE-E15 (Theme Manager)** — small port of `src/services/theme.service.ts` (91 LOC) + the anti-flash script. Estimated effort: 0.5 build-units.

### 8.2 Phase 2: Domain ISPOKEs (depend on Core only)

1. **ISPOKE-E1 (Document Vault)** — port the Document/Chapter value objects from `src/services/storage.service.ts:5-60` to PHP readonly classes; port the autosave coalescing pattern. Depends on CORE-02 DBAL, CORE-16 Crypto, CORE-03 EventDispatcher. Estimated effort: 2 build-units.
2. **ISPOKE-E4 (Paraphrase Engine)** — unify the 22 document types and 10 styles into PHP enums; port the prompt assembly logic. Depends on ISPOKE-E3. Estimated effort: 2 build-units.
3. **ISPOKE-E12 (Censorship & Redaction Engine)** — port the redactHtml regex callback and 6 CSS classes. Depends on CORE-03 EventDispatcher. Estimated effort: 1 build-unit.

### 8.3 Phase 3: Infrastructure ISPOKEs (depend on Hub tier)

1. **ISPOKE-E3 (AI Inference Hub)** — port the `executeUnifiedModelPrompt` multi-provider router (240 LOC) to a PHP strategy pattern. Depends on HUB-20 Vault (for BYOK key storage), CORE-18 Kernel. Estimated effort: 3 build-units.
2. **ISPOKE-E8 (BYOK Vault)** — port the API key vault to use HUB-20 Vault + CORE-16 Crypto. Estimated effort: 1.5 build-units.
3. **ISPOKE-E5 (Linguix Quality Reviewer)** — port the rule-based reviewer + Gemini responseSchema integration. Depends on ISPOKE-E3. Estimated effort: 1 build-unit.
4. **ISPOKE-E7 (RAG Retriever)** — port the chunking + cosine similarity + BM25 fallback. Depends on HUB-14 Search, HUB-10 Queue, ISPOKE-E3. Estimated effort: 2 build-units.

### 8.4 Phase 4: I/O ISPOKEs

1. **ISPOKE-E10 (EPUB Importer)** — port the EPUB pipeline to ZipArchive + Masterminds/HTML5. Depends on CORE-14 Filesystem. Estimated effort: 2 build-units.
2. **ISPOKE-E11 (Manuscript Exporter)** — port the 5 export formats to mpdf + ZipArchive + league/commonmark. Depends on ISPOKE-E12. Estimated effort: 2.5 build-units.
3. **ISPOKE-E9 (Remote Backup Orchestrator)** — port the 6 methods × 5 targets; replace AWS SigV4 with aws-sdk-php. Depends on HUB-10 Queue, HUB-25 Chronos, HUB-11 Cloud Storage, CORE-16 Crypto. Estimated effort: 4 build-units (largest).

### 8.5 Phase 5: UX ISPOKEs

1. **ISPOKE-E2 (Block Editor Surface)** — needs a JS editor framework (Tipap/ProseMirror/Lexical) loaded client-side; PHP SSR renders the initial HTML. Depends on CORE-07 SuperPHP Templates. Estimated effort: 4 build-units.
2. **ISPOKE-E13 (Privacy & Audit Ledger)** — thin wrapper around HUB-06 Auditor + 5 Eloq-specific policy items. Estimated effort: 0.5 build-units.
3. **ISPOKE-E14 (Productivity Metrics)** — port WritingGoals + WordCountHistoryPoint + streak maintenance. Estimated effort: 1 build-unit.

### 8.6 Phase 6: ESPOKE assembly

1. **Eloq ESPOKE** — Application Manifest, route registration in ApplicationFactory, cross-cutting policy wiring. Depends on all 15 ISPOKEs landing. Estimated effort: 1.5 build-units.

### 8.7 Total estimated effort

~30 build-units across 6 phases. At the DGLab observed throughput of 2.5 build-units/day (per ARCHITECTURE-SDLC-FUSION.md), the Eloq ESPOKE end-to-end is approximately 12 working days of implementation effort, assuming all Hub dependencies (HUB-14, HUB-20, HUB-25, HUB-26) are already shipped. If Hub dependencies are not yet shipped, add the Hub shipping effort first (per HUB-FOUNDATION-SWEEP-2, several Hubs are blocked on the runtime substrate decision).

### 8.8 Pre-implementation gates

Before any code is written, the following must be resolved:

1. **License permission** — confirm with ELQ author that porting to DGLab is permitted (see §7.4).
2. **ESPOKE ID assignment** — is Eloq one of the 15 existing planned ESPOKEs, or a new 16th? (See §7.2.)
3. **Hub readiness** — confirm HUB-04, HUB-06, HUB-10, HUB-14, HUB-20, HUB-25, HUB-26 are shipped or scheduled before their dependent ISPOKEs.
4. **Content-policy decision** — confirm whether the NSFW/uncensored feature is in scope for DGLab (see §7.3).
5. **Editor framework choice** — Tipap vs. ProseMirror vs. Lexical vs. custom (see §4.4.1 migration notes).
6. **Hub-promotion deferral** — confirm that ISPOKE-E3, ISPOKE-E8, ISPOKE-E9 remain ISPOKEs (not immediately promoted to Hubs) until a second consumer arrives (see §6.4).

---

*End of analysis. This document is self-contained and does not require re-reading the conversation to act on. All file paths and line numbers reference the cloned ELQ repository at `/home/z/my-project/external/ELQ/`.*
