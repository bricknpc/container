<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Dirthara\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Container\Tests\Fixtures\Formatter;
use Dirthara\Container\Tests\Fixtures\JsonFormatter;
use Dirthara\Container\Exception\ResolutionException;
use Dirthara\Container\Tests\Fixtures\SingletonService;
use Dirthara\Container\Tests\Fixtures\UnboundSingleton;
use Dirthara\Container\Exception\InvalidAttributeException;
use Dirthara\Container\Tests\Fixtures\ConflictingLifetimes;

final class SingletonTest extends TestCase
{
    #[Test]
    public function it_shares_an_autowired_class_that_is_a_singleton(): void
    {
        $container = new Container();

        $singleton = $container->get(SingletonService::class);

        self::assertSame($singleton, $container->get(SingletonService::class));
        self::assertNotSame($singleton, $container->make(SingletonService::class));
        self::assertSame($singleton, $container->get(SingletonService::class));
    }

    #[Test]
    public function it_prefers_a_binding_over_the_attribute(): void
    {
        $container = new Container()->bind(SingletonService::class);

        self::assertNotSame($container->get(SingletonService::class), $container->get(SingletonService::class));
    }

    #[Test]
    public function it_shares_a_bound_type_under_its_own_identifier(): void
    {
        $container = new Container();

        $formatter = $container->get(Formatter::class);

        self::assertInstanceOf(JsonFormatter::class, $formatter);
        self::assertSame($formatter, $container->get(Formatter::class));
        self::assertNotSame($formatter, $container->get(JsonFormatter::class));
    }

    #[Test]
    public function it_refuses_a_type_with_both_lifetimes(): void
    {
        $container = new Container();

        self::assertTrue($container->has(ConflictingLifetimes::class));

        try {
            $container->get(ConflictingLifetimes::class);
            self::fail('Expected an InvalidAttributeException.');
        } catch (InvalidAttributeException $exception) {
            self::assertSame(['class' => ConflictingLifetimes::class], $exception->context);
        }
    }

    #[Test]
    public function it_has_nothing_to_build_for_an_interface_that_is_only_a_singleton(): void
    {
        $this->expectException(ResolutionException::class);

        new Container()->get(UnboundSingleton::class);
    }
}
