<?php

declare(strict_types=1);

namespace App\Tests;

use App\Repository\IssueRepository;
use Karhu\App;
use Karhu\Auth\PasswordHasher;
use Karhu\Auth\Rbac;
use Karhu\Auth\UserRepositoryInterface;
use Karhu\Db\Connection;
use Karhu\Db\PdoUserRepository;
use Karhu\Http\Request;
use Karhu\Http\Response;
use Karhu\Middleware\Cors;
use Karhu\Middleware\Csrf;
use Karhu\Middleware\RequireRole;
use Karhu\Middleware\Session;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for istrbuddy integration tests.
 *
 * Boots a karhu App wired to the in-memory test DB, with all middleware
 * and RBAC gates configured identically to public/index.php.
 */
abstract class AppTestCase extends TestCase
{
    protected App $app;
    protected Connection $db;

    protected function setUp(): void
    {
        $this->db = $GLOBALS['test_db'];
        // Wrap each test in a transaction for isolation — rollback in tearDown
        $this->db->pdo()->beginTransaction();
        $this->app = $this->createApp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        // Rollback any DB changes made during the test
        if ($this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
    }

    /** Boot the app with the same wiring as public/index.php. */
    protected function createApp(): App
    {
        $app = new App();

        $userRepo = new PdoUserRepository($this->db);
        $hasher = new PasswordHasher();
        $rbac = new Rbac($userRepo);

        $app->container()->set(Connection::class, $this->db);
        $app->container()->set(UserRepositoryInterface::class, $userRepo);
        $app->container()->set(PdoUserRepository::class, $userRepo);
        $app->container()->set(PasswordHasher::class, $hasher);
        $app->container()->set(Rbac::class, $rbac);
        $app->container()->set(IssueRepository::class, new IssueRepository($this->db));

        $app->pipe(new Cors(['origins' => ['*']]));
        // Skip Session middleware in tests — we simulate sessions via $_SESSION directly.
        // Skip CSRF middleware in tests — no token verification needed.
        // RBAC gate runs against $_SESSION set by loginAs().
        $app->pipe(function(Request $req, callable $next) use ($rbac): Response {
            $path = $req->path();
            $method = $req->method();

            if ($method === 'POST' && $path === '/issues') {
                return (RequireRole::for($rbac, ['editor', 'admin']))($req, $next);
            }
            if ($method === 'POST' && preg_match('#^/issues/\d+/delete$#', $path)) {
                return (RequireRole::for($rbac, ['admin']))($req, $next);
            }
            return $next($req);
        });

        $app->router()->scanControllers([
            \App\Controllers\AuthController::class,
            \App\Controllers\IssueController::class,
        ]);

        return $app;
    }

    /**
     * Make a request through the full app stack.
     *
     * @param array<string, mixed> $body   JSON body data
     * @param array<string, string> $headers Extra headers
     */
    protected function request(
        string $method,
        string $path,
        array $body = [],
        array $headers = [],
    ): Response {
        // Parse query string from path (e.g. /issues?status=open)
        $queryString = '';
        $get = [];
        if (str_contains($path, '?')) {
            [$path, $queryString] = explode('?', $path, 2);
            parse_str($queryString, $get);
        }

        $server = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path . ($queryString !== '' ? "?{$queryString}" : ''),
        ];

        // Default to JSON for API-style tests
        $headers = array_merge(['accept' => 'application/json'], $headers);

        $jsonBody = '';
        if ($body !== [] && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);
            $headers['content-type'] = 'application/json';
        }

        /** @var array<string, string> $get */
        $request = new Request(
            server: $server,
            get: $get,
            body: $jsonBody,
            headers: $headers,
        );

        return $this->app->handle($request);
    }

    /** Simulate a logged-in user by setting session data. */
    protected function loginAs(string $username, array $roles): void
    {
        $_SESSION['username'] = $username;
        $_SESSION['roles'] = $roles;
    }
}
