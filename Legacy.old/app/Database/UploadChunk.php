<?php

/**
 * DGLab Upload Chunk Model
 *
 * Represents a chunked upload session.
 *
 * @package DGLab\Database
 */

namespace DGLab\Database;

/**
 * Class UploadChunk
 *
 * Model for tracking chunked upload sessions.
 *
 * @property string $session_id
 * @property string $service_id
 * @property string $filename
 * @property int $file_size
 * @property int $chunk_size
 * @property int $total_chunks
 * @property int $received_chunks
 * @property array $chunks
 * @property array $metadata
 * @property string $status
 * @property string $expires_at
 */
class UploadChunk extends Model
{
    /**
     * Table name
     */
    protected ?string $table = 'upload_chunks';

    /**
     * Fillable attributes
     */
    protected array $fillable = [
        'session_id',
        'service_id',
        'filename',
        'file_size',
        'chunk_size',
        'total_chunks',
        'received_chunks',
        'chunks',
        'metadata',
        'status',
        'expires_at',
    ];

    /**
     * Status constants
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Create a new upload session
     */
    public static function createSession(
        string $serviceId,
        string $filename,
        int $fileSize,
        int $chunkSize,
        array $metadata = []
    ): self {
        $sessionId = bin2hex(random_bytes(32));
        $totalChunks = (int) ceil($fileSize / $chunkSize);

        return self::create([
            'session_id' => $sessionId,
            'service_id' => $serviceId,
            'filename' => $filename,
            'file_size' => $fileSize,
            'chunk_size' => $chunkSize,
            'total_chunks' => $totalChunks,
            'received_chunks' => 0,
            'chunks' => [],
            'metadata' => $metadata,
            'status' => self::STATUS_ACTIVE,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ]);
    }

    /**
     * Record a received chunk
     */
    public function recordChunk(int $chunkIndex, string $chunkPath): self
    {
        $chunks = $this->chunks ?? [];
        $chunks[$chunkIndex] = [
            'path' => $chunkPath,
            'received_at' => date('Y-m-d H:i:s'),
        ];

        $this->chunks = $chunks;
        $this->received_chunks = count($chunks);

        // Check if all chunks received
        if ($this->received_chunks >= $this->total_chunks) {
            $this->status = self::STATUS_COMPLETED;
        }

        $this->save();

        return $this;
    }

    /**
     * Check if a chunk has been received
     */
    public function hasChunk(int $chunkIndex): bool
    {
        $chunks = $this->chunks ?? [];

        return isset($chunks[$chunkIndex]);
    }

    /**
     * Get missing chunk indices
     */
    public function getMissingChunks(): array
    {
        $chunks = $this->chunks ?? [];
        $missing = [];

        for ($i = 0; $i < $this->total_chunks; $i++) {
            if (!isset($chunks[$i])) {
                $missing[] = $i;
            }
        }

        return $missing;
    }

    /**
     * Get progress percentage
     */
    public function getProgress(): int
    {
        return (int) round(($this->received_chunks / $this->total_chunks) * 100);
    }

    /**
     * Mark as expired
     */
    public function markExpired(): self
    {
        $this->status = self::STATUS_EXPIRED;
        $this->save();

        // Clean up chunk files
        $this->cleanupChunks();

        return $this;
    }

    /**
     * Mark as cancelled
     */
    public function markCancelled(): self
    {
        $this->status = self::STATUS_CANCELLED;
        $this->save();

        // Clean up chunk files
        $this->cleanupChunks();

        return $this;
    }

    /**
     * Clean up chunk files
     */
    public function cleanupChunks(): void
    {
        $chunks = $this->chunks ?? [];

        foreach ($chunks as $chunk) {
            if (isset($chunk['path']) && file_exists($chunk['path'])) {
                unlink($chunk['path']);
            }
        }

        $this->chunks = [];
        $this->save();
    }

    /**
     * Check if session is expired
     */
    public function isExpired(): bool
    {
        return strtotime($this->expires_at) < time();
    }

    /**
     * Check if all chunks are received
     */
    public function isComplete(): bool
    {
        return $this->received_chunks >= $this->total_chunks;
    }

    /**
     * Reassemble chunks into a single file
     */
    public function reassemble(string $outputPath): bool
    {
        if (!$this->isComplete()) {
            return false;
        }

        $chunks = $this->chunks ?? [];

        // Sort chunks by index
        ksort($chunks);

        $output = fopen($outputPath, 'wb');

        if (!$output) {
            return false;
        }

        foreach ($chunks as $chunk) {
            if (!isset($chunk['path']) || !file_exists($chunk['path'])) {
                fclose($output);
                return false;
            }

            $data = file_get_contents($chunk['path']);
            fwrite($output, $data);
        }

        fclose($output);

        return true;
    }

    /**
     * Find by session ID
     */
    public static function findBySessionId(string $sessionId): ?self
    {
        /** @var self|null $model */
        $model = self::query()->where('session_id', $sessionId)->first();
        return $model;
    }

    /**
     * Get active sessions
     */
    public static function active(): array
    {
        return self::query()
            ->where('status', self::STATUS_ACTIVE)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->get();
    }

    /**
     * Get expired sessions
     */
    public static function expired(): array
    {
        return self::query()
            ->where('status', self::STATUS_ACTIVE)
            ->where('expires_at', '<', date('Y-m-d H:i:s'))
            ->get();
    }

    /**
     * Clean up expired sessions
     */
    public static function cleanupExpired(): int
    {
        $expired = self::expired();

        foreach ($expired as $session) {
            $session->markExpired();
        }

        return count($expired);
    }

    /**
     * Extend expiration
     */
    public function extendExpiration(int $hours = 24): self
    {
        $this->expires_at = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
        $this->save();

        return $this;
    }
}
