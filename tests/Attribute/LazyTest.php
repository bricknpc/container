<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Attribute;

use ReflectionClass;
use PHPUnit\Framework\TestCase;
use Dirthara\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Container\Tests\Fixtures\Plain;
use Dirthara\Container\Tests\Fixtures\Service;
use Dirthara\Container\Tests\Fixtures\Recorder;
use Dirthara\Container\Tests\Fixtures\LazySecond;
use Dirthara\Container\Tests\Fixtures\LazyService;
use Dirthara\Container\Tests\Fixtures\SystemClock;
use Dirthara\Container\Tests\Fixtures\LazyReadonly;
use Dirthara\Container\Exception\ContainerLockedException;
use Dirthara\Container\Tests\Fixtures\LazyWithoutProperties;
use Dirthara\Container\Exception\InvalidRegistrationException;

final class LazyTest extends TestCase
{
    #[Test]
    public function it_builds_a_lazy_class_and_resolves_its_dependencies_when_it_is_first_used(): void
    {
        $recorder = new Recorder();
        $container = new Container()->instance(Recorder::class, $recorder);

        $lazy = $container->get(LazyService::class);

        self::assertTrue(new ReflectionClass(LazyService::class)->isUninitializedLazyObject($lazy));
        self::assertSame([], $recorder->calls->getArrayCopy());
        self::assertSame($recorder, $lazy->recorder);
        self::assertSame([LazyService::class], $recorder->calls->getArrayCopy());
    }

    #[Test]
    public function it_passes_the_parameters_given_to_make_to_the_lazy_constructor(): void
    {
        $lazy = new Container()->make(LazyService::class, ['number' => 5]);

        self::assertSame(5, $lazy->number);
    }

    #[Test]
    public function it_builds_a_lazy_readonly_class(): void
    {
        $lazy = new Container()->get(LazyReadonly::class);

        self::assertInstanceOf(Plain::class, $lazy->plain);
    }

    #[Test]
    public function it_breaks_a_circular_dependency_through_a_lazy_class(): void
    {
        $second = new Container()->get(LazySecond::class);

        self::assertInstanceOf(LazySecond::class, $second->first->second);
    }

    #[Test]
    public function it_makes_a_class_lazy_that_is_registered_as_lazy(): void
    {
        $container = new Container()->lazy(SystemClock::class);

        self::assertTrue(new ReflectionClass(SystemClock::class)->isUninitializedLazyObject($container->get(SystemClock::class)));
    }

    #[Test]
    public function it_builds_a_lazy_class_without_instance_properties_right_away(): void
    {
        $recorder = new Recorder();
        $container = new Container()->instance(Recorder::class, $recorder);

        $lazy = $container->get(LazyWithoutProperties::class);

        self::assertFalse(new ReflectionClass(LazyWithoutProperties::class)->isUninitializedLazyObject($lazy));
        self::assertSame([LazyWithoutProperties::class], $recorder->calls->getArrayCopy());
    }

    #[Test]
    public function it_refuses_to_make_something_lazy_that_is_not_an_instantiable_class(): void
    {
        try {
            new Container()->lazy(Service::class);
            self::fail('Expected an InvalidRegistrationException.');
        } catch (InvalidRegistrationException $exception) {
            self::assertSame(['class' => Service::class], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_to_make_a_class_lazy_once_it_is_locked(): void
    {
        $container = new Container();
        $container->lock();

        $this->expectException(ContainerLockedException::class);

        $container->lazy(Plain::class);
    }
}
