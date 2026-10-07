<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repository\IssueRepository;
use App\Views\Layout;
use Karhu\Attributes\Route;
use Karhu\Http\Request;
use Karhu\Http\Response;
use Karhu\Http\Validation;
use Karhu\Middleware\Csrf;
use Karhu\Middleware\Session;

final class IssueController
{
    public function __construct(
        private readonly IssueRepository $issues,
    ) {}

    /** List issues — filterable by status. */
    #[Route('/issues', methods: ['GET'], name: 'issues.index')]
    public function index(Request $request): Response
    {
        $status = $request->query('status') ?: null;
        $issues = $this->issues->all($status);
        $counts = [
            'total' => $this->issues->count(),
            'open' => $this->issues->count('open'),
            'in_progress' => $this->issues->count('in_progress'),
            'closed' => $this->issues->count('closed'),
        ];

        if ($request->accepts('application/json') && !$request->accepts('text/html')) {
            return (new Response())->json(['issues' => $issues, 'counts' => $counts]);
        }

        return (new Response())
            ->withHeader('Content-Type', 'text/html')
            ->withBody(Layout::render('Issues', $this->indexView($issues, $counts, $status)));
    }

    /** Show a single issue. */
    #[Route('/issues/{id}', methods: ['GET'], name: 'issues.show')]
    public function show(Request $request): Response
    {
        $id = (int) ($request->routeParams()['id'] ?? 0);
        $issue = $this->issues->find($id);

        if ($issue === null) {
            return $request->accepts('application/json') && !$request->accepts('text/html')
                ? (new Response(404))->json(['error' => 'Issue not found'], 404)
                : (new Response(404))->withBody(Layout::render('Not Found', '<p>Issue not found.</p>'));
        }

        if ($request->accepts('application/json') && !$request->accepts('text/html')) {
            return (new Response())->json($issue);
        }

        return (new Response())
            ->withHeader('Content-Type', 'text/html')
            ->withBody(Layout::render("Issue #{$id}", $this->showView($issue)));
    }

    /** Show create form. */
    #[Route('/issues/new', methods: ['GET'], name: 'issues.new')]
    public function create(Request $request): Response
    {
        if (!$this->canCreate()) {
            return (new Response())->redirect('/issues');
        }

        return (new Response())
            ->withHeader('Content-Type', 'text/html')
            ->withBody(Layout::render('New Issue', $this->formView()));
    }

    /** Handle create submission. */
    #[Route('/issues', methods: ['POST'], name: 'issues.store')]
    public function store(Request $request): Response
    {
        if (!$this->canCreate()) {
            return (new Response(403))->json(['error' => 'Forbidden'], 403);
        }

        $data = is_array($request->body()) ? $request->body() : [
            'title' => $request->post('title'),
            'body' => $request->post('body'),
            'priority' => $request->post('priority', 'medium'),
        ];

        $errors = Validation::validate($data, \App\Dto\CreateIssueDto::class);
        if ($errors !== []) {
            if ($request->accepts('application/json') && !$request->accepts('text/html')) {
                return (new Response(422))->json(['errors' => $errors], 422);
            }
            return (new Response())
                ->withHeader('Content-Type', 'text/html')
                ->withBody(Layout::render('New Issue', $this->formView($data, $errors)));
        }

        $username = (string) Session::get('username', 'anonymous');
        $id = $this->issues->create(
            (string) ($data['title'] ?? ''),
            (string) ($data['body'] ?? ''),
            (string) ($data['priority'] ?? 'medium'),
            $username,
        );

        if ($request->accepts('application/json') && !$request->accepts('text/html')) {
            return (new Response())->json(['id' => $id, 'message' => 'Created'], 201);
        }

        return (new Response())->redirect("/issues/{$id}");
    }

