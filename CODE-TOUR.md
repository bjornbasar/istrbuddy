# istrbuddy — Code Tour

> A **reading-guide map**, and the third in the karhu track. Read [karhu's CODE-TOUR](../karhu/CODE-TOUR.md) (the engine) and [mishka's](../mishka/CODE-TOUR.md) (the first, *maximal* app) first. istrbuddy is the **minimal** karhu app — so this tour is deliberately a **compare-and-contrast**: two apps, one framework, opposite wiring pressures. The lesson lives in the diffs.
>
> **How to use it:** §0 is the at-a-glance contrast table; §1 walks the whole seam (it fits on one screen); §2 is the three big divergences from mishka; §3–§5 are istrbuddy's signature moves; §7 the exercises; §8 pivots to ansible.

---

## 0. Orientation — istrbuddy vs mishka at a glance

Both are karhu dogfoods. Everything you learned in the karhu tour applies unchanged. What differs is **how much of the framework each app leans on** and **what it fills in itself**:

| Dimension | **istrbuddy** (minimal) | **mishka** (maximal) |
|-----------|--------------------------|----------------------|
| Purpose | Issue tracker | Family hub (calendar/chores/push/email) |
| karhu packages | karhu + **karhu-db only** | karhu + karhu-db + **karhu-view + karhu-queue** |
| Database | **SQLite** (swap via `DB_PATH`) | PostgreSQL (SQLite in tests) |
| Views | **Inline PHP** (`Layout::render` + heredoc) | **Twig** (karhu-view) |
| Auth impl | karhu-db's **built-in `PdoUserRepository`** | hand-written `MishkaUserRepository` |
| Validation | **Uses karhu's `#[Required]` DTOs** | Inline, in-controller |
| Seam size | **67-line `public/index.php`** | ~300-line `public/bootstrap.php` |
| Background work | none | queue + worker process |
| API shape | **Dual JSON/HTML on every route** | Mostly HTML |

Read that table twice — it's the whole tour in miniature. istrbuddy's DOCS.md states the intent outright: it exists to *"demonstrate karhu works without karhu-view"* and with the framework's stock pieces.

---

## Vocabulary check — new terms (most carry over from prior tours)

- **content negotiation** — one URL serves different formats based on the request's `Accept` header: JSON to an API client, HTML to a browser. istrbuddy does this on *every* route.
- **heredoc** — PHP's `<<<HTML … HTML;` multi-line string syntax (with `{$var}` interpolation). istrbuddy builds pages by concatenating these instead of using template files.
- **`PdoUserRepository`** — a *ready-made* implementation of karhu's `UserRepositoryInterface` shipped inside **karhu-db**. If your users table matches its expected shape, you get auth for free — no custom repo.
- **belt-and-suspenders / defence in depth** — guarding the same thing in two independent places so a miss in one still fails safe. istrbuddy gates writes at *both* the middleware and the controller (§3).

(For repository pattern, PRG, static facade, RBAC, argon2id, DSN — see the karhu/mishka tours.)

---

## 1. The seam — the entire thing, in one screen

Open [public/index.php](public/index.php). Unlike mishka, there is **no `bootstrap.php` split** and no fail-fast env gauntlet — it's 67 lines, top to bottom:

```
require autoload                                     (index.php:5)
new ExceptionHandler → register()                    (:21-22)   ← karhu error handling
new Connection("sqlite:$dbPath")                     (:26)      ← karhu-db, SQLite
   $db->pdo()->exec('PRAGMA foreign_keys = ON')      (:27)      ← SQLite needs FKs turned ON explicitly
new App()                                            (:30)
container()->set(...) × 6                             (:34-39)   ← the WHOLE DI graph
pipe(Cors) pipe(Session) pipe(Csrf) pipe(rbacClosure)(:42-63)   ← middleware
router()->scanControllers(config/controllers.php)   (:66)
run()                                                 (:67)
```

