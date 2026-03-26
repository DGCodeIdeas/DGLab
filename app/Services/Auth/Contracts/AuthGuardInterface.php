<?php

namespace DGLab\Services\Auth\Contracts;

use DGLab\Models\User;

interface AuthGuardInterface
{
    public function user(): ?User;
    public function id(): int|string|null;
    public function check(): bool;
    public function guest(): bool;
    public function validate(array $credentials = []): bool;
    public function attempt(array $credentials = [], bool $remember = false): bool;
    public function setUser(User $user): void;
    public function login(User $user, bool $remember = false): mixed;
    public function logout(): void;
}
