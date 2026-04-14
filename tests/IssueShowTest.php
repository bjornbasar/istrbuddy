<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\Test;

final class IssueShowTest extends AppTestCase
{
    #[Test]
    public function show_existing_issue(): void
    {
        $res = $this->request('GET', '/issues/1');
        $this->assertSame(200, $res->status());
        $body = json_decode($res->body(), true);
        $this->assertSame('Test bug', $body['title']);
    }

    #[Test]
    public function show_nonexistent_returns_404(): void
    {
        $res = $this->request('GET', '/issues/999');
        $this->assertSame(404, $res->status());
    }

    #[Test]
    public function show_returns_html_when_browser(): void
    {
        $res = $this->request('GET', '/issues/1', headers: ['accept' => 'text/html']);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Test bug', $res->body());
    }
}
