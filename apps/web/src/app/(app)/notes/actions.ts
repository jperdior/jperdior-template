'use server';

import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { z } from 'zod';
import { apiClient } from '@jperdior/api-client-ts/server';

const noteSchema = z.object({
  title: z.string().min(1).max(200),
  body:  z.string().min(1).max(10_000),
});

export type NoteState = { error?: string };

export async function createNoteAction(_prev: NoteState, formData: FormData): Promise<NoteState> {
  const parsed = noteSchema.safeParse({
    title: formData.get('title'),
    body:  formData.get('body'),
  });
  if (!parsed.success) return { error: 'Title and body are required.' };

  try {
    const client = apiClient();
    const { id } = await client.createNote(parsed.data);
    revalidatePath('/notes');
    redirect(`/notes/${id}`);
  } catch (error) {
    if (error instanceof Error) return { error: error.message };
    return { error: 'Could not create note.' };
  }
}

export async function updateNoteAction(id: string, _prev: NoteState, formData: FormData): Promise<NoteState> {
  const parsed = noteSchema.safeParse({
    title: formData.get('title'),
    body:  formData.get('body'),
  });
  if (!parsed.success) return { error: 'Title and body are required.' };

  try {
    await apiClient().updateNote(id, parsed.data);
    revalidatePath(`/notes/${id}`);
    revalidatePath('/notes');
  } catch (error) {
    if (error instanceof Error) return { error: error.message };
    return { error: 'Could not update note.' };
  }
  return {};
}

export async function deleteNoteAction(id: string): Promise<void> {
  await apiClient().deleteNote(id);
  revalidatePath('/notes');
  redirect('/notes');
}
