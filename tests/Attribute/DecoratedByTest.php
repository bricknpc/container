<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Dirthara\Container\Container;
use Psr\Container\ContainerInterface;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Container\Tests\Fixtures\Plain;
use Dirthara\Container\Tests\Fixtures\Repository;
use Dirthara\Container\Tests\Fixtures\Misdecorated;
use Dirthara\Container\Tests\Fixtures\CachingRepository;
use Dirthara\Container\Tests\Fixtures\LoggingRepository;
use Dirthara\Container\Tests\Fixtures\DatabaseRepository;
use Dirthara\Container\Exception\InvalidAttributeException;
use Dirthara\Container\Tests\Fixtures\DecoratedWithoutParameter;
use Dirthara\Container\Tests\Fixtures\DecoratorWithoutParameter;

final class DecoratedByTest extends TestCase
{
    #[Test]
    public function it_wraps_the_entry_in_its_decorators_in_the_order_they_are_declared(): void
    {
        $repository = new Container()->get(Repository::class);

        self::assertInstanceOf(LoggingRepository::class, $repository);
        self::assertInstanceOf(Plain::class, $repository->plain);
        self::assertInstanceOf(CachingRepository::class, $repository->inner);
        self::assertInstanceOf(DatabaseRepository::class, $repository->inner->inner);
    }

    #[Test]
    public function it_decorates_what_a_binding_builds_but_not_an_instance(): void
    {
        $instance = new DatabaseRepository();
        $bound = new Container()->bind(Repository::class, DatabaseRepository::class)->get(Repository::class);

        self::assertInstanceOf(LoggingRepository::class, $bound);
        self::assertSame($instance, new Container()->instance(Repository::class, $instance)->get(Repository::class));
    }

    #[Test]
    public function it_applies_extenders_after_the_attribute_decorators(): void
    {
        $container = new Container()->extend(
            Repository::class,
            static fn(mixed $repository, ContainerInterface $container): CachingRepository => new CachingRepository(
                $repository instanceof Repository ? $repository : new DatabaseRepository(),
            ),
        );

        $repository = $container->get(Repository::class);

        self::assertInstanceOf(CachingRepository::class, $repository);
        self::assertInstanceOf(LoggingRepository::class, $repository->inner);
    }

    #[Test]
    public function it_refuses_a_decorator_that_does_not_extend_the_type(): void
    {
        try {
            new Container()->get(Misdecorated::class);
            self::fail('Expected an InvalidAttributeException.');
        } catch (InvalidAttributeException $exception) {
            self::assertSame(['class' => Misdecorated::class, 'decorator' => Plain::class], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_a_decorator_without_a_parameter_for_what_it_decorates(): void
    {
        $this->expectExceptionMessageIs(
            'The decorator "'
            . DecoratorWithoutParameter::class
            . '" of "'
            . DecoratedWithoutParameter::class
            . '" has no constructor parameter of type "'
            . DecoratedWithoutParameter::class
            . '" to receive what it decorates.',
        );

        new Container()->get(DecoratedWithoutParameter::class);
    }
}
