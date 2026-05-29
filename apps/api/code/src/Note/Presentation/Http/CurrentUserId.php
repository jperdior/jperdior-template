<?php

declare(strict_types=1);

namespace App\Note\Presentation\Http;

use App\User\Application\Query\GetCurrentUser\CurrentUserResponse;
use App\User\Application\Query\GetCurrentUser\GetCurrentUserQuery;
use Jperdior\SharedKernel\Domain\Bus\Query\QueryBus;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Resolves the bare UUID string of the currently-authenticated user. The Note context never
 * touches App\User domain or application classes — it only asks the query bus for the user's
 * id by their identifier (email).
 *
 * This file lives in Note\Presentation\ because it is a presentation-layer helper, not a domain
 * service. It is allowed to depend on `App\User\Application\Query\GetCurrentUser\…` because that
 * is a *public application service* (a query response DTO), which is the sanctioned cross-context
 * channel.
 */
final readonly class CurrentUserId
{
    public function __construct(
        private Security $security,
        private QueryBus $queryBus,
    ) {
    }

    public function value(): string
    {
        $email = (string) $this->security->getUser()?->getUserIdentifier();

        /** @var CurrentUserResponse $response */
        $response = $this->queryBus->ask(new GetCurrentUserQuery($email));

        return $response->id;
    }
}
