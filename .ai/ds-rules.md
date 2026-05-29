# Design System Rules

The frontends (`apps/web`, `apps/admin`) use **Tailwind CSS + shadcn/ui** with semantic tokens. Follow these rules to keep visual consistency and dark-mode correctness.

## Always

- Use **semantic tokens** (`bg-background`, `text-foreground`, `border-border`, `bg-card`, `text-muted-foreground`, `bg-primary`, `text-primary-foreground`, etc.) instead of raw color shades.
- Use **status tokens** for state: `text-destructive`, `bg-destructive`, `text-success`, `bg-success`, `text-warning`, `bg-warning`, `text-info`, `bg-info`.
- Use the **Tailwind text scale** (`text-xs`, `text-sm`, `text-base`, `text-lg`, `text-xl`, …). Use `font-medium` / `font-semibold` / `font-bold` for weight.
- Use shared **shadcn primitives** from `@jperdior/ui-react`: `Button`, `Input`, `Form`, `Card`, `Dialog`, `Toast`, `Skeleton`, `Spinner`, `EmptyState`.
- Use **lucide-react** icons. Every icon-only button MUST have an `aria-label`.
- Every dialog/sheet MUST support `Cmd/Ctrl+Enter` to submit and `Escape` to cancel.
- Every form MUST surface field-level errors via the shadcn `FormMessage` component (driven by `react-hook-form` + `zod`).

## Never

- **NEVER** use hardcoded Tailwind color shades for status (`text-red-500`, `bg-green-100`, `text-amber-700`). Use `text-destructive`, `bg-success/10`, `text-warning` instead.
- **NEVER** use arbitrary values (`text-[13px]`, `p-[7px]`, `rounded-[24px]`, `z-[9999]`). Use the design-system scale.
- **NEVER** add `dark:` overrides to semantic or status tokens — they already adapt to dark mode via CSS variables.
- **NEVER** hardcode hex/rgb in `className`. Use semantic CSS variables defined in `packages/ui-react/src/styles/globals.css`.
- **NEVER** import shadcn components from a third-party CDN — every primitive lives in `@jperdior/ui-react`.
- **NEVER** inline `<svg>` in page bodies — use `lucide-react`.

## Boy Scout Rule

When you touch a file containing hardcoded status colors, arbitrary text sizes, `dark:` overrides on status tokens, or inline `<svg>`, migrate at minimum the lines you touched to the design system.

## Token Reference

| Need | Token |
|------|-------|
| Page background | `bg-background` |
| Page foreground (text) | `text-foreground` |
| Subtle text | `text-muted-foreground` |
| Card surface | `bg-card text-card-foreground` |
| Primary CTA | `bg-primary text-primary-foreground` |
| Secondary CTA | `bg-secondary text-secondary-foreground` |
| Border / input border | `border-border` / `border-input` |
| Focus ring | `ring-ring` |
| Error state | `text-destructive` / `bg-destructive/10` |
| Success state | `text-success` / `bg-success/10` |
| Warning state | `text-warning` / `bg-warning/10` |
| Info state | `text-info` / `bg-info/10` |

## Status Mapping (Convention)

| Domain status | Token |
|---------------|-------|
| Failed / error / blocked | `destructive` |
| Succeeded / approved / active | `success` |
| Pending / draft / in-progress | `warning` |
| Informational / neutral hint | `info` or `muted-foreground` |
| Archived / inactive / disabled | `muted-foreground` with `opacity-60` |

## Page Layout

- Use the `<PageHeader>` / `<PageBody>` composition from `@jperdior/ui-react`.
- Use `<DataTable>` for any tabular data.
- Use `<EmptyState>` for empty lists.
- Use `<LoadingState>` / `<Skeleton>` for loading.
- Use `<ErrorState>` for unrecoverable errors.

## Forms

- Use `<Form>` (react-hook-form + zod) from `@jperdior/ui-react`.
- Use `<FormField>` per field; never write raw `<input>` outside Forms.
- Place primary actions in a `<FormFooter>` with the primary button right-aligned.
- Disable the submit button while pending; show a `<Spinner>` inside it.

## Validation Command

```bash
make lint-web   # tsc + eslint with the design-system rule pack
```
