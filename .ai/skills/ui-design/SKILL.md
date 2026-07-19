---
name: ui-design
description: Decide which UI approach to use for a frontend element. Use when adding new UI components, picking between Tailwind and a library component, or answering "what should I use for X?". Triggers on "what component", "how to do tabs", "design decision", "ui recommendation", "frontend pattern".
---

# UI Design Decision Guide

This project uses a **layered UI approach**. The rule is simple: pick the lowest layer that fully covers the need.

## The Stack

```text
Layer 1  @jperdior/ui-react          shadcn/ui primitives, Radix under the hood
Layer 2  Tailwind utility classes     layout, spacing, colour tokens only
Layer 3  Raw HTML                     semantic elements (table, ul, dl…)
```

Never import directly from `@radix-ui/*` or `shadcn/ui` in app code — go through `@jperdior/ui-react`.

---

## Decision Matrix

| What you need | Use |
|---|---|
| Interactive/stateful pattern (tabs, dialog, dropdown, accordion, tooltip, popover, select) | `@jperdior/ui-react` component — add it if missing (see below) |
| Button, input, textarea, label, card, form field, spinner, skeleton | `@jperdior/ui-react` |
| Page-level layout (header, body padding, empty/loading/error states) | `@jperdior/ui-react` (PageHeader, PageBody, EmptyState…) |
| Tabular data with columns | `@jperdior/ui-react` DataTable |
| Static layout (flex, grid, gap, padding) | Tailwind utility classes |
| Colours and visual tokens | Tailwind semantic tokens only (`bg-background`, `text-muted-foreground`, `border-border`, `text-destructive`, `bg-primary`…). **Never** raw shades (`red-500`, `green-100`). |
| Plain semantic markup (list of items, definition list, table rows) | Raw HTML + Tailwind utilities |

### Quick answers

| "How do I…" | Answer |
|---|---|
| Tabs (navigating between content panels) | `<Tabs>`, `<TabsList>`, `<TabsTrigger>`, `<TabsContent>` from `@jperdior/ui-react` |
| Modal / confirmation dialog | `<Dialog>` from `@jperdior/ui-react` |
| Select / combobox | Add `@radix-ui/react-select` to `ui-react`, wrap it, export it |
| Accordion / expandable section | Add `@radix-ui/react-accordion` to `ui-react`, wrap it, export it |
| Tooltip | Add `@radix-ui/react-tooltip` to `ui-react`, wrap it, export it |
| Dropdown menu | Add `@radix-ui/react-dropdown-menu` to `ui-react`, wrap it, export it |
| Badge / chip | Inline Tailwind `<span>` with semantic tokens |
| Status indicator | Inline Tailwind `<span>` |
| Page with a clickable card list | CSS overlay pattern (absolute-positioned `<Link>` + `relative z-10` on inner links) |
| Form | `react-hook-form` + `zod` + `<Form>`, `<FormField>`, `<FormItem>`, `<FormLabel>`, `<FormMessage>` from `@jperdior/ui-react` |

---

## Adding a New Component to `ui-react`

When `@jperdior/ui-react` doesn't have a component you need:

1. **Add the Radix primitive to the workspace.** All JS tooling runs in containers — there is no persistent named container to `docker exec` into (the JS gates are ephemeral). Add the dependency to `packages/ui-react/package.json` under `dependencies` (e.g. `"@radix-ui/react-<name>": "^1"`), then run a JS gate (`make lint-web`) — the ephemeral container reinstalls the workspace on the changed lockfile.
2. **Create** `packages/ui-react/src/primitives/<ComponentName>.tsx`:
   - Copy the shadcn/ui implementation from [ui.shadcn.com/docs/components](https://ui.shadcn.com)
   - Mark `'use client'` at the top
   - Use `cn()` for class composition
   - Use semantic Tailwind tokens — never raw colour shades
   - Pass `ref` as a plain prop (React 19 style — no `forwardRef`)
   - Set `.displayName` for DevTools
3. **Export** from `packages/ui-react/src/index.ts`
4. **Run** `make lint-web` to typecheck (`packages/*` are covered by the typecheck gate)

### Component file template

```tsx
'use client';

import * as React from 'react';
import * as FooPrimitive from '@radix-ui/react-foo';
import { cn } from '../utils/cn';

function Foo({ className, ref, ...props }: React.ComponentProps<typeof FooPrimitive.Root>) {
  return (
    <FooPrimitive.Root
      ref={ref}
      className={cn('/* semantic tokens only */', className)}
      {...props}
    />
  );
}
Foo.displayName = FooPrimitive.Root.displayName;

export { Foo };
```

---

## Rules

- **No arbitrary values**: `text-[13px]` → `text-xs`; `p-[7px]` → `p-2`.
- **No dark: overrides on semantic tokens** — the CSS variables handle dark mode.
- **No inline SVG** — use `lucide-react` icons.
- **No `any` in component props** — use `React.ComponentProps<typeof Primitive>`.
- **Interactive components must be `'use client'`** — Tabs, Dialog, Form, Dropdown all need client context.
- **Server Components can render client components** — just import them; Next.js handles the boundary.
- **Nested links**: use the CSS overlay pattern (absolute `<Link>` + `relative z-10` on inner interactive elements) to avoid invalid `<a>` inside `<a>` HTML.

See also [`packages/ui-react/AGENTS.md`](../../../packages/ui-react/AGENTS.md) for the design-system rules this skill operationalizes.
