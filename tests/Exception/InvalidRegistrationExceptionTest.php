<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Exception;

use RuntimeException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Container\Exception\ContainerException;
use Dirthara\Container\Exception\InvalidRegistrationException;

final class InvalidRegistrationExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new InvalidRegistrationException();

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
        $exception = new InvalidRegistrationException('message', 3, $previous, ['id' => 'service']);

        self::assertSame('message', $exception->getMessage());
        self::assertSame(3, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['id' => 'service'], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = new InvalidRegistrationException(context: ['id' => 'service', 'kept' => true]);

        self::assertSame($exception, $exception->addContext(['id' => 'replaced', 'route' => 'users.store']));
        self::assertSame(['id' => 'replaced', 'kept' => true, 'route' => 'users.store'], $exception->context);
    }

    #[Test]
    public function it_describes_a_class_that_cannot_be_lazy(): void
    {
        $exception = InvalidRegistrationException::notALazyClass("Service\n");

        self::assertSame(
            'Unable to make "Service\\n" lazy: it is not an instantiable class.',
            $exception->getMessage(),
        );
        self::assertSame(['class' => "Service\n"], $exception->context);
    }

    #[Test]
    public function it_describes_an_incompatible_instance_and_concrete(): void
    {
        $instance = InvalidRegistrationException::incompatibleInstance("Service\n", 'int');
        $concrete = InvalidRegistrationException::incompatibleConcrete('Service', 'Plain');

        self::assertSame(
            'Unable to register int as entry "Service\\n": an entry named after a class or interface has to be an '
            . 'instance of it.',
            $instance->getMessage(),
        );
        self::assertSame(['id' => "Service\n", 'type' => 'int'], $instance->context);
        self::assertSame(
            'Unable to bind entry "Service" to "Plain": it does not extend or implement "Service".',
            $concrete->getMessage(),
        );
        self::assertSame(['id' => 'Service', 'concrete' => 'Plain'], $concrete->context);
    }
}
