<?php

namespace DGLab\Services\Auth;

use DGLab\Models\User;
use DGLab\Core\Application;
use InvalidArgumentException;

class Gate
{
    protected array $abilities = [];
    protected array $policies = [];
    protected ?User $userResolver = null;

    public function define(string $ability, callable $callback): void
    {
        $this->abilities[$ability] = $callback;
    }

    public function policy(string $class, string $policy): void
    {
        $this->policies[$class] = $policy;
    }

    public function check(string $ability, array $arguments = []): bool
    {
        $user = $this->resolveUser();
        if (!$user) return false;

        if (isset($this->abilities[$ability])) {
            return call_user_func($this->abilities[$ability], $user, ...$arguments);
        }

        if (!empty($arguments) && is_object($arguments[0])) {
            $class = get_class($arguments[0]);
            if (isset($this->policies[$class])) {
                $policyInstance = Application::getInstance()->get($this->policies[$class]);
                if (method_exists($policyInstance, $ability)) {
                    return call_user_func([$policyInstance, $ability], $user, ...$arguments);
                }
            }
        }

        // Default to AuthorizationService can()
        $authService = Application::getInstance()->get(AuthorizationService::class);
        return $authService->can($user, $ability);
    }

    public function allows(string $ability, array $arguments = []): bool
    {
        return $this->check($ability, $arguments);
    }

    public function denies(string $ability, array $arguments = []): bool
    {
        return !$this->allows($ability, $arguments);
    }

    protected function resolveUser(): ?User
    {
        $auth = Application::getInstance()->get(AuthManager::class);
        return $auth->guard()->user();
    }
}
