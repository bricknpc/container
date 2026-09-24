<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Dirthara\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Container\Tests\Fixtures\Clock;
use Dirthara\Container\Tests\Fixtures\NeedsClock;
use Dirthara\Container\Tests\Fixtures\FrozenClock;
use Dirthara\Container\Tests\Fixtures\SystemClock;
use Dirthara\Container\Tests\Fixtures\MisboundClock;
use Dirthara\Container\Exception\InvalidAttributeException;

final class BoundToTest extends TestCase
{
    #[Test]
    public function it_resolves_a_type_to_the_class_it_is_bound_to(): void
    {
        $container = new Container();

        $clock = $container->get(Clock::class);

        self::assertTrue($container->has(Clock::class));
        self::assertInstanceOf(SystemClock::class, $clock);
        self::assertNotSame($clock, $container->get(Clock::class));
    }

    #[Test]
    public function it_autowires_a_dependency_on_a_bound_type(): void
    {
        $needsClock = new Container()->get(NeedsClock::class);

        self::assertInstanceOf(SystemClock::class, $needsClock->clock);
    }

    #[Test]
    public function it_makes_the_bound_class_with_the_given_parameters(): void
    {
        $clock = new Container()->make(Clock::class, ['zone' => 'Europe/Amsterdam']);

        self::assertInstanceOf(SystemClock::class, $clock);
        self::assertSame('Europe/Amsterdam', $clock->zone);
    }

    #[Test]
    public function it_prefers_a_binding_or_an_instance_over_the_attribute(): void
    {
        $frozen = new FrozenClock();

        self::assertInstanceOf(
            FrozenClock::class,
            new Container()->bind(Clock::class, FrozenClock::class)->get(Clock::class),
        );
        self::assertSame($frozen, new Container()->instance(Clock::class, $frozen)->get(Clock::class));
    }

    #[Test]
    public function it_refuses_a_bound_class_that_does_not_implement_the_type(): void
    {
        $container = new Container();

        self::assertTrue($container->has(MisboundClock::class));

        try {
            $container->get(MisboundClock::class);
            self::fail('Expected an InvalidAttributeException.');
        } catch (InvalidAttributeException $exception) {
            self::assertSame(
                ['class' => MisboundClock::class, 'concrete' => 'Dirthara\Container\Tests\Fixtures\Plain'],
                $exception->context,
            );
        }
    }
}
