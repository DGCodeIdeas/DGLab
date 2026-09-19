<?php

namespace DGLab\Services\Nexus;

use Swoole\Table;

/**
 * Class SwooleConnectionManager
 *
 * High-performance implementation using Swoole\Table.
 */
class SwooleConnectionManager implements ConnectionManagerInterface
{
    protected Table $table;
    protected int $size;

    public function __construct(int $size = 1024)
    {
        $this->size = $size;
        $this->table = new Table($size);
        $this->table->column('user_id', Table::TYPE_INT, 8);
        $this->table->column('tenant_id', Table::TYPE_INT, 8);
        $this->table->column('connected_at', Table::TYPE_INT, 8);
        $this->table->create();
    }

    public function add(int $fd, array $data): void
    {
        $this->table->set((string)$fd, [
            'user_id' => $data['user_id'] ?? 0,
            'tenant_id' => $data['tenant_id'] ?? 0,
            'connected_at' => time()
        ]);
    }

    public function get(int $fd): ?array
    {
        $data = $this->table->get((string)$fd);
        return $data ?: null;
    }

    public function remove(int $fd): void
    {
        $this->table->del((string)$fd);
    }

    public function getFdsByUser($userId): array
    {
        $fds = [];
        foreach ($this->table as $fd => $row) {
            if ($row['user_id'] == $userId) {
                $fds[] = (int)$fd;
            }
        }
        return $fds;
    }

    public function getFdsByTenant($tenantId): array
    {
        $fds = [];
        foreach ($this->table as $fd => $row) {
            if ($row['tenant_id'] == $tenantId) {
                $fds[] = (int)$fd;
            }
        }
        return $fds;
    }

    public function count(): int
    {
        return $this->table->count();
    }
}
