# Business Rules Registry

Domain rules enforced in the code. Add new rules here as they are introduced. Each rule names the context, the enforcement point, and the typed exception raised on violation.

## User context

### BR-U04 — Password recovery tokens are single-use and time-limited

A `PasswordRecoveryToken` can be redeemed only once and expires 1 hour after creation. Any attempt to use an expired or already-redeemed token is rejected.

- **Context**: User
- **Enforcement**: `PasswordRecoveryToken::validate()` in `apps/api/src/User/Domain/PasswordRecoveryToken.php`, plus a partial unique index `(user_id) WHERE used_at IS NULL` on `password_recovery_tokens` for race-condition safety.
- **Exceptions**: `PasswordRecoveryTokenExpired`, `PasswordRecoveryTokenAlreadyUsed`.

### BR-U05 — Password recovery never reveals user existence

`POST /auth/forgot-password` always returns `204 No Content` regardless of whether the supplied email is registered, preventing user enumeration.

- **Context**: User
- **Enforcement**: `RequestPasswordRecoveryUseCase` in `apps/api/src/User/Application/Command/RequestPasswordRecovery/` silently no-ops when `UserRepository::findByEmail()` returns `null` **or** when `Email` value-object construction throws `InvalidArgumentException`.
