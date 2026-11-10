<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Identity\Session;

interface SessionStoreInterface
{
    public function get(string $sessionId): ?array;

    public function set(string $sessionId, array $data, int $ttlSeconds = 86400): void;

    public function delete(string $sessionId): void;

    public function exists(string $sessionId): bool;
}