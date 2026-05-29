import { Spinner } from '../primitives/Spinner';

export function LoadingState({ label = 'Loading…' }: { label?: string }) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-16 text-muted-foreground" role="status" aria-live="polite">
      <Spinner className="size-6" />
      <p className="text-sm">{label}</p>
    </div>
  );
}
