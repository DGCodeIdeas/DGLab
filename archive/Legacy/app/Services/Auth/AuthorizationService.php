<?php

namespace DGLab\Services\Auth;

use DGLab\Models\User;
use DGLab\Services\Tenancy\TenancyService;
use DGLab\Database\Connection;

class AuthorizationService
{
    protected TenancyService $tenancy;
    protected array $permissionCache = [];

    public function __construct(TenancyService $tenancy)
    {
        $this->tenancy = $tenancy;
    }

    public function can(User $user, string $permission): bool
    {
        $tenantId = $this->tenancy->tenantId();
        if (!$tenantId) {
            return false;
        }

        $permissions = $this->getUserPermissionsForTenant($user, $tenantId);
        return in_array($permission, $permissions);
    }

    public function hasRole(User $user, string $role): bool
    {
        $tenantId = $this->tenancy->tenantId();
        if (!$tenantId) {
            return false;
        }

        $sql = "SELECT r.name FROM roles r
                INNER JOIN tenant_user_roles tur ON r.id = tur.role_id
                WHERE tur.user_id = ? AND tur.tenant_id = ? AND r.name = ?";

        $result = Connection::getInstance()->selectOne($sql, [$user->id, $tenantId, $role]);
        return !is_null($result);
    }

    protected function getUserPermissionsForTenant(User $user, int $tenantId): array
    {
        $cacheKey = "{$user->id}:{$tenantId}";
        if (isset($this->permissionCache[$cacheKey])) {
            return $this->permissionCache[$cacheKey];
        }

        $sql = "SELECT DISTINCT p.name FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                INNER JOIN tenant_user_roles tur ON rp.role_id = tur.role_id
                WHERE tur.user_id = ? AND tur.tenant_id = ?";

        $results = Connection::getInstance()->select($sql, [$user->id, $tenantId]);
        $permissions = array_column($results, 'name');

        $this->permissionCache[$cacheKey] = $permissions;
        return $permissions;
    }
}
