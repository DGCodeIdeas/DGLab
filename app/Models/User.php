<?php

namespace DGLab\Models;

use DGLab\Database\Model;

/**
 * User Model
 *
 * Represents a global identity in the system.
 */
class User extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string|null
     */
    protected ?string $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected array $fillable = [
        'uuid',
        'email',
        'username',
        'phone_number',
        'password_hash',
        'password_algo',
        'display_name',
        'avatar_url',
        'status',
        'mfa_enabled',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected array $guarded = [
        'id',
        'password_hash',
    ];

    /**
     * User status constants
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_PENDING_VERIFICATION = 'pending_verification';

    /**
     * Check if user is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && (!isset($this->attributes['deleted_at']) || $this->attributes['deleted_at'] === null);
    }

    /**
     * Check if user has MFA enabled
     *
     * @return bool
     */
    public function hasMfa(): bool
    {
        return (bool) $this->mfa_enabled;
    }

    /**
     * Get the password hash
     *
     * @return string
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * Overriding delete to support soft deletes if column exists
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $now = date($this->dateFormat);
        $this->attributes['deleted_at'] = $now;

        return $this->update();
    }

    /**
     * Restore a soft-deleted model
     *
     * @return bool
     */
    public function restore(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $this->attributes['deleted_at'] = null;

        return $this->update();
    }
}
