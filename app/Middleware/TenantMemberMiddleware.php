<?php

namespace DGLab\Middleware;

use DGLab\Core\MiddlewareInterface;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Core\Contracts\ResponseFactoryInterface;
use DGLab\Services\Auth\AuthManager;
use DGLab\Services\Tenancy\TenancyService;

class TenantMemberMiddleware implements MiddlewareInterface
{
    protected AuthManager $auth;
    protected TenancyService $tenancy;
    protected ResponseFactoryInterface $responseFactory;

    public function __construct(AuthManager $auth, TenancyService $tenancy, ResponseFactoryInterface $responseFactory)
    {
        $this->auth = $auth;
        $this->tenancy = $tenancy;
        $this->responseFactory = $responseFactory;
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $this->auth->user();
        $tenantId = $this->tenancy->tenantId();

        if (!$user || !$tenantId) {
            return $this->responseFactory->json(['error' => 'Unauthorized or missing tenant context'], 403);
        }

        // Check if user is a member of this tenant (has any role in it)
        $isMember = $this->auth->can('tenant.access'); // Conceptual permission or generic check

        // More direct check for "any role in this tenant"
        if (!$isMember) {
             $isMember = $this->checkTenantMembership($user->id, $tenantId);
        }

        if (!$isMember) {
            return $this->responseFactory->json(['error' => 'Forbidden: You do not have access to this tenant'], 403);
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
