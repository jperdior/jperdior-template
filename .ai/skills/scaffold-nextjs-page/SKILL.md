---
name: scaffold-nextjs-page
description: Create a Next.js 15 App Router page with loading.tsx, error.tsx, and optional Server Action stub. Uses shadcn primitives from @jperdior/ui-react. Triggers on "scaffold page", "new page", "add Next.js page", "create route".
---

# Scaffold Next.js Page

Generate a Next.js App Router segment with loading + error states and (optionally) a Server Action for mutations.

## Workflow

1. **Identify the target app**: `apps/web` (public) or `apps/admin` (back-office).
2. **Pick the route**: e.g. `/notes`, `/notes/[id]`, `/(app)/profile`. Decide if it sits in a route group (`(app)`).
3. **Decide Server vs Client**:
   - Default to **Server Component** (`page.tsx` is server by default).
   - Only mark `"use client"` when the file needs interactivity (forms, browser APIs, state, event handlers).
4. **Generate**:

```
apps/<web|admin>/src/app/<segment>/
├── page.tsx           ← server component by default; fetches via api-client-ts
├── loading.tsx        ← <LoadingState /> from @jperdior/ui-react
├── error.tsx          ← "use client" — <ErrorState />
└── (optional)
    ├── actions.ts     ← "use server" — Server Actions for mutations
    └── form.tsx       ← "use client" — interactive form
```

5. **Run `pnpm -C apps/<app> typecheck`** to confirm.

## Templates

### page.tsx (Server Component)

```tsx
import { apiClient } from '@jperdior/api-client-ts/server';
import { PageHeader, PageBody, DataTable, EmptyState } from '@jperdior/ui-react';

export default async function NotesPage() {
  const notes = await apiClient.listNotes();

  if (notes.length === 0) {
    return (
      <PageBody>
        <PageHeader title="Notes" />
        <EmptyState
          title="No notes yet"
          description="Create your first note to get started."
        />
      </PageBody>
    );
  }

  return (
    <PageBody>
      <PageHeader title="Notes" />
      <DataTable
        data={notes}
        columns={[
          { header: 'Title', accessor: 'title' },
          { header: 'Created', accessor: 'createdAt' },
        ]}
      />
    </PageBody>
  );
}
```

### loading.tsx

```tsx
import { LoadingState } from '@jperdior/ui-react';

export default function Loading() {
  return <LoadingState />;
}
```

### error.tsx

```tsx
'use client';

import { ErrorState } from '@jperdior/ui-react';

export default function Error({ error, reset }: { error: Error; reset: () => void }) {
  return <ErrorState message={error.message} onRetry={reset} />;
}
```

### actions.ts (Server Action)

```tsx
'use server';

import { revalidatePath } from 'next/cache';
import { apiClient } from '@jperdior/api-client-ts/server';

export async function createNote(formData: FormData) {
  const title = String(formData.get('title'));
  const body  = String(formData.get('body'));
  await apiClient.createNote({ title, body });
  revalidatePath('/notes');
}
```

## Rules

- **Server Component by default.** Every `"use client"` MUST be justified.
- **Use `@jperdior/api-client-ts`**, never raw `fetch`.
- **Use shadcn primitives from `@jperdior/ui-react`**, never raw HTML for layout-sensitive UI.
- **Loading + error states are mandatory** — the file must exist even if it's a one-liner.
- **DS tokens** for colors; no hardcoded shades. See `.ai/ds-rules.md`.
- **Cmd/Ctrl+Enter** to submit + `Escape` to cancel on every dialog.

## Output

```
✅ Page scaffolded: /<segment>
   Files: page.tsx, loading.tsx, error.tsx (+ actions.ts + form.tsx if interactive)
   Server / Client breakdown: {ServerCount} / {ClientCount}
   Typecheck: PASS
```
