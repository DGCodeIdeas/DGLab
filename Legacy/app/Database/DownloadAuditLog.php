<?php

namespace DGLab\Database;

/**
 * Download Audit Log Model
 *
 * @property string $file_path
 * @property string $driver
 * @property int $status_code
 * @property string|null $error_message
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int $download_time_ms
 * @property int $bytes_served
 */
class DownloadAuditLog extends Model
{
    /**
     * @var string
     */
    protected ?string $table = 'download_logs';

    /**
     * @var array
     */
    protected array $fillable = [
        'file_path',
        'driver',
        'status_code',
        'error_message',
        'ip_address',
        'user_agent',
        'download_time_ms',
        'bytes_served',
    ];

    /**
     * @var bool
     */
    protected bool $timestamps = false;

    /**
     * Set the created_at timestamp on insert
     */
    protected function insert(): bool
    {
        $this->setAttribute('created_at', date($this->dateFormat));
        return parent::insert();
    }
}
