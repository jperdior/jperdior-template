import Link from 'next/link';
import { Button, Card, CardContent, CardDescription, CardHeader, CardTitle } from '@jperdior/ui-react';

export default function HomePage() {
  return (
    <main className="mx-auto flex min-h-screen max-w-2xl flex-col justify-center px-6 py-16">
      <Card>
        <CardHeader>
          <CardTitle>jperdior-template</CardTitle>
          <CardDescription>Spec-driven Symfony + Next.js + JWT auth. Hello-world: Notes.</CardDescription>
        </CardHeader>
        <CardContent className="flex gap-3">
          <Button asChild>
            <Link href="/login">Sign in</Link>
          </Button>
          <Button asChild variant="outline">
            <Link href="/signup">Create account</Link>
          </Button>
        </CardContent>
      </Card>
    </main>
  );
}
