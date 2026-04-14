<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Karhu\Auth\PasswordHasher;
use Karhu\Db\Connection;

/**
 * Test bootstrap — creates an in-memory SQLite database, runs schema,
 * seeds test users and issues. The Connection is stored globally for
 * AppTestCase to pick up.
 */

$db = new Connection('sqlite::memory:');
$db->pdo()->exec('PRAGMA foreign_keys = ON');

// Run schema
$schema = file_get_contents(__DIR__ . '/../db/schema.sql');
$db->pdo()->exec($schema);

// Seed users
$hasher = new PasswordHasher();
$users = [
    ['username' => 'admin', 'hash' => $hasher->hash('admin123'), 'display_name' => 'Admin', 'roles' => ['admin', 'editor']],
    ['username' => 'editor', 'hash' => $hasher->hash('editor123'), 'display_name' => 'Editor', 'roles' => ['editor']],
    ['username' => 'viewer', 'hash' => $hasher->hash('viewer123'), 'display_name' => 'Viewer', 'roles' => ['viewer']],
];

foreach ($users as $user) {
    $db->insert('users', [
        'username' => $user['username'],
        'password_hash' => $user['hash'],
        'display_name' => $user['display_name'],
    ]);
    foreach ($user['roles'] as $role) {
        $db->insert('user_roles', ['username' => $user['username'], 'role' => $role]);
    }
}

// Seed issues
$db->insert('issues', ['title' => 'Test bug', 'body' => 'This is a test bug report with enough detail.', 'status' => 'open', 'priority' => 'high', 'author' => 'admin']);
$db->insert('issues', ['title' => 'Test feature', 'body' => 'This is a test feature request with detail.', 'status' => 'in_progress', 'priority' => 'medium', 'author' => 'editor']);
$db->insert('issues', ['title' => 'Closed issue', 'body' => 'This issue was already resolved and closed.', 'status' => 'closed', 'priority' => 'low', 'author' => 'viewer']);

// Store for AppTestCase
$GLOBALS['test_db'] = $db;
