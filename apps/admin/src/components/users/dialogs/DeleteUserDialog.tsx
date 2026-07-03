import { ConfirmActionDialog } from './ConfirmActionDialog';
import type { ActionState } from '@/app/(admin)/users/actions';

interface Props {
  user: { id: string; email: string };
  open: boolean;
  onClose: () => void;
  action: (id: string) => Promise<ActionState>;
}

export function DeleteUserDialog({ user, open, onClose, action }: Props) {
  return (
    <ConfirmActionDialog
      open={open}
      onClose={onClose}
      title="Delete User"
      description={`Soft-delete ${user.email}? The user will be hidden but can be restored.`}
      confirmLabel="Delete"
      confirmVariant="destructive"
      action={() => action(user.id)}
    />
  );
}
