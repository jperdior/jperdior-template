import { DataTable, EmptyState, PageBody, PageHeader } from '@jperdior/ui-react';
import { apiClient } from '@jperdior/api-client-ts/server';

export default async function UsersPage({
  searchParams,
}: {
  searchParams: Promise<{ offset?: string }>;
}) {
  const { offset } = await searchParams;
  const offsetNum  = Math.max(0, Number(offset) || 0);

  const client = apiClient();
  const { total, users } = await client.listAllUsers({ limit: 100, offset: offsetNum });

  return (
    <PageBody>
      <PageHeader
        title="Users"
        description={`${total} user${total === 1 ? '' : 's'}.`}
      />

      {users.length === 0 ? (
        <EmptyState title="No users yet" description="Users will appear here once they sign up." />
      ) : (
        <DataTable
          data={users}
          rowKey={(u) => u.id}
          columns={[
            { header: 'Email', accessor: (u) => <span className="font-medium">{u.email}</span> },
            {
              header: 'Roles',
              accessor: (u) => (
                <div className="flex flex-wrap gap-1">
                  {u.roles.map((r) => (
                    <span key={r} className="rounded bg-muted px-2 py-0.5 text-xs">
                      {r}
                    </span>
                  ))}
                </div>
              ),
            },
            {
              header: 'Joined',
              accessor: (u) => new Date(u.createdAt).toLocaleString(),
              className: 'text-muted-foreground',
            },
            {
              header: 'ID',
              accessor: (u) => <span className="font-mono text-xs text-muted-foreground">{u.id}</span>,
            },
          ]}
        />
      )}
    </PageBody>
  );
}
