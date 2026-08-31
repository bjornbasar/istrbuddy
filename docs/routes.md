# Routes

Seven paths, declared as `#[Route]` attributes on two controllers. Every one answers HTML or
JSON depending on `Accept` — see [the note on content negotiation](index.md#every-route-answers-html-or-json).

| Route | Methods | Name | Requires | Does |
|---|---|---|---|---|
| `/login` | GET, POST | `login` | — | The form, and the credential check. |
| `/logout` | POST | `logout` | session | Ends the session. POST-only, deliberately. |
| `/issues` | GET, POST | `issues.index`, `issues.store` | GET: any · POST: **editor** | List, filterable by status; create. |
| `/issues/new` | GET | `issues.new` | any | The create form. |
| `/issues/{id}` | GET | `issues.show` | any | One issue. |
| `/issues/{id}/status` | POST | `issues.status` | **editor** | Change status. |
| `/issues/{id}/delete` | POST | `issues.delete` | **admin** | Delete. |

Declared in `app/Controllers/IssueController.php` and `app/Controllers/AuthController.php`.

## Who can reach what

Two role predicates, both reading `Session::get('roles')`:

- **editor** — satisfied by the `editor` *or* the `admin` role. Admin holds both in the seed
  data, so this is belt-and-braces rather than redundant: the check does not assume the seed.
- **admin** — `admin` only.

Reads are open to any authenticated session. Only three routes are gated, and they are the
three that change something: create, change status, delete.

A denied request returns **403** — as JSON when the client asked for JSON, as a page
otherwise. It does not redirect to the login form, which would be the wrong answer for
someone who *is* logged in and simply lacks the role.

## `/logout` is POST-only

A GET logout is a link, and a link is something another site can put in an `<img>` tag or a
crawler can follow. POST plus the CSRF token means logging someone out takes a form they
submitted themselves.

Every mutating route carries a CSRF token via `Csrf::field()`, rendered into the form.

## `GET /issues` takes a `status` filter

`?status=open`, `in_progress` or `closed`. Absent or empty means all.

The response always includes counts for **every** status, not just the filtered one:

```json
{
  "issues": [ ... ],
  "counts": { "total": 12, "open": 5, "in_progress": 3, "closed": 4 }
}
```

That is what lets the filter UI show each option's size without a second request — and it is
why the counts are computed even when nobody is filtering.

## `GET /issues/{id}` when the id does not exist

**404**, as `{"error": "Issue not found"}` for a JSON client and as a page otherwise. Note the
status is set in both the `Response` constructor and the `json()` call — belt-and-braces
against karhu's `json()` defaulting to 200 if the argument is dropped.

## Adding a route

Add the `#[Route]` attribute, then add a row to the table above. `tools/check-docs.php` fails
the deploy if you do not — and fails the other way too, if the table claims a path that
nothing declares.

Only the first column of a table headed **Route** counts as a claim, so prose and the roles
table can mention any path they need without becoming a tripwire.
