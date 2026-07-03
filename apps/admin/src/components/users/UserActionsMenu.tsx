'use client';

import { useState } from 'react';
import Link from 'next/link';
import type { UserSummary } from '@jperdior/api-client-ts';
import { Button } from '@jperdior/ui-react';
import { deleteUser, forcePasswordReset, updateUserRoles } from '@/app/(admin)/users/actions';
import { DeleteUserDialog } from './dialogs/DeleteUserDialog';
import { EditRolesDialog } from './dialogs/EditRolesDialog';
import { ForceResetDialog } from './dialogs/ForceResetDialog';

interface Props {
  user: UserSummary;
}

type ActiveDialog = 'editRoles' | 'forceReset' | 'delete' | null;

export function UserActionsMenu({ user }: Props) {
  const [menuOpen, setMenuOpen] = useState(false);
  const [activeDialog, setActiveDialog] = useState<ActiveDialog>(null);

  const isAdmin = user.roles.includes('ROLE_ADMIN');

  function close() {
    setActiveDialog(null);
    setMenuOpen(false);
  }

  function openDialog(dialog: Exclude<ActiveDialog, null>) {
    setMenuOpen(false);
    setActiveDialog(dialog);
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
              onClick={() => openDialog('editRoles')}
            >
              {isAdmin ? 'Demote from Admin' : 'Promote to Admin'}
            </button>
            <button
              className="block w-full px-4 py-2 text-left text-sm hover:bg-accent"
              onClick={() => openDialog('forceReset')}
            >
              Force Password Reset
            </button>
            <button
              className="block w-full px-4 py-2 text-left text-sm text-destructive hover:bg-accent"
              onClick={() => openDialog('delete')}
            >
              Delete
            </button>
          </div>
        </>
      )}

      <EditRolesDialog user={user} open={activeDialog === 'editRoles'} onClose={close} action={updateUserRoles} />
      <ForceResetDialog user={user} open={activeDialog === 'forceReset'} onClose={close} action={forcePasswordReset} />
      <DeleteUserDialog user={user} open={activeDialog === 'delete'} onClose={close} action={deleteUser} />
    </div>
  );
}
