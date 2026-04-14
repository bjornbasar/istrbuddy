<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\Test;

final class IssueStatusTest extends AppTestCase
{
    #[Test]
    public function update_status_succeeds(): void
    {
        $this->loginAs('editor', ['editor']);
        $res = $this->request('POST', '/issues/1/status', ['status' => 'in_progress']);
        $this->assertSame(200, $res->status());
        $body = json_decode($res->body(), true);
        $this->assertSame('in_progress', $body['status']);
    }

    #[Test]
    public function invalid_status_rejected(): void
    {
        $this->loginAs('editor', ['editor']);
        $res = $this->request('POST', '/issues/1/status', ['status' => 'banana']);
        $this->assertSame(422, $res->status());
    }
}
