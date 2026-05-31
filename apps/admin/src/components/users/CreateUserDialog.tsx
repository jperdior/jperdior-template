'use client';

import { useRef, useState, useTransition } from 'react';
import {
  Button,
  Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
  Input, Label, Spinner,
} from '@jperdior/ui-react';
import { createUser } from '@/app/(admin)/users/actions';

export function CreateUserDialog() {
  const [open, setOpen] = useState(false);
  const [error, setError] = useState<string | undefined>();
  const [isPending, startTransition] = useTransition();
  const formRef = useRef<HTMLFormElement>(null);

  function handleOpenChange(next: boolean) {
    setOpen(next);
    if (!next) {
      formRef.current?.reset();
      setError(undefined);
    }
  }

  function handleSubmit(formData: FormData) {
    startTransition(async () => {
      const result = await createUser({}, formData);
      if (result.success) {
        handleOpenChange(false);
      } else {
        setError(result.error);
      }
    });
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger asChild>
        <Button size="sm">New User</Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Create User</DialogTitle>
        </DialogHeader>
        <form ref={formRef} action={handleSubmit} className="grid gap-4">
          <div className="space-y-2">
            <Label htmlFor="create-email">Email</Label>
            <Input id="create-email" name="email" type="email" required autoFocus />
          </div>
          <div className="space-y-2">
            <Label htmlFor="create-password">Password</Label>
            <Input id="create-password" name="password" type="password" minLength={8} required />
          </div>
          {error && <p className="text-sm text-destructive">{error}</p>}
          <DialogFooter>
            <Button type="button" variant="ghost" onClick={() => handleOpenChange(false)} disabled={isPending}>
              Cancel
            </Button>
            <Button type="submit" disabled={isPending}>
              {isPending && <Spinner />}
              Create
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
