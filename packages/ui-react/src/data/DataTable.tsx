import { cn } from '../utils/cn';

export interface Column<T> {
  header: string;
  accessor: keyof T | ((row: T) => React.ReactNode);
  className?: string;
}

interface Props<T> {
  data: T[];
  columns: Column<T>[];
  rowKey: (row: T) => string;
  onRowClick?: (row: T) => void;
}

export function DataTable<T>({ data, columns, rowKey, onRowClick }: Props<T>) {
  return (
    <div className="rounded-lg border">
      <table className="w-full text-sm">
        <thead className="bg-muted/40 text-left text-muted-foreground">
          <tr>
            {columns.map((col) => (
              <th key={col.header} className={cn('px-4 py-2 font-medium', col.className)}>
                {col.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {data.map((row) => (
            <tr
              key={rowKey(row)}
              className={cn(
                'border-t transition-colors',
                onRowClick && 'cursor-pointer hover:bg-accent',
              )}
              onClick={onRowClick ? () => onRowClick(row) : undefined}
            >
              {columns.map((col) => {
                const value = typeof col.accessor === 'function' ? col.accessor(row) : (row[col.accessor] as React.ReactNode);
                return (
                  <td key={col.header} className={cn('px-4 py-3', col.className)}>
                    {value}
                  </td>
                );
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
