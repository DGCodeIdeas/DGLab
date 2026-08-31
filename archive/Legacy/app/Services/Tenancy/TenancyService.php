<?php

namespace DGLab\Services\Tenancy;

use DGLab\Models\Tenant;
use DGLab\Core\Request;
use RuntimeException;

class TenancyService
{
    protected ?Tenant $currentTenant = null;
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getCurrentTenant(): ?Tenant
    {
        if ($this->currentTenant) {
            return $this->currentTenant;
        }
        $this->currentTenant = $this->identify();
        return $this->currentTenant;
    }

    public function setCurrentTenant(Tenant $tenant): void
    {
        $this->currentTenant = $tenant;
    }

    public function tenantId(): ?int
    {
        $tenant = $this->getCurrentTenant();
        return $tenant ? (int)$tenant->id : null;
    }

    public function getCurrentTenantId(): ?int
    {
        return $this->tenantId();
    }

    protected function identify(): ?Tenant
    {
        $identifier = $this->request->getHeader('X-Tenant-Id');
        if ($identifier) {
            return Tenant::findBy(['identifier' => $identifier]);
        }

        $host = $this->request->getServer('HTTP_HOST');
        if ($host) {
            $tenant = Tenant::findBy(['domain' => $host]);
            if ($tenant) {
                return $tenant;
            }
        }

        return null;
    }

    public function requireTenant(): Tenant
    {
        $tenant = $this->getCurrentTenant();
        if (!$tenant) {
            throw new RuntimeException("Tenant context required.");
        }
        return $tenant;
    }
}
