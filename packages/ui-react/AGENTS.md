# @jperdior/ui-react — Agents Guidelines

Shared React component primitives. Used by `apps/web` and `apps/admin`. Built on **shadcn/ui** + Radix + Tailwind v3 + lucide-react.

## Always

- Build new primitives by copying from **shadcn/ui** then trimming. Don't reach for new libs without ask-first.
- Use the **semantic Tailwind tokens** exposed via the Tailwind preset (`bg-background`, `text-foreground`, `border-border`, `bg-destructive`, etc.).
- Export from `src/index.ts`.
- Use `cn()` for className composition (clsx + tailwind-merge).
- For interactive components, mark them `'use client'` at the top of the file.
- In React 19, pass `ref` as a plain prop (no `forwardRef`). Keep `.displayName` for DevTools.

## Never

- **Never** use hardcoded colors (`text-red-500`, `bg-green-100`). Use semantic / status tokens.
- **Never** use arbitrary values (`text-[13px]`, `p-[7px]`).
- **Never** add `dark:` overrides on semantic tokens — they handle dark mode via CSS variables.
- **Never** import from a specific app (`apps/web/...`). This package is consumer-agnostic.
- **Never** inline `<svg>` — use `lucide-react`.

## Validation Commands

```bash
pnpm -C packages/ui-react typecheck
pnpm -C packages/ui-react lint
```

## Structure

```
src/
├── index.ts                      ← public exports
├── styles/globals.css            ← Tailwind layer + CSS variables for tokens
├── utils/cn.ts                   ← clsx + twMerge
├── primitives/                   ← Button, Input, Textarea, Label, Card, Form, Spinner, Skeleton
├── states/                       ← Loading/Error/Empty states
├── layout/                       ← PageHeader, PageBody
└── data/                         ← DataTable
tailwind.preset.cjs               ← consumed by apps' tailwind.config.js via `presets: [...]`
```

## Consumption in an app

```ts
// tailwind.config.ts in apps/web
import preset from '@jperdior/ui-react/tailwind-preset';
export default {
  presets: [preset],
  content: [
    './src/**/*.{ts,tsx}',
    '../../packages/ui-react/src/**/*.{ts,tsx}',
  ],
};

// in a page or component
import { Button, Form, FormField, /* ... */ } from '@jperdior/ui-react';
import '@jperdior/ui-react/styles.css';
```
