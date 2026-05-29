import type { ReactNode } from 'react';
import { cn } from '../utils/cn';

export function PageBody({ children, className }: { children: ReactNode; className?: string }) {
  return <main className={cn('mx-auto max-w-5xl px-6 py-10', className)}>{children}</main>;
}
