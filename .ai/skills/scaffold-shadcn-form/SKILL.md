---
name: scaffold-shadcn-form
description: Generate a shadcn-based form using react-hook-form + zod, with submit/cancel handlers and DS-compliant primitives. Triggers on "scaffold form", "new form", "add form", "shadcn form".
---

# Scaffold shadcn Form

Generate a typed, validated form following the template's DS rules.

## Workflow

1. **Ask for the form's purpose** ("sign up", "edit user profile", "change password").
2. **Identify the API endpoint** the form submits to.
3. **Generate the zod schema** matching the API Request DTO.
4. **Generate the form component**:

```
apps/<web|admin>/src/components/<feature>/<verb><Aggregate>Form.tsx
```

5. **Wire it up** in the page (Server Action or client mutation).

## Template

```tsx
'use client';

import { z } from 'zod';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
  Input,
  Textarea,
  Button,
  Spinner,
} from '@jperdior/ui-react';

const schema = z.object({
  title: z.string().min(1, 'Title is required').max(200),
  body:  z.string().min(1, 'Body is required').max(10_000),
});

type FormValues = z.infer<typeof schema>;

interface Props {
  onSubmit: (values: FormValues) => Promise<void>;
  onCancel?: () => void;
  defaultValues?: Partial<FormValues>;
}

export function CreateNoteForm({ onSubmit, onCancel, defaultValues }: Props) {
  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { title: '', body: '', ...defaultValues },
  });

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit(onSubmit)}
        onKeyDown={(e) => {
          if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
            form.handleSubmit(onSubmit)();
          }
          if (e.key === 'Escape' && onCancel) {
            onCancel();
          }
        }}
        className="flex flex-col gap-4"
      >
        <FormField
          control={form.control}
          name="title"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Title</FormLabel>
              <FormControl>
                <Input {...field} autoFocus />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="body"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Body</FormLabel>
              <FormControl>
                <Textarea rows={6} {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <div className="flex justify-end gap-2 pt-2">
          {onCancel && (
            <Button type="button" variant="ghost" onClick={onCancel}>
              Cancel
            </Button>
          )}
          <Button type="submit" disabled={form.formState.isSubmitting}>
            {form.formState.isSubmitting && <Spinner className="mr-2 size-4" />}
            Save
          </Button>
        </div>
      </form>
    </Form>
  );
}
```

## Rules

- **zod schema drives types** — `type FormValues = z.infer<typeof schema>`.
- **`Cmd/Ctrl+Enter`** to submit, **`Escape`** to cancel — on every form.
- **Disabled submit while pending**, with spinner.
- **shadcn `Form` primitives only** — never raw `<input>` outside a Form.
- **No hardcoded colors / arbitrary sizes** — see `.ai/ds-rules.md`.
- **Schema MUST match the API DTO** — generate the schema FROM `@jperdior/api-client-ts` types where possible:
  ```ts
  import type { CreateNoteRequest } from '@jperdior/api-client-ts';
  ```

## Output

```
✅ Form scaffolded: <Verb><Aggregate>Form
   File: src/components/<feature>/<file>.tsx
   Schema: zod
   API binding: <CreateNoteRequest type>
```
