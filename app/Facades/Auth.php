<?php

namespace DGLab\Facades;

use DGLab\Core\Application;
use DGLab\Services\Auth\AuthManager;
use DGLab\Services\Auth\Contracts\AuthGuardInterface;
use DGLab\Models\User;

/**
 * Auth Facade
 */
class Auth
{
    protected static ?AuthManager $manager = null;

    protected static function getManager(): AuthManager
    {
        if (static::$manager === null) {
            static::$manager = Application::getInstance()->get(AuthManager::class);
        }
        return static::$manager;
    }

    public static function guard(?string $name = null): AuthGuardInterface
    {
        return static::getManager()->guard($name);
    }

    public static function user(): ?User
    {
        return static::getManager()->user();
    }

    public static function id(): int|string|null
    {
        return static::getManager()->id();
    }

    public static function check(): bool
    {
        return static::getManager()->check();
    }

    public static function attempt(array $credentials = [], bool $remember = false): bool
    {
        return static::getManager()->attempt($credentials, $remember);
    }

    public static function logout(): void
    {
        static::getManager()->logout();
    }

    public static function login(User $user, bool $remember = false): void
    {
        static::getManager()->guard()->login($user, $remember);
    }

    public static function can(string $ability, array $arguments = []): bool
    {
        return static::getManager()->can($ability, $arguments);
    }
}
