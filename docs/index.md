# IsTrBuddy

**Issue Tracking Buddy** — a small issue tracker, and the first real application built on
the [karhu](https://docs.twobots.dev/karhu/) microframework.

Its job was never to be an issue tracker. It exists to prove karhu's stack works when
something actually depends on it: attribute routing, the middleware pipeline, the DI
container, RBAC, CSRF, validation and content negotiation, all exercised by a real app
rather than by a test suite written to pass.

| | |
|---|---|
| [**Routes**](routes.md) | Seven paths, who may reach them, and what each returns. |
| [**Operating**](operating.md) | Running it, seeding, and the roles model. |

## Every route answers HTML *or* JSON

The same handler serves both, chosen by the `Accept` header. There is no `/api` prefix and no
second controller — content negotiation is a karhu feature, and using it properly was one of
the things this app was built to check.

The condition is deliberately asymmetric:

```php
if ($request->accepts('application/json') && !$request->accepts('text/html')) {
```

A browser sends `Accept: text/html, application/xhtml+xml, ... */*`, and that `*/*` matches
`application/json` too. Testing only for JSON would hand every browser a JSON document. The
`&& !accepts('text/html')` is what makes the check mean "a client that wants JSON and is not
a browser".

## Authorisation is checked in the handler, not only in middleware

Session middleware establishes *who* you are; the controller decides what that permits, via
two private predicates over `Session::get('roles')` — one for editor-or-admin, one for admin
alone.

Keeping it there rather than in route metadata is a deliberate limitation of this app, not a
karhu one: the rules are small enough to read in place, and pushing them into an attribute
would have meant designing karhu's RBAC-in-routing story around a single consumer. See
[Routes](routes.md#who-can-reach-what).

## Where it runs

SQLite, PHP 8.3+, port 8080. `composer serve` for local work; `docker compose up` for a
container. The database lives in `db/`.

`README.md` is the quick start and `DOCS.md` the architecture notes. This site is the
reference for using and operating it.

## The docs are machine-checked

`php tools/check-docs.php` fails when a `#[Route]` has no entry in the reference, when the
reference claims a route nothing declares, or when prose cites an `app/` or `config/` path
that has moved.

It deliberately does **not** reuse karhu's gate, even though both are PHP. karhu's requires
every public *method* of a library to be documented, which is right for something people call
from their own code. Nobody imports `App\Controllers\IssueController` — what this app offers
a reader is its routes, so that is what is checked.
