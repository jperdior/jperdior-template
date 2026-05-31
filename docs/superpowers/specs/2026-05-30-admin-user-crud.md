# Admin User CRUD

**Date:** 2026-05-30  
**Status:** In Progress  
**Scope:** `apps/api` · `packages/api-client-ts` · `apps/admin` · `apps/web`

---

## Problem

The admin panel has a read-only user list. Admins have no way to create users, edit roles, force a password reset, or remove accounts without direct database or CLI access.

## Solution

Full CRUD for users across all layers, plus a web-app enforcement gate for forced password resets.

---

## Scope

| Operation | Detail |
|---|---|
| **Create** | Admin creates a user (email + password); `mustResetPassword` auto-set `true` |
| **Read** | Paginated list (existing, enhanced) + new detail page at `/users/[id]` |
| **Update roles** | Toggle `ROLE_ADMIN` on any user |
| **Update password** | Admin forces reset flag; user must change password on next login |
| **Delete** | Soft delete (`deleted_at`); deleted users visible in list with restore action |
| **Web gate** | After login in `apps/web`, if `mustResetPassword === true` → redirect to `/reset-password` |

---

## Architecture

### apps/api — New Endpoints

All admin endpoints require `ROLE_ADMIN`. The self-reset endpoint requires `IS_AUTHENTICATED_FULLY`.

| Method | Path | Notes |
|--------|------|-------|
| POST | `/api/admin/users` | Create user; auto-sets `mustResetPassword = true` |
| GET | `/api/admin/users/{id}` | Get user detail (includes deleted users) |
| PATCH | `/api/admin/users/{id}/roles` | Update roles array |
| POST | `/api/admin/users/{id}/force-password-reset` | Set `mustResetPassword = true` |
| DELETE | `/api/admin/users/{id}` | Soft delete (sets `deleted_at`) |
| POST | `/api/admin/users/{id}/restore` | Clear `deleted_at` |
| POST | `/api/users/me/reset-password` | User changes own password; clears flag |

Existing `GET /api/admin/users` is updated to include deleted users (admin list shows all).
Existing `GET /api/me` is updated to include `mustResetPassword` field.

### Domain Changes (`User.php`)

Two new mutable fields:
- `mustResetPassword: bool` — default `false`
- `deletedAt: ?DateTimeImmutable` — default `null`; `null` = active

New domain methods: `forcePasswordReset()`, `clearPasswordReset()`, `demoteFromAdmin()`, `softDelete(DateTimeImmutable)`, `restore()`, `isDeleted()`.

`changePassword()` updated to also call `clearPasswordReset()`.

New domain exception: `CannotDeleteSelf` — thrown when an admin targets their own account for deletion.

### Database Migration

Two new columns on `users` table:
- `must_reset_password TINYINT(1) NOT NULL DEFAULT 0`
- `deleted_at DATETIME NULL`

### Repository

New methods on `UserRepository` interface (and `DoctrineUserRepository` implementation):
- `findByIdIncludingDeleted(UserId $id): ?User`
- `findAllIncludingDeleted(int $limit, int $offset): array`
- `countAllIncludingDeleted(): int`

Existing `findAll`/`countAll` add `AND u.deletedAt IS NULL` so soft-deleted users are invisible to non-admin queries. Existing `findById` also excludes deleted users.

### Application Layer

New commands and queries following existing Command/Query/UseCase/Handler pattern:

**Queries:**
- `GetUserByIdQuery` → `UserDetailResponse` (includes `mustResetPassword`, `deletedAt`)

**Commands:**
- `AdminCreateUserCommand(email, password)` — creates user + sets reset flag
- `UpdateUserRolesCommand(userId, roles[])` — promote or demote
- `ForcePasswordResetCommand(userId)` — sets flag
- `SoftDeleteUserCommand(userId, requestingAdminId)` — guards self-delete
- `RestoreUserCommand(userId)` — clears `deletedAt`
- `SelfResetPasswordCommand(userId, newPassword)` — changes password + clears flag

### packages/api-client-ts

- `CurrentUser` gains `mustResetPassword: boolean`
- New `UserDetail` interface with full detail fields including `deletedAt`
- 7 new methods: `adminGetUser`, `adminCreateUser`, `adminUpdateUserRoles`, `adminForcePasswordReset`, `adminDeleteUser`, `adminRestoreUser`, `selfResetPassword`

### apps/admin — New Pages & Components

**Enhanced list (`/users`):**
- Page size 25 with URL-param pagination (`?offset=N`)
- `PaginationControls` server component (prev/next + "Showing X–Y of Z")
- Status column: Active / Must reset pw / Deleted badges (DS tokens)
- Deleted rows: muted + strikethrough + inline "Restore" link
- "New User" button opens `CreateUserDialog`
- `⋯` actions menu per active row: View Detail, Edit Roles, Force Reset, Delete

**New components:**
- `CreateUserDialog` — modal with email + password form
- `UserActionsMenu` — dropdown triggering targeted dialogs
- `PaginationControls` — server component, plain `<Link>` prev/next

**New detail page (`/users/[id]`):**
- Server component fetching `GET /api/admin/users/{id}`
- Shows: email, ID, joined date, roles, mustResetPassword status
- Actions: Promote/Demote toggle, Force Reset, Delete/Restore
- `loading.tsx` + `error.tsx` alongside

**Server Actions (`users/actions.ts`):**
- `createUser`, `updateUserRoles`, `forcePasswordReset`, `deleteUser`, `restoreUser`
- Each calls `revalidatePath('/users')` and `revalidatePath('/users/[id]')` after mutation

### apps/web — Password Reset Gate

**Login action update:**  
After `persistTokens`, call `me()` with the new access token. If `mustResetPassword === true`, redirect to `/reset-password`.

**New `/reset-password` page:**
- Server component: if flag is false, redirects to `/`
- `ResetPasswordForm` client component with Zod validation (min 8 chars, passwords match)
- Server action calls `selfResetPassword(newPassword)` then redirects to `/`

---

## Verification

```bash
make lint          # phpstan + cs-fixer + deptrac + tsc + eslint
make test          # phpunit (unit + functional) + pnpm test
pnpm -C apps/admin typecheck && pnpm -C apps/admin lint
pnpm -C apps/web typecheck && pnpm -C apps/web lint
make build-web
```

Manual test flow: create user → verify reset flag → user logs into web app → redirect to `/reset-password` → reset → flag cleared → soft delete → restore.
