<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database;

/**
 * Immutable value object carrying the active tenant ULID.
 *
 * Constructed by HUB-04 (Identity) after authentication.
 * When active, QueryBuilder auto-injects WHERE tenant_id = :tenant_id.
 *
 * @package SovereignStack\Core\Database
 */
final class TenantContext
{
    private ?string $tenantId;

    public function __construct(?string $tenantId = null)
    {
        $this->tenantId = $tenantId;
    }

    public function isActive(): bool
    {
        return $this->tenantId !== null;
    }

    public function tenantId(): ?string
    {
        return $this->tenantId;
    }
}
