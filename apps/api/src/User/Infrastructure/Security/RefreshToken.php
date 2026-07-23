<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

// Not `final`: Doctrine must be able to generate a lazy-ghost proxy for a mapped entity
// (enable_lazy_ghost_objects + cache:warmup pre-generation), and PHP 8.4 native lazy objects
// cannot wrap a final class. Mirrors the non-final UserModel / PasswordRecoveryTokenModel.
#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends BaseRefreshToken
{
}
