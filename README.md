# IsTrBuddy

**Issue Tracking Buddy** — a [karhu](https://github.com/bjornbasar/karhu)-powered issue tracker.

Built as the first real application on the karhu PHP microframework, validating the full stack: attribute routing, middleware pipeline, DI container, RBAC auth, CSRF protection, validation, and content negotiation.

## Quick start

```bash
git clone git@github.com:bjornbasar/istrbuddy.git
cd istrbuddy
composer install
bin/karhu db:seed
composer serve
```

Open http://localhost:8080/issues

## Demo accounts

Seed passwords are set via environment variables (default: `changeme`):

```bash
SEED_ADMIN_PASS=mypass SEED_EDITOR_PASS=mypass SEED_VIEWER_PASS=mypass bin/karhu db:seed
```

| Username | Env var | Roles | Can |
|----------|---------|-------|-----|
| admin | `SEED_ADMIN_PASS` | admin, editor | Everything (create, edit status, delete) |
| editor | `SEED_EDITOR_PASS` | editor | Create issues, edit status |
| viewer | `SEED_VIEWER_PASS` | viewer | View only |

## Features

- Issue CRUD with status (open / in progress / closed) and priority (low / medium / high / critical)
- Role-based access: admin deletes, editor+ creates, anyone views
- CSRF protection on all state-changing forms
- Content negotiation: same controllers serve HTML and JSON
- SQLite for zero-config persistence (swap to Postgres via DSN)
- CLI seed command: `bin/karhu db:seed`

## karhu features exercised

| Feature | Where |
|---------|-------|
| `#[Route]` attribute routing | All controllers |
| Session middleware | Auth flow |
| CSRF middleware | All forms |
| CORS middleware | JSON API |
| RequireRole middleware | Create + delete gates |
| PasswordHasher (argon2id) | Login |
| Rbac + UserRepositoryInterface | Role checks |
| Validation (#[Required], #[StringLength], #[In]) | Issue creation |
| Content negotiation | All endpoints (Accept header) |
| CLI #[Command] | `bin/karhu db:seed` |
| karhu-db Connection | All DB queries |
| karhu-db PdoUserRepository | Auth backend |

## JSON API

```bash
# List issues
curl http://localhost:8080/issues -H 'Accept: application/json'

# Login
curl -X POST http://localhost:8080/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"admin123"}'

# Create issue (needs session cookie + CSRF token)
curl -X POST http://localhost:8080/issues \
  -H 'Content-Type: application/json' \
  -H 'X-CSRF-Token: <token>' \
  -b 'PHPSESSID=<session>' \
  -d '{"title":"New bug","body":"Description of the bug here","priority":"high"}'
```
