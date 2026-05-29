import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@jperdior/ui-react';
import { SignUpForm } from './SignUpForm';

export default function SignUpPage() {
  return (
    <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6">
      <Card>
        <CardHeader>
          <CardTitle>Create account</CardTitle>
          <CardDescription>You&apos;ll be signed in automatically.</CardDescription>
        </CardHeader>
        <CardContent>
          <SignUpForm />
        </CardContent>
      </Card>
    </main>
  );
}
