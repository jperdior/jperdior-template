# Persistence

Database and ORM conventions for the API.

---

## Database

- **PostgreSQL 16** — the only supported dialect. No MySQL or SQLite.
- Connection configured via `DATABASE_URL` env var in `.env.local`.
- Test DB: `DATABASE_URL` + `_test{TOKEN}` suffix (auto-appended in `when@test:` doctrine config).

---

## Naming Conventions

| Thing | Convention | Example |
|-------|-----------|---------|
| Tables | snake_case **plural** | `users`, `password_recovery_tokens`, `refresh_tokens` |
| Columns | snake_case | `created_at`, `must_reset_password`, `token_hash` |
| Primary keys | `id` (UUID v4, string) | `id VARCHAR(36) NOT NULL` |
| Foreign keys | `<referenced_table_singular>_id` | `user_id`, `owner_id` |
| Unique constraints | `uq_<table>_<columns>` | `uq_users_email` |
| Indexes | `idx_<table>_<columns>` | `idx_password_recovery_tokens_token_hash` |
| Foreign key constraints | `fk_<table>_<referenced>` | `fk_password_recovery_tokens_user` |

Standard columns on every table:
- `id` — UUID v4 primary key (string, 36 chars)
- `created_at` — `TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL`
- `updated_at` — optional, for mutable aggregates
- `deleted_at` — optional, `TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL` for soft-delete

---

## Current Schema

### `users`

| Column | Type | Notes |
|--------|------|-------|
| `id` | `VARCHAR(36)` | UUID v4 PK |
| `email` | `VARCHAR(180)` | Unique, `uq_users_email` |
| `password` | `VARCHAR(255)` | Argon2id hash |
| `roles` | `JSON` | String array, e.g. `["ROLE_USER"]` |
| `created_at` | `TIMESTAMP` | `datetime_immutable` |
| `must_reset_password` | `BOOLEAN` | Default `false` |
| `deleted_at` | `TIMESTAMP?` | Soft-delete |

### `refresh_tokens`

Managed by Lexik/Gesdinet bundle. See bundle docs for schema. Has FK to `users` implicitly via `username` (email) field.

### `password_recovery_tokens`

| Column | Type | Notes |
|--------|------|-------|
| `id` | `VARCHAR(36)` | UUID v4 PK |
| `user_id` | `VARCHAR(36)` | FK → `users(id)` ON DELETE CASCADE |
| `token_hash` | `VARCHAR(64)` | SHA-256 of the plain token |
| `expires_at` | `TIMESTAMP` | 1-hour TTL |
| `used_at` | `TIMESTAMP?` | NULL until redeemed |
| `created_at` | `TIMESTAMP` | |

Partial unique index: `uq_password_recovery_tokens_active_per_user ON (user_id) WHERE used_at IS NULL` — enforces at most one active token per user atomically.

---

## Persistence Model Pattern

**Domain entities never carry Doctrine attributes.** Every aggregate has a dedicated `*Model` class in `Infrastructure/Persistence/Doctrine/` that holds all ORM mapping.

### Architecture

```
Domain/
└── <Aggregate>.php           ← pure PHP, no #[ORM\*], no framework imports

Infrastructure/
└── Persistence/
    ├── Doctrine<Aggregate>Repository.php   ← toDomain() / toOrm() bridge
    └── Doctrine/
        └── <Aggregate>Model.php            ← #[ORM\Entity], primitive fields only
```

### `*Model` class rules

1. **`#[ORM\Entity]`** on the class.
2. **`#[ORM\Table(name: 'snake_case_plural')]`** with explicit table name.
3. **`*Model` classes are NOT `final`.** This is the one exception to the project's final-by-default
   rule: Doctrine builds a runtime subclass (proxy/lazy-ghost) for `getReference()` and lazy
   associations, and you cannot subclass a final class (`Cannot generate lazy ghost … is final`).
   The `final_class` php-cs-fixer rule exempts classes carrying `#[ORM\Entity]`, so leaving them
   non-final is lint-clean.
4. Fields are **`public`** — Doctrine 3 lazy-ghost proxies set them directly.
5. Scalar fields use **primitive PHP types only**: `string`, `int`, `bool`, `DateTimeImmutable`, `array` (for JSON). No value objects.
6. **`#[ORM\Id]`** + `#[ORM\Column(type: 'string', length: 36)]` on `$id`. **No auto-generation** (`GeneratedValue(strategy: 'NONE')`) — IDs are UUIDs generated at the application layer.
7. **Explicit column names** with `name:` attribute when they differ from the property name.
8. **Relationships are permitted** where a real, Doctrine-managed foreign key is wanted:
   `#[ORM\ManyToOne]` with a `#[ORM\JoinColumn(... onDelete: 'CASCADE')]`. Doctrine owns the FK and
   its indexes, so `migrate-diff` stays stable. Set associations from the repository via
   `em->getReference(TargetModel::class, $id)` (a proxy — no DB round-trip). For an id-only read/filter
   use `IDENTITY(x.assoc)` in DQL so you don't need to load the target. See **Cross-Context FK
   References** for the boundary rule.

Example — `UserModel`:
```php
#[ORM\Entity]
#[ORM\Table(name: 'users')]
class UserModel
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    public string $id;

    #[ORM\Column(type: 'string', length: 180)]
    public string $email;

    #[ORM\Column(type: 'json')]
    public array $roles = [];

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    public DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $deletedAt = null;
    // …
}
```

### Repository pattern

```
Domain/<Aggregate>Repository.php              ← interface (port)
Infrastructure/Persistence/Doctrine<Aggregate>Repository.php  ← implementation (adapter)
```

