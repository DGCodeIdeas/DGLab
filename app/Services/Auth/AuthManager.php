<?php

namespace DGLab\Services\Auth;

use DGLab\Core\Application;
use DGLab\Services\Auth\Contracts\AuthGuardInterface;
use InvalidArgumentException;

class AuthManager
{
    protected Application $app;
    protected array $guards = [];
    protected array $customCreators = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function guard(?string $name = null): AuthGuardInterface
    {
        $name = $name ?: $this->getDefaultDriver();
        if (!isset($this->guards[$name])) $this->guards[$name] = $this->resolve($name);
        return $this->guards[$name];
    }

    public function user() { return $this->guard()->user(); }
    public function id() { return $this->guard()->id(); }
    public function check() { return $this->guard()->check(); }

    public function can(string $ability, array $arguments = [])
    {
        return $this->app->get(Gate::class)->check($ability, $arguments);
    }

    protected function resolve(string $name): AuthGuardInterface
    {
        $config = config("auth.guards.{$name}");
        if (is_null($config)) throw new InvalidArgumentException("Auth guard [{$name}] is not defined.");
        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';
        if (method_exists($this, $driverMethod)) return $this->{$driverMethod}($name, $config);
        throw new InvalidArgumentException("Auth driver [{$config['driver']}] for guard [{$name}] is not supported.");
    }

    protected function createSessionDriver(string $name, array $config)
    {
        $provider = $this->createUserProvider($config['provider'] ?? null);
        return new \DGLab\Services\Auth\Guards\SessionGuard($name, $provider, $this->app->get(\DGLab\Core\Request::class));
    }

    protected function createTokenDriver(string $name, array $config)
    {
        $provider = $this->createUserProvider($config['provider'] ?? null);
        return new \DGLab\Services\Auth\Guards\OpaqueTokenGuard($provider, $this->app->get(\DGLab\Core\Request::class));
    }

    protected function createJwtDriver(string $name, array $config)
    {
        $provider = $this->createUserProvider($config['provider'] ?? null);
        return new \DGLab\Services\Auth\Guards\JwtGuard($provider, $this->app->get(\DGLab\Core\Request::class), $this->app->get(JWTService::class));
    }

    public function createUserProvider(?string $provider = null)
    {
        $config = config("auth.providers." . ($provider ?: 'users'));
        if (is_null($config)) throw new InvalidArgumentException("Authentication user provider [{$provider}] is not defined.");
        if ($config['driver'] === 'database') return $this->app->get(\DGLab\Services\Auth\Repositories\UserRepository::class);
        throw new InvalidArgumentException("Authentication user provider driver [{$config['driver']}] is not supported.");
    }

    public function getDefaultDriver(): string { return config('auth.defaults.guard', 'web'); }
    public function __call(string $method, array $parameters) { return $this->guard()->$method(...$parameters); }
}
