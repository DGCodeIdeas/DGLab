# LMS Founder-Ready Implementation Plan (Final)

**Base Commit:** `24ec093eef56cac3f5522251e5cf13a000715cbf` (origin/main, 2026-11-02)
**Plan Created:** 2026-11-06
**Plan Status:** Awaiting Owner Approval

---

## Executive Summary

The LMS backend on `origin/main` (`24ec093eef56cac3f5522251e5cf13a000715cbf`) has domain entities, repositories, and application services for Courses, Modules, and Enrollments. **Critical gaps** prevent founder-ready status:

| Gap | Impact |
|-----|--------|
| **No HTTP API layer** | Zero LMS endpoints exposed |
| **Auth middleware unwired** | `AuthMiddleware` exists but not in pipeline |
| **Progress persistence incomplete** | Migration exists; no repository/service/command |
| **No MySQL CI service** | Cannot run integration tests in CI |
| **No OpenAPI spec** | No machine-readable API contract |
| **Auth transport undecided** | ADR for auth transport not yet written (not ADR-022 — reserved for Core/Filesystem) |

This plan defines **6 workstreams (P0-A through P0-F + P1)** to achieve founder-ready status.

---

## Settled Decisions (Not Open Questions)

The following decisions are **settled** and must be implemented as specified:

| # | Decision | Reference |
|---|----------|-----------|
| 1 | Enrollment cancellation preserves history. Re-enrollment creates a new enrollment record with new ID. | AGENTS.md §6.1 |
| 2 | Course completion requires **all required modules**. Optional modules do not block completion. | AGENTS.md §6.1 |
| 3 | Empty or optional-only course versions do **not** auto-complete. | AGENTS.md §6.1 |
| 4 | Existing enrollments remain attached to their enrolled course version. New versions apply to new enrollments. | AGENTS.md §6.1 |
| 5 | Course requirements must be versioned and immutable for existing enrollments. | AGENTS.md §6.1 |
| 6 | Founder owns frontend/UI. Backend provides stable, documented API contracts. | AGENTS.md §6.1 |
| 7 | **ADR-022 is reserved for Core/Filesystem.** The auth transport decision requires a **new ADR** (not ADR-022). | User correction |
| 8 | **Immutable course versions** with requirements snapshot. Enrollment references enrolled version; server determines published version. | User correction |
| 9 | Progress concurrency: **transactional locking** (pessimistic) via `SELECT ... FOR UPDATE`. Proven with real-MySQL `pcntl_fork` tests. | User correction |
| 10 | Preserve enrollment history and progress. No destructive rollback or dropping existing LMS tables/data. | User correction |
| 11 | **MySQL-compatible active-enrollment uniqueness**: at most one active enrollment per `(learner_id, course_id)`, regardless of course version. Use generated nullable column + unique index. | User correction |
| 11 | Server determines published course version during enrollment. Client does not select version. | User correction |
| 12 | Re-enrollment creates new record + ID; old record preserved. Server resolves current published version. | User correction |
| 12 | Immutable course-version data includes **module membership + learning content** (not just required module IDs). | User correction |
| 12 | Completion = all required modules completed. Optional modules don't block. Empty/optional-only versions don't auto-complete. | User correction |
| 13 | Split **API contract design** (DTOs, OpenAPI, error schema) from **endpoint implementation** (controllers, routing). | User correction |
| 14 | Inspect Vanguard middleware lifecycle before specifying auth ordering. Reuse existing error/validation conventions. | User correction |
| 13 | No custom OpenAPI generator or second CLI mechanism without evidence existing conventions can't support it. | User correction |
| 14 | Verify `make setup`, `CLI/seed.php`, `CLI/migrate.php` paths against current repo. | User correction |
| 15 | Keep Core/Filesystem, CLI consolidation, architecture restructuring outside LMS remediation. | User correction |

---

## Workstream Specifications

---

### P0-A — Course Lifecycle and Versioning

#### Files/Classes to Add/Modify

