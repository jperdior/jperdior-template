---
name: translate-strings
description: Extract new/changed user-facing strings in apps/web into the next-intl message catalogs (en source + es translation) and keep key-parity green. Triggers on "translate strings", "update translations", "i18n strings", "add translations", after adding any web UI text.
---

# Translate Strings (apps/web i18n)

`apps/web` is internationalized with **next-intl** using an as-needed locale prefix: English is the
default and served at `/` (no prefix), Spanish is served under `/es`. Every user-facing string MUST
come from the message catalogs — never a hard-coded literal. Run this skill whenever a feature or fix
adds or changes visible text in `apps/web`.

> Scope: **`apps/web` only**. `apps/admin` is not internationalized. Skip files under `apps/admin/`.

## When to run

- During `/implement-spec` (per phase) when the phase touches `apps/web` UI.
- During `/fix` when the fix adds or changes web-facing text.
- Before `/check-and-commit` / `/open-pr` when `apps/web/src/**` UI files are staged.

## The catalogs

- `apps/web/messages/en.json` — **source of truth** (English).
- `apps/web/messages/es.json` — Spanish, mirrors `en.json` key-for-key.
- Keys are namespaced by feature area (`common`, `nav`, `home`, `auth`, …). Reuse an existing
  namespace before inventing one. Shared words (Save, Cancel, Loading…) live in `common`.

## Access patterns (how strings are consumed)

- **Server Components (async)**: `const t = await getTranslations('namespace')` from `next-intl/server`.
- **Server Components (sync)** and **Client Components**: `const t = useTranslations('namespace')` from `next-intl`.
- **Metadata** (`generateMetadata`): `getTranslations({ locale, namespace })`.
- **Links that stay in-app**: import `Link`, `useRouter`, `usePathname`, `redirect` from
  `@/i18n/navigation` (locale-aware) — NOT from `next/link` / `next/navigation`.
- **Links to non-localized routes** (e.g. an OAuth start URL): keep a plain `<a>` and add
  `{/* eslint-disable-next-line @next/next/no-html-link-for-pages -- … */}`.
- **Plurals / interpolation**: use ICU syntax, e.g.
  `"itemsAria": "{count, plural, one {# item} other {# items}}"` consumed as
  `t('itemsAria', { count })`.

## Workflow

1. **Find new/changed strings.** Diff the branch against its base for `apps/web/src/**`:
   ```bash
   git diff --merge-base origin/main -- apps/web/src | grep -nE '>[^<>{]*[A-Za-z]{2,}[^<>{]*<|(aria-label|placeholder|title|alt)='
   ```
   Also eyeball added JSX text nodes and string props (`aria-label`, `placeholder`, `title`).
2. **Flag hard-coded literals.** Any user-facing literal not routed through `t(...)` is a
   finding — wire it through `useTranslations`/`getTranslations` (add the `t` call + a key).
   Ignore non-user-facing strings (test ids, `className`, `href`, data keys, `console`/logger).
3. **Add keys to `en.json`** under the right namespace. Use a descriptive camelCase leaf key.
4. **Add the Spanish translation to `es.json`** at the identical key path.
5. **Verify parity + lint:**
   ```bash
   make test-web    # runs apps/web/src/lib/message-parity.test.ts — fails if a key is missing in es.json
   make lint-web    # tsc + eslint (catches missing namespace / bad t() usage)
   ```
6. **Report**: list the keys added (per namespace) and any literals that still need extraction
   in files you did not migrate this pass.

## Rules

- **Never** leave a user-facing English literal inline in `apps/web/src/**`.
- **Never** add a key to `en.json` without adding the same key to `es.json` — the parity test
  (`apps/web/src/lib/message-parity.test.ts`) is the CI gate of record.
- **Never** translate user-generated content (entity names, user-authored text, data values) —
  only application chrome.
- Keep the key **structure identical** across locales; only the values differ.
- Prefer reusing an existing key over adding a near-duplicate.

## Definition of done

- Every new/changed web string is consumed via `t(...)`.
- `en.json` and `es.json` have an identical key set (parity test green).
- `make lint-web` and `make test-web` pass.
