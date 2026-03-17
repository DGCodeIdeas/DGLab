<?php
namespace DGLab\Services\Auth\Contracts;
use DGLab\Models\User;
interface AuthGuardInterface {
    public function user(): ?User;
    public function id(): int|string|null;
    public function check(): bool;
    public function guest(): bool;
    public function validate(array $credentials = []): bool;
    public function setUser(User $user): void;
    public function logout(): void;
}
