# User — business rules

The rules the User context owns: accounts, credentials, and password recovery.

Each rule is a statement line and a **Why:** line, and nothing else: the id itself is the link back
to the code, and `grep -rn <id> apps/api` finds the enforcement point. The prefix table in
`.ai/business-rules.md` says which file holds which ids; that file also holds the cross-cutting
rules every context needs, and the retired ids that resolve to no rule at all.

- **BR-U04** — A password-recovery token is redeemable exactly once and expires an hour after it is created; an expired or already-redeemed token is rejected.
  **Why:** the partial unique index `(user_id) WHERE used_at IS NULL` is the race backstop — an application-level check alone lets two concurrent redemptions of one token both pass.
- **BR-U05** — `POST /auth/forgot-password` always answers `204 No Content`, whether or not the supplied email is registered.
  **Why:** any difference in status, body or timing is a user-enumeration oracle, so the use case no-ops both on an unknown address and on an `Email` that fails to construct.
