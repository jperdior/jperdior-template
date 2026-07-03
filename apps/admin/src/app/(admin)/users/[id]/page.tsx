import { notFound, unstable_rethrow } from 'next/navigation';
import Link from 'next/link';
import { apiClient } from '@jperdior/api-client-ts/server';
import { Button, Card, CardContent, CardHeader, CardTitle, PageBody, PageHeader } from '@jperdior/ui-react';
import { UserDetailActions } from '@/components/users/UserDetailActions';

export default async function UserDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  let user;
  try {
    user = await apiClient().adminGetUser(id);
  } catch (e) {
    // A dead session redirects to login; only genuine lookup failures 404.
    unstable_rethrow(e);
    notFound();
  }

  return (
    <PageBody>
      <PageHeader
        title={user.email}
        description={`ID: ${user.id}`}
        actions={
          <Button asChild variant="ghost" size="sm">
            <Link href="/users">← Back to Users</Link>
          </Button>
        }
      />

      <div className="grid gap-6">
        {/* Info card */}
        <Card>
          <CardHeader>
            <CardTitle>Details</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-3 text-sm">
            <div className="flex justify-between">
              <span className="text-muted-foreground">Email</span>
              <span>{user.email}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Joined</span>
              <span>{new Date(user.createdAt).toLocaleString()}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Roles</span>
              <div className="flex flex-wrap gap-1">
                {user.roles.map((r) => (
                  <span key={r} className="rounded bg-muted px-2 py-0.5 text-xs">{r}</span>
                ))}
              </div>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Must reset password</span>
              <span>{user.mustResetPassword ? (
                <span className="rounded bg-warning/20 px-2 py-0.5 text-xs text-warning">Yes</span>
              ) : (
                <span className="rounded bg-success/20 px-2 py-0.5 text-xs text-success">No</span>
              )}</span>
            </div>
            {user.deletedAt && (
              <div className="flex justify-between">
                <span className="text-muted-foreground">Deleted at</span>
                <span className="text-destructive">{new Date(user.deletedAt).toLocaleString()}</span>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Actions card */}
        <UserDetailActions user={user} />
      </div>
    </PageBody>
  );
}
