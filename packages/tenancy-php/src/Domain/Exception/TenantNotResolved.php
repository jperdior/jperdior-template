<?php

declare(strict_types=1);

namespace Jperdior\Tenancy\Domain\Exception;

final class TenantNotResolved extends \RuntimeException
{
    public static function forCurrentRequest(): self
    {
        return new self('No tenant has been resolved for the current request.');
    }
}
