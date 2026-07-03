<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http;

use DomainException;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Throwable;

#[AsEventListener]
final class ExceptionListener
{
    /** @var array<class-string<Throwable>, array{status: int, code: string, message: string}> */
    private array $statusMap = [];

    /** @param iterable<ExceptionStatusMapProvider> $statusMapProviders */
    public function __construct(
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
        private readonly LoggerInterface $logger,
        #[AutowireIterator('app.exception_status_map')]
        iterable $statusMapProviders = [],
    ) {
        foreach ($statusMapProviders as $provider) {
            foreach ($provider->map() as $class => $entry) {
                if (isset($this->statusMap[$class])) {
                    throw new LogicException(\sprintf('Exception "%s" is mapped by more than one ExceptionStatusMapProvider.', $class));
                }
                $this->statusMap[$class] = $entry;
            }
        }
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $mapped = $this->statusMap[$exception::class] ?? null;

        if (null !== $mapped) {
            [$status, $code, $message] = [$mapped['status'], $mapped['code'], $mapped['message']];
        } else {
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
        }

        if ($status >= 500) {
            $this->logger->error('Unhandled exception: '.$exception->getMessage(), ['exception' => $exception]);
        }

        $body = ['code' => $code, 'message' => $message];

        if ($this->debug && $status >= 500) {
            $body['debug'] = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        $event->setResponse(new JsonResponse($body, $status));
    }
}
