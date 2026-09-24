# Showcase + LMS Implementation Plan

**Status:** Contractor-ready plan, derived from the SPEC-001 architecture baseline.
**Verified against:** DGLab HEAD `8d05893` (origin/main) + the 3 in-flight PRs (#261, #262, #263).
**Scope basis:** User-confirmed answers to 6 architecture/scope questions.

---

## 1. Confirmed Scope

### 1.1 Architecture decisions (all confirmed)

| Decision | Choice | Implication |
|---|---|---|
| Where products live | **Hub bounded contexts** inside DGLab monorepo | `packages/hub/showcase/`, `packages/hub/lms/` — fits SPEC §16 Hub target structure; M09 domain purity applies; repository ports inward-facing |
| How to sequence vs current M0-M7 | **Extend M5** | Showcase = M5a, LMS = M5b. SPEC M5 already says "select one representative business capability" — these ARE the capabilities. Continues M4 (release gate) in parallel. |
| First vertical slice | **Showcase first** | M5a = Showcase Product Catalog (simpler CRUD domain). M5b = LMS Course Catalog (richer domain). Both eventually land. |
| Boundary between products | **Shared platform** | Shared Hub contexts (Identity, Audit, Config) consumed by both products; separate bounded contexts per product (Showcase Hub, LMS Hub). Single shared database; FKs enforce cross-product integrity (e.g., wishlist.user_id + enrollments.learner_id both reference the same `users` table). See §6 trade-off notes. |

### 1.2 Showcase MVP features (must-have)

1. **Catalog CRUD** — product list + product detail pages (read-only for end users); admin CRUD for products
2. **Search** — basic text matching on product name/description (MySQL FULLTEXT or LIKE-based)
3. **User accounts + wishlist** — authenticated user accounts + per-user wishlist
4. **Asset management** — multi-asset management (product images, video, 3D models)
5. **Admin dashboard** — admin dashboard for product CRUD + inventory management

### 1.3 LMS MVP features (must-have)

1. **Course catalog** — course list + course detail pages (read-only for end users); admin CRUD for courses
2. **Course player** — course content player (video hosting, text content, embedded content)
3. **Enrollment** — enrollment flow (free or paid)
4. **Admin dashboard** — admin dashboard for course CRUD + enrollment management + learner analytics
5. **Progress tracking** — learner progress tracking per course + per module

---

## 2. Dependency Graph

```
                       ┌────────────────────────────────┐
                       │  EXISTING (already implemented) │
                       └────────────────────────────────┘
                                       │
        ┌──────────────────────────────┼──────────────────────────────┐
        ▼                              ▼                              ▼
   CORE-19 DBAL ✅              CORE-16 Crypto ✅              CORE-18 Kernel ✅
   (Connection,                 (AES-256-GCM,                   (lifecycle, state machine,
    QueryBuilder,                Argon2id,                        RequestContext value object
    Transaction,                 HKDF, sodium_memzero)            from PR #262)
    TenantContext)
        │
        │  consumed by
        ▼
   Hub Application Services (per SPEC §20 transaction boundary)

                       ┌────────────────────────────────┐
                       │   BLOCKING (must land first)   │
                       └────────────────────────────────┘
                                       │
                ┌──────────────────────┴──────────────────────┐
                ▼                                             ▼
          HUB-04 Identity                            CORE-14 Filesystem
          (blueprint, NOT YET                        (blueprint, NOT YET
           implemented)                              implemented)
                │                                             │
                │  blocks                                      │  blocks
                ▼                                             ▼
   Showcase "User accounts + wishlist"        Showcase "Asset management"
   LMS "Enrollment"                           LMS "Course player"
                                              LMS "Progress tracking" (depends on Course player)
```

### 2.1 Phase 1 — Unblock dependencies

Two parallel Hub/Core packages must land before the Showcase/LMS feature work can begin.

#### PR #264 — `packages/hub/identity/` (HUB-04)

Implements minimal user authentication. Per "Shared platform" boundary, this is a SHARED Hub package — both Showcase and LMS consume the same `packages/hub/identity/` code and the same `users` table. A single user account works across both products.

**File structure:**
```
packages/hub/identity/
├── src/
│   ├── Domain/
│   │   ├── Entity/
│   │   │   └── User.php
│   │   ├── ValueObject/
│   │   │   ├── UserId.php
│   │   │   ├── Email.php
│   │   │   └── PasswordHash.php        (uses CORE-16 Argon2id per ADR-008)
│   │   ├── Exception/
│   │   │   ├── UserNotFoundException.php
│   │   │   ├── InvalidCredentialsException.php      (class: Permanent-Local per SPEC §25)
│   │   │   └── EmailAlreadyRegisteredException.php  (class: Permanent-Local)
│   │   └── Repository/
│   │       └── UserRepositoryInterface.php
│   ├── Application/
│   │   ├── Command/
│   │   │   ├── RegisterUserCommand.php
│   │   │   ├── AuthenticateUserCommand.php
│   │   │   └── RefreshUserSessionCommand.php
│   │   ├── Query/
│   │   │   └── GetUserByIdQuery.php
│   │   └── Service/
│   │       └── IdentityApplicationService.php   (owns transaction boundary per SPEC §20)
│   └── Infrastructure/
│       └── Persistence/
│           └── MySQLUserRepository.php         (implements UserRepositoryInterface)
├── tests/
│   ├── Unit/
│   │   ├── Domain/UserTest.php
│   │   ├── ValueObject/{UserId,Email,PasswordHash}Test.php
│   │   └── Application/IdentityApplicationServiceTest.php
│   └── Integration/
│       └── Persistence/MySQLUserRepositoryTest.php
├── composer.json
├── phpunit.xml.dist
└── phpstan.neon
```

**Migrations:**
- `users` table: `id CHAR(26) PK` (ULID per ADR-009), `email VARCHAR(255) UNIQUE`, `password_hash VARCHAR(255)` (Argon2id), `created_at TIMESTAMP(6)`, `updated_at TIMESTAMP(6)`

**Approximate scope:** 18 PHP files, 1 migration, ~25 tests.

#### PR #265 — `packages/core/filesystem/` (CORE-14)

Implements minimal local filesystem adapter with the nuclear-grade hardening per doctrine §4.3 (referenced in SPEC §58 "Do Not Rebuild Existing Foundations"). Per the doctrine: atomic writes, path-traversal hardening, quarantine.

**File structure:**
```
packages/core/filesystem/
├── src/
│   ├── FilesystemInterface.php
│   ├── Filesystem.php                     (concrete local implementation)
│   ├── FilesystemException.php            (base exception)
│   ├── PathTraversalRefusedException.php (class: Permanent-Local per SPEC §25)
│   ├── FileIntegrityCheckFailedException.php (class: Corrupt per SPEC §25)
│   ├── StreamByteLimitExceededException.php (class: Permanent-Local)
│   └── Quarantine/
│       └── Quarantine.php                (per doctrine §4.3 quarantine)
├── tests/
│   ├── Unit/
│   │   ├── FilesystemTest.php           (read/write/delete, path traversal rejection)
│   │   └── QuarantineTest.php
│   └── Integration/
│       └── AtomicWriteTest.php          (atomic-on-crash invariant)
├── composer.json
├── phpunit.xml.dist
└── phpstan.neon
```

**Approximate scope:** 12 PHP files, 0 migrations (filesystem doesn't need a database table — paths are stored in the consumer's tables), ~15 tests.

---

## 3. Phased Implementation Plan

### 3.1 Phase 1 — Unblock dependencies (parallel to current PR review)

| PR | Branch | Scope | Depends on |
|---|---|---|---|
| #264 | `feat/hub-04-identity` | `packages/hub/identity/` — minimal User entity + MySQLUserRepository + IdentityApplicationService | #261 (so architecture-boundary-lint is enforced) |
| #265 | `feat/core-14-filesystem` | `packages/core/filesystem/` — local FS adapter + atomic writes + path-traversal hardening + quarantine | #261 (same) |

Both can be built in parallel — they don't depend on each other.

### 3.2 Phase 2 — Showcase vertical slice (M5a)

| PR | Branch | Scope | Depends on |
|---|---|---|---|
| #266 | `feat/hub-showcase-domain` | `packages/hub/showcase/src/Domain/` — Product entity, value objects, Catalog aggregate, exceptions, repository interfaces + tests | Phase 1 |
| #267 | `feat/hub-showcase-application` | `packages/hub/showcase/src/Application/` — Commands (Create/Update/Delete), Queries (List/Get), ProductApplicationService (owns UoW per SPEC §20) | #266 |
| #268 | `feat/hub-showcase-infrastructure` | `packages/hub/showcase/src/Infrastructure/` — MySQLProductRepository, MySQLAssetRepository, MySQLWishlistRepository + integration tests | #266, #267 |
| #269 | `feat/showcase-routes` | `app/Controller/ProductController.php`, `app/Controller/AdminProductController.php`, routes for `/products`, `/products/{id}`, `/admin/products` | #268 (wiring via ApplicationFactory from PR #263) |
| #270 | `feat/showcase-asset-mgmt` | Showcase asset upload/serve routes + multi-asset management | #268, #265 (CORE-14 Filesystem) |
| #271 | `feat/showcase-user-wishlist` | Showcase user accounts + wishlist routes (login, register, wishlist) | #268, #264 (HUB-04 Identity) |
| #272 | `feat/showcase-search` | Showcase search service + search route (MySQL FULLTEXT) | #268 |
| #273 | `feat/showcase-admin-dashboard` | Showcase admin dashboard (UI routes + admin Spoke services) | #271 (admin auth required) |

### 3.3 Phase 3 — LMS vertical slice (M5b)

| PR | Branch | Scope | Depends on |
|---|---|---|---|
| #274 | `feat/hub-lms-domain` | `packages/hub/lms/src/Domain/` — Course, Module, Enrollment, Progress entities + aggregates + exceptions + repository interfaces | Phase 1 |
| #275 | `feat/hub-lms-application` | `packages/hub/lms/src/Application/` — Commands (CreateCourse, Enroll, RecordProgress), Queries, 3 application services (Course, Enrollment, Progress) | #274 |
| #276 | `feat/hub-lms-infrastructure` | `packages/hub/lms/src/Infrastructure/` — MySQLCourseRepository, MySQLEnrollmentRepository, MySQLProgressRepository + integration tests | #274, #275 |
| #277 | `feat/lms-routes` | `app/Controller/CourseController.php`, `app/Controller/AdminCourseController.php`, routes for `/courses`, `/courses/{id}`, `/admin/courses` | #276 |
| #278 | `feat/lms-course-player` | LMS course content player routes + video/text serving via CORE-14 Filesystem | #276, #265 (CORE-14 Filesystem) |
| #279 | `feat/lms-enrollment` | LMS enrollment flow (free tier initially; paid deferred — needs payment Outer Spoke) | #276, #264 (HUB-04 Identity) |
| #280 | `feat/lms-progress-tracking` | LMS progress tracking (depends on Course player — completed modules per enrollment) | #278, #276 |
| #281 | `feat/lms-admin-dashboard` | LMS admin dashboard (course CRUD + enrollment management + learner analytics) | #279 (admin auth required) |

---

## 4. File Structure — Full Detail

### 4.1 Showcase Hub (`packages/hub/showcase/`)

```
packages/hub/showcase/
├── src/
│   ├── Domain/
│   │   ├── Entity/
│   │   │   ├── Product.php
│   │   │   ├── ProductAsset.php
│   │   │   └── Wishlist.php
│   │   ├── ValueObject/
│   │   │   ├── ProductId.php
│   │   │   ├── Sku.php
│   │   │   ├── Price.php
│   │   │   ├── ProductTitle.php
│   │   │   ├── ProductSlug.php
│   │   │   ├── AssetType.php          (enum: image|video|model_3d)
│   │   │   └── SortOrder.php
│   │   ├── Aggregate/
│   │   │   └── Catalog.php            (aggregate root managing Product lifecycle)
│   │   ├── Event/
│   │   │   ├── ProductCreated.php
│   │   │   ├── ProductUpdated.php
│   │   │   ├── ProductDeleted.php
│   │   │   └── AssetAdded.php
│   │   ├── Exception/
│   │   │   ├── ProductNotFoundException.php        (class: Permanent-Local per SPEC §25)
│   │   │   ├── DuplicateSkuException.php          (class: Permanent-Local)
│   │   │   └── InvalidProductDataException.php   (class: Permanent-Local)
│   │   └── Repository/
│   │       ├── ProductRepositoryInterface.php    (port — inward-facing per SPEC §18)
│   │       ├── AssetRepositoryInterface.php
│   │       └── WishlistRepositoryInterface.php
│   ├── Application/
│   │   ├── Command/
│   │   │   ├── CreateProductCommand.php
│   │   │   ├── UpdateProductCommand.php
│   │   │   ├── DeleteProductCommand.php
│   │   │   ├── AddAssetCommand.php
│   │   │   ├── RemoveAssetCommand.php
│   │   │   ├── AddToWishlistCommand.php
│   │   │   └── RemoveFromWishlistCommand.php
│   │   ├── Query/
│   │   │   ├── ListProductsQuery.php
│   │   │   ├── GetProductQuery.php
│   │   │   ├── SearchProductsQuery.php
│   │   │   └── GetUserWishlistQuery.php
│   │   └── Service/
│   │       ├── ProductApplicationService.php     (owns transaction boundary per SPEC §20)
│   │       ├── AssetApplicationService.php
│   │       ├── SearchApplicationService.php
│   │       └── WishlistApplicationService.php
│   └── Infrastructure/
│       └── Persistence/
│           ├── MySQLProductRepository.php         (implements ProductRepositoryInterface)
│           ├── MySQLAssetRepository.php
│           └── MySQLWishlistRepository.php
├── tests/
│   ├── Unit/
│   │   ├── Domain/
│   │   │   ├── ProductTest.php
│   │   │   ├── CatalogTest.php
│   │   │   └── ValueObject/
│   │   │       ├── ProductIdTest.php
│   │   │       ├── SkuTest.php
│   │   │       └── PriceTest.php
│   │   └── Application/
│   │       └── ProductApplicationServiceTest.php
│   └── Integration/
│       └── Persistence/
│           ├── MySQLProductRepositoryTest.php
│           └── MySQLWishlistRepositoryTest.php
├── migrations/
│   ├── 001_create_products_table.sql
│   ├── 002_create_product_assets_table.sql
│   ├── 003_create_wishlists_table.sql
│   └── 004_create_product_search_index.sql
├── composer.json
├── phpunit.xml.dist
└── phpstan.neon
```

### 4.2 LMS Hub (`packages/hub/lms/`)

```
packages/hub/lms/
├── src/
│   ├── Domain/
│   │   ├── Entity/
│   │   │   ├── Course.php
│   │   │   ├── Module.php
│   │   │   ├── Enrollment.php
│   │   │   └── Progress.php
│   │   ├── ValueObject/
│   │   │   ├── CourseId.php
│   │   │   ├── CourseSlug.php
│   │   │   ├── ModuleId.php
│   │   │   ├── EnrollmentId.php
│   │   │   ├── EnrollmentStatus.php  (enum: pending|active|completed|cancelled)
│   │   │   └── ModuleStatus.php     (enum: not_started|in_progress|completed)
│   │   ├── Aggregate/
│   │   │   ├── CourseCatalog.php     (manages Course + Module lifecycle)
│   │   │   └── LearnerProgress.php  (manages Enrollment + Progress)
│   │   ├── Event/
│   │   │   ├── CourseCreated.php
│   │   │   ├── CoursePublished.php
│   │   │   ├── ModuleCompleted.php
│   │   │   ├── EnrollmentCreated.php
│   │   │   ├── EnrollmentCompleted.php
│   │   │   └── ProgressRecorded.php
│   │   ├── Exception/
│   │   │   ├── CourseNotFoundException.php       (Permanent-Local)
│   │   │   ├── ModuleNotFoundException.php       (Permanent-Local)
│   │   │   ├── EnrollmentException.php           (Permanent-Local)
│   │   │   ├── AlreadyEnrolledException.php     (Permanent-Local)
│   │   │   ├── NotEnrolledException.php         (Permanent-Local)
│   │   │   └── ProgressAlreadyRecordedException.php  (Permanent-Local)
│   │   └── Repository/
│   │       ├── CourseRepositoryInterface.php
│   │       ├── EnrollmentRepositoryInterface.php
│   │       └── ProgressRepositoryInterface.php
│   ├── Application/
│   │   ├── Command/
│   │   │   ├── CreateCourseCommand.php
│   │   │   ├── UpdateCourseCommand.php
│   │   │   ├── PublishCourseCommand.php
│   │   │   ├── AddModuleCommand.php
│   │   │   ├── EnrollCommand.php
│   │   │   ├── CancelEnrollmentCommand.php
│   │   │   └── RecordProgressCommand.php
│   │   ├── Query/
│   │   │   ├── ListCoursesQuery.php
│   │   │   ├── GetCourseQuery.php
│   │   │   ├── GetLearnerEnrollmentsQuery.php
│   │   │   └── GetLearnerProgressQuery.php
│   │   └── Service/
│   │       ├── CourseApplicationService.php
│   │       ├── EnrollmentApplicationService.php
│   │       └── ProgressApplicationService.php
│   └── Infrastructure/
│       └── Persistence/
│           ├── MySQLCourseRepository.php
│           ├── MySQLEnrollmentRepository.php
│           └── MySQLProgressRepository.php
├── tests/
│   ├── Unit/
│   │   ├── Domain/
│   │   │   ├── CourseTest.php
│   │   │   ├── CourseCatalogTest.php
│   │   │   ├── EnrollmentTest.php
│   │   │   └── LearnerProgressTest.php
│   │   └── Application/
│   │       ├── CourseApplicationServiceTest.php
│   │       ├── EnrollmentApplicationServiceTest.php
│   │       └── ProgressApplicationServiceTest.php
│   └── Integration/
│       └── Persistence/
│           ├── MySQLCourseRepositoryTest.php
│           ├── MySQLEnrollmentRepositoryTest.php
│           └── MySQLProgressRepositoryTest.php
├── migrations/
│   ├── 001_create_courses_table.sql
│   ├── 002_create_course_modules_table.sql
│   ├── 003_create_course_content_table.sql
│   ├── 004_create_enrollments_table.sql
│   └── 005_create_progress_table.sql
├── composer.json
├── phpunit.xml.dist
└── phpstan.neon
```

---

## 5. Database Migrations

### 5.1 Showcase (4 migrations)

```sql
-- 001_create_products_table.sql
CREATE TABLE products (
    id           CHAR(26)      NOT NULL PRIMARY KEY,         -- ULID per ADR-009
    sku          VARCHAR(64)   NOT NULL UNIQUE,
    title        VARCHAR(255)  NOT NULL,
    slug         VARCHAR(255)  NOT NULL UNIQUE,
    description  TEXT          NULL,
    price_cents  BIGINT        NOT NULL,                      -- store as cents to avoid float drift
    currency     CHAR(3)       NOT NULL DEFAULT 'USD',
    status       VARCHAR(32)   NOT NULL DEFAULT 'draft',     -- draft|published|archived
    created_at   TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at   TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    INDEX idx_products_status_slug (status, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 002_create_product_assets_table.sql
CREATE TABLE product_assets (
    id            CHAR(26)     NOT NULL PRIMARY KEY,
    product_id    CHAR(26)     NOT NULL,
    type          VARCHAR(32)  NOT NULL,                       -- image|video|model_3d
    path          VARCHAR(512) NOT NULL,                      -- filesystem path or S3 key
    metadata_json JSON         NOT NULL,
    sort_order    INT          NOT NULL DEFAULT 0,
    created_at    TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_product_assets_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_assets_product (product_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 003_create_wishlists_table.sql
CREATE TABLE wishlists (
    user_id       CHAR(26)     NOT NULL,
    product_id    CHAR(26)     NOT NULL,
    created_at    TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (user_id, product_id),
    CONSTRAINT fk_wishlists_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wishlists_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 004_create_product_search_index.sql
ALTER TABLE products
    ADD FULLTEXT INDEX ft_products_search (title, description);
```

### 5.2 LMS (5 migrations)

```sql
-- 001_create_courses_table.sql
CREATE TABLE courses (
    id           CHAR(26)      NOT NULL PRIMARY KEY,         -- ULID
    title        VARCHAR(255)  NOT NULL,
    slug         VARCHAR(255)  NOT NULL UNIQUE,
    description  TEXT          NULL,
    status       VARCHAR(32)   NOT NULL DEFAULT 'draft',     -- draft|published|archived
    created_at   TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at   TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 002_create_course_modules_table.sql
CREATE TABLE course_modules (
    id           CHAR(26)      NOT NULL PRIMARY KEY,
    course_id    CHAR(26)      NOT NULL,
    title        VARCHAR(255)  NOT NULL,
    sort_order   INT           NOT NULL DEFAULT 0,
    created_at   TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_modules_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_modules_course (course_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 003_create_course_content_table.sql
CREATE TABLE course_content (
    id            CHAR(26)     NOT NULL PRIMARY KEY,
    module_id     CHAR(26)     NOT NULL,
    type          VARCHAR(32)  NOT NULL,                       -- video|text|embed
    content_path  VARCHAR(512) NULL,                            -- filesystem path for video/text
    content_text  TEXT         NULL,                            -- inline text content
    metadata_json JSON         NOT NULL,
    sort_order    INT          NOT NULL DEFAULT 0,
    created_at    TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_content_module FOREIGN KEY (module_id) REFERENCES course_modules(id) ON DELETE CASCADE,
    INDEX idx_content_module (module_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 004_create_enrollments_table.sql
CREATE TABLE enrollments (
    id           CHAR(26)      NOT NULL PRIMARY KEY,
    course_id    CHAR(26)      NOT NULL,
    learner_id   CHAR(26)      NOT NULL,                        -- references users.id (per-product)
    status       VARCHAR(32)   NOT NULL DEFAULT 'pending',     -- pending|active|completed|cancelled
    enrolled_at  TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    completed_at TIMESTAMP(6)  NULL,
    CONSTRAINT fk_enrollments_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_learner FOREIGN KEY (learner_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_enrollments_course_learner (course_id, learner_id),
    INDEX idx_enrollments_learner (learner_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 005_create_progress_table.sql
CREATE TABLE progress (
    enrollment_id CHAR(26)     NOT NULL,
    module_id     CHAR(26)     NOT NULL,
    status        VARCHAR(32)  NOT NULL DEFAULT 'not_started',  -- not_started|in_progress|completed
    started_at    TIMESTAMP(6) NULL,
    completed_at  TIMESTAMP(6) NULL,
    PRIMARY KEY (enrollment_id, module_id),
    CONSTRAINT fk_progress_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    CONSTRAINT fk_progress_module FOREIGN KEY (module_id) REFERENCES course_modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 5.3 Identity (1 migration, shared Hub package per "Shared platform")

Per "Shared platform" boundary, both products share a SINGLE `users` table in the shared database. The `users` migration lives in `packages/hub/identity/migrations/` and runs ONCE per deployment. Foreign keys from `wishlists.user_id` (Showcase) and `enrollments.learner_id` (LMS) both reference this same `users.id` table — single sign-on across both products.

```sql
-- packages/hub/identity/migrations/001_create_users_table.sql
CREATE TABLE users (
    id            CHAR(26)     NOT NULL PRIMARY KEY,           -- ULID
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,                        -- Argon2id per ADR-008
    created_at    TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at    TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 6. Trade-off Notes

### 6.1 "Shared platform" boundary — what it actually means in code

The user picked "Shared platform" for product boundary. This means shared Hub bounded contexts (Identity, Audit, Config) consumed by both products, with separate bounded contexts per product (Showcase Hub, LMS Hub). Concretely:

**Concrete model:**
- `packages/hub/identity/` is ONE Hub package (HUB-04). Both Showcase and LMS depend on it via Composer.
- ONE shared database per deployment. The `users` migration lives in `packages/hub/identity/migrations/` and runs ONCE.
- Showcase's `wishlists.user_id` references `users.id` via FK.
- LMS's `enrollments.learner_id` references the SAME `users.id` via FK.
- A single user account (one row in `users`) can have a Showcase wishlist AND LMS enrollments — single sign-on across both products.
- Each product's `ApplicationFactory` (per PR #263) wires its own `IdentityApplicationService` but they share the same `users` table.
- Future HUB-06 (Audit) and HUB-01 (Config, already exists as `packages/hub/config/`) follow the same pattern — shared Hub code, shared tables.

This is the cleanest model: maximum code reuse, single sign-on, FK-enforced referential integrity across products, single audit log. The cost is tighter coupling — both products must coordinate schema changes to shared tables (e.g., adding a column to `users` affects both products).

### 6.2 HUB-04 Identity is the largest blocker

HUB-04 Identity blocks:
- Showcase "User accounts + wishlist" (PR #271)
- LMS "Enrollment" (PR #279)
- LMS "Admin dashboard" (PR #281, indirectly — admin auth required)
- Showcase "Admin dashboard" (PR #273, indirectly — admin auth required)

So 4 of the 9 feature PRs in Phase 2+3 depend on HUB-04 landing first. Phase 1 PR #265 (re-numbered after PR #264 became this planning doc) unblocks all of them.

### 6.3 CORE-14 Filesystem is the second blocker

CORE-14 Filesystem blocks:
- Showcase "Asset management" (PR #270)
- LMS "Course player" (PR #278)
- LMS "Progress tracking" (PR #280, indirectly — depends on Course player)

So 3 of the 9 feature PRs depend on CORE-14. Phase 1 PR #265 unblocks all of them.

### 6.4 "Free tier" LMS enrollment only

Per the user's MVP selection, LMS "Enrollment" is in scope but the question of free vs. paid was deferred. Per the option description: *"Enrollment flow (free or paid). If paid, requires payment Outer Spoke."*

The payment Outer Spoke (Stripe/PayPal adapter) is NOT in this MVP scope. So **enrollment is free-tier only** for this plan. Paid enrollment requires:
- A new Outer Spoke package (`packages/spoke/external/payment-stripe/` or similar)
- An Order Hub aggregate (Order, OrderLine, Payment) per the Showcase "Cart + checkout" feature (which is OUT of scope)
- Webhook handling for payment confirmation
- A new ADR for payment provider selection

This is significant additional work and should be a separate planning round.

### 6.5 Architecture-boundary-lint enforcement

Per PR #261, the architecture-boundary-lint checker will run on every PR. The new Hub packages must respect the ring rules:
- `packages/hub/showcase/src/Domain/` MUST NOT import from HTTP, SQL, DBAL implementation, vendor SDKs (per SPEC §17)
- `packages/hub/showcase/src/Application/` MAY import from Hub Domain + Core (PSR interfaces)
- `packages/hub/showcase/src/Infrastructure/` MAY import from Hub Domain + Core DBAL + PDO

The existing architecture-boundary-lint will catch any ring-boundary violation at PR time.

---

## 7. Test Strategy

### 7.1 Domain unit tests (per SPEC §46 vertical slice)

Each domain entity/value object/aggregate gets:
- Immutability tests (similar to RequestContextTest from PR #262)
- Invariant tests (e.g., `Sku` rejects empty strings, `Price` rejects negative values, `Email` rejects malformed)
- Aggregate lifecycle tests (e.g., `Catalog::createProduct()` produces `ProductCreated` event; `Catalog::deleteProduct()` produces `ProductDeleted` event)

### 7.2 Repository integration tests

Each MySQL*Repository gets integration tests using SQLite for parity per ADR-013. Tests verify:
- `save()` persists all fields
- `get()` round-trips the aggregate
- `get()` returns `null` for unknown IDs
- `delete()` removes the row
- Multi-row queries (e.g., `ListProductsQuery`) return correct ordering

### 7.3 Application service tests (with rollback per SPEC §20)

Each ApplicationService gets tests that verify:
- Transaction ownership: a single command executes in ONE transaction (no nested commits)
- Rollback: if any operation fails, the entire transaction rolls back (per SPEC §20)
- Event recording: domain events are recorded in the same transaction as state changes (per SPEC §22)

### 7.4 HTTP route smoke tests

Each Outer Rim route gets a smoke test that:
- Makes an HTTP request through the Kernel (not a unit test)
- Verifies the response status code
- Verifies the response body structure
- Verifies no internal error leakage (per SPEC §27)

### 7.5 Architecture-boundary-lint regression

The existing 13 regression tests from PR #261 will continue to run on every PR. New Hub packages will be scanned for ring-boundary violations.

---

## 8. PR Sequencing — Recommended Merge Order

```
CURRENT (in-flight):
  #261  M0 baseline + M1 ring-boundary CI + SPEC-001       (merge first — establishes the contract)
  #262  M2 RequestContext + contamination tests           (merge second — establishes isolation contract)
  #263  M3 ApplicationFactory + thin public/index.php      (merge third — establishes composition boundary)

PHASE 1 — Unblock dependencies (parallel):
  #264  HUB-04 Identity                                     (Phase 1 — unblocks Showcase #271, LMS #279/#281)
  #265  CORE-14 Filesystem                                  (Phase 1 — unblocks Showcase #270, LMS #278/#280)

PHASE 2 — Showcase vertical slice (M5a):
  #266  packages/hub/showcase/ Domain                      (depends on Phase 1)
  #267  packages/hub/showcase/ Application                 (depends on #266)
  #268  packages/hub/showcase/ Infrastructure              (depends on #266, #267)
  #269  Showcase Outer Rim routes                         (depends on #268, #263 ApplicationFactory)
  #270  Showcase asset management                          (depends on #268, #265 CORE-14)
  #271  Showcase user accounts + wishlist                  (depends on #268, #264 HUB-04)
  #272  Showcase search service                            (depends on #268)
  #273  Showcase admin dashboard                           (depends on #271 — admin auth required)

PHASE 3 — LMS vertical slice (M5b):
  #274  packages/hub/lms/ Domain                            (depends on Phase 1)
  #275  packages/hub/lms/ Application                       (depends on #274)
  #276  packages/hub/lms/ Infrastructure                    (depends on #274, #275)
  #277  LMS Outer Rim routes                                (depends on #276, #263 ApplicationFactory)
  #278  LMS course player                                  (depends on #276, #265 CORE-14)
  #279  LMS enrollment flow (free tier)                     (depends on #276, #264 HUB-04)
  #280  LMS progress tracking                              (depends on #278 Course player)
  #281  LMS admin dashboard                                (depends on #279 — admin auth required)

PARALLEL — M4 release-gate (continues per "Extend M5" decision):
  M4a   Three-tier health split (/health/live, /health/ready, /health/dependencies)
  M4b   Full-stack release verification gate (Caddy → Tengine → FrankenPHP → Application)
  M4c   Blue/green rollback test
```

---

## 9. Estimated Scope

### Phase 1 (unblock dependencies)
- **HUB-04 Identity (PR #264):** ~18 PHP files, 1 migration, ~25 tests
- **CORE-14 Filesystem (PR #265):** ~12 PHP files, 0 migrations, ~15 tests

### Phase 2 (Showcase M5a)
- **Hub Domain (PR #266):** ~15 PHP files, 0 migrations, ~15 tests
- **Hub Application (PR #267):** ~12 PHP files, 0 migrations, ~10 tests
- **Hub Infrastructure (PR #268):** ~5 PHP files, 0 migrations, ~10 tests
- **Outer Rim routes (PR #269):** ~6 PHP files, 4 migrations, ~6 tests
- **Asset management (PR #270):** ~5 PHP files, 0 migrations, ~5 tests
- **User + wishlist (PR #271):** ~8 PHP files, 0 migrations, ~8 tests
- **Search (PR #272):** ~3 PHP files, 0 migrations, ~4 tests
- **Admin dashboard (PR #273):** ~5 PHP files, 0 migrations, ~5 tests

**Showcase total:** ~59 PHP files, 4 migrations, ~63 tests across 8 PRs

### Phase 3 (LMS M5b)
- **Hub Domain (PR #274):** ~18 PHP files, 0 migrations, ~18 tests
- **Hub Application (PR #275):** ~15 PHP files, 0 migrations, ~12 tests
- **Hub Infrastructure (PR #276):** ~6 PHP files, 0 migrations, ~12 tests
- **Outer Rim routes (PR #277):** ~6 PHP files, 5 migrations, ~6 tests
- **Course player (PR #278):** ~6 PHP files, 0 migrations, ~6 tests
- **Enrollment (PR #279):** ~5 PHP files, 0 migrations, ~6 tests
- **Progress tracking (PR #280):** ~5 PHP files, 0 migrations, ~6 tests
- **Admin dashboard (PR #281):** ~5 PHP files, 0 migrations, ~5 tests

**LMS total:** ~66 PHP files, 5 migrations, ~71 tests across 8 PRs

### Grand total across Phases 1-3
- **~155 PHP files**
- **~10 database migrations**
- **~174 tests**
- **~18 PRs** (3 current + 2 Phase 1 + 8 Phase 2 + 8 Phase 3, minus M4 parallel work)

This is multi-week work. Each PR is independently mergeable in dependency order.

---

## 10. What's Next — Concrete Action

### Immediate (next 1-3 PRs)

The 3 in-flight PRs (#261, #262, #263) should merge first to establish the M0+M1+M2+M3 foundation. Then Phase 1 begins.

**Recommended first action after the 3 in-flight PRs merge:**

Start **PR #264 (HUB-04 Identity)** because:
1. It unblocks the most feature work (4 downstream PRs depend on it)
2. It exercises the Hub Domain → Application → Infrastructure vertical slice template that PR #266 (Showcase Hub Domain) will follow
3. It can be built in parallel with PR #265 (CORE-14 Filesystem) since neither depends on the other
4. The User entity + Argon2id password hashing (CORE-16 from PR #260 already merged) + MySQLUserRepository + IdentityApplicationService is a complete vertical slice in itself — it becomes the architectural template for Showcase + LMS Hub packages

### Decision needed from user

This plan as written is ~155 PHP files / 18 PRs / multi-week. To start executing, pick one:

1. **"Start Phase 1"** — I begin PR #264 (HUB-04 Identity) + PR #265 (CORE-14 Filesystem) immediately. Branches `feat/hub-04-identity` + `feat/core-14-filesystem` get created, code gets written, PRs get opened. The 3 current in-flight PRs merge whenever they're ready (Phase 1 work doesn't depend on them being merged first, just on the M1 architecture-boundary-lint being part of the repo).

2. **"Adjust the plan first"** — you want to revise scope, drop features, add features, change PR ordering, or discuss the "Shared platform" boundary interpretation before any code is written.

3. **"Plan only, defer execution"** — save this plan as a contractor reference, defer all execution until a later session.

---

*End of Showcase + LMS Implementation Plan.*
