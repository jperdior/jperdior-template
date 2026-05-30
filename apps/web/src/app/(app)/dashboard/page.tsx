import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@jperdior/ui-react';
import { apiClient } from '@jperdior/api-client-ts/server';

export default async function DashboardPage() {
  const client = apiClient();
  const user = await client.me();

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
