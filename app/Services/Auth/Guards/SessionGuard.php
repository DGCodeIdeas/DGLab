<?php

namespace DGLab\Services\Auth\Guards;

use DGLab\Models\User;
use DGLab\Services\Auth\Contracts\AuthGuardInterface;
use DGLab\Core\Request;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Database\Connection;

class SessionGuard implements AuthGuardInterface
{
    protected string $name;
    protected UserRepository $provider;
    protected Request $request;
    protected ?User $user = null;
    protected bool $loggedOut = false;

    public function __construct(string $name, UserRepository $provider, Request $request)
    {
        $this->name = $name;
        $this->provider = $provider;
        $this->request = $request;

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public function user(): ?User
    {
        if ($this->loggedOut) {
            return null;
        }
        if (!is_null($this->user)) {
            return $this->user;
        }

        $id = $_SESSION[$this->getName()] ?? null;
        if (!is_null($id)) {
            $this->user = $this->provider->find($id);
        }

        if (is_null($this->user)) {
            $this->user = $this->recallFromRememberCookie();
        }

        return $this->user;
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
    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->findByIdentifier($credentials['login'] ?? '');
        return $user && password_verify($credentials['password'] ?? '', $user->password_hash);
    }

    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        $user = $this->provider->findByIdentifier($credentials['login'] ?? '');
        if ($user && password_verify($credentials['password'] ?? '', $user->password_hash)) {
            $this->login($user, $remember);
            return true;
        }
        return false;
    }

    public function login(User $user, bool $remember = false): mixed
    {
        $this->updateSession($user->id);
        if ($remember) {
            $this->ensureRememberTokenIsSet($user);
        }
        $this->setUser($user);
        return null;
    }

    protected function updateSession(int $id): void
    {
        $_SESSION[$this->getName()] = $id;
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
        $this->loggedOut = false;
    }

    public function logout(): void
    {
        $this->clearUserDataFromStorage();
        $this->user = null;
        $this->loggedOut = true;
    }

    protected function clearUserDataFromStorage(): void
    {
        unset($_SESSION[$this->getName()]);

        $cookieName = $this->getRememberTokenName();
        if (isset($_COOKIE[$cookieName])) {
            $token = $_COOKIE[$cookieName];
            Connection::getInstance()->delete("DELETE FROM remember_tokens WHERE token = ?", [$token]);
            if (!headers_sent()) {
                setcookie($cookieName, '', time() - 3600, '/');
            }
        }
    }

    protected function getName(): string
    {
        return 'login_' . $this->name . '_' . sha1(static::class);
    }
    protected function getRememberTokenName(): string
    {
        return 'remember_' . $this->name . '_' . sha1(static::class);
    }

    protected function recallFromRememberCookie(): ?User
    {
        $token = $_COOKIE[$this->getRememberTokenName()] ?? null;
        if (!$token) {
            return null;
        }

        $record = Connection::getInstance()->selectOne(
            "SELECT user_id FROM remember_tokens WHERE token = ? AND expires_at > ?",
            [$token, date('Y-m-d H:i:s')]
        );

        if ($record) {
            $user = $this->provider->find($record['user_id']);
            if ($user) {
                $this->updateSession($user->id);
                return $user;
            }
        }

        return null;
    }

    protected function ensureRememberTokenIsSet(User $user): void
    {
        $token = bin2hex(random_bytes(40));
        $expires = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);

        Connection::getInstance()->insert(
            "INSERT INTO remember_tokens (user_id, token, expires_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?)",
            [$user->id, $token, $expires, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );

        if (!headers_sent()) {
            setcookie($this->getRememberTokenName(), $token, time() + 60 * 60 * 24 * 30, '/', '', false, true);
        }
    }
}
