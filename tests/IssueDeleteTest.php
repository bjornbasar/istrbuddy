<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\Test;

final class IssueDeleteTest extends AppTestCase
{
    #[Test]
    public function admin_can_delete(): void
    {
        $this->loginAs('admin', ['admin', 'editor']);
        $res = $this->request('POST', '/issues/1/delete');
        // 204 or redirect — both are success
        $this->assertTrue(in_array($res->status(), [200, 204], true));
    }

    #[Test]
    public function editor_cannot_delete(): void
    {
        $this->loginAs('editor', ['editor']);
        $res = $this->request('POST', '/issues/2/delete');
        $this->assertSame(403, $res->status());
    }

    #[Test]
    public function viewer_cannot_delete(): void
    {
        $this->loginAs('viewer', ['viewer']);
        $res = $this->request('POST', '/issues/2/delete');
        $this->assertSame(403, $res->status());
    }

    #[Test]
    public function unauthenticated_cannot_delete(): void
    {
        $res = $this->request('POST', '/issues/2/delete');
        $this->assertSame(401, $res->status());
    }
}
