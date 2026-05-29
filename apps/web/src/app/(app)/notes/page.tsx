import Link from 'next/link';
import { Button, DataTable, EmptyState, PageBody, PageHeader } from '@jperdior/ui-react';
import { apiClient } from '@jperdior/api-client-ts/server';

export default async function NotesPage() {
  const client = apiClient();
  const { notes } = await client.listNotes({ limit: 100 });

  return (
    <PageBody>
      <PageHeader
        title="My notes"
        description={`${notes.length} note${notes.length === 1 ? '' : 's'}.`}
        actions={
          <Button asChild>
            <Link href="/notes/new">New note</Link>
          </Button>
        }
      />

      {notes.length === 0 ? (
        <EmptyState
          title="No notes yet"
          description="Create your first note to get started."
          action={
            <Button asChild>
              <Link href="/notes/new">New note</Link>
            </Button>
          }
        />
      ) : (
        <DataTable
          data={notes}
          rowKey={(n) => n.id}
          columns={[
            {
              header: 'Title',
              accessor: (n) => (
                <Link href={`/notes/${n.id}`} className="font-medium hover:underline">
                  {n.title}
                </Link>
              ),
            },
            {
              header: 'Created',
              accessor: (n) => new Date(n.createdAt).toLocaleString(),
              className: 'text-muted-foreground',
            },
            {
              header: 'Updated',
              accessor: (n) => new Date(n.updatedAt).toLocaleString(),
              className: 'text-muted-foreground',
            },
          ]}
        />
      )}
    </PageBody>
  );
}
