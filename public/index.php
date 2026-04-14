<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Repository\IssueRepository;
use Karhu\App;
use Karhu\Auth\PasswordHasher;
use Karhu\Auth\Rbac;
use Karhu\Auth\UserRepositoryInterface;
use Karhu\Db\Connection;
use Karhu\Db\PdoUserRepository;
use Karhu\Error\ExceptionHandler;
use Karhu\Middleware\Cors;
use Karhu\Middleware\Csrf;
use Karhu\Middleware\RequireRole;
use Karhu\Middleware\Session;

// --- Error handling ---
$handler = new ExceptionHandler();
$handler->register();

// --- Database ---
$dbPath = getenv('DB_PATH') ?: __DIR__ . '/../db/istrbuddy.db';
$db = new Connection("sqlite:{$dbPath}");
$db->pdo()->exec('PRAGMA foreign_keys = ON');

// --- App boot ---
$app = new App();

// Register services
$userRepo = new PdoUserRepository($db);
$app->container()->set(Connection::class, $db);
$app->container()->set(UserRepositoryInterface::class, $userRepo);
$app->container()->set(PdoUserRepository::class, $userRepo);
$app->container()->set(PasswordHasher::class, new PasswordHasher());
$app->container()->set(Rbac::class, new Rbac($userRepo));
$app->container()->set(IssueRepository::class, new IssueRepository($db));

// --- Middleware ---
$app->pipe(new Cors(['origins' => ['*']]));
$app->pipe(new Session());
$app->pipe(new Csrf());

// RBAC gates: creating requires editor+, deleting requires admin
$rbac = $app->container()->get(Rbac::class);
$app->pipe(function (\Karhu\Http\Request $req, callable $next) use ($rbac): \Karhu\Http\Response {
    $path = $req->path();
    $method = $req->method();

    // POST /issues → editor or admin
    if ($method === 'POST' && $path === '/issues') {
        return (RequireRole::for($rbac, ['editor', 'admin']))($req, $next);
    }

    // POST /issues/*/delete → admin only
    if ($method === 'POST' && preg_match('#^/issues/\d+/delete$#', $path)) {
        return (RequireRole::for($rbac, ['admin']))($req, $next);
    }

    return $next($req);
});

// --- Routes ---
$app->router()->scanControllers(require __DIR__ . '/../config/controllers.php');
$app->run();
