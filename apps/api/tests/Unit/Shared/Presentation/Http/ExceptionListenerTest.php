<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Presentation\Http;

use App\Shared\Presentation\Http\ExceptionListener;
use App\Shared\Presentation\Http\ExceptionStatusMapProvider;
use DomainException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

final class ExceptionListenerTest extends TestCase
{
    public function testMapsProviderListedExceptionToItsStatusCodeAndMessage(): void
    {
        $listener = $this->listener([
            $this->provider([
                MappedException::class => ['status' => 404, 'code' => 'mapped_code', 'message' => 'Fixed message.'],
            ]),
        ]);

        $event = $this->event(new MappedException('internal detail that must not leak'));
        $listener($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame(
            ['code' => 'mapped_code', 'message' => 'Fixed message.'],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testExactClassLookupDoesNotMatchSubclasses(): void
    {
        $listener = $this->listener([
            $this->provider([
                RuntimeException::class => ['status' => 404, 'code' => 'mapped_code', 'message' => 'Fixed message.'],
            ]),
        ]);

        $event = $this->event(new class('child message') extends RuntimeException {});
        $listener($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(500, $response->getStatusCode());
    }

    public function testUnmappedDomainExceptionStillFallsBackToConflict(): void
    {
        $listener = $this->listener([
            $this->provider([
                MappedException::class => ['status' => 404, 'code' => 'mapped_code', 'message' => 'Fixed message.'],
            ]),
        ]);

        $event = $this->event(new DomainException('A user with email x@y.z already exists.'));
        $listener($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(409, $response->getStatusCode());
        self::assertSame(
            ['code' => 'CONFLICT', 'message' => 'A user with email x@y.z already exists.'],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testDuplicateClassKeyAcrossProvidersFailsFast(): void
    {
        $this->expectException(LogicException::class);

        $this->listener([
            $this->provider([MappedException::class => ['status' => 404, 'code' => 'a', 'message' => 'A.']]),
            $this->provider([MappedException::class => ['status' => 422, 'code' => 'b', 'message' => 'B.']]),
        ]);
    }

    /** @param list<ExceptionStatusMapProvider> $providers */
    private function listener(array $providers): ExceptionListener
    {
        return new ExceptionListener(false, new NullLogger(), $providers);
    }

    /** @param array<class-string<Throwable>, array{status: int, code: string, message: string}> $map */
    private function provider(array $map): ExceptionStatusMapProvider
    {
        return new readonly class($map) implements ExceptionStatusMapProvider {
            /** @param array<class-string<Throwable>, array{status: int, code: string, message: string}> $map */
            public function __construct(private array $map)
            {
            }

            public function map(): array
            {
                return $this->map;
            }
        };
    }

    private function event(Throwable $throwable): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );
    }
}

final class MappedException extends RuntimeException
{
}
