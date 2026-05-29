---
name: lint-js
description: Run all frontend quality checks locally — TypeScript typecheck and ESLint — across apps/web, apps/admin, and packages. Triggers on "run eslint", "js lint", "frontend quality", "typecheck", "check typescript", "ts errors", "lint frontend", "check frontend".
---

# Frontend Quality Checks

Run the full JS/TS lint suite that mirrors the `js-lint` CI job.

## Scope

| Target | Tool |
|--------|------|
| `apps/web`, `apps/admin`, `packages/*` | TypeScript (`tsc --noEmit`) |
| `apps/web`, `apps/admin` | ESLint |

## Workflow

Run these from the **repo root**. Stop and report errors before continuing.

### 1 — Install dependencies

```bash
pnpm install --frozen-lockfile=false
```

### 2 — TypeScript (all apps + packages)

```bash
pnpm -r --filter './apps/*' --filter './packages/*' typecheck
```

### 3 — ESLint (apps only)

```bash
pnpm -r --filter './apps/*' lint
```

## Error handling

- **TypeScript errors**: fix the types in the source file. Never use `@ts-ignore` or `as any` — find the correct type.
- **ESLint errors**: fix the source. If a rule fires on a legitimate pattern (e.g. `react/no-unescaped-entities`), escape the character properly (`&apos;`, `&quot;`) — don't disable rules.
- **ESLint warnings** with `--max-warnings=0`: treat them as errors.

## Quick one-liner (from repo root)

```bash
make lint          # runs everything (php + js)
# or JS only:
pnpm -r --filter './apps/*' --filter './packages/*' typecheck && pnpm -r --filter './apps/*' lint
```

## Per-package shortcut

```bash
pnpm -C apps/web typecheck
pnpm -C apps/web lint

pnpm -C apps/admin typecheck
pnpm -C apps/admin lint

pnpm -C packages/ui-react typecheck
pnpm -C packages/api-client-ts typecheck
```