| File | Status | Action |
|------|--------|--------|
| `Spokes/Internal/Lms/Domain/Entity/Course.php` | Modify | Add `version` (int, starts at 1), `requirementsSnapshot` (JSON: required module IDs + module membership + learning content) |
| `Spokes/Internal/Lms/Domain/Entity/CourseVersion.php` | **New** | Immutable VO: `version`, `requirementsSnapshot` (requiredModuleIds[], moduleMembership[], learningContent[]), `createdAt` |
| `Spokes/Internal/Lms/Domain/Repository/CourseRepositoryInterface.php` | Modify | Add `findPublished()`, `findVersion()`, `saveVersion()` |
| `Spokes/Internal/Lms/Infrastructure/Persistence/MySQLCourseRepository.php` | Modify | Implement versioned persistence; `saveVersion()` inserts row in `lms_course_versions` |
| `Spokes/Internal/Lms/migrations/006_create_course_versions_table.sql` | **New** | See schema below |
| `Spokes/Internal/Lms/Application/Command/CreateCourseCommand.php` | Modify | Accept initial `requirementsSnapshot` (module membership + learning content) |
| `Spokes/Internal/Lms/Application/Command/PublishCourseCommand.php` | **New** | `courseId`, `requirementsSnapshot` → creates `CourseVersion(1)`, publishes |
| `Spokes/Internal/Lms/Application/Command/UpdateCourseCommand.php` | **New** | Creates new `CourseVersion` (incremented) on publish |
| `Spokes/Internal/Lms/Application/Command/ArchiveCourseCommand.php` | **New** | Soft-delete (status = Archived) |
| `Spokes/Internal/Lms/Application/Service/CourseApplicationService.php` | Modify | Versioning logic; `publish()` creates v1, `update()` creates new version |
| `Spokes/Internal/Lms/Application/Service/EnrollmentApplicationService.php` | Modify | `enroll()` captures `courseId` + **server-resolved** `courseVersion` |

#### Migration: `006_create_course_versions_table.sql`

