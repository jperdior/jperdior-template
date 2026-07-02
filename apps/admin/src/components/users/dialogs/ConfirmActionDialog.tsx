'use client';

import { useState, useTransition, type ComponentProps, type KeyboardEvent, type ReactNode } from 'react';
import {
  Button,
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
  Spinner,
} from '@jperdior/ui-react';
import type { ActionState } from '@/app/(admin)/users/actions';

interface Props {
  open: boolean;
  onClose: () => void;
  title: string;
  description: ReactNode;
  confirmLabel: string;
  confirmVariant?: ComponentProps<typeof Button>['variant'];
  action: () => Promise<ActionState>;
}

/**
 * Owns the whole confirm-dialog lifecycle: pending state, error display,
 * close-on-success. The named user dialogs configure it; callers only decide
 * which dialog is open.
 */
export function ConfirmActionDialog({
  open,
  onClose,
  title,
  description,
  confirmLabel,
  confirmVariant = 'default',
  action,
}: Props) {
  const [isPending, startTransition] = useTransition();
  const [error, setError] = useState<string | undefined>();

  function close() {
    setError(undefined);
    onClose();
  }

  function handleConfirm() {
    startTransition(async () => {
      const result = await action();
      if (result.error) {
        setError(result.error);
      } else {
        close();
      }
    });
  }

  function handleKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    if (event.key === 'Enter' && (event.metaKey || event.ctrlKey) && !isPending) {
      event.preventDefault();
      handleConfirm();
    }
  }

  return (
    <Dialog open={open} onOpenChange={(o) => !o && close()}>
      <DialogContent onKeyDown={handleKeyDown}>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>
        {error && <p className="text-sm text-destructive">{error}</p>}
        <DialogFooter>
          <Button variant="ghost" onClick={close} disabled={isPending}>Cancel</Button>
          <Button variant={confirmVariant} disabled={isPending} onClick={handleConfirm}>
            {isPending && <Spinner />}
            {confirmLabel}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
