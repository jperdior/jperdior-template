import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { EditRolesDialog } from '../EditRolesDialog';
import { ForceResetDialog } from '../ForceResetDialog';
import { DeleteUserDialog } from '../DeleteUserDialog';
import { RestoreUserDialog } from '../RestoreUserDialog';

const user = { id: 'u-1', email: 'jane@example.com', roles: ['ROLE_USER'] };

describe('EditRolesDialog', () => {
  it('promotes a non-admin and closes on success', async () => {
    const action = vi.fn().mockResolvedValue({ success: true });
    const onClose = vi.fn();
    render(<EditRolesDialog user={user} open onClose={onClose} action={action} />);

    expect(screen.getByText('Promote to Admin')).toBeInTheDocument();
    expect(screen.getByText('Grant ROLE_ADMIN to jane@example.com?')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

    await waitFor(() => expect(onClose).toHaveBeenCalled());
    expect(action).toHaveBeenCalledWith('u-1', ['ROLE_USER', 'ROLE_ADMIN']);
  });

  it('demotes an admin with the ROLE_ADMIN role removed', async () => {
    const admin = { ...user, roles: ['ROLE_USER', 'ROLE_ADMIN'] };
    const action = vi.fn().mockResolvedValue({ success: true });
    render(<EditRolesDialog user={admin} open onClose={vi.fn()} action={action} />);

    expect(screen.getByText('Demote from Admin')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

    await waitFor(() => expect(action).toHaveBeenCalledWith('u-1', ['ROLE_USER']));
  });
});

describe('ForceResetDialog', () => {
  it('invokes the action with the user id and closes on success', async () => {
    const action = vi.fn().mockResolvedValue({ success: true });
    const onClose = vi.fn();
    render(<ForceResetDialog user={user} open onClose={onClose} action={action} />);

    expect(
      screen.getByText('jane@example.com will be required to set a new password on next login.'),
    ).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Force Reset' }));

    await waitFor(() => expect(onClose).toHaveBeenCalled());
    expect(action).toHaveBeenCalledWith('u-1');
  });
});

describe('DeleteUserDialog', () => {
  it('renders and invokes the action on confirm', async () => {
    const action = vi.fn().mockResolvedValue({ success: true });
    const onClose = vi.fn();
    render(<DeleteUserDialog user={user} open onClose={onClose} action={action} />);

    expect(screen.getByText('Delete User')).toBeInTheDocument();
    expect(screen.getByText('Soft-delete jane@example.com? The user will be hidden but can be restored.')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Delete' }));

    await waitFor(() => expect(onClose).toHaveBeenCalled());
    expect(action).toHaveBeenCalledWith('u-1');
  });

  it('confirms via Cmd/Ctrl+Enter', async () => {
    const action = vi.fn().mockResolvedValue({ success: true });
    const onClose = vi.fn();
    render(<DeleteUserDialog user={user} open onClose={onClose} action={action} />);

    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Enter', metaKey: true });

    await waitFor(() => expect(onClose).toHaveBeenCalled());
    expect(action).toHaveBeenCalledWith('u-1');
  });

  it('shows the action error and stays open on failure', async () => {
    const action = vi.fn().mockResolvedValue({ error: 'Cannot delete yourself.' });
    const onClose = vi.fn();
    render(<DeleteUserDialog user={user} open onClose={onClose} action={action} />);

    fireEvent.click(screen.getByRole('button', { name: 'Delete' }));

    await waitFor(() => expect(screen.getByText('Cannot delete yourself.')).toBeInTheDocument());
    expect(onClose).not.toHaveBeenCalled();
  });
});

describe('RestoreUserDialog', () => {
  it('invokes the action and closes on success', async () => {
    const action = vi.fn().mockResolvedValue({ success: true });
    const onClose = vi.fn();
    render(<RestoreUserDialog user={user} open onClose={onClose} action={action} />);

    expect(screen.getByText('Restore jane@example.com? Their account will become active again.')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Restore' }));

    await waitFor(() => expect(onClose).toHaveBeenCalled());
    expect(action).toHaveBeenCalledWith('u-1');
  });
});
