<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http;

use Throwable;

/**
 * Contributes context-specific exception→HTTP mappings to the ExceptionListener.
 *
 * Lookup is by exact exception class (no instanceof walking); exceptions not listed
 * by any provider fall back to the listener's generic mapping. Contexts implement
 * this in their Presentation layer so Shared never depends on a context.
 *
 * map() must be pure and idempotent — it is read once at container boot, and a
 * duplicate class key across providers fails fast with a LogicException.
 */
interface ExceptionStatusMapProvider
{
    /** @return array<class-string<Throwable>, array{status: int, code: string, message: string}> */
    public function map(): array;
}