    /** Handle status update (PATCH-like via POST). */
    #[Route('/issues/{id}/status', methods: ['POST'], name: 'issues.status')]
    public function updateStatus(Request $request): Response
    {
        $id = (int) ($request->routeParams()['id'] ?? 0);
        $issue = $this->issues->find($id);

        if ($issue === null) {
            return (new Response(404))->json(['error' => 'Not found'], 404);
        }

        $data = is_array($request->body()) ? $request->body() : ['status' => $request->post('status')];
        $newStatus = (string) ($data['status'] ?? '');

        if (!in_array($newStatus, ['open', 'in_progress', 'closed'], true)) {
            return (new Response(422))->json(['error' => 'Invalid status'], 422);
        }

        $this->issues->update($id, ['status' => $newStatus]);

        if ($request->accepts('application/json') && !$request->accepts('text/html')) {
            return (new Response())->json(['id' => $id, 'status' => $newStatus]);
        }

        return (new Response())->redirect("/issues/{$id}");
    }

    /** Delete issue (admin only — gated by middleware). */
    #[Route('/issues/{id}/delete', methods: ['POST'], name: 'issues.delete')]
    public function delete(Request $request): Response
    {
        $id = (int) ($request->routeParams()['id'] ?? 0);

        if (!$this->canDelete()) {
            return (new Response(403))->json(['error' => 'Forbidden'], 403);
        }

        $this->issues->delete($id);

        if ($request->accepts('application/json') && !$request->accepts('text/html')) {
            return (new Response(204));
        }

        return (new Response())->redirect('/issues');
    }

    // --- View helpers ---

    private function canCreate(): bool
    {
        $roles = Session::get('roles', []);
        return is_array($roles) && (in_array('admin', $roles, true) || in_array('editor', $roles, true));
    }

    private function canDelete(): bool
    {
        $roles = Session::get('roles', []);
        return is_array($roles) && in_array('admin', $roles, true);
    }

    /** @param list<array<string, mixed>> $issues */
    private function indexView(array $issues, array $counts, ?string $activeStatus): string
    {
        $canCreate = $this->canCreate();

        $tabs = '';
        foreach (['all' => $counts['total'], 'open' => $counts['open'], 'in_progress' => $counts['in_progress'], 'closed' => $counts['closed']] as $key => $count) {
            $href = $key === 'all' ? '/issues' : "/issues?status={$key}";
            $active = ($activeStatus === null && $key === 'all') || $activeStatus === $key ? ' class="active"' : '';
            $label = ucwords(str_replace('_', ' ', $key));
            $tabs .= "<a href=\"{$href}\"{$active}>{$label} <small>{$count}</small></a>";
        }

        $rows = '';
        foreach ($issues as $issue) {
            $priority = htmlspecialchars((string) $issue['priority']);
            $title    = htmlspecialchars((string) $issue['title']);
            $status   = htmlspecialchars((string) $issue['status']);
            $statusLabel = ucwords(str_replace('_', ' ', $status));
            $author   = htmlspecialchars((string) $issue['author']);
            $assignee = $issue['assignee'] ? htmlspecialchars((string) $issue['assignee']) : '<span style="color:var(--muted)">—</span>';
            $id       = (int) $issue['id'];
            $rows .= "<tr>";
            $rows .= "<td style=\"color:var(--muted);font-size:.82rem\">#{$id}</td>";
            $rows .= "<td><a href=\"/issues/{$id}\">{$title}</a></td>";
            $rows .= "<td><span class=\"badge badge-{$priority}\">{$priority}</span></td>";
            $rows .= "<td><span class=\"badge badge-{$status}\">{$statusLabel}</span></td>";
            $rows .= "<td style=\"font-size:.85rem\">{$author}</td>";
            $rows .= "<td style=\"font-size:.85rem\">{$assignee}</td>";
            $rows .= "</tr>";
        }

        $emptyState = $rows === '' ? '<tr><td colspan="6"><div class="empty-state"><p>No issues found.</p></div></td></tr>' : '';
        $newBtn = $canCreate ? '<a href="/issues/new" class="btn btn-primary">New Issue</a>' : '';

        return <<<HTML
        <div class="toolbar">
            <div class="tabs">{$tabs}</div>
            {$newBtn}
        </div>
        <table>
            <thead><tr><th>#</th><th>Title</th><th>Priority</th><th>Status</th><th>Author</th><th>Assignee</th></tr></thead>
            <tbody>{$rows}{$emptyState}</tbody>
        </table>
        HTML;
    }

