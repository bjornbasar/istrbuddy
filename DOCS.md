# IsTrBuddy — Project Documentation

**Version:** 0.1.0 | **License:** MIT

Issue Tracking Buddy — first real application built on the karhu PHP microframework.

---

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Framework | karhu 0.1 (zero-dep PHP microframework) |
| Database | karhu-db + SQLite (swappable to PostgreSQL) |
| Auth | karhu RBAC + PasswordHasher (argon2id) |
| Views | Inline PHP (no template engine) |
| CSS | Inline styles (dark theme, no build step) |
| CI | GitHub Actions (ubuntu-latest) |

---

## Directory Structure

```
istrbuddy/
├── app/
│   ├── Commands/          # CLI commands (SeedCommand)
│   ├── Controllers/       # AuthController, IssueController
│   ├── Dto/               # Validation DTOs (CreateIssueDto)
│   ├── Repository/        # IssueRepository (karhu-db backed)
│   └── Views/             # Layout helper (inline HTML/CSS)
├── config/
│   ├── controllers.php    # Route-scanned controller list
│   └── commands.php       # CLI command list
├── db/
│   └── schema.sql         # SQLite schema
├── public/
│   └── index.php          # Front controller
└── composer.json
```

---

## Key Design Decisions

- **SQLite default** — zero-config; swap to Postgres by changing the DSN in `DB_PATH` env
- **No template engine** — Layout::render() builds HTML inline; demonstrates karhu works without karhu-view
- **RBAC via middleware** — route-level gating in public/index.php, not per-controller checks
- **Content negotiation** — every endpoint returns JSON or HTML based on Accept header
