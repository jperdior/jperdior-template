'use client';

import { useState, useTransition } from 'react';
import type { UserDetail } from '@jperdior/api-client-ts';
import {
  Button,
  Card, CardContent, CardHeader, CardTitle,
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
  Spinner,
} from '@jperdior/ui-react';
import {
  deleteUser, forcePasswordReset, restoreUser, updateUserRoles,
  type ActionState,
} from '@/app/(admin)/users/actions';

interface Props {
  user: UserDetail;
}

type ActiveDialog = 'editRoles' | 'forceReset' | 'delete' | 'restore' | null;

export function UserDetailActions({ user }: Props) {
  const [activeDialog, setActiveDialog] = useState<ActiveDialog>(null);
  const [isPending, startTransition] = useTransition();
  const [error, setError] = useState<string | undefined>();

  const isAdmin = user.roles.includes('ROLE_ADMIN');
  const isDeleted = user.deletedAt !== null;

  function close() {
    setActiveDialog(null);
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
    <>
      <Card>
        <CardHeader>
          <CardTitle>Actions</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-3">
          {!isDeleted && (
            <>
              <Button variant="outline" size="sm" onClick={() => setActiveDialog('editRoles')}>
                {isAdmin ? 'Demote from Admin' : 'Promote to Admin'}
              </Button>
              {!user.mustResetPassword && (
                <Button variant="outline" size="sm" onClick={() => setActiveDialog('forceReset')}>
                  Force Password Reset
                </Button>
              )}
              <Button variant="destructive" size="sm" onClick={() => setActiveDialog('delete')}>
                Delete User
              </Button>
            </>
          )}
          {isDeleted && (
            <Button variant="outline" size="sm" onClick={() => setActiveDialog('restore')}>
              Restore User
            </Button>
          )}
        </CardContent>
      </Card>

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

      {/* Restore Dialog */}
      <Dialog open={activeDialog === 'restore'} onOpenChange={(o) => !o && close()}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Restore User</DialogTitle>
            <DialogDescription>
              Restore {user.email}? Their account will become active again.
            </DialogDescription>
          </DialogHeader>
          {error && <p className="text-sm text-destructive">{error}</p>}
          <DialogFooter>
            <Button variant="ghost" onClick={close} disabled={isPending}>Cancel</Button>
            <Button disabled={isPending} onClick={() => handleAction(() => restoreUser(user.id))}>
              {isPending && <Spinner />}
              Restore
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
