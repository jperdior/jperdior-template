<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http;

use DomainException;
use InvalidArgumentException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[AsEventListener]
final class ExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        [$status, $code, $message] = match (true) {
            $exception instanceof AuthenticationException => [401, 'UNAUTHORIZED', 'Authentication required.'],
            $exception instanceof AccessDeniedException => [403, 'FORBIDDEN', 'Access denied.'],
            $exception instanceof InvalidArgumentException => [400, 'BAD_REQUEST', $exception->getMessage()],
            $exception instanceof DomainException => [409, 'CONFLICT', $exception->getMessage()],
            $exception instanceof HttpExceptionInterface => [
                $exception->getStatusCode(),
                'HTTP_ERROR',
                $exception->getMessage() ?: 'Request failed.',
            ],
            default => [500, 'INTERNAL_ERROR', 'An unexpected error occurred.'],
        };

        $event->setResponse(new JsonResponse([
            'code' => $code,
            'message' => $message,
        ], $status));
    }
}
