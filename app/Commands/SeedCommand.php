<?php

declare(strict_types=1);

namespace App\Commands;

use Karhu\Attributes\Command;
use Karhu\Auth\PasswordHasher;
use Karhu\Db\Connection;

/**
 * Seeds the database with schema + demo data.
 */
final class SeedCommand
{
    /**
     * @param array<string, string|true> $args --db=path to SQLite file
     */
    #[Command('db:seed', 'Create schema and seed demo data')]
    public function handle(array $args): int
    {
        $dbPath = is_string($args['db'] ?? null) ? $args['db'] : 'db/istrbuddy.db';
        $schemaPath = is_string($args['schema'] ?? null) ? $args['schema'] : 'db/schema.sql';

        $db = new Connection("sqlite:{$dbPath}");
        $db->pdo()->exec('PRAGMA foreign_keys = ON');

        // Run schema
        $schema = file_get_contents($schemaPath);
        if ($schema === false) {
            fwrite(\STDERR, "Schema file not found: {$schemaPath}\n");
            return 1;
        }
        $db->pdo()->exec($schema);
        fwrite(\STDOUT, "Schema applied.\n");

        // Seed users — passwords from env vars (defaults for local dev only)
        $hasher = new PasswordHasher();
        $users = [
            ['username' => 'admin', 'password' => getenv('SEED_ADMIN_PASS') ?: 'changeme', 'display_name' => 'Admin', 'roles' => ['admin', 'editor']],
            ['username' => 'editor', 'password' => getenv('SEED_EDITOR_PASS') ?: 'changeme', 'display_name' => 'Editor', 'roles' => ['editor']],
            ['username' => 'viewer', 'password' => getenv('SEED_VIEWER_PASS') ?: 'changeme', 'display_name' => 'Viewer', 'roles' => ['viewer']],
        ];

        foreach ($users as $user) {
            $existing = $db->fetchOne('SELECT username FROM users WHERE username = :u', ['u' => $user['username']]);
            if ($existing !== null) {
                continue;
            }

            $db->insert('users', [
                'username' => $user['username'],
                'password_hash' => $hasher->hash($user['password']),
                'display_name' => $user['display_name'],
            ]);

            foreach ($user['roles'] as $role) {
                $db->insert('user_roles', ['username' => $user['username'], 'role' => $role]);
            }
            fwrite(\STDOUT, "  User: {$user['username']} ({$user['display_name']})\n");
        }

        // Seed issues
        $issues = [
            ['title' => 'Fix login redirect', 'body' => 'The login page redirects to the wrong URL after successful authentication.', 'status' => 'open', 'priority' => 'high', 'author' => 'admin'],
            ['title' => 'Add dark mode', 'body' => 'Users have requested a dark mode toggle in the settings page.', 'status' => 'open', 'priority' => 'medium', 'author' => 'editor'],
            ['title' => 'Update dependencies', 'body' => 'Several packages are outdated. Run composer update and verify tests pass.', 'status' => 'in_progress', 'priority' => 'low', 'author' => 'admin', 'assignee' => 'editor'],
            ['title' => 'API rate limiting', 'body' => 'Implement rate limiting on the JSON API endpoints to prevent abuse.', 'status' => 'open', 'priority' => 'critical', 'author' => 'admin'],
            ['title' => 'Improve mobile layout', 'body' => 'The issue list table overflows on small screens. Switch to card layout on mobile.', 'status' => 'closed', 'priority' => 'medium', 'author' => 'viewer'],
        ];

        $count = (int) $db->fetchScalar('SELECT COUNT(*) FROM issues');
        if ($count === 0) {
            foreach ($issues as $issue) {
                $db->insert('issues', $issue);
            }
            fwrite(\STDOUT, "  Seeded " . count($issues) . " issues.\n");
        } else {
            fwrite(\STDOUT, "  Issues table not empty, skipping seed.\n");
        }

        fwrite(\STDOUT, "Done.\n");
        return 0;
    }
}
