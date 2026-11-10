<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Identity\Session;

use Predis\ClientInterface;
use Predis\Client;

final class RedisSessionStore implements SessionStoreInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly string $prefix = 'dglab:session:',
        private readonly int $defaultTtl = 86400,
    ) {}

    public function get(string $sessionId): ?array
    {
        $data = $this->client->get($this->prefix . $sessionId);
        if ($data === false) {
            return null;
        }
        $data = json_decode($data, true);
        if (!is_array($data)) {
            return null;
        }
        return $data;
    }

    public function set(string $sessionId, array $data, int $ttlSeconds = 86400): void
    {
        $key = $this->prefix . $sessionId;
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $this->client->setex($key, $ttlSeconds, $json);
    }

    public function delete(string $sessionId): void
    {
        $this->client->del($this->prefix . $sessionId);
    }

    public function exists(string $sessionId): bool
    {
        return (bool)$this->client->exists($this->prefix . $sessionId);
    }
}