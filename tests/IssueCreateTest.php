<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\Test;

final class IssueCreateTest extends AppTestCase
{
    #[Test]
    public function editor_can_create_issue(): void
    {
        $this->loginAs('editor', ['editor']);
        $res = $this->request('POST', '/issues', [
            'title' => 'New test issue',
            'body' => 'This is a detailed description of the new test issue.',
            'priority' => 'high',
        ]);
        $this->assertSame(201, $res->status());
        $body = json_decode($res->body(), true);
        $this->assertSame('Created', $body['message']);
    }

    #[Test]
    public function admin_can_create_issue(): void
    {
        $this->loginAs('admin', ['admin', 'editor']);
        $res = $this->request('POST', '/issues', [
            'title' => 'Admin issue',
            'body' => 'Created by admin user with full permissions.',
            'priority' => 'medium',
        ]);
        $this->assertSame(201, $res->status());
    }

    #[Test]
    public function viewer_cannot_create_issue(): void
    {
        $this->loginAs('viewer', ['viewer']);
        $res = $this->request('POST', '/issues', [
            'title' => 'Should fail',
            'body' => 'Viewers cannot create issues in this system.',
        ]);
        $this->assertSame(403, $res->status());
    }

    #[Test]
    public function unauthenticated_cannot_create(): void
    {
        $res = $this->request('POST', '/issues', [
            'title' => 'Should fail',
            'body' => 'Anonymous users cannot create issues.',
        ]);
        $this->assertSame(401, $res->status());
    }

    #[Test]
    public function validation_rejects_short_title(): void
    {
        $this->loginAs('editor', ['editor']);
        $res = $this->request('POST', '/issues', [
            'title' => 'AB',
            'body' => 'This body is long enough to pass validation.',
            'priority' => 'medium',
        ]);
        $this->assertSame(422, $res->status());
        $body = json_decode($res->body(), true);
        $this->assertArrayHasKey('title', $body['errors']);
    }

    #[Test]
    public function validation_rejects_missing_body(): void
    {
        $this->loginAs('editor', ['editor']);
        $res = $this->request('POST', '/issues', [
            'title' => 'Valid title here',
            'body' => '',
        ]);
        $this->assertSame(422, $res->status());
        $body = json_decode($res->body(), true);
        $this->assertArrayHasKey('body', $body['errors']);
    }
}
