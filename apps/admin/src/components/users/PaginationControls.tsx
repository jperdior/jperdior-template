import Link from 'next/link';

interface Props {
  total: number;
  offset: number;
  limit: number;
}

export function PaginationControls({ total, offset, limit }: Props) {
  if (total <= limit) return null;
  const from = offset + 1;
  const to   = Math.min(offset + limit, total);
  const hasPrev = offset > 0;
  const hasNext = offset + limit < total;

  return (
    <div className="mt-4 flex items-center justify-between text-sm text-muted-foreground">
      <span>
        Showing {from}–{to} of {total}
      </span>
      <div className="flex gap-2">
        {hasPrev ? (
          <Link href={`?offset=${Math.max(0, offset - limit)}`} className="rounded border px-3 py-1 hover:bg-accent">
            Previous
          </Link>
        ) : (
          <span className="rounded border px-3 py-1 opacity-40">Previous</span>
        )}
        {hasNext ? (
          <Link href={`?offset=${offset + limit}`} className="rounded border px-3 py-1 hover:bg-accent">
            Next
          </Link>
        ) : (
          <span className="rounded border px-3 py-1 opacity-40">Next</span>
        )}
      </div>
    </div>
  );
}
