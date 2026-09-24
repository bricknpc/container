<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests;

use PHPUnit\Framework\TestCase;
use Dirthara\Container\Lifetime;
use Dirthara\Container\Container;
use Dirthara\Container\EntrySource;
use Psr\Container\ContainerInterface;
use Dirthara\Container\Contract\Scope;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Container\Contract\Invoker;
use Dirthara\Container\Contract\Inspector;
use Dirthara\Container\Contract\TagResolver;
use Dirthara\Container\Tests\Fixtures\Clock;
use Dirthara\Container\Tests\Fixtures\Plain;
use Dirthara\Container\Tests\Fixtures\Service;
use Dirthara\Container\Contract\InstanceFactory;
use Dirthara\Container\Tests\Fixtures\Formatter;
use Dirthara\Container\Tests\Fixtures\Repository;
use Dirthara\Container\Tests\Fixtures\LazyService;
use Dirthara\Container\Tests\Fixtures\SystemClock;
use Dirthara\Container\Contract\ContainerConfigurator;
use Dirthara\Container\Tests\Fixtures\CachingRepository;
use Dirthara\Container\Tests\Fixtures\LoggingRepository;
use Dirthara\Container\Tests\Fixtures\ServiceImplementation;

use function sort;

final class EntryDescriptionTest extends TestCase
{
    #[Test]
    public function it_lists_the_registered_identifiers_in_order(): void
    {
        $container = new Container()
            ->bind('b')
            ->instance('a', 1)
            ->instance('10', 2);
        $container->scopedInstance('c', 3);
        $expected = [
            '10',
            'a',
            'b',
            'c',
            Container::class,
            ContainerConfigurator::class,
            InstanceFactory::class,
            Inspector::class,
            Invoker::class,
            Scope::class,
            TagResolver::class,
            ContainerInterface::class,
        ];
        sort($expected);

        self::assertSame($expected, $container->registered());
    }

    #[Test]
    public function it_describes_instances_and_scoped_instances(): void
    {
        $container = new Container()->instance('config', [])->tag('config', 'settings');
        $container->scopedInstance('request', []);

        $config = $container->describe('config');
        $request = $container->describe('request');

        self::assertSame(EntrySource::Instance, $config?->source);
        self::assertNull($config?->lifetime);
        self::assertNull($config?->concrete);
        self::assertFalse($config?->factory);
        self::assertSame(['settings'], $config?->tags);
        self::assertSame(EntrySource::ScopedInstance, $request?->source);
    }

    #[Test]
    public function it_describes_bindings_and_factories(): void
    {
        $container = new Container()
            ->singleton(Service::class, ServiceImplementation::class)
            ->scoped('factory', static fn(): int => 1)
            ->extend(Service::class, static fn(mixed $service): mixed => $service)
            ->extend(Service::class, static fn(mixed $service): mixed => $service)
            ->tag(Service::class, 'first')
            ->tag(Service::class, '2');

        $service = $container->describe(Service::class);
        $factory = $container->describe('factory');

        self::assertSame(Service::class, $service?->id);
        self::assertSame(EntrySource::Binding, $service?->source);
        self::assertSame(Lifetime::Singleton, $service?->lifetime);
        self::assertSame(ServiceImplementation::class, $service?->concrete);
        self::assertSame(['first', '2'], $service?->tags);
        self::assertSame(2, $service?->extenders);
        self::assertSame(Lifetime::Scoped, $factory?->lifetime);
        self::assertNull($factory?->concrete);
        self::assertTrue($factory?->factory);
    }

    #[Test]
    public function it_describes_entries_that_come_from_attributes(): void
    {
        $container = new Container();

        $clock = $container->describe(Clock::class);
        $formatter = $container->describe(Formatter::class);
        $repository = $container->describe(Repository::class);

        self::assertSame(EntrySource::Attribute, $clock?->source);
        self::assertSame(Lifetime::Transient, $clock?->lifetime);
        self::assertSame(SystemClock::class, $clock?->concrete);
        self::assertSame(Lifetime::Singleton, $formatter?->lifetime);
        self::assertSame([CachingRepository::class, LoggingRepository::class], $repository?->decorators);
    }

    #[Test]
    public function it_describes_autowired_classes_and_whether_they_are_lazy(): void
    {
        $container = new Container();

        $plain = $container->describe(Plain::class);
        $lazy = $container->describe(LazyService::class);

        self::assertSame(EntrySource::Autowired, $plain?->source);
        self::assertSame(Lifetime::Transient, $plain?->lifetime);
        self::assertSame(Plain::class, $plain?->concrete);
        self::assertFalse($plain?->lazy);
        self::assertTrue($lazy?->lazy);
    }

    #[Test]
    public function it_describes_nothing_for_an_entry_it_does_not_have(): void
    {
        $container = new Container();

        self::assertNull($container->describe('missing'));
        self::assertNull($container->describe(Service::class));
    }
}