**The DI graph is six lines** ([:34-39](public/index.php#L34-L39)) versus mishka's ~40. Why so few? Because istrbuddy has almost nothing to wire: one `Connection`, karhu-db's stock `PdoUserRepository`, a `PasswordHasher`, an `Rbac`, and one app repo (`IssueRepository`). No factories at all — nothing here takes a scalar the auto-wirer can't handle, so the karhu `factory()` escape hatch (mishka §2e) never comes up.

This *is* the answer to the karhu-tour question "what's the **minimum** a karhu app must wire?" — and istrbuddy is close to that floor.

---

## 2. The three big divergences from mishka

### 2a. Auth — framework default vs. custom implementation

The single most instructive diff. istrbuddy writes **zero** auth-repository code:

```php
$userRepo = new PdoUserRepository($db);                       // index.php:33 — from karhu-db
$app->container()->set(UserRepositoryInterface::class, $userRepo);
```

karhu-db *ships* `PdoUserRepository` — a stock implementation of the very `UserRepositoryInterface` that mishka had to implement by hand ([mishka §3](../mishka/CODE-TOUR.md)). istrbuddy's users table matches the stock shape, so it just uses it. **Same interface, two ways to satisfy it:** take the framework's default (istrbuddy) or write your own when your schema diverges (mishka's email/int-PK). That choice — default vs. custom — is the essence of designing to an interface. ([ADR-0006](../karhu/docs/adr/0006-rbac-via-repository-interface.md) is what makes both possible.)

### 2b. Views — inline PHP heredoc vs. Twig

istrbuddy has **no template engine**. [app/Views/Layout.php](app/Views/Layout.php) is a static `render(title, content)` that returns a heredoc HTML document (inline CSS and all), and each controller builds its `content` string in private view-helper methods ([IssueController.php:178-299](app/Controllers/IssueController.php#L178-L299)). This proves karhu-view is optional — but note the **real cost**, and flag it as a sharp edge:

> **Manual escaping.** With inline heredoc you must call `htmlspecialchars()` on every user value yourself ([IssueController.php:197-205](app/Controllers/IssueController.php#L197-L205)). Twig auto-escapes by default; here, one forgotten `htmlspecialchars` is a stored-XSS hole. This is precisely the class of bug a template engine exists to prevent — the trade-off istrbuddy accepts for zero dependencies. *Exercise material in §7.*

### 2c. Validation — istrbuddy USES the DTO validator mishka skipped

Remember the mishka §4 callout — "karhu ships a validation mechanism the flagship app chose not to use"? **istrbuddy is the app that uses it.** [app/Dto/CreateIssueDto.php](app/Dto/CreateIssueDto.php) is a DTO with karhu's validation attributes:

```php
#[Required] #[StringLength(min: 3, max: 100)]  public string $title = '';
#[Required] #[StringLength(min: 10, …)]        public string $body = '';
#[In(values: ['low','medium','high','critical'])] public string $priority = 'medium';
```

and the controller runs it in one line ([IssueController.php:93](app/Controllers/IssueController.php#L93)):

```php
$errors = Validation::validate($data, CreateIssueDto::class);
```

This closes the loop from the karhu tour (karhu §9): the attributes → reflection → error-map mechanism, used exactly as designed. Compare the two apps' philosophies directly — mishka hand-rolls per-field checks in the controller; istrbuddy declares them on a DTO. Both are defensible; seeing both is the point.

---

## 3. RBAC via middleware — istrbuddy's signature move

istrbuddy's DOCS calls out "RBAC via middleware, not per-controller checks." Read [index.php:46-63](public/index.php#L46-L63): a **closure middleware** inspects method+path and applies karhu's `RequireRole` only to the mutating routes:

```php
if ($method === 'POST' && $path === '/issues') {
    return (RequireRole::for($rbac, ['editor','admin']))($req, $next);   // create → editor+
}
if ($method === 'POST' && preg_match('#^/issues/\d+/delete$#', $path)) {
    return (RequireRole::for($rbac, ['admin']))($req, $next);            // delete → admin
}
return $next($req);
```

Two things to notice, both worth a beat:

1. **This is the callable-middleware pattern from karhu §4 in raw form** — an inline `fn(Request,$next)` closure, not a class. karhu's `RequireRole::for(...)` is itself a factory that *returns* a middleware callable, so this composes middleware inside middleware.
2. **Belt-and-suspenders.** The controller *also* checks roles (`canCreate()`/`canDelete()`, [IssueController.php:166-176](app/Controllers/IssueController.php#L166-L176)). So a write is gated **twice** — once at the pipeline, once in the handler. That's deliberate defence in depth, but there's a subtlety:

> **Sharp edge (great review target):** the *middleware* checks roles via `Rbac` (which queries the user repository — the authoritative source), while the *controller* reads `Session::get('roles')` (a login-time cache). If a user's DB roles change mid-session, the two gates can momentarily disagree. And the middleware re-encodes route knowledge as a `preg_match` on the path — duplicating what the router already knows. Rename `/issues/{id}/delete` in the controller attribute and forget the regex here, and the admin gate silently stops matching. Both are in §7.

---

## 4. Content negotiation everywhere — one route, two clients

istrbuddy is simultaneously an HTML app and a JSON API. Every handler ends with the same fork ([IssueController.php:35, 57, 95, 111, 138, 157](app/Controllers/IssueController.php#L35)):

```php
if ($request->accepts('application/json') && !$request->accepts('text/html')) {
    return (new Response())->json(...);   // API client
}
return (new Response())->withBody(Layout::render(...));   // browser
```

The `accepts('application/json') && !accepts('text/html')` idiom (built on karhu's `Request::accepts`, karhu §7) means "prefers JSON *and* isn't a browser." So `curl -H 'Accept: application/json' /issues` returns `{issues:[…]}` while a browser hitting the same URL gets the rendered table — no separate `/api` routes. This is more thorough than mishka (mostly HTML) and mirrors karhu's own content-negotiated error handler.

---

## 5. The data layer — the *other* way to use karhu-db

mishka's repositories write raw SQL strings. istrbuddy's [IssueRepository](app/Repository/IssueRepository.php) leans on karhu-db's **query-builder-lite helpers** instead:

```php
$this->db->insert('issues', ['title'=>…, 'priority'=>…, …]);   // :39
$this->db->update('issues', $data, ['id' => $id]);              // :51
$this->db->delete('issues', ['id' => $id]);                     // :56
```

`insert`/`update`/`delete` take a table + associative arrays and build the parameterised SQL for you; raw `fetchAll`/`fetchOne`/`fetchScalar` remain for reads. So the two apps show karhu-db's **two registers**: hand-written SQL (mishka, for complex JOINs/CTEs/`RETURNING`) vs. the array helpers (istrbuddy, for straightforward CRUD). Same `Connection`, different ergonomics — pick per query complexity.

---

## 6. Pattern catalog — the two-apps-one-framework matrix

| Decision point | istrbuddy chose | mishka chose | Why the difference |
|----------------|-----------------|--------------|--------------------|
| `UserRepositoryInterface` | stock `PdoUserRepository` | custom impl | schema fit vs. divergence |
| View layer | inline heredoc | Twig | zero-dep demo vs. real UI complexity |
| Validation | `#[attribute]` DTO | in-controller | API-shaped vs. form-with-old-input |
| DI wiring | 6 `set()`, no factories | ~40 `set()` + factories | few deps vs. many + scalars |
| karhu-db usage | array helpers | raw SQL | simple CRUD vs. complex queries |
| Authz placement | pipeline middleware | per-handler `Authorizer` | coarse route gate vs. row-level ownership |
| Escaping | manual `htmlspecialchars` | Twig auto-escape | cost of the no-template-engine choice |

There is no "right" column — the value is understanding *why each app landed where it did*, which is what "reviewing properly" means.

---

## 7. Active-recall exercises

Trace in the source before concluding.

1. **Where does istrbuddy satisfy `UserRepositoryInterface` with zero app code?** Name the class and the package it comes from, and say what would force istrbuddy to write its own (like mishka did). ([index.php:33](public/index.php#L33).)
2. **A `viewer`-role user POSTs `/issues`.** There are *two* independent gates that reject them — name both, say which fires first, and explain what each would return. ([index.php:53](public/index.php#L53) + [IssueController.php:83](app/Controllers/IssueController.php#L83).)
3. **You rename the delete route** in the controller attribute from `/issues/{id}/delete` to `/issues/{id}/remove`. What breaks, and why is it a *silent* security regression rather than a 404? ([index.php:58](public/index.php#L58).)
4. **Find the XSS that a template engine would have prevented.** Look at the view helpers — is *every* interpolated user value escaped? What happens if a future edit adds a field and forgets `htmlspecialchars`? ([IssueController.php:178-299](app/Controllers/IssueController.php#L178-L299).)
5. **Same route, two clients.** Predict the exact response body + status for `GET /issues/999` (nonexistent) as (a) `curl -H 'Accept: application/json'` and (b) a browser. Then find both branches. ([IssueController.php:51-55](app/Controllers/IssueController.php#L51-L55).)
6. **The role-source divergence.** The middleware trusts `Rbac`; the controller trusts `Session`. Construct the sequence of events where they disagree, and decide whether it's exploitable or merely inconsistent. (§3.)

---

## 8. Bridge to ansible — a hard gear change

The next tour leaves PHP entirely. ansible is **infrastructure-as-code**: you don't trace a request through it, you describe a *desired end state* for a fleet of hosts and let it converge them there. But there's a direct through-line from these tours:

- **Idempotency becomes the whole paradigm.** Remember `idempotent` from the karhu Vocabulary check (and mishka's `markEmailVerified` guard)? Ansible is built on it end-to-end: a playbook says "this package is *installed*, this file has *these contents*, this service is *running*" — and running it twice changes nothing the second time. You'll stop thinking "do X" and start thinking "X should be true."
- **Declarative over imperative** — same shift as karhu's attribute routing (declare the route, don't wire it), scaled to servers.

So carry two questions in: *what is the entry point when there's no request?* (the playbook + inventory), and *where does "convergence" actually happen?* (the modules and their idempotency checks).

---

*Tour covers istrbuddy @ `c51e454` (v0.1.0). Companion docs: [DOCS.md](DOCS.md), [karhu/CODE-TOUR.md](../karhu/CODE-TOUR.md) (engine), [mishka/CODE-TOUR.md](../mishka/CODE-TOUR.md) (the maximal counterpart). Next tour: ansible.*
