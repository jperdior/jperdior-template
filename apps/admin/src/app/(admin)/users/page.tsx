import Link from 'next/link';
import { DataTable, EmptyState, PageBody, PageHeader } from '@jperdior/ui-react';
import { apiClient } from '@jperdior/api-client-ts/server';
import { CreateUserDialog } from '@/components/users/CreateUserDialog';
import { PaginationControls } from '@/components/users/PaginationControls';
import { UserActionsMenu } from '@/components/users/UserActionsMenu';
import { restoreUser } from './actions';

const LIMIT = 25;

function StatusBadge({ user }: { user: { mustResetPassword: boolean; deletedAt: string | null } }) {
  if (user.deletedAt) {
    return <span className="rounded bg-destructive/20 px-2 py-0.5 text-xs text-destructive">Deleted</span>;
  }
  if (user.mustResetPassword) {
    return <span className="rounded bg-warning/20 px-2 py-0.5 text-xs text-warning">Must reset pw</span>;
  }
  return <span className="rounded bg-success/20 px-2 py-0.5 text-xs text-success">Active</span>;
}

export default async function UsersPage({
  searchParams,
}: {
  searchParams: Promise<{ offset?: string }>;
}) {
  const { offset: offsetParam } = await searchParams;
  const offset = Math.max(0, Number(offsetParam) || 0);

  const client = apiClient();
  const { total, users } = await client.listAllUsers({ limit: LIMIT, offset });

  return (
    <PageBody>
      <PageHeader
        title="Users"
        description={`${total} user${total === 1 ? '' : 's'}.`}
        actions={<CreateUserDialog />}
      />

      {users.length === 0 ? (
        <EmptyState title="No users yet" description="Users will appear here once they sign up." />
      ) : (
        <>
          <DataTable
            data={users}
            rowKey={(u) => u.id}
            columns={[
              {
                header: 'Email',
                accessor: (u) =>
                  u.deletedAt ? (
                    <s className="text-muted-foreground">{u.email}</s>
                  ) : (
                    <Link href={`/users/${u.id}`} className="font-medium hover:underline">
                      {u.email}
                    </Link>
                  ),
              },
              {
                header: 'Roles',
                accessor: (u) => (
                  <div className={`flex flex-wrap gap-1 ${u.deletedAt ? 'opacity-60' : ''}`}>
                    {u.roles.map((r) => (
                      <span key={r} className="rounded bg-muted px-2 py-0.5 text-xs">
                        {r}
                      </span>
                    ))}
                  </div>
                ),
              },
              {
                header: 'Status',
                accessor: (u) => <StatusBadge user={u} />,
              },
              {
                header: 'Joined',
                accessor: (u) => (
                  <span className={u.deletedAt ? 'text-muted-foreground opacity-60' : 'text-muted-foreground'}>
                    {new Date(u.createdAt).toLocaleDateString()}
                  </span>
                ),
              },
              {
                header: '',
                accessor: (u) =>
                  u.deletedAt ? (
                    <form
                      action={async () => {
                        'use server';
                        await restoreUser(u.id);
                      }}
                    >
                      <button type="submit" className="text-xs text-primary hover:underline">
                        Restore
                      </button>
                    </form>
                  ) : (
                    <UserActionsMenu user={u} />
                  ),
              },
            ]}
          />
          <PaginationControls total={total} offset={offset} limit={LIMIT} />
        </>
      )}
    </PageBody>
  );
}
