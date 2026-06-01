import Link from 'next/link';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, PageBody, PageHeader } from '@jperdior/ui-react';

const sections = [
  {
    title: 'Users',
    description: 'Manage user accounts, roles, and permissions.',
    href: '/users',
  },
] as const;

export default function DashboardPage() {
  return (
    <PageBody>
      <PageHeader title="Dashboard" description="Back-office overview." />
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {sections.map((section) => (
          <Link key={section.href} href={section.href} className="group block">
            <Card className="h-full transition-colors group-hover:border-primary">
              <CardHeader>
                <CardTitle className="text-base">{section.title}</CardTitle>
                <CardDescription>{section.description}</CardDescription>
              </CardHeader>
              <CardContent>
                <span className="text-xs text-primary group-hover:underline">Go to {section.title} →</span>
              </CardContent>
            </Card>
          </Link>
        ))}
      </div>
    </PageBody>
  );
}