```sql
CREATE TABLE lms_course_versions (
    course_id BIGINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL,
    requirements_snapshot JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (course_id, version),
    FOREIGN KEY (course_id) REFERENCES lms_courses(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**`requirements_snapshot` JSON structure:**

```json
{
  "requiredModuleIds": ["01HX...", "01HY..."],
  "moduleMembership": [
    {"moduleId": "01HX...", "sortOrder": 1, "isRequired": true},
    {"moduleId": "01HY...", "sortOrder": 2, "isRequired": false}
  ],
  "learningContent": {
    "01HX...": {"title": "Module 1", "content": "..."},
    "01HY...": {"title": "Module 2", "content": "..."}
  }
}
```

#### Version Semantics

| Operation | Behavior |
|-----------|----------|
| Draft editing | Mutates `lms_courses` row directly (not versioned) |
| Publish | Creates `CourseVersion(1)` with requirements snapshot; sets status = Published |
| Update Published course | Creates `CourseVersion(2+)` with new snapshot; old versions immutable |
| Archive | Soft-delete course row; course versions preserved |
| Existing enrollment | Retains original `courseVersion`; unaffected by new versions |

#### Acceptance Criteria
- [ ] `POST /api/v1/courses` creates course (Draft, v1, empty requirements)
- [ ] `POST /api/v1/courses/{id}/publish` creates v1 with requirements snapshot, sets Published
- [ ] `PUT /api/v1/courses/{id}` on Published creates v2+, new snapshot
- [ ] `POST /api/v1/enrollments` stores `courseId` + **server-resolved** `courseVersion`
- [ ] `GET /api/v1/courses/{id}/versions` returns version history
- [ ] Existing enrollments retain original `courseVersion` after new versions published

#### Dependencies
- **Blocked by:** P0-D (Auth transport decision) for HTTP endpoints
- **Independent of:** P0-B, P0-C, P0-E, P0-F (domain logic only)

#### Tests Required
| Test | Type |
|------|------|
| `CourseVersionValueObjectTest` | Unit |
| `CourseApplicationServicePublishTest` | Unit |
| `CourseApplicationServiceUpdateCreatesNewVersionTest` | Unit |
| `EnrollmentCapturesCourseVersionTest` | Unit |
| `CourseVersionHistoryIntegrationTest` | Integration (MySQL) |

#### Risks & Rollback
- **Rollback:** `DROP TABLE lms_course_versions` + revert `Course` entity (forward-only corrective migration; never drop `lms_courses` or `lms_enrollments`)
- **Risk:** Version conflicts during concurrent publishes → optimistic locking via `version` column

#### Effort Estimate: **~5 developer-days**

---

### P0-B — Enrollment API

#### Files/Classes to Add/Modify

| File | Status | Action |
|------|--------|--------|
| `Spokes/Internal/Lms/Application/Command/EnrollCommand.php` | Modify | Add `courseId` (required); **server resolves** `courseVersion` |
| `Spokes/Internal/Lms/Application/Command/CancelEnrollmentCommand.php` | Modify | Ensure `enrollmentId` + `learnerId` for authorization |
| `Spokes/Internal/Lms/Application/Service/EnrollmentApplicationService.php` | Modify | `enroll()` idempotency key; `cancel()` soft-delete; `reEnroll()` creates new record with new ID |
| `Spokes/Internal/Lms/Infrastructure/Persistence/MySQLEnrollmentRepository.php` | Modify | Add `findActiveByLearnerAndCourse()`, `findByIdWithLock()` |
| `Spokes/Internal/Lms/Infrastructure/Persistence/MySQLCourseRepository.php` | Modify | Add `findPublishedById()` |
| `Spokes/Internal/Lms/Application/Command/ReEnrollCommand.php` | **New** | `enrollmentId`, `learnerId` → new enrollment, new ID, server resolves current published version |

#### Database Constraints (Migration 007)

**MySQL-compatible active-enrollment uniqueness** — enforces at most one active enrollment per `(learner_id, course_id)`, regardless of course version:

```sql
-- Generated nullable column: NULL when not active, so unique index only
-- constrains active enrollments
ALTER TABLE lms_enrollments
ADD COLUMN _active_flag SMALLINT GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN 1 ELSE NULL END) VIRTUAL,
ADD UNIQUE INDEX uq_active_learner_course (learner_id, course_id, _active_flag);
```

**Concurrency behavior:** `SELECT ... FOR UPDATE` on `lms_enrollments` row for the `(learner_id, course_id)` key before insert. Concurrent `enroll()` calls for the same learner+course serialize on the row lock; only one succeeds; subsequent calls find existing active enrollment and return it (idempotent).

#### Acceptance Criteria
- [ ] `POST /api/v1/enrollments` with JWT → creates enrollment (Published course only)
- [ ] Duplicate `POST` with same `courseId` + `learnerId` → returns existing enrollment (idempotent)
- [ ] `DELETE /api/v1/enrollments/{id}` → soft-delete (status = Cancelled)
- [ ] `POST /api/v1/enrollments/{id}/reenroll` → new enrollment, new ID, preserves history
- [ ] Concurrent enrollments for same learner/course → only one succeeds (DB constraint + row lock)
- [ ] Cancelled enrollment excluded from active enrollment queries

#### Dependencies
- **Requires:** P0-D (Auth), P0-E (Routes/DTOs/Errors)
- **Independent of:** P0-A (versioning), P0-C (progress)

#### Tests Required
| Test | Type |
|------|------|
| `EnrollCommandIdempotencyTest` | Unit |
| `EnrollmentApplicationServiceCancelTest` | Unit |
| `EnrollmentApplicationServiceReEnrollTest` | Unit |
| `EnrollmentConcurrencyIntegrationTest` | Integration (MySQL, `pcntl_fork`) |
| `EnrollmentApiContractTest` | Integration (HTTP) |

#### Effort Estimate: **~4 developer-days**

---

### P0-C — Progress Persistence

#### Files/Classes to Add/Modify (Port from `lms-progress-persistence` branch → `Spokes/Internal/Lms/`)

| File | Status | Action |
|------|--------|--------|
| `Spokes/Internal/Lms/Domain/Entity/Progress.php` | Modify | `transition()` enforces `not_started → in_progress → completed`; sets `startedAt`/`completedAt` |
| `Spokes/Internal/Lms/Domain/Repository/ProgressRepositoryInterface.php` | **New** | Port: `get()`, `getForUpdate()`, `findByEnrollment()`, `insert()`, `save()`, `delete()` |
| `Spokes/Internal/Lms/Infrastructure/Persistence/MySQLProgressRepository.php` | **New** | Port: `SELECT ... FOR UPDATE`, atomic upsert, `NULL started_at` validation |
| `Spokes/Internal/Lms/Application/Interface/ProgressApplicationInterface.php` | **New** | Port from branch |
| `Spokes/Internal/Lms/Application/Service/ProgressApplicationService.php` | **New** | Port: validation, authorization, transaction handling |
| `Spokes/Internal/Lms/Application/Command/RecordProgressCommand.php` | **New** | `enrollmentId`, `moduleId`, `newStatus` (no `recordedBy`) |
| `Spokes/Internal/Lms/Application/Service/CourseApplicationService.php` | Modify | Add `isCourseComplete(enrollmentId)` using progress repo |

#### Migration
Already exists: `004_create_progress_table.sql` (verified on main)

#### Concurrency Model
**Transactional locking (pessimistic)** via `SELECT ... FOR UPDATE` on `lms_progress` row. Proven with real-MySQL `pcntl_fork` integration test.

#### Acceptance Criteria
- [ ] `POST /api/v1/progress` with JWT → records progress transition
- [ ] State machine: `not_started → in_progress → completed` (no backward)
- [ ] Same-state requests idempotent
- [ ] Completed progress cannot regress
- [ ] Validation: active enrollment, published course, module in course, authorization
- [ ] **Concurrency:** `ProgressConcurrencyIntegrationTest` passes on real MySQL (`pcntl_fork`)

#### Dependencies
- **Requires:** P0-B (Enrollment FK), P0-D (Auth), P0-E (Routes)
- **Port from:** `lms-progress-persistence` branch (path migration: `packages/spoke/internal/lms/` → `Spokes/Internal/Lms/`)

#### Tests Required
| Test | Type |
|------|------|
| `ProgressEntityTransitionTest` | Unit (25 cases) |
| `ProgressApplicationServiceValidationTest` | Unit |
| `ProgressApplicationServiceAuthorizationTest` | Unit |
| `ProgressConcurrencyIntegrationTest` | **Integration (MySQL, `pcntl_fork`)** — **MUST RUN ON REAL MYSQL** |
| `ProgressApiContractTest` | Integration (HTTP) |

#### Effort Estimate: **~6 developer-days** (includes porting + concurrency hardening)

---

### P0-D — Authentication and Authorization

#### Current State
- `Hub/Identity/Http/AuthMiddleware.php` exists (JWT ES256, ADR-003)
- `JwtVerifier` + `JwtSigner` exist (ES256, RFC 7518)
- **NOT WIRED** in `ApplicationFactory.php`
- `AuthMiddleware::stampRequestContext()` is no-op

#### Auth Transport ADR
**NOT YET WRITTEN** — blocks all HTTP work. **ADR-022 is reserved for Core/Filesystem.** Create **new ADR** for auth transport.

**Proposed Decision (for Owner):**
| Option | Pros | Cons |
|--------|------|------|
| **Session cookies (same-origin)** | CSRF protection built-in; HttpOnly; no token storage | Same-origin only; CORS complexity |
| **JWT Bearer** | Stateless; mobile-friendly | Token storage/XSS risk; refresh token complexity |
| **Both** | Flexibility | Complexity |

**Recommendation:** Session cookies (same-origin) for LMS web app; JWT for API clients.

#### Files/Classes to Add/Modify

| File | Status | Action |
|------|--------|--------|
| `Architecture/ADRs/ADR-XXX-auth-transport.md` | **New** | Document decision (session cookies + JWT for API) |
| `Hub/Identity/Http/AuthMiddleware.php` | Modify | Implement `stampRequestContext()` → `RequestContext::stampUserId()` |
| `app/ApplicationFactory.php` | Modify | Register `AuthMiddleware` in Vanguard pipeline (after contract lookup, before controllers) |
| `Hub/Identity/Http/JwtVerifier.php` | Verify | Confirm ES256 validation, `sub` claim → `UserId` |
| `Hub/Identity/Http/AuthMiddleware.php` | Modify | Add session cookie parsing + JWT bearer support (dual) |

#### Vanguard Middleware Integration
Inspect `Bridge/Vanguard/Vanguard.php` — JWT verification is currently a pass-through stub at Step 1. AuthMiddleware should be inserted **after Vanguard's contract lookup (Step 4)** but before controller execution. The Vanguard chain order: contract lookup → auth → downstream pipeline.

Reuse existing error-handling: Vanguard uses `ResponseFactory` + PSR-3 logging. `ErrorHandlerMiddleware` from Bridge handles exceptions → standard error format.

#### Acceptance Criteria
- [ ] Auth transport ADR written and approved
- [ ] `AuthMiddleware` in Vanguard pipeline (after contract lookup, before controllers)
- [ ] `RequestContext::userId()` returns authenticated `UserId` in controllers
- [ ] `AuthMiddleware` validates JWT ES256 + session cookie; extracts `sub` → `UserId`
- [ ] Unauthenticated requests → 401 with standard error format
- [ ] Role checks work: `lms:learner`, `lms:instructor`, `lms:admin`, `platform:admin`

#### Owner Decision Required
**Auth transport ADR** — Must be created and approved before any HTTP implementation. This is the **sole remaining blocking owner decision.**

#### Effort Estimate: **~2 developer-days** (after ADR decision)

---

### P0-E — API Contract (Design Only — Separate from Implementation)

#### Files/Classes to Add/Modify

| File | Status | Action |
|------|--------|--------|
| `Spokes/Internal/Lms/Application/Dto/` | **New Directory** | Request/Response DTOs |
| `.../Dto/CourseDto.php` | **New** | Course response DTO |
| `.../Dto/ModuleDto.php` | **New** | Module response DTO |
| `.../Dto/EnrollmentDto.php` | **New** | Enrollment response DTO |
| `.../Dto/ProgressDto.php` | **New** | Progress response DTO |
| `.../Dto/ErrorResponse.php` | **New** | Standard error envelope |
| `.../Dto/PaginatedResponse.php` | **New** | Pagination envelope |
| `Spokes/Internal/Lms/Http/Controller/` | **New Directory** | Controllers (implementation in P0-E-impl) |
| `.../Controller/CourseController.php` | **New** | REST endpoints |
| `.../Controller/EnrollmentController.php` | **New** | REST endpoints |
| `.../Controller/ProgressController.php` | **New** | REST endpoints |
| `.../Controller/ModuleController.php` | **New** | REST endpoints |
| `.../Middleware/ValidationMiddleware.php` | **New** | Request validation (DTO → exceptions) |
| `.../Middleware/ErrorHandlerMiddleware.php` | **New** | Maps exceptions → `ErrorResponse` |
| `openapi.yaml` | **New** | OpenAPI 3.1 spec |
| `.github/workflows/openapi-validation.yml` | **New** | CI validation job |

#### Route Map (Design)
| Method | Path | Controller | Auth |
|--------|------|------------|------|
| POST | `/api/v1/courses` | `CourseController::create` | `lms:admin` |
| GET | `/api/v1/courses` | `CourseController::list` | `lms:learner` |
| GET | `/api/v1/courses/{id}` | `CourseController::get` | `lms:learner` |
| PUT | `/api/v1/courses/{id}` | `CourseController::update` | `lms:admin` |
| POST | `/api/v1/courses/{id}/publish` | `CourseController::publish` | `lms:admin` |
| POST | `/api/v1/courses/{id}/archive` | `CourseController::archive` | `lms:admin` |
| GET | `/api/v1/courses/{id}/versions` | `CourseController::versions` | `lms:learner` |
| POST | `/api/v1/enrollments` | `EnrollmentController::enroll` | `lms:learner` |
| DELETE | `/api/v1/enrollments/{id}` | `EnrollmentController::cancel` | `lms:learner` |
| POST | `/api/v1/enrollments/{id}/reenroll` | `EnrollmentController::reenroll` | `lms:learner` |
| POST | `/api/v1/progress` | `ProgressController::record` | `lms:learner` |
| GET | `/api/v1/enrollments/{id}/progress` | `ProgressController::get` | `lms:learner` |
| GET | `/api/v1/courses/{id}/modules` | `ModuleController::list` | `lms:learner` |

#### Error Format
```json
{
  "error": {
    "code": "ENROLLMENT_CONFLICT",
    "message": "Active enrollment already exists for this course",
    "details": { "enrollmentId": "01HX..." }
  }
}
```

#### OpenAPI Spec
- `openapi.yaml` at repo root (or `Docs/openapi.yaml`)
- CI validation job validates spec against implementation

#### Acceptance Criteria
- [ ] All endpoints return 200/201/400/401/403/404/409/422/500 with standard envelope
- [ ] Request validation → 422 with field-level errors
- [ ] Pagination: `?page=1&per_page=20` → `PaginatedResponse`
- [ ] OpenAPI spec at `/openapi.yaml` validates via CI
- [ ] CI job validates spec against implementation

#### Dependencies
- **Requires:** P0-A, P0-B, P0-C, P0-D (all HTTP endpoints)
- **Separated from:** P0-E-impl (endpoint implementation)

#### Tests Required
| Test | Type |
|------|------|
| `OpenApiValidationTest` | CI (spec vs implementation) |
| `CourseApiContractTest` | Integration (HTTP) |
| `EnrollmentApiContractTest` | Integration (HTTP) |
| `ProgressApiContractTest` | Integration (HTTP) |
| `ErrorFormatContractTest` | Integration (HTTP) |
| `PaginationContractTest` | Integration (HTTP) |

#### Effort Estimate: **~8 developer-days** (design + implementation separated)

---

### P0-F — MySQL and CI

#### Files/Classes to Add/Modify

| File | Status | Action |
|------|--------|--------|
| `.github/workflows/ci.yml` | Modify | Add MySQL 8.4 service to `component-tests`, `integration-tests` |
| `phpunit.xml.dist` (root) | Modify | Add `tests/Integration` testsuite |
| `tests/Integration/` | **New Directory** | Integration test bootstrap |
| `tests/Integration/Bootstrap.php` | **New** | Creates test DB, runs migrations |
| `tests/Integration/MigrationRunnerIntegrationTest.php` | **New** | Fresh install + idempotent re-run |
| `tests/Integration/ProgressConcurrencyIntegrationTest.php` | **New** | `pcntl_fork` concurrent transitions |
| `tests/Integration/EnrollmentConcurrencyIntegrationTest.php` | **New** | `pcntl_fork` concurrent enrollments |
| `tests/Integration/ApiJourneyIntegrationTest.php` | **New** | Full learner/admin journey |
| `scripts/generate-component-manifest.py` | Modify | Include migration runner component |

#### CI Configuration (No Hardcoded Credentials)

**Do NOT hardcode database credentials in workflow files. Use environment variables:**

```yaml
# .github/workflows/ci.yml additions
services:
  mysql:
    image: mysql:8.4
    env:
      MYSQL_DATABASE: ${{ secrets.MYSQL_DATABASE || 'dglab_test' }}
      MYSQL_USER: ${{ secrets.MYSQL_USER || 'dglab_test' }}
      MYSQL_PASSWORD: ${{ secrets.MYSQL_PASSWORD || 'test_password' }}
      MYSQL_ROOT_PASSWORD: ${{ secrets.MYSQL_ROOT_PASSWORD || 'root' }}
    ports: ["3306:3306"]
    options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
