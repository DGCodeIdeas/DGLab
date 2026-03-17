<?php

namespace DGLab\Models;

use DGLab\Database\Model;
use DGLab\Core\Application;
use DGLab\Services\Auth\AuthorizationService;
use DGLab\Services\Auth\Gate;

class User extends Model
{
    protected ?string $table = 'users';
    protected array $fillable = ['uuid', 'email', 'username', 'phone_number', 'password_hash', 'password_algo', 'display_name', 'avatar_url', 'status', 'mfa_enabled', 'last_login_at'];
    protected array $guarded = ['id', 'password_hash'];

    public function can(string $permission, array $arguments = []): bool
    {
        if (empty($arguments)) {
            return Application::getInstance()->get(AuthorizationService::class)->can($this, $permission);
        }
        return Application::getInstance()->get(Gate::class)->check($permission, array_merge([$this], $arguments));
    }

    public function hasRole(string $role): bool
    {
        return Application::getInstance()->get(AuthorizationService::class)->hasRole($this, $role);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && (!isset($this->attributes['deleted_at']) || $this->attributes['deleted_at'] === null);
    }

    public function delete(): bool
    {
        if (!$this->exists) return false;
        $this->attributes['deleted_at'] = date($this->dateFormat);
        return $this->update();
    }
}
