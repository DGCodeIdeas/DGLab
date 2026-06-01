<?php

namespace DGLab\Services\Auth;

use DGLab\Core\Application;
use DGLab\Core\AuditService;
use DGLab\Services\Auth\Contracts\AuthGuardInterface;
use DGLab\Core\Contracts\DispatcherInterface;
use InvalidArgumentException;
use DGLab\Core\Request;

class AuthManager
{
    protected Application $app;
    protected array $guards = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function guard(?string $name = null): AuthGuardInterface
    {
        $name = $name ?: $this->app->config('auth.defaults.guard', 'web');
        if (!isset($this->guards[$name])) {
            $config = $this->app->config("auth.guards.{$name}");
            if (!$config) {
                throw new InvalidArgumentException("Guard [{$name}] not defined.");
            }
            $driver = $config['driver'];
            $provider = $this->app->get(\DGLab\Services\Auth\Repositories\UserRepository::class);
            $req = $this->app->get(Request::class);

            if ($driver === 'session') {
                $this->guards[$name] = new \DGLab\Services\Auth\Guards\SessionGuard($name, $provider, $req);
            } elseif ($driver === 'jwt') {
                $this->guards[$name] = new \DGLab\Services\Auth\Guards\JwtGuard($provider, $req, $this->app->get(JWTService::class));
            } else {
                $this->guards[$name] = new \DGLab\Services\Auth\Guards\OpaqueTokenGuard($provider, $req);
            }
        }
        return $this->guards[$name];
    }

    public function user()
    {
        return $this->guard()->user();
    }
    public function id()
    {
        return $this->guard()->id();
    }
    public function check()
    {
        return $this->guard()->check();
    }

    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        $guard = $this->guard();
        if ($guard->attempt($credentials, $remember)) {
            $user = $guard->user();
            if ($user) {
                if ($this->app->has(AuditService::class)) {
                    $this->app->get(AuditService::class)->log('auth', 'auth.login.success', $user->email);
                }
                $this->app->get(DispatcherInterface::class)->dispatch(new \DGLab\Core\GenericEvent('auth.login.success', ['user_id' => $user->id]));
            }
            return true;
        }
        $idnt = $credentials['email'] ?? ($credentials['login'] ?? 'unknown');
        if ($this->app->has(AuditService::class)) {
            $this->app->get(AuditService::class)->log('auth', 'auth.login.failed', $idnt);
        }
        $this->app->get(DispatcherInterface::class)->dispatch(new \DGLab\Core\GenericEvent('auth.login.failed', ['identifier' => $idnt]));
        return false;
    }

    public function logout(): void
    {
        $user = $this->user();
        if ($user) {
            if ($this->app->has(AuditService::class)) {
                $this->app->get(AuditService::class)->log('auth', 'auth.logout', $user->email);
            }
            $this->app->get(DispatcherInterface::class)->dispatch(new \DGLab\Core\GenericEvent('auth.logout', ['user_id' => $user->id]));
        }
        $this->guard()->logout();
    }

    public function can(string $permission, array $arguments = []): bool
    {
        return $this->guard()->can($permission, $arguments);
    }
}
