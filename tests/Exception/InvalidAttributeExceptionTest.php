<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Exception;

use RuntimeException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Container\Exception\ContainerException;
use Dirthara\Container\Exception\InvalidAttributeException;

final class InvalidAttributeExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new InvalidAttributeException();

        self::assertInstanceOf(ContainerException::class, $exception);
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame('', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
        self::assertSame([], $exception->context);
    }

    #[Test]
    public function it_keeps_a_previous_exception_and_its_context(): void
    {
        $previous = new RuntimeException('cause');
        $exception = new InvalidAttributeException('message', 3, $previous, ['id' => 'service']);

        self::assertSame('message', $exception->getMessage());
        self::assertSame(3, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['id' => 'service'], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = new InvalidAttributeException(context: ['id' => 'service', 'kept' => true]);

        self::assertSame($exception, $exception->addContext(['id' => 'replaced', 'route' => 'users.store']));
        self::assertSame(['id' => 'replaced', 'kept' => true, 'route' => 'users.store'], $exception->context);
    }

    #[Test]
    public function it_describes_a_bound_class_that_is_not_a_subtype(): void
    {
        $exception = InvalidAttributeException::notASubtype("Clock\n", 'Plain');

        self::assertSame(
            'The #[BoundTo] attribute of "Clock\\n" names "Plain", which is not a class or interface that extends or '
            . 'implements it.',
            $exception->getMessage(),
        );
        self::assertSame(['class' => "Clock\n", 'concrete' => 'Plain'], $exception->context);
    }

    #[Test]
    public function it_describes_a_type_with_conflicting_lifetimes(): void
    {
        $exception = InvalidAttributeException::conflictingLifetimes('Service');

        self::assertSame(
            'The type "Service" has both a #[Singleton] and a #[Scoped] attribute, and can only have one lifetime.',
            $exception->getMessage(),
        );
        self::assertSame(['class' => 'Service'], $exception->context);
    }
}
