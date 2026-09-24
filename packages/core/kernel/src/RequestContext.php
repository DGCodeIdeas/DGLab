<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel;

use DateTimeImmutable;

/**
 * Immutable value object carrying the request-scoped correlation context.
 *
 * Per SPEC-001 §8 (RequestContext): follows the immutable-value-object
 * approach already established by {@see \SovereignStack\Core\Database\TenantContext}.
 *
 * Per the locked MVP architectural baseline (PR #267): RequestContext is
 * **identity-light** — it carries only the userId correlation key, NOT the
 * full AuthenticatedUser or roles[]. The distinction:
 *   - RequestContext describes the request (correlation keys: requestId,
 *     traceId, tenantId, userId, startedAt, routeName).
 *   - IdentityInterface describes the authenticated principal (full user
 *     object with email + roles).
 * Application services resolve the full principal via
 * IdentityInterface::getUserById(RequestContext->userId) when needed.
 *
 * Per SPEC §13 (State Ownership Rules): lifetime is Pulse/Fiber — MUST never
 * cross Fibers. Per SPEC §14: composes with TenantContext conceptually — no
 * static tenant accessor is introduced.
 *
 * Per SPEC §8: "If a tenant is established later in request processing, the
 * context SHOULD be replaced with a new immutable instance rather than
 * mutated." The with*() methods implement this discipline — they return new
 * instances, never mutate.
 *
 * @package SovereignStack\Core\Kernel
 *
 * @see \SovereignStack\Core\Database\TenantContext The existing immutable-value-object pattern this class extends.
 * @see \SovereignStack\Core\Container\Container::pulse() The Fiber-isolated storage mechanism.
 */
final readonly class RequestContext
{
    /**
     * @param string $requestId     Globally-unique request identifier (e.g., UUIDv7 or ULID per ADR-009).
     * @param string $traceId       Distributed-trace identifier (W3C traceparent format when S01 is implemented).
     * @param ?string $tenantId     Tenant ULID, populated after authentication. Null pre-auth or for non-tenant requests.
     * @param DateTimeImmutable $startedAt   Request start timestamp (monotonic-friendly; used for duration measurement).
     * @param ?string $routeName    Matched route name, populated after routing. Null pre-routing.
     * @param ?string $userId       Authenticated user ULID, populated by AuthMiddleware after token verification.
     *                              Null for unauthenticated requests. Identity-light: carries only the ID,
     *                              NOT AuthenticatedUser or roles[] (those are resolved via IdentityInterface).
     */
    public function __construct(
        public string $requestId,
        public string $traceId,
        public ?string $tenantId,
        public DateTimeImmutable $startedAt,
        public ?string $routeName = null,
        public ?string $userId = null,
    ) {}

    /**
     * Return a new instance with the tenant_id populated.
     *
     * Per SPEC §8: the context is REPLACED with a new immutable instance
     * rather than mutated. The original instance remains unchanged.
     *
     * @param non-empty-string $tenantId
     */
    public function withTenantId(string $tenantId): self
    {
        return new self(
            requestId: $this->requestId,
            traceId: $this->traceId,
            tenantId: $tenantId,
            startedAt: $this->startedAt,
            routeName: $this->routeName,
            userId: $this->userId,
        );
    }

    /**
     * Return a new instance with the route name populated.
     *
     * @param non-empty-string $routeName
     */
    public function withRouteName(string $routeName): self
    {
        return new self(
            requestId: $this->requestId,
            traceId: $this->traceId,
            tenantId: $this->tenantId,
            startedAt: $this->startedAt,
            routeName: $routeName,
            userId: $this->userId,
        );
    }

    /**
     * Return a new instance with the userId populated.
     *
     * Use case: AuthMiddleware verifies credentials (JWT/session), resolves
     * the user ID, and stamps the RequestContext with it. Per the locked
     * MVP architectural baseline: RequestContext is identity-light — only
     * the userId correlation key is stored here, NOT the full
     * AuthenticatedUser value object. The full principal is resolved by
     * application services via IdentityInterface::getUserById().
     *
     * @param non-empty-string $userId  The authenticated user's ULID
     */
    public function withUserId(string $userId): self
    {
        return new self(
            requestId: $this->requestId,
            traceId: $this->traceId,
            tenantId: $this->tenantId,
            startedAt: $this->startedAt,
            routeName: $this->routeName,
            userId: $userId,
        );
    }

    /**
     * True if the tenant_id is populated (post-authentication).
     */
    public function hasTenant(): bool
    {
        return $this->tenantId !== null && $this->tenantId !== '';
    }

    /**
     * True if the route name is populated (post-routing).
     */
    public function hasRouteName(): bool
    {
        return $this->routeName !== null && $this->routeName !== '';
    }

    /**
     * True if the userId is populated (post-authentication).
     * For unauthenticated requests, returns false.
     */
    public function hasUserId(): bool
    {
        return $this->userId !== null && $this->userId !== '';
    }
}
