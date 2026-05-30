<?php

namespace DGLab\Core;

use DGLab\Database\Connection;
use DGLab\Core\Request;
use DGLab\Services\Tenancy\TenancyService;
use DGLab\Services\Auth\AuthManager;

/**
 * Unified Audit Service
 *
 * Provides a central interface for logging system, security, and performance events.
 */
class AuditService
{
    protected Connection $db;
    protected Request $request;
    protected ?TenancyService $tenancy;
    protected ?AuthManager $auth;

    public function __construct(Connection $db, Request $request, ?TenancyService $tenancy = null, ?AuthManager $auth = null)
    {
        $this->db = $db;
        $this->request = $request;
        $this->tenancy = $tenancy;
        $this->auth = $auth;
    }

    /**
     * Log an event to the unified audit log
     */
    public function log(
        string $category,
        string $eventType,
        ?string $identifier = null,
        array $metadata = [],
        ?int $statusCode = null,
        ?int $latencyMs = null
    ): void {
        try {
            $userId = $this->auth ? $this->auth->id() : null;
            $tenantId = $this->tenancy ? $this->tenancy->getCurrentTenantId() : null;

            $this->db->insert(
                "INSERT INTO audit_logs (tenant_id, user_id, category, event_type, identifier, status_code, ip_address, user_agent, metadata, latency_ms, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $tenantId,
                    $userId,
                    $category,
                    $eventType,
                    $identifier,
                    $statusCode,
                    $this->request->getServer('REMOTE_ADDR'),
                    $this->request->getServer('HTTP_USER_AGENT'),
                    json_encode($metadata),
                    $latencyMs,
                    date('Y-m-d H:i:s')
                ]
            );
        } catch (\Exception $e) {
            // Fail silently to avoid crashing core operations
        }
    }
}
