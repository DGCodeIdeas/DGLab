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
        parent::__construct();
        $this->users = $users;
    }

    public function showLogin(Request $request): Response
    {
        return $this->view('auth/login');
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

        if ($request->expectsJson()) {
            return $this->json(['message' => 'Registered successfully', 'user' => $user->toArray()], 201);
        }

        return $this->redirect('/login');
    }

    public function login(Request $request): Response
    {
        $credentials = $request->only(['email', 'password']);
        $auth = $this->auth();

        if ($auth->attempt($credentials)) {
            $user = $auth->user();
            $token = $auth->guard()->login($user);

            if ($request->expectsJson()) {
                return $this->json(['token' => $token]);
            }

            return $this->redirect('/');
        }

        if ($request->expectsJson()) {
            return $this->json(['error' => 'Invalid credentials'], 401);
        }

        return $this->view('auth/login', ['error' => 'Invalid credentials']);
    }

    public function me(Request $request): Response
    {
        $auth = $this->auth();
        $user = $auth->user();

        if (!$user) {
            if ($request->expectsJson()) {
                return $this->json(['error' => 'Unauthorized'], 401);
            }
            return $this->redirect('/login');
        }

        return $this->json(['user' => $user->toArray()]);
    }

    public function logout(Request $request): Response
    {
        $this->auth()->logout();
        return $this->redirect('/login');
    }
}
