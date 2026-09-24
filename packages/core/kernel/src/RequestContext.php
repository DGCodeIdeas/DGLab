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
 * Created at the request boundary (by the worker handler in public/index.php
 * or by the future ApplicationFactory). Stored in Fiber-isolated request scope
 * via the Container's pulse() WeakMap<Fiber, ...> mechanism — per SPEC §42,
 * "the existing container model already provides the mechanism." Available to
 * logging, error handling, middleware, event stamping, and downstream
 * request propagation.
 *
 * Per SPEC §13 (State Ownership Rules): lifetime is Pulse/Fiber — MUST never
 * cross Fibers. Per SPEC §14: composes with TenantContext conceptually — no
 * static tenant accessor is introduced.
 *
 * Per SPEC §8: "If a tenant is established later in request processing, the
 * context SHOULD be replaced with a new immutable instance rather than
 * mutated." The {@see withTenantId()} and {@see withRouteName()} methods
 * implement this discipline — they return new instances, never mutate.
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
     */
    public function __construct(
        public string $requestId,
        public string $traceId,
        public ?string $tenantId,
        public DateTimeImmutable $startedAt,
        public ?string $routeName = null,
    ) {}

    /**
     * Return a new instance with the tenant_id populated.
     *
     * Use case: authentication runs after the request boundary (e.g., JWT
     * verified in middleware). Per SPEC §8, the context is REPLACED with a
     * new immutable instance rather than mutated. The original instance
     * remains unchanged (immutable value semantics).
     *
     * Per SPEC §14: "The DBAL's existing tenant-aware QueryBuilder behavior
     * should continue to receive TenantContext explicitly." This method does
     * NOT inject anything into QueryBuilder — it merely returns a new
     * RequestContext. The caller (application service, middleware, etc.)
     * is responsible for constructing a TenantContext from this value if
     * needed for DBAL queries.
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
        );
    }

    /**
     * Return a new instance with the route name populated.
     *
     * Use case: router matches the request to a named route after the
     * RequestContext is initially created at the request boundary. Per
     * SPEC §8: "Optional metadata such as route name MAY be populated after
     * routing."
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
}
