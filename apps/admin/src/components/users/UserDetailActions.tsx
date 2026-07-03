'use client';

import { useState } from 'react';
import type { UserDetail } from '@jperdior/api-client-ts';
import { Button, Card, CardContent, CardHeader, CardTitle } from '@jperdior/ui-react';
import { deleteUser, forcePasswordReset, restoreUser, updateUserRoles } from '@/app/(admin)/users/actions';
import { DeleteUserDialog } from './dialogs/DeleteUserDialog';
import { EditRolesDialog } from './dialogs/EditRolesDialog';
import { ForceResetDialog } from './dialogs/ForceResetDialog';
import { RestoreUserDialog } from './dialogs/RestoreUserDialog';

interface Props {
  user: UserDetail;
}

type ActiveDialog = 'editRoles' | 'forceReset' | 'delete' | 'restore' | null;

export function UserDetailActions({ user }: Props) {
  const [activeDialog, setActiveDialog] = useState<ActiveDialog>(null);

  const isAdmin = user.roles.includes('ROLE_ADMIN');
  const isDeleted = user.deletedAt !== null;

  function close() {
    setActiveDialog(null);
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

      <EditRolesDialog user={user} open={activeDialog === 'editRoles'} onClose={close} action={updateUserRoles} />
      <ForceResetDialog user={user} open={activeDialog === 'forceReset'} onClose={close} action={forcePasswordReset} />
      <DeleteUserDialog user={user} open={activeDialog === 'delete'} onClose={close} action={deleteUser} />
      <RestoreUserDialog user={user} open={activeDialog === 'restore'} onClose={close} action={restoreUser} />
    </>
  );
}
