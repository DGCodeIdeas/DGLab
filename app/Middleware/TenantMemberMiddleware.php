<?php

namespace DGLab\Middleware;

use DGLab\Core\MiddlewareInterface;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Services\Auth\AuthManager;
use DGLab\Services\Tenancy\TenancyService;

class TenantMemberMiddleware implements MiddlewareInterface
{
    protected AuthManager $auth;
    protected TenancyService $tenancy;

    public function __construct(AuthManager $auth, TenancyService $tenancy)
    {
        $this->auth = $auth;
        $this->tenancy = $tenancy;
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $user = $this->auth->user();
        $tenantId = $this->tenancy->tenantId();

        if (!$user || !$tenantId) {
            return new Response(json_encode(['error' => 'Unauthorized or missing tenant context']), 403, ['Content-Type' => 'application/json']);
        }

        // Check if user is a member of this tenant (has any role in it)
        $isMember = $this->auth->can('tenant.access'); // Conceptual permission or generic check

        // More direct check for "any role in this tenant"
        if (!$isMember) {
             // Fallback to checking if they have at least one entry in tenant_user_roles
             // This could be implemented in AuthorizationService as isMemberOf($tenantId)
             $isMember = $this->checkTenantMembership($user->id, $tenantId);
        }

        if (!$isMember) {
            return new Response(json_encode(['error' => 'Forbidden: You do not have access to this tenant']), 403, ['Content-Type' => 'application/json']);
        }

        return $next($request);
    }

    protected function checkTenantMembership(int $userId, int $tenantId): bool
    {
        $sql = "SELECT 1 FROM tenant_user_roles WHERE user_id = ? AND tenant_id = ? LIMIT 1";
        $result = \DGLab\Database\Connection::getInstance()->selectOne($sql, [$userId, $tenantId]);
        return !is_null($result);
    }
}
