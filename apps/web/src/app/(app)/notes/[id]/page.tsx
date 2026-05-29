import { notFound } from 'next/navigation';
import { Card, CardContent, CardHeader, CardTitle, PageBody } from '@jperdior/ui-react';
import { apiClient, type Note } from '@jperdior/api-client-ts/server';
import { EditNoteForm } from './EditNoteForm';

interface Props {
  params: Promise<{ id: string }>;
}

export default async function NoteDetailPage({ params }: Props) {
  const { id } = await params;
  let note: Note;
  try {
    note = await apiClient().getNote(id);
  } catch {
    notFound();
  }

  return (
    <PageBody>
      <Card>
        <CardHeader>
          <CardTitle>{note.title}</CardTitle>
        </CardHeader>
        <CardContent>
          <EditNoteForm id={note.id} initialTitle={note.title} initialBody={note.body} />
        </CardContent>
      </Card>
    </PageBody>
  );
}
