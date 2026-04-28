<?php

namespace DGLab\Services\Auth\Guards;

use DGLab\Models\User;
use DGLab\Services\Auth\Contracts\AuthGuardInterface;
use DGLab\Core\Request;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Database\Connection;

class OpaqueTokenGuard implements AuthGuardInterface
{
    protected UserRepository $provider;
    protected Request $request;
    protected ?User $user = null;
    protected ?array $tokenData = null;
    public function __construct(UserRepository $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request = $request;
    }
    public function user(): ?User
    {
        if (!is_null($this->user)) {
            return $this->user;
        }
        $token = $this->getTokenFromRequest();
        if (!$token) {
            return null;
        }
        $tokenHash = hash('sha256', $token);
        $record = Connection::getInstance()->selectOne("SELECT * FROM personal_access_tokens WHERE token_hash = ? AND (expires_at IS NULL OR expires_at > ?)", [$tokenHash, date('Y-m-d H:i:s')]);
        if ($record) {
            $this->user = $this->provider->find($record['user_id']);
            $this->tokenData = $record;
            Connection::getInstance()->update("UPDATE personal_access_tokens SET last_used_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $record['id']]);
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
        return false;
    }
    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        return false;
    }
    public function login(User $user, bool $remember = false): mixed
    {
        $this->setUser($user);
        return null;
    }
    public function setUser(User $user): void
    {
        $this->user = $user;
    }
    public function can(string $permission, array $arguments = []): bool
    {
        $user = $this->user();
        return $user && $user->can($permission, $arguments) && $this->tokenCan($permission);
    }
    public function logout(): void
    {
        $token = $this->getTokenFromRequest();
        if ($token) {
            $tokenHash = hash('sha256', $token);
            Connection::getInstance()->delete("DELETE FROM personal_access_tokens WHERE token_hash = ?", [$tokenHash]);
        }
        $this->user = null;
    }
    public function createToken(User $user, string $name, array $abilities = ['*'], ?int $expiresInDays = null): string
    {
        $token = bin2hex(random_bytes(40));
        $tokenHash = hash('sha256', $token);
        $expiresAt = $expiresInDays ? date('Y-m-d H:i:s', time() + ($expiresInDays * 86400)) : null;
        Connection::getInstance()->insert("INSERT INTO personal_access_tokens (user_id, name, token_hash, abilities, expires_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)", [$user->id, $name, $tokenHash, json_encode($abilities), $expiresAt, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        return $token;
    }
    public function tokenCan(string $ability): bool
    {
        if (!$this->tokenData) {
            return false;
        }
        $abilities = json_decode($this->tokenData['abilities'], true) ?: [];
        return in_array('*', $abilities) || in_array($ability, $abilities);
    }
    protected function getTokenFromRequest(): ?string
    {
        $header = $this->request->getHeader('Authorization') ?: '';
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }
}
