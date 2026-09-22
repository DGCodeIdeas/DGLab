<?php

namespace DGLab\Database;

/**
 * Download Token Model
 *
 * @property string $token
 * @property string $file_path
 * @property string $driver
 * @property string $expires_at
 * @property int $max_uses
 * @property int $use_count
 * @property string|null $ip_address
 * @property bool $enforce_ip
 * @property string|null $user_agent
 * @property bool $is_permanent
 */
class DownloadToken extends Model
{
    /**
     * @var string
     */
    protected ?string $table = 'download_tokens';

    /**
     * @var array
     */
    protected array $fillable = [
        'token',
        'file_path',
        'driver',
        'expires_at',
        'max_uses',
        'use_count',
        'ip_address',
        'enforce_ip',
        'user_agent',
        'is_permanent',
    ];

    /**
     * Find a valid token
     *
     * @param string $token
     * @return static|null
     */
    public static function findValid(string $token): ?static
    {
        $model = static::query()
            ->where('token', $token)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->first();

        if (!$model) {
            return null;
        }

        /** @var static $model */
        if ($model->getAttribute('use_count') >= $model->getAttribute('max_uses')) {
            return null;
        }

        return $model;
    }

    /**
     * Increment use count
     */
    public function incrementUse(): bool
    {
        $this->setAttribute('use_count', (int)$this->getAttribute('use_count') + 1);
        return $this->save();
    }

    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        return strtotime((string)$this->getAttribute('expires_at')) < time();
    }

    /**
     * Check if token has reached max uses
     */
    public function isConsumed(): bool
    {
        return (int)$this->getAttribute('use_count') >= (int)$this->getAttribute('max_uses');
    }
}
