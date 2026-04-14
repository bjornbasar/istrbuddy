<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Views\Layout;
use Karhu\Attributes\Route;
use Karhu\Auth\PasswordHasher;
use Karhu\Auth\Rbac;
use Karhu\Http\Request;
use Karhu\Http\Response;
use Karhu\Middleware\Csrf;
use Karhu\Middleware\Session;

final class AuthController
{
    public function __construct(
        private readonly Rbac $rbac,
        private readonly PasswordHasher $hasher,
    ) {}

    /** Show login form / handle login. */
    #[Route('/login', methods: ['GET'], name: 'login')]
    public function showLogin(Request $request): Response
    {
        if (Session::has('username')) {
            return (new Response())->redirect('/issues');
        }

        return (new Response())
            ->withHeader('Content-Type', 'text/html')
            ->withBody(Layout::render('Login', $this->loginForm()));
    }

    #[Route('/login', methods: ['POST'])]
    public function handleLogin(Request $request): Response
    {
        $body = is_array($request->body()) ? $request->body() : [];
        $username = (string) ($body['username'] ?? $request->post('username'));
        $password = (string) ($body['password'] ?? $request->post('password'));

        $user = $this->rbac->authenticate($username, $password, $this->hasher);

        if ($user === null) {
            // JSON clients
            if ($request->accepts('application/json') && !$request->accepts('text/html')) {
                return (new Response(401))->json(['error' => 'Invalid credentials'], 401);
            }

            return (new Response())
                ->withHeader('Content-Type', 'text/html')
                ->withBody(Layout::render('Login', $this->loginForm('Invalid username or password.')));
        }

        Session::set('username', $user['username']);
        Session::set('roles', $user['roles']);
        Session::regenerate();

        if ($request->accepts('application/json') && !$request->accepts('text/html')) {
            return (new Response())->json(['message' => 'Logged in', 'user' => $user]);
        }

        return (new Response())->redirect('/issues');
    }

    #[Route('/logout', methods: ['POST'], name: 'logout')]
    public function logout(Request $request): Response
    {
        Session::destroy();

        if ($request->accepts('application/json') && !$request->accepts('text/html')) {
            return (new Response())->json(['message' => 'Logged out']);
        }

        return (new Response())->redirect('/login');
    }

    private function loginForm(string $error = ''): string
    {
        $errorHtml = $error !== '' ? "<p class=\"error\">{$error}</p>" : '';
        $csrf = Csrf::field();
        return <<<HTML
        <div class="auth-form">
            <h2>Sign In</h2>
            {$errorHtml}
            <form method="POST" action="/login">
                {$csrf}
                <label>Username<input type="text" name="username" required autofocus></label>
                <label>Password<input type="password" name="password" required></label>
                <button type="submit">Login</button>
            </form>
            <p class="hint">Demo: admin/admin123, editor/editor123, viewer/viewer123</p>
        </div>
        HTML;
    }
}
