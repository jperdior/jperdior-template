'use client';

import { useActionState } from 'react';
import Link from 'next/link';
import { Button, Input, Label, Spinner, Textarea } from '@jperdior/ui-react';
import { createNoteAction, type NoteState } from '../actions';

export function NewNoteForm() {
  const [state, formAction, isPending] = useActionState<NoteState, FormData>(createNoteAction, {});

  return (
    <form
      action={formAction}
      onKeyDown={(e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
          (e.currentTarget as HTMLFormElement).requestSubmit();
        }
      }}
      className="flex flex-col gap-4"
    >
      <div className="space-y-2">
        <Label htmlFor="title">Title</Label>
        <Input id="title" name="title" autoFocus required maxLength={200} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="body">Body</Label>
        <Textarea id="body" name="body" rows={8} required maxLength={10_000} />
      </div>
      {state.error && <p className="text-sm text-destructive">{state.error}</p>}
      <div className="flex justify-end gap-2 pt-2">
        <Button type="button" variant="ghost" asChild>
          <Link href="/notes">Cancel</Link>
        </Button>
        <Button type="submit" disabled={isPending}>
          {isPending && <Spinner />}
          Save
        </Button>
      </div>
    </form>
  );
}
