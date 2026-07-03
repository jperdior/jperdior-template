import { redirect } from 'next/navigation';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@jperdior/ui-react';
import { apiClient } from '@jperdior/api-client-ts/server';
import { UnauthorizedError, type CurrentUser } from '@jperdior/api-client-ts';

export default async function DashboardPage() {
  // The middleware only checks cookie presence; a dead session (expired access
  // token + revoked refresh token) reaches this page and must go back to
  // login instead of crashing the render.
  let user: CurrentUser;
  try {
    user = await apiClient().me();
  } catch (e) {
    if (e instanceof UnauthorizedError) redirect('/login?reason=expired&next=/dashboard');
    throw e;
  }

  return (
    <main className="mx-auto max-w-5xl px-6 py-10">
      <Card>
        <CardHeader>
          <CardTitle>Welcome</CardTitle>
          <CardDescription>{user.email}</CardDescription>
        </CardHeader>
        <CardContent className="text-sm text-muted-foreground">
          <p>Roles: {user.roles.join(', ')}</p>
        </CardContent>
      </Card>
    </main>
  );
}