```

**PHP `pcntl` verification:** CI job must first run `php -m \| grep pcntl`; if unavailable, concurrency tests are skipped (not failed) and documented as environment-dependent.

#### Acceptance Criteria
- [ ] CI pipeline provisions MySQL 8.4 service via environment variables (no hardcoded credentials)
- [ ] `DoctrineMigrationRunner` tests pass (fresh install + idempotent re-run)
- [ ] `ProgressConcurrencyIntegrationTest` passes (real MySQL, `pcntl_fork`) — skipped if `pcntl` unavailable
- [ ] `EnrollmentConcurrencyIntegrationTest` passes (real MySQL)
- [ ] `ApiJourneyIntegrationTest` passes (full learner/admin journey)
- [ ] All component tests pass with MySQL service

#### Effort Estimate: **~4 developer-days**

---

### P1 — Founder Onboarding

| File | Status | Action |
|------|--------|--------|
| `Docs/LMS/SETUP.md` | **New** | Local setup: PHP 8.4, Composer, MySQL, `composer install`, `php CLI/migrate.php` |
| `Docs/LMS/API-EXAMPLES.md` | **New** | cURL examples for all endpoints + auth |
| `Docs/LMS/AUTH-GUIDE.md` | **New** | JWT/session auth flow, token refresh |
| `Docs/LMS/SCHEMA.md` | **New** | ER diagram, table descriptions, migration guide |
| `Docs/LMS/SEED.md` | **New** | `php CLI/seed.php` (demo courses, modules, learners) |
| `Docs/LMS/FRONTEND-INTEGRATION.md` | **New** | CORS, auth flow, error handling, pagination |
| `Docs/LMS/KNOWN-LIMITATIONS.md` | **New** | Explicitly document gaps |
| `CLI/seed.php` | **New** | Seeder command |

#### Acceptance Criteria
- [ ] New contributor: `make setup && php CLI/seed.php && php CLI/migrate.php migrate` → working LMS
- [ ] All API examples in `API-EXAMPLES.md` execute successfully
- [ ] `KNOWN-LIMITATIONS.md` honestly documents gaps
- [ ] Frontend developer integrates auth + course listing in < 30 min

#### Dependencies
- **Requires:** P0-A through P0-E complete
- **Can start:** Documentation drafting in parallel

#### Effort Estimate: **~3 developer-days**

---

## Dependency Graph & Implementation Sequence

```
                    ┌─────────────────┐
                    │  Auth Transport ADR │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              ▼              ▼              ▼
        ┌───────────┐  ┌───────────┐  ┌───────────┐
        │   P0-F    │  │   P0-A    │  │   P1      │
        │  MySQL CI │  │ Course    │  │  Onboard  │
        │  Foundation│  │ Versioning│  │  (draft)  │
        └─────┬─────┘  └─────┬─────┘  └─────┬─────┘
              │               │              │
              └───────────────┴──────────────┘
                      │              │
                      ▼              ▼
              ┌─────────────────────────────┐
              │       P0-B                    │
              │   Enrollment API              │
              │   (depends on P0-A)           │
              └──────────────┬──────────────┘
                             │
                             ▼
                    ┌─────────────────────┐
                    │       P0-C          │
                    │ Progress Persistence│
                    │ (depends on P0-B)   │
                    └──────────┬────────┘
                               │
                               ▼
                    ┌─────────────────────┐
                    │      P0-D           │
                    │ Auth & Authorization│
                    │ (after ADR-XXX)     │
                    └──────────┬────────┘
                               │
                               ▼
                    ┌─────────────────────┐
                    │       P0-E          │
                    │   API Contracts     │
                    │   (design + impl)   │
                    └──────────┬────────┘
                               │
                               ▼
                    ┌─────────────────────┐
                    │   E2E Integration   │
                    │   Tests             │
                    └─────────────────────┘
