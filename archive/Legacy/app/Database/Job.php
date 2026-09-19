<?php

/**
 * DGLab Job Model
 *
 * Represents an async processing job for services.
 *
 * @package DGLab\Database
 */

namespace DGLab\Database;

/**
 * Class Job
 *
 * Job model for tracking service processing jobs.
 *
 * @property string $service_id
 * @property string $status
 * @property array $input_data
 * @property array $output_data
 * @property int $progress
 * @property string|null $message
 * @property string|null $started_at
 * @property string|null $completed_at
 */
class Job extends Model
{
    /**
     * Table name
     */
    protected ?string $table = 'jobs';

    /**
     * Fillable attributes
     */
    protected array $fillable = [
        'service_id',
        'status',
        'input_data',
        'output_data',
        'progress',
        'message',
        'started_at',
        'completed_at',
    ];

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Create a new job
     */
    public static function createForService(string $serviceId, array $inputData = []): self
    {
        return self::create([
            'service_id' => $serviceId,
            'status' => self::STATUS_PENDING,
            'input_data' => $inputData,
            'progress' => 0,
        ]);
    }

    /**
     * Mark as processing
     */
    public function markProcessing(): self
    {
        $this->status = self::STATUS_PROCESSING;
        $this->started_at = date('Y-m-d H:i:s');
        $this->save();

        return $this;
    }

    /**
     * Mark as completed
     */
    public function markCompleted(array $outputData = [], ?string $message = null): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->output_data = $outputData;
        $this->progress = 100;
        $this->message = $message;
        $this->completed_at = date('Y-m-d H:i:s');
        $this->save();

        return $this;
    }

    /**
     * Mark as failed
     */
    public function markFailed(string $message): self
    {
        $this->status = self::STATUS_FAILED;
        $this->message = $message;
        $this->completed_at = date('Y-m-d H:i:s');
        $this->save();

        return $this;
    }

    /**
     * Mark as cancelled
     */
    public function markCancelled(?string $message = null): self
    {
        $this->status = self::STATUS_CANCELLED;
        $this->message = $message;
        $this->completed_at = date('Y-m-d H:i:s');
        $this->save();

        return $this;
    }

    /**
     * Update progress
     */
    public function updateProgress(int $percent, ?string $message = null): self
    {
        $this->progress = min(100, max(0, $percent));

        if ($message !== null) {
            $this->message = $message;
        }

        $this->save();

        return $this;
    }

    /**
     * Check if job is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if job is processing
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if job is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if job is failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if job is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if job is finished (completed, failed, or cancelled)
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ], true);
    }

    /**
     * Get pending jobs
     */
    public static function pending(): array
    {
        return self::query()->where('status', self::STATUS_PENDING)->get();
    }

    /**
     * Get processing jobs
     */
    public static function processing(): array
    {
        return self::query()->where('status', self::STATUS_PROCESSING)->get();
    }

    /**
     * Get jobs by service
     */
    public static function byService(string $serviceId): array
    {
        return self::query()->where('service_id', $serviceId)->orderBy('created_at', 'DESC')->get();
    }

    /**
     * Get recent jobs
     */
    public static function recent(int $limit = 10): array
    {
        return self::query()->orderBy('created_at', 'DESC')->limit($limit)->get();
    }

    /**
     * Clean up old jobs
     */
    public static function cleanup(int $days = 30): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return self::query()
            ->where('created_at', '<', $cutoff)
            ->where('status', '!=', self::STATUS_PROCESSING)
            ->delete();
    }
}
