import { ConfirmActionDialog } from './ConfirmActionDialog';
import type { ActionState } from '@/app/(admin)/users/actions';

interface Props {
  user: { id: string; email: string };
  open: boolean;
  onClose: () => void;
  action: (id: string) => Promise<ActionState>;
}

export function ForceResetDialog({ user, open, onClose, action }: Props) {
  return (
    <ConfirmActionDialog
      open={open}
      onClose={onClose}
      title="Force Password Reset"
      description={`${user.email} will be required to set a new password on next login.`}
      confirmLabel="Force Reset"
      action={() => action(user.id)}
    />
  );
}