```

**Key sequencing notes:**
1. **API contract design (P0-E design)** may proceed in parallel with domain implementation (P0-A/B/C)
2. **Endpoint implementation (P0-E impl)** must use completed domain services
3. Auth middleware wiring (P0-D) happens after domain services are ready but before endpoint implementation

---

## Milestone Plan with Completion Gates

| Milestone | Workstreams | Gate Criteria | Target |
|-----------|-------------|---------------|--------|
| **M0: Foundation** | Auth ADR decision, P0-F (CI) | Auth ADR approved; CI runs MySQL 8.4 | Week 1 |
| **M1: Domain Core** | P0-A, P0-B, P0-C | All domain services + repos + tests pass | Week 2-3 |
| **M2: API Contracts** | P0-E (design) | All DTOs, OpenAPI valid, error schema defined | Week 3 |
| **M3: API Implementation** | P0-E (impl) + P0-D wiring | Endpoints return 200/401/422; OpenAPI valid | Week 4 |
| **M4: Integration** | All P0 + P0-F | E2E journey passes; concurrency tests pass | Week 5 |
| **M5: Onboarding** | P1 | Docs complete; seed works; founder onboards | Week 5-6 |

---

## Minimum Backend Slice (First Frontend Milestone)

**Frontend can start when:**

1. **Auth ADR approved** (session cookies + JWT for API)
2. **AuthMiddleware wired** in Vanguard pipeline (after contract lookup)
3. **Course discovery** (`GET /api/v1/courses?status=published`, `GET /api/v1/courses/{id}`)
3. **Module listing** (`GET /api/v1/courses/{id}/modules`)
4. **Enrollment API** (`POST /enrollments`, `DELETE /enrollments/{id}`, `POST /reenroll`)
4. **Progress recording** (`POST /progress`, `GET /enrollments/{id}/progress`)
5. **OpenAPI spec** at `/openapi.yaml` passing CI validation
5. **MySQL CI service** running in GitHub Actions
5. **Stable response/error schemas** + pagination

*This slice allows: course catalog, enrollment flow, progress tracking.*

---

## Founder-Ready Definition

The LMS is **founder-ready** when a new frontend developer can:

1. **Clone repo** → `make setup` → working backend in < 10 min
2. **Run `php CLI/seed.php && php CLI/migrate.php migrate`** → seeded database
3. **Read `Docs/LMS/API-EXAMPLES.md`** → copy-paste cURL commands → all work
4. **Read `Docs/LMS/AUTH-GUIDE.md`** → implement login + token refresh in < 30 min
5. **Build course catalog page** → `GET /api/v1/courses?status=published` works
5. **Build enrollment flow** → `POST /api/v1/enrollments` works idempotently
6. **Build progress tracker** → `POST/GET /api/v1/progress` works with state machine
6. **Read `Docs/LMS/KNOWN-LIMITATIONS.md`** → knows exactly what's not done

**All 8 steps must work without backend changes.**

---

## Genuine Owner Decisions Required

| # | Decision | Blocking | Deadline |
|---|----------|----------|----------|
| 1 | **Auth transport ADR** (session cookies + JWT for API) | P0-D, P0-E, P0-B, P0-C, P0-A | Before M0 |
| 2 | **Rate limiter scope** (per-IP, per-user, per-endpoint) | P1 (Pass 2) | Before P1 |
| 3 | **Seed data scope** (minimal vs comprehensive) | P1 | Before M3.5 |

---

## Effort Summary

| Workstream | Estimate | Can Parallelize |
|------------|----------|-----------------|
| P0-A Course Lifecycle | ~5 dev-days | After M0 |
| P0-B Enrollment API | ~4 dev-days | After M0 |
| P0-C Progress Persistence | ~6 dev-days | After M0 |
| P0-D Auth & Authorization | ~2 dev-days | **M0 gate** |
| P0-E API Contract | ~8 dev-days | After M1 |
| P0-F MySQL & CI | ~4 dev-days | **M0 gate** (parallel) |
| P1 Founder Onboarding | ~3 dev-days | After M2 |
| **Total** | **~32 dev-days** | ~5-6 weeks |

---

## Verification Commands (to be run after implementation)

```bash
# 1. Verify auth middleware wired
php -r "require 'vendor/autoload.php'; (new App\ApplicationFactory())->create(); echo 'OK';"

# 2. Verify migration command
php CLI/migrate.php status

# 3. Verify OpenAPI spec
php -r "require 'vendor/autoload.php'; echo file_exists('openapi.yaml') ? 'OK' : 'MISSING';"

# 4. Run integration tests (requires MySQL)
php vendor/bin/phpunit --testsuite integration

# 5. Verify seed + migrate
php CLI/seed.php && php CLI/migrate.php migrate

# 6. Run component tests
php vendor/bin/phpunit
```

---

## Plan File Location

Plan saved at: `/home/dgci/www/DGLab/.kilo/plans/1793443157576-lms-founder-ready-plan.md`
