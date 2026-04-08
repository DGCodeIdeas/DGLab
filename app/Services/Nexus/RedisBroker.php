<?php

namespace DGLab\Services\Nexus;

use Swoole\Coroutine\Redis;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class RedisBroker
 *
 * Handles non-blocking Redis Pub/Sub operations using Swoole Coroutines.
 */
class RedisBroker
{
    protected string $host;
    protected int $port;
    protected ?string $password;
    protected int $database;
    protected LoggerInterface $logger;
    protected ?Redis $client = null;

    public function __construct(
        string $host,
        int $port,
        ?string $password = null,
        int $database = 0,
        ?LoggerInterface $logger = null
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
        $this->database = $database;
        $this->logger = $logger ?? new \DGLab\Core\Log\NullLogger();
    }

    /**
     * Connect to Redis in a coroutine.
     */
    public function connect(): bool
    {
        $this->client = new Redis();
        if (!$this->client->connect($this->host, $this->port)) {
            $this->logger->error("Failed to connect to Redis at {$this->host}:{$this->port}");
            return false;
        }

        if ($this->password && !$this->client->auth($this->password)) {
            $this->logger->error("Redis authentication failed");
            return false;
        }

        if (!$this->client->select($this->database)) {
            $this->logger->error("Failed to select Redis database {$this->database}");
            return false;
        }

        return true;
    }

    /**
     * Subscribe to channels and handle messages in a loop.
     */
    public function subscribe(array $channels, callable $callback): void
    {
        if (!$this->client || !$this->client->connected) {
            if (!$this->connect()) {
                return;
            }
        }

        try {
            if (!$this->client->subscribe($channels)) {
                $this->logger->error("Failed to subscribe to Redis channels");
                return;
            }

            while (true) {
                $msg = $this->client->recv();
                if ($msg === false || $msg === null) {
                    $this->logger->warning("Redis connection lost during recv()");
                    break;
                }

                // Swoole Redis subscribe returns: ['subscribe', 'channel', count] or ['message', 'channel', 'payload']
                if (is_array($msg) && $msg[0] === 'message') {
                    $callback($msg[1], $msg[2]);
                }
            }
        } catch (Throwable $e) {
            $this->logger->error("Redis subscription error: " . $e->getMessage());
        } finally {
            $this->client->close();
            $this->client = null;
        }
    }

    /**
     * Publish a message to a channel.
     */
    public function publish(string $channel, string $payload): bool
    {
        $publishClient = new Redis();
        if (!$publishClient->connect($this->host, $this->port)) {
            return false;
        }

        if ($this->password) {
            $publishClient->auth($this->password);
        }

        $publishClient->select($this->database);
        $result = $publishClient->publish($channel, $payload);
        $publishClient->close();

        return $result !== false;
    }
}