The interface lives in `Domain/` so application-layer code depends on an abstraction. The implementation extends `Shared\Infrastructure\Doctrine\DoctrineRepository` (which wraps `EntityManagerInterface`) and handles the `toDomain()` / `toOrm()` mapping.

```php
// Interface in Domain — pure abstraction
interface UserRepository
{
    public function save(User $user): void;
    public function findById(UserId $id): ?User;
}

// Implementation in Infrastructure — Doctrine + mapping
final class DoctrineUserRepository extends DoctrineRepository implements UserRepository
{
    public function save(User $user): void
    {
        $existing = $this->entityManager()->find(UserModel::class, $user->id()->value);
        $this->persist($this->toOrm($user, $existing));
    }

    public function findById(UserId $id): ?User
    {
        $model = $this->entityManager()
            ->createQueryBuilder()
            ->select('u')
            ->from(UserModel::class, 'u')
            ->where('u.id = :id')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('id', $id->value)
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();

        return null !== $model ? $this->toDomain($model) : null;
    }

    private function toDomain(UserModel $m): User
    {
        return User::rehydrate(
            UserId::fromString($m->id),
            new Email($m->email),
            // …value-object construction validates invariants
        );
    }

    private function toOrm(User $user, ?UserModel $existing = null): UserModel
    {
        $model = $existing ?? new UserModel();
        $model->id = $user->id()->value;
        $model->email = $user->email()->value;
        // …primitive assignment, no Doctrine metadata needed
        return $model;
    }
}
```

The repository alias is registered in `config/services.yaml`:
```yaml
App\<Context>\Domain\<Aggregate>Repository:
    alias: App\<Context>\Infrastructure\Persistence\Doctrine<Aggregate>Repository
```

### Why not custom DBAL types?

The older approach defined custom `Type` classes (e.g. `UserIdType`) and registered them in `doctrine.yaml` so Doctrine could hydrate value objects directly on the domain entity. This fails with PHP 8.4 lazy-ghost objects because Doctrine enforces typed property assignment strictly — a `UserId` value object can't be set on a `string $id` field.

The `*Model` pattern sidesteps this entirely: the model uses only primitives, the repository handles conversion, and no custom DBAL types are needed.

---

## Doctrine Configuration

`config/packages/doctrine.yaml`:
```yaml
doctrine:
    orm:
        auto_generate_proxy_classes: true
        enable_lazy_ghost_objects: true
        report_fields_where_declared: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: false
        mappings:
            <Context>:
                type: attribute
                is_bundle: false
                dir: '%kernel.project_dir%/src/<Context>/Infrastructure/Persistence/Doctrine'
                prefix: 'App\<Context>\Infrastructure\Persistence\Doctrine'
                alias: <Context>
```

Key points:
- `auto_mapping: false` — each context is registered explicitly.
- `naming_strategy: underscore_number_aware` — `mustResetPassword` → `must_reset_password` automatically, but explicit `name:` on `#[ORM\Column]` takes priority.
- `enable_lazy_ghost_objects: true` — PHP 8.4 lazy ghost proxies.
- `report_fields_where_declared: true` — avoids issues with promoted constructor properties.

---

## Migrations

Generated with `make migrate-diff`, reviewed manually, applied with `make migrate`.

Rules:
- One migration per logical change — don't bundle unrelated table changes.
- Always implement `down()` — at minimum document why it's a no-op.
- Review generated SQL for unrelated churn before committing.
- Never edit a migration that's already been merged to `main`.
- Test both `up()` and `down()` locally before committing.

---

## Cross-Context FK References

A cross-aggregate reference is a **real, Doctrine-managed foreign key** mapped as an
`#[ORM\ManyToOne]` association on the owning `*Model`, with a `#[ORM\JoinColumn]` carrying
`onDelete: 'CASCADE'`. Doctrine owns the FK constraint and its index, so `migrate-diff` generates
them and stays stable (no perpetual drift). This applies both **within** a context and **across**
contexts.

```php
// An association owner (e.g. an Order↔User link) mapping two real FKs
#[ORM\ManyToOne(targetEntity: OrderModel::class)]
#[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
public OrderModel $order;

#[ORM\ManyToOne(targetEntity: UserModel::class)]                 // cross-context — Persistence boundary only
#[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
public UserModel $user;
```

**Boundary rule (L-009).** A cross-context association is the **one** place a `*Model` may import
another context's `*Model`. It is a Persistence-boundary coupling only — Domain / Application /
Presentation must never import across contexts — and every such import is whitelisted in
`apps/api/deptrac.yaml` `skip_violations` (both the `*Model` and any repository that calls
`getReference(OtherModel::class, …)`). Keep filters id-based (`IDENTITY(x.assoc) = :id`) so read
repositories don't need the other `*Model` at all.

To name the referenced id in the **domain** without importing another context's ID type, define a
local value object (extends the shared-kernel `Uuid`):

```php
// In Order\Domain\OwnerId — NOT importing User\Domain\UserId
final readonly class OwnerId extends \Jperdior\SharedKernel\Domain\ValueObject\Uuid {}
```

This keeps the domain decoupled — `Order\Domain\*` never imports `User\Domain\*`.

---

## Soft Delete

When an aggregate supports soft delete:
- `*Model` has a nullable `deletedAt: ?DateTimeImmutable` column.
- Repository queries filter `WHERE deletedAt IS NULL` by default.
- Expose `findByIdIncludingDeleted()` / `findAllIncludingDeleted()` for admin/restore use cases.
- The domain aggregate tracks its own `deletedAt` state and refuses mutations after deletion.
