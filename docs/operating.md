# Operating

```bash
composer install
bin/karhu db:seed
composer serve          # http://localhost:8080/issues
```

Or `docker compose up`. PHP 8.3+, SQLite, port 8080.

## Seeding

`bin/karhu db:seed` (implemented by `app/Commands/SeedCommand.php`) creates three accounts:

| Username | Env var | Roles | Can |
|---|---|---|---|
| admin | `SEED_ADMIN_PASS` | admin, editor | Everything — create, change status, delete |
| editor | `SEED_EDITOR_PASS` | editor | Create issues, change status |
| viewer | `SEED_VIEWER_PASS` | viewer | View only |

Passwords come from the environment and default to `changeme`:

```bash
SEED_ADMIN_PASS=… SEED_EDITOR_PASS=… SEED_VIEWER_PASS=… bin/karhu db:seed
```

**The default is a real default, not a placeholder** — an unseeded-with-env instance has three
accounts whose password is `changeme`. That is fine for a local reference app and would not be
fine anywhere reachable, which is the reason this application has never been given a public
hostname.

Note `admin` holds **both** `admin` and `editor`. The editor predicate accepts either role
rather than relying on that, so the authorisation logic does not silently depend on how the
seed happens to be shaped.

## Tests

```bash
composer test        # PHPUnit
composer analyse     # PHPStan
```

Run **both** before pushing. PHPStan catches type and docblock problems PHPUnit cannot see —
the same rule that applies to karhu and mishka.

## Why this app exists

It is karhu's first real dogfood, and the failure it was built to catch is the one karhu
actually had: the framework's own README shipped a hello-world with the wrong `#[Route]`
argument order and a static call to an instance method, and nothing noticed, because nothing
depended on it.

An application that boots is a stronger claim than a test suite that passes, because the test
suite was written by the same person, at the same time, with the same misunderstanding.

Keep that in mind when changing it: the point of a feature here is that it exercises
something in karhu. Removing the JSON negotiation because "nothing consumes the API" would
delete the only consumer of a framework feature, which is exactly how the hello-world rotted.

## Docs

Published at **[docs.bjornbasar.com/istrbuddy/](https://docs.bjornbasar.com/istrbuddy/)**,
behind Cloudflare Access. Private-only: this is a reference application with default
passwords, not something to advertise.

`php tools/check-docs.php` gates it — an undocumented route, a documented route that no
longer exists, or a dead cited path all fail the build.
