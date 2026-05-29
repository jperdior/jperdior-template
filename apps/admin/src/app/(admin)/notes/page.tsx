import { DataTable, EmptyState, PageBody, PageHeader } from '@jperdior/ui-react';
import { apiClient } from '@jperdior/api-client-ts/server';

export default async function NotesPage({
  searchParams,
}: {
  searchParams: Promise<{ offset?: string }>;
}) {
  const { offset } = await searchParams;
  const offsetNum  = Math.max(0, Number(offset) || 0);

  const client = apiClient();
  const { total, notes } = await client.listAllNotes({ limit: 100, offset: offsetNum });

  return (
    <PageBody>
      <PageHeader
        title="Notes"
        description={`${total} note${total === 1 ? '' : 's'} across every user.`}
      />

      {notes.length === 0 ? (
        <EmptyState title="No notes yet" description="Notes will appear here once users create them." />
      ) : (
        <DataTable
          data={notes}
          rowKey={(n) => n.id}
          columns={[
            { header: 'Title', accessor: (n) => <span className="font-medium">{n.title}</span> },
            {
              header: 'Owner',
              accessor: (n) => <span className="font-mono text-xs text-muted-foreground">{n.ownerId}</span>,
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
