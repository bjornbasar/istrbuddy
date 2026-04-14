<?php

declare(strict_types=1);

namespace App\Views;

use Karhu\Middleware\Csrf;
use Karhu\Middleware\Session;

/**
 * Minimal HTML layout — inline CSS, no template engine dependency.
 */
final class Layout
{
    public static function render(string $title, string $content): string
    {
        $username = Session::get('username');
        $isLoggedIn = is_string($username) && $username !== '';
        $roles = Session::get('roles', []);
        $roleStr = is_array($roles) ? implode(', ', $roles) : '';
        $csrf = Csrf::field();

        $nav = $isLoggedIn
            ? "<span class=\"user\">{$username} <small>({$roleStr})</small></span>
               <form method=\"POST\" action=\"/logout\" class=\"inline\">{$csrf}<button type=\"submit\" class=\"btn btn-sm\">Logout</button></form>"
            : '<a href="/login" class="btn btn-sm">Login</a>';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en-NZ">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$title} — IsTrBuddy</title>
            <style>
                :root { --bg: #0f1117; --surface: #1a1d27; --border: #2a2d37; --text: #e0e0e0; --muted: #888; --primary: #4a9eff; --danger: #ef4444; --success: #22c55e; --warning: #f59e0b; }
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; }
                a { color: var(--primary); text-decoration: none; }
                a:hover { text-decoration: underline; }

                header { background: var(--surface); border-bottom: 1px solid var(--border); padding: .75rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
                header h1 { font-size: 1.1rem; }
                header h1 a { color: var(--text); }
                .nav-right { display: flex; gap: 1rem; align-items: center; }
                .user { font-size: .85rem; }

                main { max-width: 960px; margin: 2rem auto; padding: 0 1.5rem; }

                .btn { display: inline-block; padding: .4rem .8rem; border: 1px solid var(--border); border-radius: 4px; background: var(--surface); color: var(--text); cursor: pointer; font-size: .85rem; }
                .btn:hover { background: var(--border); text-decoration: none; }
                .btn-primary { background: var(--primary); color: #fff; border-color: var(--primary); }
                .btn-danger { background: var(--danger); color: #fff; border-color: var(--danger); }
                .btn-sm { padding: .25rem .5rem; font-size: .8rem; }

                table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
                th, td { padding: .6rem .8rem; text-align: left; border-bottom: 1px solid var(--border); }
                th { color: var(--muted); font-size: .8rem; text-transform: uppercase; }
                tr:hover { background: var(--surface); }

                .badge { padding: .15rem .5rem; border-radius: 3px; font-size: .75rem; font-weight: 600; }
                .badge-open { background: #1e3a5f; color: var(--primary); }
                .badge-in_progress { background: #3b3210; color: var(--warning); }
                .badge-closed { background: #1a2e1a; color: var(--success); }
                .badge-low { background: #1a2e1a; color: var(--success); }
                .badge-medium { background: #2a2520; color: var(--warning); }
                .badge-high { background: #3b2020; color: #f87171; }
                .badge-critical { background: #4a1515; color: var(--danger); }

                .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
                .tabs a { padding: .4rem .8rem; border-radius: 4px; font-size: .85rem; color: var(--muted); }
                .tabs a.active { background: var(--surface); color: var(--text); }
                .tabs a:hover { text-decoration: none; color: var(--text); }

                label { display: block; margin-bottom: 1rem; font-size: .85rem; color: var(--muted); }
                input, textarea, select { display: block; width: 100%; margin-top: .25rem; padding: .5rem; background: var(--surface); border: 1px solid var(--border); border-radius: 4px; color: var(--text); font-size: .9rem; }
                textarea { resize: vertical; }

                .auth-form { max-width: 360px; margin: 4rem auto; }
                .error { color: var(--danger); margin-bottom: 1rem; }
                .errors { color: var(--danger); margin-bottom: 1rem; padding-left: 1.5rem; }
                .hint { color: var(--muted); font-size: .8rem; margin-top: 1rem; }
                .inline { display: inline; }
                .meta { color: var(--muted); font-size: .85rem; margin: .5rem 0 1rem; }
                .issue-body { background: var(--surface); padding: 1rem; border-radius: 4px; margin: 1rem 0; }
                .issue-actions { display: flex; gap: .5rem; align-items: center; margin-top: 1rem; }
                .issue-header { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
                .issue-header h2 { margin-right: auto; }
            </style>
        </head>
        <body>
            <header>
                <h1><a href="/issues">IsTrBuddy</a></h1>
                <div class="nav-right">{$nav}</div>
            </header>
            <main>{$content}</main>
        </body>
        </html>
        HTML;
    }
}
