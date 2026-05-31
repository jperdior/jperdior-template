'use client';

import { useState, useTransition } from 'react';
import Link from 'next/link';
import type { UserSummary } from '@jperdior/api-client-ts';
import {
  Button,
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
  Spinner,
} from '@jperdior/ui-react';
import { deleteUser, forcePasswordReset, updateUserRoles, type ActionState } from '@/app/(admin)/users/actions';

interface Props {
  user: UserSummary;
}

type ActiveDialog = 'editRoles' | 'forceReset' | 'delete' | null;

export function UserActionsMenu({ user }: Props) {
  const [menuOpen, setMenuOpen] = useState(false);
  const [activeDialog, setActiveDialog] = useState<ActiveDialog>(null);
  const [isPending, startTransition] = useTransition();
  const [error, setError] = useState<string | undefined>();

  const isAdmin = user.roles.includes('ROLE_ADMIN');

  function close() {
    setActiveDialog(null);
    setMenuOpen(false);
    setError(undefined);
  }

  function handleAction(action: () => Promise<ActionState>) {
    startTransition(async () => {
      const result = await action();
      if (result.error) {
        setError(result.error);
      } else {
        close();
      }
    });
  }

  return (
    <div className="relative">
      <Button variant="ghost" size="sm" onClick={() => setMenuOpen((p) => !p)} aria-label="Actions">
        ⋯
      </Button>

      {menuOpen && (
        <>
          <div className="fixed inset-0 z-10" onClick={() => setMenuOpen(false)} />
          <div className="absolute right-0 z-20 mt-1 w-44 rounded-md border bg-card shadow-md">
            <Link
              href={`/users/${user.id}`}
              className="block px-4 py-2 text-sm hover:bg-accent"
              onClick={() => setMenuOpen(false)}
            >
              View Detail
            </Link>
            <button
              className="block w-full px-4 py-2 text-left text-sm hover:bg-accent"
              onClick={() => { setMenuOpen(false); setActiveDialog('editRoles'); }}
            >
              {isAdmin ? 'Demote from Admin' : 'Promote to Admin'}
            </button>
            <button
              className="block w-full px-4 py-2 text-left text-sm hover:bg-accent"
              onClick={() => { setMenuOpen(false); setActiveDialog('forceReset'); }}
            >
              Force Password Reset
            </button>
            <button
              className="block w-full px-4 py-2 text-left text-sm text-destructive hover:bg-accent"
              onClick={() => { setMenuOpen(false); setActiveDialog('delete'); }}
            >
              Delete
            </button>
          </div>
        </>
      )}

      {/* Edit Roles Dialog */}
      <Dialog open={activeDialog === 'editRoles'} onOpenChange={(o) => !o && close()}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{isAdmin ? 'Demote from Admin' : 'Promote to Admin'}</DialogTitle>
            <DialogDescription>
              {isAdmin
                ? `Remove ROLE_ADMIN from ${user.email}?`
                : `Grant ROLE_ADMIN to ${user.email}?`}
            </DialogDescription>
          </DialogHeader>
          {error && <p className="text-sm text-destructive">{error}</p>}
          <DialogFooter>
            <Button variant="ghost" onClick={close} disabled={isPending}>Cancel</Button>
            <Button
              disabled={isPending}
              onClick={() =>
                handleAction(() =>
                  updateUserRoles(
                    user.id,
                    isAdmin
                      ? user.roles.filter((r) => r !== 'ROLE_ADMIN')
                      : [...user.roles, 'ROLE_ADMIN'],
                  ),
                )
              }
            >
              {isPending && <Spinner />}
              Confirm
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Force Reset Dialog */}
      <Dialog open={activeDialog === 'forceReset'} onOpenChange={(o) => !o && close()}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Force Password Reset</DialogTitle>
            <DialogDescription>
              {user.email} will be required to set a new password on next login.
            </DialogDescription>
          </DialogHeader>
          {error && <p className="text-sm text-destructive">{error}</p>}
          <DialogFooter>
            <Button variant="ghost" onClick={close} disabled={isPending}>Cancel</Button>
            <Button disabled={isPending} onClick={() => handleAction(() => forcePasswordReset(user.id))}>
              {isPending && <Spinner />}
              Force Reset
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Dialog */}
      <Dialog open={activeDialog === 'delete'} onOpenChange={(o) => !o && close()}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delete User</DialogTitle>
            <DialogDescription>
              Soft-delete {user.email}? The user will be hidden but can be restored.
            </DialogDescription>
          </DialogHeader>
          {error && <p className="text-sm text-destructive">{error}</p>}
          <DialogFooter>
            <Button variant="ghost" onClick={close} disabled={isPending}>Cancel</Button>
            <Button variant="destructive" disabled={isPending} onClick={() => handleAction(() => deleteUser(user.id))}>
              {isPending && <Spinner />}
              Delete
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
