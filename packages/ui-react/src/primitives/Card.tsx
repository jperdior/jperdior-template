import * as React from 'react';
import { cn } from '../utils/cn';

export function Card({ className, ref, ...props }: React.ComponentProps<'div'>) {
  return (
    <div ref={ref} className={cn('rounded-lg border bg-card text-card-foreground shadow-sm', className)} {...props} />
  );
}
Card.displayName = 'Card';

export function CardHeader({ className, ref, ...props }: React.ComponentProps<'div'>) {
  return (
    <div ref={ref} className={cn('flex flex-col space-y-1.5 p-6', className)} {...props} />
  );
}
CardHeader.displayName = 'CardHeader';

export function CardTitle({ className, ref, ...props }: React.ComponentProps<'h3'>) {
  return (
    <h3 ref={ref} className={cn('text-2xl font-semibold leading-none tracking-tight', className)} {...props} />
  );
}
CardTitle.displayName = 'CardTitle';

export function CardDescription({ className, ref, ...props }: React.ComponentProps<'p'>) {
  return (
    <p ref={ref} className={cn('text-sm text-muted-foreground', className)} {...props} />
  );
}
CardDescription.displayName = 'CardDescription';

export function CardContent({ className, ref, ...props }: React.ComponentProps<'div'>) {
  return (
    <div ref={ref} className={cn('p-6 pt-0', className)} {...props} />
  );
}
CardContent.displayName = 'CardContent';

export function CardFooter({ className, ref, ...props }: React.ComponentProps<'div'>) {
  return (
    <div ref={ref} className={cn('flex items-center p-6 pt-0', className)} {...props} />
  );
}
CardFooter.displayName = 'CardFooter';