    /** @param array<string, mixed> $issue */
    private function showView(array $issue): string
    {
        $id       = (int) $issue['id'];
        $title    = htmlspecialchars((string) $issue['title']);
        $body     = nl2br(htmlspecialchars((string) $issue['body']));
        $status   = htmlspecialchars((string) $issue['status']);
        $priority = htmlspecialchars((string) $issue['priority']);
        $author   = htmlspecialchars((string) $issue['author']);
        $csrf     = Csrf::field();
        $canDelete = $this->canDelete();

        $assigneeMeta = $issue['assignee']
            ? ' <span class="meta-sep">·</span> assigned to <strong>' . htmlspecialchars((string) $issue['assignee']) . '</strong>'
            : '';

        $statusOptions = '';
        foreach (['open', 'in_progress', 'closed'] as $s) {
            $selected = $s === $issue['status'] ? ' selected' : '';
            $label = ucwords(str_replace('_', ' ', $s));
            $statusOptions .= "<option value=\"{$s}\"{$selected}>{$label}</option>";
        }

        $deleteBtn = $canDelete
            ? "<form method=\"POST\" action=\"/issues/{$id}/delete\" class=\"inline\" onsubmit=\"return confirm('Permanently delete this issue?')\">{$csrf}<button type=\"submit\" class=\"btn btn-danger btn-sm\">Delete</button></form>"
            : '';

        $statusLabel = ucwords(str_replace('_', ' ', $status));

        return <<<HTML
        <div class="issue-title-row">
            <span class="issue-num">#{$id}</span>
            <h1 class="issue-title">{$title}</h1>
        </div>
        <div class="issue-meta">
            <span class="badge badge-{$priority}">{$priority}</span>
            <span class="badge badge-{$status}">{$statusLabel}</span>
            <span class="meta-sep">·</span>
            <span>by <strong>{$author}</strong></span>
            <span class="meta-sep">·</span>
            <span>{$issue['created_at']}</span>
            {$assigneeMeta}
        </div>
        <div class="issue-body-card">{$body}</div>
        <div class="issue-action-bar">
            <form method="POST" action="/issues/{$id}/status" class="inline">
                {$csrf}
                <div class="input-group">
                    <select name="status">{$statusOptions}</select>
                    <button type="submit" class="btn">Update status</button>
                </div>
            </form>
            <div class="action-right">
                {$deleteBtn}
                <a href="/issues" class="btn btn-ghost btn-sm">← Back to issues</a>
            </div>
        </div>
        HTML;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function formView(array $data = [], array $errors = []): string
    {
        $csrf = Csrf::field();
        $title = htmlspecialchars((string) ($data['title'] ?? ''));
        $body = htmlspecialchars((string) ($data['body'] ?? ''));
        $priority = (string) ($data['priority'] ?? 'medium');

        $errorHtml = '';
        foreach ($errors as $field => $msg) {
            $errorHtml .= "<li><strong>{$field}:</strong> {$msg}</li>";
        }
        $errorBlock = $errorHtml !== '' ? "<ul class=\"errors\">{$errorHtml}</ul>" : '';

        $priorityOptions = '';
        foreach (['low', 'medium', 'high', 'critical'] as $p) {
            $selected = $p === $priority ? ' selected' : '';
            $priorityOptions .= "<option value=\"{$p}\"{$selected}>" . ucfirst($p) . "</option>";
        }

        return <<<HTML
        <div class="page-header">
            <h1>New Issue</h1>
            <a href="/issues" class="btn btn-ghost btn-sm">Cancel</a>
        </div>
        {$errorBlock}
        <div class="form-card">
            <form method="POST" action="/issues">
                {$csrf}
                <label>Title<input type="text" name="title" value="{$title}" required minlength="3" maxlength="100" placeholder="Short, descriptive title"></label>
                <label>Description<textarea name="body" rows="7" required minlength="10" placeholder="Describe the issue in detail…">{$body}</textarea></label>
                <label>Priority<select name="priority">{$priorityOptions}</select></label>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Issue</button>
                </div>
            </form>
        </div>
        HTML;
    }
}
