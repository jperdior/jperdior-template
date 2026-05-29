<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * composer.json lives one level above the Symfony app root (code/).
     * Override so config/, var/, templates/ all resolve inside code/.
     */
    public function getProjectDir(): string
    {
        return parent::getProjectDir().'/code';
    }
}
