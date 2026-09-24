<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Exception;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Container\Exception\ContainerException;
use Dirthara\Container\Exception\ContainerLockedException;

final class ContainerLockedExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new ContainerLockedException();

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
        $exception = new ContainerLockedException('message', 3, $previous, ['id' => 'service']);

        self::assertSame('message', $exception->getMessage());
        self::assertSame(3, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['id' => 'service'], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = new ContainerLockedException(context: ['id' => 'service', 'kept' => true]);

        self::assertSame($exception, $exception->addContext(['id' => 'replaced', 'route' => 'users.store']));
        self::assertSame(['id' => 'replaced', 'kept' => true, 'route' => 'users.store'], $exception->context);
    }

    #[Test]
    public function it_describes_a_registration_on_a_locked_container(): void
    {
        $exception = ContainerLockedException::cannotRegister("service\n");

        self::assertSame(
            'Unable to register entry "service\n": the container is locked and accepts no new registrations.',
            $exception->getMessage(),
        );
        self::assertSame(['id' => "service\n"], $exception->context);
    }

    #[Test]
    public function it_describes_a_contextual_binding_on_a_locked_container(): void
    {
        $exception = ContainerLockedException::cannotAddContextualBinding(['First', 'Second']);

        self::assertSame(
            'Unable to add a contextual binding for "First", "Second": the container is locked and accepts no new '
            . 'registrations.',
            $exception->getMessage(),
        );
        self::assertSame(['classes' => ['First', 'Second']], $exception->context);
    }

    #[Test]
    public function it_describes_a_configuration_call_on_a_locked_container(): void
    {
        $exception = ContainerLockedException::cannotConfigure('tag');

        self::assertSame(
            'Unable to call tag(): the container is locked and accepts no new registrations.',
            $exception->getMessage(),
        );
        self::assertSame(['method' => 'tag'], $exception->context);
    }
}
