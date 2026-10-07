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
                :root {
                    --bg: #0d0f16; --surface: #13161f; --surface-2: #1b1f2e; --border: #252836;
                    --text: #dde1f0; --muted: #6c7591; --dim: #9aa3c2;
                    --primary: #4a9eff; --primary-hover: #2d88f0;
                    --danger: #ef4444; --danger-hover: #dc2626;
                    --success: #22c55e; --warning: #f59e0b;
                    --radius: 6px; --radius-pill: 999px;
                    --shadow-sm: 0 1px 3px rgba(0,0,0,.5); --shadow: 0 4px 14px rgba(0,0,0,.55);
                }
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; font-size: 15px; }
                a { color: var(--primary); text-decoration: none; }
                a:hover { text-decoration: underline; }

                /* ---- Header ---- */
                header { position: relative; background: var(--surface); border-bottom: 1px solid var(--border); padding: .875rem 1.75rem; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm); }
                header::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 3px; background: linear-gradient(90deg, var(--primary) 0%, #6366f1 100%); }
                header h1 { font-size: 1rem; font-weight: 700; letter-spacing: -.01em; }
                header h1 a { color: var(--text); }
                .nav-right { display: flex; gap: .75rem; align-items: center; }
                .user { font-size: .82rem; color: var(--dim); }

                main { max-width: 960px; margin: 2rem auto; padding: 0 1.75rem; }

                /* ---- Buttons ---- */
                .btn { display: inline-flex; align-items: center; gap: .35rem; padding: .45rem 1rem; border-radius: var(--radius); border: 1px solid var(--border); background: var(--surface-2); color: var(--text); cursor: pointer; font-size: .875rem; font-weight: 500; font-family: inherit; text-decoration: none; transition: background .15s, border-color .15s; white-space: nowrap; line-height: 1.4; }
                .btn:hover { background: #252836; border-color: #343748; text-decoration: none; }
                .btn:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
                .btn-primary { background: var(--primary); border-color: var(--primary); color: #fff; }
                .btn-primary:hover { background: var(--primary-hover); border-color: var(--primary-hover); }
                .btn-danger { background: var(--danger); border-color: var(--danger); color: #fff; }
                .btn-danger:hover { background: var(--danger-hover); border-color: var(--danger-hover); }
                .btn-ghost { background: transparent; border-color: transparent; color: var(--dim); }
                .btn-ghost:hover { background: var(--surface-2); border-color: var(--border); color: var(--text); }
                .btn-sm { padding: .3rem .65rem; font-size: .8rem; }
                .btn-full { width: 100%; justify-content: center; }

                /* ---- Badges ---- */
                .badge { display: inline-flex; align-items: center; padding: .2rem .65rem; border-radius: var(--radius-pill); font-size: .68rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
                .badge-open { background: #1b3a5c; color: #7dbfff; }
                .badge-in_progress { background: #3b2e0a; color: #fbbf24; }
                .badge-closed { background: #1a3025; color: #4ade80; }
                .badge-low { background: #1a3025; color: #4ade80; }
                .badge-medium { background: #2f2415; color: #fbbf24; }
                .badge-high { background: #3a1c1c; color: #f87171; }
                .badge-critical { background: #4a1010; color: #fc8181; border: 1px solid #7f1d1d; }

                /* ---- Table ---- */
                table { width: 100%; border-collapse: collapse; }
                thead tr { border-bottom: 1px solid var(--border); }
                th { padding: .6rem .8rem; text-align: left; color: var(--muted); font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; }
                td { padding: .7rem .8rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
                tbody tr { transition: background .1s; }
                tbody tr:hover { background: var(--surface-2); }
                tbody tr:last-child td { border-bottom: none; }

                /* ---- Toolbar & tabs ---- */
                .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; gap: .5rem; flex-wrap: wrap; }
                .tabs { display: flex; gap: .2rem; }
                .tabs a { padding: .38rem .9rem; border-radius: var(--radius-pill); font-size: .82rem; font-weight: 500; color: var(--muted); transition: background .15s, color .15s; }
                .tabs a:hover { background: var(--surface-2); color: var(--dim); text-decoration: none; }
                .tabs a.active { background: var(--primary); color: #fff; }

                /* ---- Forms ---- */
                label { display: block; margin-bottom: 1.25rem; font-size: .82rem; font-weight: 500; color: var(--dim); }
                input, textarea, select { display: block; width: 100%; margin-top: .35rem; padding: .55rem .75rem; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-size: .9rem; font-family: inherit; transition: border-color .15s; }
                input:focus, textarea:focus, select:focus { outline: none; border-color: var(--primary); }
                textarea { resize: vertical; min-height: 120px; }

                /* joined select+button */
                .input-group { display: flex; }
                .input-group select, .input-group input { border-radius: var(--radius) 0 0 var(--radius); border-right: 0; flex: 1; min-width: 0; margin-top: 0; }
                .input-group .btn { border-radius: 0 var(--radius) var(--radius) 0; }

                /* ---- Auth ---- */
                .auth-wrap { display: flex; justify-content: center; padding: 4rem 1rem; }
                .auth-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 2.5rem; width: 100%; max-width: 380px; box-shadow: var(--shadow); }
                .auth-logo { font-size: 1rem; font-weight: 700; color: var(--primary); margin-bottom: 1.5rem; }
                .auth-card h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: .3rem; }
                .auth-sub { color: var(--muted); font-size: .83rem; margin-bottom: 1.75rem; }
                .error { color: var(--danger); font-size: .85rem; margin-bottom: 1rem; padding: .6rem .75rem; background: rgba(239,68,68,.1); border-radius: var(--radius); border: 1px solid rgba(239,68,68,.25); }
                .errors { color: var(--danger); font-size: .85rem; margin-bottom: 1.25rem; padding: .75rem 1rem .75rem 1.5rem; background: rgba(239,68,68,.08); border-radius: var(--radius); border: 1px solid rgba(239,68,68,.2); }

                /* ---- Issue detail ---- */
                .issue-title-row { display: flex; align-items: baseline; gap: .6rem; flex-wrap: wrap; margin-bottom: .6rem; }
                .issue-num { font-size: 1.4rem; font-weight: 700; color: var(--muted); flex-shrink: 0; }
                .issue-title { font-size: 1.4rem; font-weight: 700; }
                .issue-meta { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; color: var(--muted); font-size: .83rem; margin-bottom: 1.5rem; }
                .issue-meta .badge { margin-right: .15rem; }
                .meta-sep { color: var(--border); }
                .issue-body-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; line-height: 1.7; color: var(--dim); }
                .issue-action-bar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding-top: 1.25rem; border-top: 1px solid var(--border); flex-wrap: wrap; }
                .action-right { display: flex; gap: .5rem; align-items: center; }

                /* ---- New issue form ---- */
                .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
                .page-header h1 { font-size: 1.25rem; font-weight: 700; }
                .form-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.75rem; }
                .form-actions { margin-top: .25rem; }

                /* ---- Misc ---- */
                .inline { display: inline; }
                .empty-state { text-align: center; padding: 3rem 1rem; color: var(--muted); }
                .empty-state p { font-size: .95rem; }
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
