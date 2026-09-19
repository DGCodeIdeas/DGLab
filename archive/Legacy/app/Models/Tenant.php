<?php

namespace DGLab\Models;

use DGLab\Database\Model;

/**
 * @property int $id
 * @property string $identifier
 * @property string $domain
 * @property string $config
 * @property string $status
 */
class Tenant extends Model
{
    protected ?string $table = 'tenants';
    protected array $fillable = ['identifier', 'domain', 'config', 'status'];

    public function isEnabled(): bool
    {
        return $this->status === 'active';
    }

    public function getConfig(): array
    {
        return json_decode($this->config ?? '{}', true);
    }
}
