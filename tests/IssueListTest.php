<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\Test;

final class IssueListTest extends AppTestCase
{
    #[Test]
    public function list_returns_200_with_issues(): void
    {
        $res = $this->request('GET', '/issues');
        $this->assertSame(200, $res->status());
        $body = json_decode($res->body(), true);
        $this->assertArrayHasKey('issues', $body);
        $this->assertGreaterThanOrEqual(3, count($body['issues']));
    }

    #[Test]
    public function list_filters_by_status(): void
    {
        $res = $this->request('GET', '/issues?status=open');
        $this->assertSame(200, $res->status());
        $body = json_decode($res->body(), true);
        foreach ($body['issues'] as $issue) {
            $this->assertSame('open', $issue['status']);
        }
    }

    #[Test]
    public function list_includes_counts(): void
    {
        $res = $this->request('GET', '/issues');
        $body = json_decode($res->body(), true);
        $this->assertArrayHasKey('counts', $body);
        $this->assertArrayHasKey('total', $body['counts']);
        $this->assertArrayHasKey('open', $body['counts']);
    }

    #[Test]
    public function list_returns_html_when_browser(): void
    {
        $res = $this->request('GET', '/issues', headers: ['accept' => 'text/html']);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('text/html', $res->header('content-type'));
        $this->assertStringContainsString('IsTrBuddy', $res->body());
    }
}
