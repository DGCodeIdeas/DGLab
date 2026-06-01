<?php

namespace DGLab\Services\Nexus;

/**
 * Interface ConnectionManagerInterface
 *
 * Defines the contract for tracking active WebSocket connections.
 */
interface ConnectionManagerInterface
{
    /**
     * Store connection details.
     *
     * @param int $fd File Descriptor
     * @param array $data Metadata (user_id, tenant_id, etc.)
     */
    public function add(int $fd, array $data): void;

    /**
     * Get connection details.
     *
     * @param int $fd
     * @return array|null
     */
    public function get(int $fd): ?array;

    /**
     * Remove a connection.
     *
     * @param int $fd
     */
    public function remove(int $fd): void;

    /**
     * Get all connections for a specific user.
     *
     * @param mixed $userId
     * @return array Array of FDs
     */
    public function getFdsByUser($userId): array;

    /**
     * Get all connections for a specific tenant.
     *
     * @param mixed $tenantId
     * @return array Array of FDs
     */
    public function getFdsByTenant($tenantId): array;

    /**
     * Count active connections.
     *
     * @return int
     */
    public function count(): int;
}
