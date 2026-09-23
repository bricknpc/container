<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Exception;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Container\Exception\ContainerException;
use Dirthara\Container\Exception\ResolutionException;

final class ResolutionExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new ResolutionException();

        self::assertInstanceOf(ContainerException::class, $exception);
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
        self::assertSame([], $exception->context);
    }

    #[Test]
    public function it_keeps_a_previous_exception_and_its_context(): void
    {
        $previous = new RuntimeException('cause');
        $exception = new ResolutionException('message', 3, $previous, ['id' => 'service']);

        self::assertSame('message', $exception->getMessage());
        self::assertSame(3, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['id' => 'service'], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = new ResolutionException(context: ['id' => 'service', 'kept' => true]);

        self::assertSame($exception, $exception->addContext(['id' => 'replaced', 'route' => 'users.store']));
        self::assertSame(['id' => 'replaced', 'kept' => true, 'route' => 'users.store'], $exception->context);
    }
}
