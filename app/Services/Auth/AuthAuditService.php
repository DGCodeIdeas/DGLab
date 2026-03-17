<?php

namespace DGLab\Services\Auth;

use DGLab\Database\Connection;
use DGLab\Core\Request;
use DGLab\Models\User;

class AuthAuditService
{
    protected Connection $db;
    protected Request $request;

    public function __construct(Connection $db, Request $request)
    {
        $this->db = $db;
        $this->request = $request;
    }

    public function log(string $eventType, ?User $user = null, ?string $identifier = null, array $metadata = []): void
    {
        $this->db->insert(
            "INSERT INTO auth_audit_logs (user_id, event_type, identifier, ip_address, user_agent, metadata, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $user ? $user->id : null,
                $eventType,
                $identifier ?: ($user ? $user->email : null),
                $this->request->getServer('REMOTE_ADDR'),
                $this->request->getServer('HTTP_USER_AGENT'),
                json_encode($metadata),
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            ]
        );
    }
}
