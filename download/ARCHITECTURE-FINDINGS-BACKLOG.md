# Architecture Findings Backlog — Post-Lap-1 Review

**Generated:** 2026-09-26
**Verified against:** DGLab HEAD `3e214df`
**Source:** External architecture review by Software Architect AI

---

## Governance Note

Per the SDLC-AGRD + ARCHITECTURE-SDLC-FUSION.md: these findings are **recorded during Cooldown 1** but are **not fixed during Cooldown 1**. They become Lap 2 admission candidates, prioritized by the SDLC's calibration + dependency graph + per-blueprint depth floors.

The Cooldown 1 sequence remains:
1. Worklog reconciliation ✅
2. ADR discrepancy resolution ✅
3. Fitness-function expansion ← current
4. SDLC recalibration ← next
5. Lap 2 admission ← after recalibration

---

## P0 — Architecture Correctness (must address before external authentication/production use)

### P0-1: Authoritative RequestContext.userId integration

**Finding:** AuthMiddleware stamps `$request->withAttribute('userId', $userId)` instead of calling `RequestContext::withUserId()` via the Container's pulse mechanism.

**Impact:** Two possible identity sources exist — PSR-7 request attribute vs Fiber-scoped RequestContext. Downstream code can observe different identities depending on which source it reads.

**Desired model:**
```
AuthMiddleware → RequestContext::withUserId() → Container::pulse(RequestContext)
→ authorization, audit, application services all read from one source
```

**Resolution:** AuthMiddleware should stamp RequestContext (via pulse), not just the request attribute. The request attribute may remain as a transport convenience but must not be the authoritative source.

---

### P0-2: ES256/JWS interoperability + algorithm enforcement

**Finding:** JwtSigner uses `openssl_sign()` which produces ASN.1/DER ECDSA signatures. JWS ES256 (RFC 7518) requires 64-octet R||S format. The current implementation is internally consistent (sign→verify round-trips) but non-standard for external JWT consumers.

**Impact:** Tokens produced by DGLab's JwtSigner cannot be verified by standard JWT libraries. External identity providers cannot produce tokens DGLab's JwtVerifier accepts.

**Additional issue:** JwtVerifier does not enforce `header.alg === 'ES256'` — it accepts any algorithm without checking the header.

**Resolution:**
1. Convert DER ECDSA signature to R||S format (or vice versa) for JWS compliance
2. Parse and enforce `alg === 'ES256'` in JwtVerifier before accepting signature
3. Add interoperability test: sign with DGLab → verify with external JWT library (and vice versa)

---

### P0-3: Filesystem root-containment check (prefix collision)

**Finding:** PathGuard uses `str_starts_with($resolved, $this->rootPath)` without ensuring a directory boundary separator. If root is `/tmp/data`, path `/tmp/database/file` passes the check because `str_starts_with('/tmp/database/file', '/tmp/data')` returns true.

**Impact:** Path traversal protection can be bypassed via root-prefix collision.

**Resolution:** Add trailing directory separator to root before comparison:
```php
$root = rtrim($this->rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if ($resolved !== rtrim($this->rootPath, DIRECTORY_SEPARATOR) && !str_starts_with($resolved, $root)) {
    throw new PathTraversalRefusedException(...);
}
```

---

### P0-4: Register and integration-test all health routes

**Finding:** ApplicationFactory registers Vanguard contracts for `/health/live`, `/health/ready`, `/health/dependencies` but does NOT register corresponding Router routes (`addRoute()`) for these paths. The controller methods exist but are unreachable via HTTP.

**Impact:** The three-tier health split is declared but not composed. `GET /health/live` returns 404 instead of `{"status":"alive"}`.

**Resolution:** Add three `addRoute()` calls in ApplicationFactory's anonymous bootstrapper for the new health paths. Add HTTP-level integration test.

---

## P1 — Product/Persistence Correctness

### P1-1: Showcase publishProduct() missing authorization

**Finding:** `ProductApplicationService::createProduct()` calls `requireAnyRole()` but `publishProduct()` does not. Any authenticated user can publish any product.

