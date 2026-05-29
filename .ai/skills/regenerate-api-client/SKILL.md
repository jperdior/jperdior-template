---
name: regenerate-api-client
description: Regenerate the TypeScript API client (packages/api-client-ts) from the running API's OpenAPI spec. Triggers on "regen API client", "update TS client", "openapi-typescript".
---

# Regenerate API Client

Refresh `packages/api-client-ts/src/types.gen.ts` so the frontend types match the current API.

## Workflow

1. **Ensure the API is running**:
   ```sh
   make ps        # check that the `api` container is up
   make start     # if not
   ```
2. **Run the generator**:
   ```sh
   make gen-api
   ```
   This:
   - Dumps the OpenAPI spec to `apps/api/openapi.json` via `php bin/console nelmio:apidoc:dump --format=json`.
   - Runs `openapi-typescript apps/api/openapi.json -o packages/api-client-ts/src/types.gen.ts`.
3. **Verify the diff**:
   ```sh
   git diff packages/api-client-ts/src/types.gen.ts
   ```
4. **Run `pnpm -C apps/web typecheck && pnpm -C apps/admin typecheck`** to catch any frontend code that now doesn't compile because the API contract changed.
5. **Fix downstream consumers** — usually small adjustments to field names or required/optional changes.

## When to Run

- After adding or modifying any HTTP endpoint.
- After changing a Request or Response DTO.
- After bumping `nelmio/api-doc-bundle`.
- Before every PR that touches the API.

## Rules

- **`types.gen.ts` is generated** — never edit by hand. The `.gitignore` does NOT ignore it, because it's checked in for CI parity, but it's regenerated on every API change.
- **CI fails if `types.gen.ts` is out of date.** A pre-commit hook (optional) runs the generator on commits that touch `apps/api/code/src/`.
- **Always run the generator from `make`** — it ensures the running API serves the dump (the static spec file isn't enough).

## Output

```
✅ API client regenerated
   OpenAPI dump: apps/api/openapi.json ({size} bytes)
   Types: packages/api-client-ts/src/types.gen.ts (+{added} −{removed} lines)
   Downstream typecheck: PASS
```
