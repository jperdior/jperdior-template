# Persistence Model Pattern

**Date:** 2026-06-02
**Status:** Approved

## Summary

Replace XML-based Doctrine mapping (where domain aggregates are managed directly by Doctrine) with the Persistence Model pattern: a dedicated infrastructure `*Model` class holds Doctrine attributes and primitive fields; the repository converts between the model and the domain aggregate via `toDomain()` / `toOrm()`.

## Motivation

The current approach forces domain aggregates to carry Doctrine-friendly backing fields:

> "Backing fields are primitive types so Doctrine can hydrate them directly. Getters wrap them in value objects."

This leaks ORM concerns into the Domain layer. The aggregate cannot use `readonly` properties freely, and `rehydrate()` exists primarily to satisfy Doctrine's proxy mechanism rather than as a clean domain factory. The Persistence Model pattern removes all ORM constraints from the Domain layer.

## Scope

Migration-wide clean cut. Applies to all bounded contexts. Currently only the `User` context has mappings; `Note` has no Doctrine entities and is unaffected.

## Architecture

```
Domain/User.php                                      ← pure aggregate, no ORM
        ↑  toDomain(UserModel): User
Infrastructure/Persistence/Doctrine/UserModel.php    ← PHP attributes, primitives
        ↑  toOrm(User): UserModel
Infrastructure/Persistence/DoctrineUserRepository.php ← conversion lives here
```

Doctrine manages `UserModel` exclusively. The domain aggregate is never seen by Doctrine.

## Components

### `UserModel` (new)

Location: `User/Infrastructure/Persistence/Doctrine/UserModel.php`

- PHP attributes: `#[ORM\Entity]`, `#[ORM\Table(name: 'users')]`
- All fields **public** — no getters needed; this is infrastructure-only, no encapsulation required
- All fields as primitives: `string $id`, `string $email`, `string $password`, `array $roles`, `DateTimeImmutable $createdAt`, `bool $mustResetPassword`, `?DateTimeImmutable $deletedAt`
- No business logic. No value objects. No domain imports.

### `DoctrineUserRepository` (modified)

- All DQL queries reference `UserModel::class` instead of `User::class`
- `save(User $user)` upserts: finds existing `UserModel` by id via identity map (`em->find()`) or creates a new one, then sets all fields from the aggregate
- Private `toDomain(UserModel $m): User` — reconstructs the aggregate via `User::rehydrate()` with value objects built from primitives
- Private `toOrm(User $user): UserModel` — maps aggregate state to the model's primitive fields

```php
private function toDomain(UserModel $m): User
{
    return User::rehydrate(
        UserId::fromString($m->id),
        new Email($m->email),
        new HashedPassword($m->password),
        array_map(Role::from(...), $m->roles),
        $m->createdAt,
        $m->mustResetPassword,
        $m->deletedAt,
    );
}

private function toOrm(User $user, ?UserModel $existing = null): UserModel
{
    $model = $existing ?? new UserModel();
    $model->id = $user->id()->value;
    $model->email = $user->email()->value;
    $model->password = $user->password()->value;
    $model->roles = $user->roleStrings();
    $model->createdAt = $user->createdAt();
    $model->mustResetPassword = $user->mustResetPassword();
    $model->deletedAt = $user->deletedAt();
    return $model;
}

public function save(User $user): void
{
    $existing = $this->entityManager()->find(UserModel::class, $user->id()->value);
    $this->persist($this->toOrm($user, $existing));
}
```

### `RefreshToken` (modified)

`User/Infrastructure/Security/RefreshToken.php` adds `#[ORM\Entity]` and `#[ORM\Table(name: 'refresh_tokens')]` attributes. The XML file `Security/Mapping/RefreshToken.orm.xml` is deleted.

### `doctrine.yaml` (modified)

- Remove DBAL type registrations: `user_id`, `user_email`, `hashed_password`
- Change `User` mapping: `type: xml` → `type: attribute`, dir points to `src/User/Infrastructure/Persistence/Doctrine`, prefix `App\User\Infrastructure\Persistence\Doctrine`
- Change `UserSecurity` mapping: `type: xml` → `type: attribute`, dir points to `src/User/Infrastructure/Security`, prefix `App\User\Infrastructure\Security`
- `GesdinetJWTRefreshTokenBundle` xml entry is unchanged (vendor-owned)

## Deleted Files

| File | Reason |
|------|--------|
| `User/Infrastructure/Persistence/Doctrine/Mapping/User.orm.xml` | Replaced by `UserModel` attributes |
| `User/Infrastructure/Persistence/Doctrine/Type/EmailType.php` | Custom types redundant; `UserModel` uses primitives |
| `User/Infrastructure/Persistence/Doctrine/Type/HashedPasswordType.php` | Same |
| `User/Infrastructure/Persistence/Doctrine/Type/UserIdType.php` | Same |
| `User/Infrastructure/Security/Mapping/RefreshToken.orm.xml` | Replaced by attributes on `RefreshToken.php` |

## Data Flow

**Read path:**
1. Repository executes DQL against `UserModel`
2. Doctrine hydrates `UserModel` with primitive values
3. `toDomain()` constructs value objects and calls `User::rehydrate()`
4. Domain aggregate returned to the application layer

**Write path:**
1. Application layer passes domain `User` to `save()`
2. `em->find(UserModel::class, $id)` loads existing row (or `null`)
3. New `UserModel` created if null; existing one updated if found (identity map upsert)
4. All primitive fields set from aggregate
5. `persist()` + `flush()`

## Testing

No schema changes. All tables, columns, and migration files remain identical. Existing functional tests pass without modification. The change is purely in how Doctrine maps PHP objects to rows.

## AGENTS.md Updates

**`apps/api/AGENTS.md`:**
- Remove: "Use **XML** Doctrine mapping under `Infrastructure/Persistence/Doctrine/Mapping/`."
- Remove: "**Never** add `#[ORM\*]` attributes to domain entities; XML only."
- Add: "Use PHP attributes on `*Model` persistence classes under `Infrastructure/Persistence/Doctrine/`. Never put `#[ORM\*]` on Domain entities."

**Scaffold skill / bounded-context template:** updated to generate `*Model` with attributes instead of XML mapping files.

## Trade-offs

| | Current (XML + custom types) | Persistence Model |
|-|------------------------------|-------------------|
| Domain freedom | Constrained (primitive backing fields) | Full freedom |
| Code volume | XML file + custom type per VO | `toDomain` + `toOrm` per repository |
| Memory | One object per aggregate | Two objects per aggregate |
| Query complexity | DQL references domain class directly | DQL references `*Model` |
| Schema evolution | XML change required | `UserModel` change only |

## Future Contexts

Every new bounded context follows this pattern:
1. Create `Infrastructure/Persistence/Doctrine/<Context>Model.php` with attributes
2. Implement `toDomain()` and `toOrm()` in the repository
3. Register `type: attribute` mapping in `doctrine.yaml`
4. No XML files, no custom DBAL types