**Resolution:** Add `requireAnyRole($userId, ['showcase:admin', 'showcase:editor', 'platform:admin'])` to `publishProduct()`. Note: this requires passing the userId (from RequestContext) into the method or command.

---

### P1-2: Showcase persistence hydration invokes domain behavior

**Finding:** `MySQLProductRepository::hydrate()` calls `$product->publish()` when `status === 'published'`. This invokes the domain's state-transition method, which sets `updatedAt = now()` — corrupting the persisted `updated_at` value during read.

**Resolution:** Hydration should restore persisted state directly (set status without invoking the transition method). Add a `Product::restoreFromPersistence()` factory or a `setStatus()` method for hydration-only use.

---

### P1-3: Explicit application-service transaction boundaries

**Finding:** `MySQLUserRepository::save()` performs multiple SQL statements (user update + role delete + role inserts) without a transaction. Partial failure leaves the aggregate in an inconsistent state. Same pattern in `MySQLProductRepository::save()`.

**Resolution:** Application services should wrap repository operations in a `Transaction` (from CORE-19 DBAL). The repository should NOT manage its own transaction — the application service owns the unit-of-work boundary per SPEC §20.

---

### P1-4: Filesystem writeStream() failure handling

**Finding:** `writeStream()` has no read-back integrity check (unlike `write()`). `AtomicWriter::writeStream()` treats `fread() === false` as `break` (silent truncation) and doesn't verify `fwrite()` wrote the complete chunk. Partial writes can be renamed to final path.

**Resolution:**
1. Add integrity check to `writeStream()` (or document why it's excluded)
2. Treat `fread() === false` as an error, not a clean break
3. Verify `fwrite()` return value matches chunk length

---

### P1-5: PanicException propagation semantics

**Finding:** `ApplicationFactory::run()` catches `\Throwable` in the request handler, which intercepts `PanicException` — the exception that should escape the request boundary and trigger worker isolation/restart per doctrine §4.5.4.

**Resolution:** Split the catch:
```php
catch (PanicException $e) {
    // log + terminate worker
    throw $e;
}
catch (\Throwable $e) {
    // ordinary HTTP 500 handling
}
```

---

### P1-6: Architecture-lint REPO_ROOT portability

**Finding:** `REPO_ROOT = Path("/home/z/my-project")` is hardcoded. GitHub Actions checks out to `/github/workspace`, not `/home/z/my-project`. The lint script fails on CI because it can't find the repo.

**Resolution:** Derive root from script location:
```python
REPO_ROOT = Path(__file__).resolve().parents[1]
```

---

### P1-7: Explicit external dependency allow-list

**Finding:** The architecture lint treats any namespace not matching DGLab's known internal prefixes as `"external"` (allowed). Unknown dependencies pass without question.

**Resolution:** Add an explicit external dependency allow-list (PSR interfaces, approved vendor packages). Unknown namespaces should be flagged, not silently allowed.

---

## P2 — Scalability/Completeness

### P2-1: AST-based dependency analysis

**Finding:** The architecture lint uses regex-based `use` statement scanning. It doesn't see `extends`, `implements`, attributes, `new \Foo()`, `Foo::method()`, or return types.

**Resolution:** Progress to AST analysis via `nikic/php-parser` or equivalent. Current regex scanning is useful but shouldn't be named "architecture boundary lint" if it only covers imports.

---

### P2-2: Auto-generated CI package matrix

**Finding:** The packages-ci workflow uses a manually enumerated package matrix. New packages (Filesystem, Identity, Showcase) may not be in CI. Adding a package requires remembering to update CI.

**Resolution:** Derive the package set from `packages/*/*/composer.json` discovery. CI matrix generated dynamically.

---

## Summary

| Priority | Count | Category |
|---|---|---|
| P0 | 4 | Architecture correctness (identity, security, composition) |
| P1 | 7 | Product/persistence correctness + lint portability |
| P2 | 2 | Scalability/completeness |
| Total | 13 | |

**No findings are fixed during Cooldown 1.** They are recorded for Lap 2 admission. The SDLC determines which of these enter Lap 2 based on capacity, dependency graph, and per-blueprint depth floors.

---

*End of Architecture Findings Backlog.*
