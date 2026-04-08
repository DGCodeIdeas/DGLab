<?php

namespace DGLab\Services\Nexus;

use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class NexusClient
 *
 * Backend client for interacting with Nexus via Redis Pub/Sub.
 */
class NexusClient
{
    protected RedisClient $redis;
    protected LoggerInterface $logger;
    protected string $channel;

    public function __construct(array $config, LoggerInterface $logger, string $channel = 'nexus_broadcast')
    {
        $this->redis = new RedisClient([
            'scheme' => 'tcp',
            'host'   => $config['host'] ?? '127.0.0.1',
            'port'   => $config['port'] ?? 6379,
            'password' => $config['password'] ?? null,
            'database' => $config['database'] ?? 0,
        ]);
        $this->logger = $logger;
        $this->channel = $channel;
    }

    /**
     * Publish a message to Nexus.
     *
     * @param string $topic The routing topic (e.g., 'user.1.console')
     * @param array $payload The message data
     * @return bool
     */
    public function publish(string $topic, array $payload): bool
    {
        try {
            $packet = json_encode([
                'topic' => $topic,
                'payload' => $payload,
                'meta' => [
                    'timestamp' => microtime(true),
                    'request_id' => uuid(),
                ]
            ]);

            $this->redis->publish($this->channel, $packet);
            $this->logger->debug("Published to Nexus topic {$topic}");
            return true;
        } catch (Throwable $e) {
            $this->logger->error("Failed to publish to Nexus: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper to broadcast a console log.
     */
    public function console(string $userId, string $line, string $level = 'info'): void
    {
        $this->publish("user.{$userId}.console", [
            'line' => $line,
            'level' => $level,
            'userId' => $userId
        ]);
    }

    /**
     * Helper to broadcast job progress.
     */
    public function jobProgress(string $jobId, float $progress, string $message, ?string $userId = null): void
    {
        $this->publish("job.{$jobId}.progress", [
            'jobId' => $jobId,
            'progress' => $progress,
            'message' => $message,
            'userId' => $userId
        ]);
    }
}
