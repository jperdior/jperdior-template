<?php

declare(strict_types=1);

namespace Jperdior\SharedKernel\Domain\Bus\Command;

interface CommandBus
{
    public function dispatch(Command $command): void;
}
