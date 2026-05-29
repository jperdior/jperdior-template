---
name: scaffold-doctrine-migration
description: Generate a Doctrine migration via `php bin/console doctrine:migrations:diff`, then review and trim it to the intended scope. Triggers on "create migration", "scaffold migration", "doctrine diff".
---

# Scaffold Doctrine Migration

Generate a migration matching the current entity / XML mapping state, then **review** it before committing.

## Workflow

1. **Confirm entity / XML change is committed locally** (not just in the editor — the diff tool reads what Doctrine sees on disk).
2. **Run the diff**:
   ```sh
   make migrate-diff
   ```
3. **Open the generated file** under `apps/api/migrations/Version{timestamp}.php`.
4. **Review every statement**:
   - Does each statement correspond to an intended change?
   - Are any unrelated tables / columns being altered? If yes, that's a sign of snapshot drift or another change in flight — investigate.
   - Are indexes / constraints sensible?
5. **Trim unrelated churn**. If the generator produced changes for tables outside the intended scope, manually edit the migration to remove them. Document why in the migration's `getDescription()`.
6. **Apply locally** to verify:
   ```sh
   make migrate
   ```
7. **Roll back to verify down()**:
   ```sh
   docker compose -p jperdior exec api php bin/console doctrine:migrations:migrate prev --no-interaction
   docker compose -p jperdior exec api php bin/console doctrine:migrations:migrate --no-interaction
   ```
8. **Commit** the migration with a precise message.

## Rules

- **Migrations are immutable once merged.** Never edit a migration that's already on `main`.
- **One migration per logical change.** Don't bundle "add notes table + rename user column".
- **Always implement `down()`** even when reversal is awkward — at minimum document why it's intentionally a no-op.
- **Never** apply a migration locally that adds destructive `DROP` statements without confirming.
- **Never** commit a migration that touches > 1 bounded context's tables in a single feature PR — split into multiple migrations.

## Naming

The default `Version{timestamp}.php` is fine for production. Optionally rename via `getDescription()`:

```php
public function getDescription(): string
{
    return 'Add notes table with owner FK to users.';
}
```

## Output

```
✅ Migration scaffolded: Version<timestamp>.php
   Description: <one-liner>
   Up statements: {N}
   Down statements: {M}
   Tables affected: <list>
   Local apply: SUCCESS
   Local rollback: SUCCESS
```
