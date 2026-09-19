<?php

namespace DGLab\Services\Auth\Guards;

use DGLab\Models\User;
use DGLab\Services\Auth\Contracts\AuthGuardInterface;
use DGLab\Core\Request;
use DGLab\Services\Auth\Repositories\UserRepository;

class SessionGuard implements AuthGuardInterface
{
    protected string $name;
    protected UserRepository $provider;
    protected Request $request;
    protected ?User $user = null;

    public function __construct(string $name, UserRepository $provider, Request $request)
    {
        $this->name = $name;
        $this->provider = $provider;
        $this->request = $request;
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    public function user(): ?User
    {
        if ($this->user) {
            return $this->user;
        }
        $id = $_SESSION[$this->name] ?? null;
        return $id ? $this->user = $this->provider->find($id) : null;
    }
    public function id(): int|string|null
    {
        return $this->user() ? $this->user()->id : null;
    }
    public function check(): bool
    {
        return !is_null($this->user());
    }
    public function guest(): bool
    {
        return !$this->check();
    }
    public function validate(array $c = []): bool
    {
        $u = $this->provider->findByIdentifier($c['email'] ?? ($c['login'] ?? ''));
        return $u && password_verify($c['password'] ?? '', $u->password_hash);
    }
    public function attempt(array $c = [], bool $r = false): bool
    {
        if ($this->validate($c)) {
            $u = $this->provider->findByIdentifier($c['email'] ?? ($c['login'] ?? ''));
            if ($u) {
                $this->login($u, $r);
            }
            return true;
        }
        return false;
    }
    public function login(User $u, bool $r = false): mixed
    {
        $_SESSION[$this->name] = $u->id;
        $this->user = $u;
        return null;
    }
    public function setUser(User $u): void
    {
        $this->user = $u;
    }
    public function can(string $permission, array $arguments = []): bool
    {
        $user = $this->user();
        return $user && $user->can($permission, $arguments);
    }
    public function logout(): void
    {
        unset($_SESSION[$this->name]);
        $this->user = null;
    }
}
