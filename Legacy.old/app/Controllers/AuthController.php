<?php

namespace DGLab\Controllers;

use DGLab\Core\BaseController;
use DGLab\Core\Response;
use DGLab\Core\Request;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Services\Auth\AuthManager;

class AuthController extends BaseController
{
    protected UserRepository $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function showLogin(): Response
    {
        return view('auth.login');
    }

    public function register(Request $request): Response
    {
        $data = $request->only(['email', 'username', 'password']);
        $data['password_hash'] = password_hash($data['password'] ?? 'secret', PASSWORD_DEFAULT);
        unset($data['password']);
        $data['status'] = 'active';

        $user = $this->users->create($data);
        $user->password_hash = $data['password_hash'];
        $user->save();

        event('auth.registered', ['user_id' => $user->id]);

        return json(['message' => 'Registered successfully', 'user' => $user->toArray()], 201);
    }

    public function login(Request $request): Response
    {
        $credentials = $request->only(['email', 'password']);
        $auth = \DGLab\Core\Application::getInstance()->get(AuthManager::class);

        if ($auth->attempt($credentials)) {
            $user = $auth->user();
            $result = $auth->guard()->login($user);

            if ($request->expectsJson()) {
                return json(['token' => $result]);
            }

            return redirect('/dashboard');
        }

        if ($request->expectsJson()) {
            return json(['error' => 'Invalid credentials'], 401);
        }

        return view('auth.login', ['error' => 'Invalid credentials']);
    }

    public function me(Request $request): Response
    {
        $auth = \DGLab\Core\Application::getInstance()->get(AuthManager::class);
        $user = $auth->user();

        if (!$user) {
            return json(['error' => 'Unauthorized'], 401);
        }

        return json(['user' => $user->toArray()]);
    }
}
