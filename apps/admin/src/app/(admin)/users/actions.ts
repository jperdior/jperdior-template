'use server';

import { revalidatePath } from 'next/cache';
import { apiClient } from '@jperdior/api-client-ts/server';

export type ActionState = { error?: string; success?: boolean };

function errMsg(e: unknown): string {
  return (e as { message?: string } | null)?.message ?? 'Something went wrong.';
}

export async function createUser(_prev: ActionState, formData: FormData): Promise<ActionState> {
  const email    = (formData.get('email')    as string | null) ?? '';
  const password = (formData.get('password') as string | null) ?? '';
  try {
    await apiClient().adminCreateUser({ email, password });
  } catch (e) {
    return { error: errMsg(e) };
  }
  revalidatePath('/users');
  return { success: true };
}

export async function updateUserRoles(id: string, roles: string[]): Promise<ActionState> {
  try {
    await apiClient().adminUpdateUserRoles(id, roles);
  } catch (e) {
    return { error: errMsg(e) };
  }
  revalidatePath('/users');
  revalidatePath(`/users/${id}`);
  return { success: true };
}

export async function forcePasswordReset(id: string): Promise<ActionState> {
  try {
    await apiClient().adminForcePasswordReset(id);
  } catch (e) {
    return { error: errMsg(e) };
  }
  revalidatePath('/users');
  revalidatePath(`/users/${id}`);
  return { success: true };
}

export async function deleteUser(id: string): Promise<ActionState> {
  try {
    await apiClient().adminDeleteUser(id);
  } catch (e) {
    return { error: errMsg(e) };
  }
  revalidatePath('/users');
  revalidatePath(`/users/${id}`);
  return { success: true };
}

export async function restoreUser(id: string): Promise<ActionState> {
  try {
    await apiClient().adminRestoreUser(id);
  } catch (e) {
    return { error: errMsg(e) };
  }
  revalidatePath('/users');
  revalidatePath(`/users/${id}`);
  return { success: true };
}
