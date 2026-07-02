import { ConfirmActionDialog } from './ConfirmActionDialog';
import type { ActionState } from '@/app/(admin)/users/actions';

interface Props {
  user: { id: string; email: string; roles: string[] };
  open: boolean;
  onClose: () => void;
  action: (id: string, roles: string[]) => Promise<ActionState>;
}

export function EditRolesDialog({ user, open, onClose, action }: Props) {
  const isAdmin = user.roles.includes('ROLE_ADMIN');

  return (
    <ConfirmActionDialog
      open={open}
      onClose={onClose}
      title={isAdmin ? 'Demote from Admin' : 'Promote to Admin'}
      description={isAdmin
        ? `Remove ROLE_ADMIN from ${user.email}?`
        : `Grant ROLE_ADMIN to ${user.email}?`}
      confirmLabel="Confirm"
      action={() =>
        action(
          user.id,
          isAdmin
            ? user.roles.filter((r) => r !== 'ROLE_ADMIN')
            : [...user.roles, 'ROLE_ADMIN'],
        )}
    />
  );
}
