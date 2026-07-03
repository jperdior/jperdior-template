import { ConfirmActionDialog } from './ConfirmActionDialog';
import type { ActionState } from '@/app/(admin)/users/actions';

interface Props {
  user: { id: string; email: string };
  open: boolean;
  onClose: () => void;
  action: (id: string) => Promise<ActionState>;
}

export function RestoreUserDialog({ user, open, onClose, action }: Props) {
  return (
    <ConfirmActionDialog
      open={open}
      onClose={onClose}
      title="Restore User"
      description={`Restore ${user.email}? Their account will become active again.`}
      confirmLabel="Restore"
      action={() => action(user.id)}
    />
  );
}
