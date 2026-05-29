# User — Bounded Context

Owns: authentication, account lifecycle, password hashing, role assignment.

## Surface

| Endpoint | Method | Auth | Notes |
|----------|--------|------|-------|
| `/auth/signup` | POST | public | Creates a User with `ROLE_USER`. |
| `/auth/login` | POST | public | Lexik JWT `json_login`. Returns `token` + `refresh_token`. |
| `/auth/refresh` | POST | public | Gesdinet refresh-token rotation. |
| `/api/me` | GET | `IS_AUTHENTICATED_FULLY` | Current user payload. |

CLI: `app:user:promote-admin <email>` grants `ROLE_ADMIN`.

## Always

- Use `Email`, `PlainPassword`, `HashedPassword`, `UserId` value objects at the boundary.
- Pass passwords through `PasswordHasherInterface`. NEVER hash inline.
- Emit `UserRegistered` after sign-up.
- Enforce refresh-token single-use rotation (Gesdinet config: `single_use: true`).
- Update the `users` and `refresh_tokens` tables only through migrations.

## Never

- Never import `App\Note\…` or any other context's `Domain/`/`Application/`. Communicate via events.
- Never store plaintext passwords. The aggregate only knows `HashedPassword`.
- Never log `PlainPassword` or any password-shaped string.
- Never return the password hash from any endpoint.
- Never add `tenant_id` here. The User aggregate is single-tenant by design.

## Structure

```
Domain/
├── User.php                      (aggregate)
├── UserId.php                    (Uuid VO)
├── Email.php                     (string VO with normalisation + RFC validation)
├── PlainPassword.php             (DTO; length checks)
├── HashedPassword.php            (string VO; opaque)
├── Role.php                      (enum: USER, ADMIN)
├── PasswordHasherInterface.php   (port)
├── UserRepository.php            (port)
├── Event/UserRegistered.php
└── Exception/{UserNotFound,UserAlreadyExists}.php

Application/
├── Command/SignUp/{SignUpCommand,SignUpCommandHandler}.php
├── Command/PromoteToAdmin/{PromoteToAdminCommand,PromoteToAdminCommandHandler}.php
└── Query/GetCurrentUser/{GetCurrentUserQuery,GetCurrentUserQueryHandler,CurrentUserResponse}.php

Infrastructure/
├── Persistence/{DoctrineUserRepository.php, Doctrine/Mapping/User.orm.xml}
├── Symfony/SymfonyPasswordHasher.php           (adapter for Symfony password_hasher)
└── Symfony/Security/{SecurityUser,UserProvider}.php
└── Symfony/Console/PromoteAdminCommand.php

Presentation/
└── Http/{SignUpController, MeController, Dto/SignUpRequest}.php
```

Login + Refresh endpoints come from Symfony Security + Lexik + Gesdinet bundles — no controller needed.

## Tests

- `tests/Functional/User/Presentation/Http/SignUpControllerTest.php`
- `tests/Functional/User/Presentation/Http/LoginAndMeTest.php`

Add a `<Verb>CommandHandlerTest.php` whenever you add a new Application command.

## Validation Commands

```bash
make test-api ARG="--testsuite Functional --filter User"
```
