# Note — Bounded Context (hello-world)

Owns the `Note` aggregate as a reference example. Demonstrates the four-layer pattern, command/query handlers, the Doctrine XML mapping, and ownership enforcement at the aggregate level.

## Surface

| Endpoint | Method | Auth | Notes |
|----------|--------|------|-------|
| `/api/notes` | POST | user | Creates a note owned by the current user. |
| `/api/notes` | GET | user | Lists the current user's notes (paginated). |
| `/api/notes/{id}` | GET | user | Single note; 409 if not owner. |
| `/api/notes/{id}` | PATCH | user | Update title/body. |
| `/api/notes/{id}` | DELETE | user | Soft-delete (aggregate emits `NoteDeleted`). |
| `/api/admin/notes` | GET | `ROLE_ADMIN` | Paginated list of every note across users. |

## Always

- Use `NoteId`, `OwnerId`, `NoteTitle`, `NoteBody` at the aggregate/handler boundary.
- Enforce ownership in the aggregate: `Note::update()` and `Note::delete()` accept an `OwnerId editor` and throw `NoteNotOwnedByUser` on mismatch.
- Convert `Security::getUser()` → user ID via the `CurrentUserId` helper in `Presentation/Http/`. That helper is allowed to call `User\Application\Query\GetCurrentUser` because Query Responses are the sanctioned cross-context channel.

## Never

- **Never** import `App\User\Domain\*` here. Use `OwnerId` (defined inside Note) — it just happens to be a UUID that matches a `UserId`.
- **Never** join `notes` with `users` in DQL. If you need user details for a list, project them via a domain event into a local read-model.
- **Never** trust the request body to identify ownership; always derive from the authenticated principal.

## Structure

Follows the canonical four-layer layout. See root `apps/api/AGENTS.md`.

## Validation

```bash
make test-api ARG="--filter NoteEndpointsTest"
```
