<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\Test;

final class AuthTest extends AppTestCase
{
    #[Test]
    public function login_with_valid_credentials(): void
    {
        $res = $this->request('POST', '/login', ['username' => 'admin', 'password' => 'admin123']);
        $this->assertSame(200, $res->status());
        $body = json_decode($res->body(), true);
        $this->assertSame('Logged in', $body['message']);
        $this->assertSame('admin', $body['user']['username']);
    }

    #[Test]
    public function login_with_wrong_password(): void
    {
        $res = $this->request('POST', '/login', ['username' => 'admin', 'password' => 'wrong']);
        $this->assertSame(401, $res->status());
    }

    #[Test]
    public function login_with_unknown_user(): void
    {
        $res = $this->request('POST', '/login', ['username' => 'nobody', 'password' => 'pass']);
        $this->assertSame(401, $res->status());
    }

    #[Test]
    public function logout_clears_session(): void
    {
        $this->loginAs('admin', ['admin']);
        $res = $this->request('POST', '/logout');
        $this->assertSame(200, $res->status());
        $this->assertArrayNotHasKey('username', $_SESSION);
    }
}
