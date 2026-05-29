import { Card, CardContent, CardHeader, CardTitle, PageBody } from '@jperdior/ui-react';
import { NewNoteForm } from './NewNoteForm';

export default function NewNotePage() {
  return (
    <PageBody>
      <Card>
        <CardHeader>
          <CardTitle>New note</CardTitle>
        </CardHeader>
        <CardContent>
          <NewNoteForm />
        </CardContent>
      </Card>
    </PageBody>
  );
}
