<?php

namespace DGLab\Services\Auth;

use DGLab\Core\AuditService;
use DGLab\Models\User;

/**
 * Auth Audit Service (Legacy Wrapper)
 *
 * @deprecated Use DGLab\Core\AuditService directly
 */
class AuthAuditService
{
    protected AuditService $audit;

    public function __construct(AuditService $audit)
    {
        $this->audit = $audit;
    }

    public function log(string $eventType, ?User $user = null, ?string $identifier = null, array $metadata = []): void
    {
        $this->audit->log('auth', $eventType, $identifier ?: ($user ? $user->email : null), $metadata);
    }
}
