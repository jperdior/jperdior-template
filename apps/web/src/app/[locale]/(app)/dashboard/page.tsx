import { getTranslations } from 'next-intl/server';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@jperdior/ui-react';
import { apiClient } from '@jperdior/api-client-ts/server';

export default async function DashboardPage() {
  // The middleware only checks cookie presence; a dead session (expired access
  // token + revoked refresh token) reaches this page. `apiClient()` handles it
  // globally — on a dead session it clears the cookies and redirects to
  // /login?reason=expired — so there is nothing to catch here.
  const user = await apiClient().me();
  const t = await getTranslations('dashboard');

  return (
    <main className="mx-auto max-w-5xl px-6 py-10">
      <Card>
        <CardHeader>
          <CardTitle>{t('welcome')}</CardTitle>
          <CardDescription>{user.email}</CardDescription>
        </CardHeader>
        <CardContent className="text-sm text-muted-foreground">
          <p>
            {t('roles')} {user.roles.join(', ')}
          </p>
        </CardContent>
      </Card>
    </main>
  );
}
