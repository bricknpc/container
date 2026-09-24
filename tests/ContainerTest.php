<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests;

use Error;
use Fiber;
use Closure;
use stdClass;
use TypeError;
use ArrayObject;
use LogicException;
use ReflectionClass;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use Dirthara\Container\Container;
use Psr\Container\ContainerInterface;
use Dirthara\Container\Contract\Scope;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Container\Contract\Invoker;
use Dirthara\Container\Tests\Fixtures\Suit;
use Dirthara\Container\Contract\TagResolver;
use Dirthara\Container\Tests\Fixtures\First;
use Dirthara\Container\Tests\Fixtures\Plain;
use Dirthara\Container\Tests\Fixtures\Mailer;
use Dirthara\Container\Tests\Fixtures\Nested;
use Dirthara\Container\Tests\Fixtures\Second;
use Psr\Container\NotFoundExceptionInterface;
use Dirthara\Container\Tests\Fixtures\Handler;
use Dirthara\Container\Tests\Fixtures\Scalars;
use Dirthara\Container\Tests\Fixtures\Service;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Container\Contract\InstanceFactory;
use Dirthara\Container\Tests\Fixtures\SelfTyped;
use Dirthara\Container\Tests\Fixtures\DefinedLate;
use Dirthara\Container\Tests\Fixtures\ParentTyped;
use Dirthara\Container\Tests\Fixtures\NeedsService;
use Dirthara\Container\Exception\ContainerException;
use Dirthara\Container\Exception\ResolutionException;
use Dirthara\Container\Tests\Fixtures\RequiresNumber;
use Dirthara\Container\Contract\ContainerConfigurator;
use Dirthara\Container\Tests\Fixtures\AbstractService;
use Dirthara\Container\Tests\Fixtures\NullableService;
use Dirthara\Container\Tests\Fixtures\OptionalService;
use Dirthara\Container\Exception\EntryNotFoundException;
use Dirthara\Container\Tests\Fixtures\InheritsSelfTyped;
use Dirthara\Container\Exception\ContainerLockedException;
use Dirthara\Container\Exception\InvalidCallableException;
use Dirthara\Container\Tests\Fixtures\ServiceImplementation;
use Dirthara\Container\Exception\CircularDependencyException;
use Dirthara\Container\Tests\Fixtures\OtherServiceImplementation;
use Dirthara\Container\Exception\InvalidContextualBindingException;

final class ContainerTest extends TestCase
{
    #[Test]
    public function it_resolves_itself_as_the_container(): void
    {
        $container = new Container();

        self::assertSame($container, $container->get(Container::class));
        self::assertSame($container, $container->get(ContainerInterface::class));
        self::assertSame($container, $container->get(ContainerConfigurator::class));
        self::assertSame($container, $container->get(InstanceFactory::class));
        self::assertSame($container, $container->get(Invoker::class));
        self::assertSame($container, $container->get(Scope::class));
        self::assertSame($container, $container->get(TagResolver::class));
    }

    #[Test]
    public function it_registers_entries_through_the_configurator_interface(): void
    {
        $container = new Container();
        $configurator = $container->get(ContainerConfigurator::class);

        $returned = $configurator
            ->bind(Service::class, ServiceImplementation::class)
            ->singleton('shared', Plain::class)
            ->instance('config', ['debug' => true])
            ->when(NeedsService::class)
            ->needs(Service::class)
            ->give(OtherServiceImplementation::class);

        self::assertSame($container, $returned);
        self::assertInstanceOf(ServiceImplementation::class, $container->get(Service::class));
        self::assertSame($container->get('shared'), $container->get('shared'));
        self::assertSame(['debug' => true], $container->get('config'));
        self::assertInstanceOf(OtherServiceImplementation::class, $container->get(NeedsService::class)->service);
    }

    #[Test]
    public function it_makes_and_calls_through_the_instance_factory_and_invoker_interfaces(): void
    {
        $container = new Container();
        $factory = $container->get(InstanceFactory::class);
        $invoker = $container->get(Invoker::class);

        self::assertSame(4, $factory->make(RequiresNumber::class, ['number' => 4])->number);
        self::assertSame(['a', 'b'], $invoker->call([new Handler(), 'collect'], ['items' => ['a', 'b']]));
    }

    #[Test]
    public function it_lets_a_bound_factory_make_instances_through_the_instance_factory(): void
    {
        $container = new Container()->bind('number', static function (ContainerInterface $container): RequiresNumber {
            // @mago-expect analysis:mixed-assignment -- PSR-11 declares get() to return mixed, which the assertion narrows
            $factory = $container->get(InstanceFactory::class);
            self::assertInstanceOf(InstanceFactory::class, $factory);

            return $factory->make(RequiresNumber::class, ['number' => 3]);
        });

        $made = $container->get('number');

        self::assertInstanceOf(RequiresNumber::class, $made);
        self::assertSame(3, $made->number);
    }

    #[Test]
    public function it_shares_a_scoped_entry_until_the_scope_is_reset(): void
    {
        $container = new Container()->scoped(Service::class, ServiceImplementation::class);

        $first = $container->get(Service::class);

        self::assertInstanceOf(ServiceImplementation::class, $first);
        self::assertSame($first, $container->get(Service::class));

        $container->resetScope();

        self::assertNotSame($first, $container->get(Service::class));
    }

    #[Test]
    public function it_keeps_singletons_and_instances_when_the_scope_is_reset(): void
    {
        $instance = new Plain();
        $container = new Container()
            ->singleton(Service::class, ServiceImplementation::class)
            ->instance(Plain::class, $instance);
        $singleton = $container->get(Service::class);

        $container->resetScope();

        self::assertSame($singleton, $container->get(Service::class));
        self::assertSame($instance, $container->get(Plain::class));
    }

    #[Test]
    public function it_returns_a_scoped_instance_until_the_scope_is_reset(): void
    {
        $container = new Container();
        $container->scopedInstance('request', ['path' => '/']);

        self::assertTrue($container->has('request'));
        self::assertSame(['path' => '/'], $container->get('request'));

        $container->resetScope();

        self::assertFalse($container->has('request'));
    }

    #[Test]
    public function it_prefers_a_scoped_instance_and_falls_back_to_the_registration_after_a_reset(): void
    {
        $scoped = new ServiceImplementation();
        $instance = new Plain();
        $container = new Container()
            ->bind(Service::class, OtherServiceImplementation::class)
            ->instance(Plain::class, $instance);
        $container->scopedInstance(Service::class, $scoped)->scopedInstance(Plain::class, new Plain());

        self::assertSame($scoped, $container->get(Service::class));
        self::assertNotSame($instance, $container->get(Plain::class));

        $container->resetScope();

        self::assertInstanceOf(OtherServiceImplementation::class, $container->get(Service::class));
        self::assertSame($instance, $container->get(Plain::class));
    }

    #[Test]
    public function it_discards_a_scoped_value_when_the_entry_is_registered_again(): void
    {
        $instance = new Plain();
        $container = new Container()->scoped(Service::class, ServiceImplementation::class);
        $container->get(Service::class);
        $container->scopedInstance(Plain::class, new Plain());

        $container->bind(Service::class, OtherServiceImplementation::class)->instance(Plain::class, $instance);

        self::assertInstanceOf(OtherServiceImplementation::class, $container->get(Service::class));
        self::assertSame($instance, $container->get(Plain::class));
    }

    #[Test]
    public function it_makes_a_new_instance_of_a_scoped_entry_without_replacing_the_scoped_one(): void
    {
        $container = new Container()->scoped(Plain::class);
        $scoped = $container->get(Plain::class);

        self::assertNotSame($scoped, $container->make(Plain::class));
        self::assertSame($scoped, $container->get(Plain::class));
    }

    /**
     * @return iterable<string, array{Closure(Container): mixed}>
     */
    public static function registrations(): iterable
    {
        yield 'bind' => [static fn(Container $container): Container => $container->bind('service', Plain::class)];
        yield 'singleton' => [static fn(Container $container): Container => $container->singleton('service')];
        yield 'scoped' => [static fn(Container $container): Container => $container->scoped('service')];
        yield 'instance' => [static fn(Container $container): Container => $container->instance('service', 1)];
    }

    /**
     * @param Closure(Container): mixed $register
     */
    #[Test]
    #[DataProvider('registrations')]
    public function it_refuses_to_register_an_entry_once_it_is_locked(Closure $register): void
    {
        $container = new Container();
        $container->lock();

        try {
            $register($container);
            self::fail('Expected a ContainerLockedException.');
        } catch (ContainerLockedException $exception) {
            self::assertSame(['id' => 'service'], $exception->context);
            self::assertFalse($container->has('service'));
        }
    }

    #[Test]
    public function it_refuses_a_contextual_binding_once_it_is_locked(): void
    {
        $container = new Container();
        $container->lock();

        try {
            $container->when([Mailer::class, NeedsService::class]);
            self::fail('Expected a ContainerLockedException.');
        } catch (ContainerLockedException $exception) {
            self::assertSame(['classes' => [Mailer::class, NeedsService::class]], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_a_contextual_binding_that_is_completed_after_it_is_locked(): void
    {
        $container = new Container();
        $pending = $container->when(NeedsService::class)->needs(Service::class);
        $container->lock();

        try {
            $pending->give(ServiceImplementation::class);
            self::fail('Expected a ContainerLockedException.');
        } catch (ContainerLockedException $exception) {
            self::assertSame(['classes' => [NeedsService::class]], $exception->context);
        }

        $this->expectException(ResolutionException::class);

        $container->get(NeedsService::class);
    }

    #[Test]
    public function it_keeps_resolving_and_scoping_entries_once_it_is_locked(): void
    {
        $container = new Container()
            ->singleton(Service::class, ServiceImplementation::class)
            ->scoped(Plain::class);
        $container->lock();
        $container->lock();

        $scoped = $container->get(Plain::class);
        $container->scopedInstance('request', ['path' => '/']);

        self::assertInstanceOf(ServiceImplementation::class, $container->get(Service::class));
        self::assertSame($scoped, $container->get(Plain::class));
        self::assertSame(['path' => '/'], $container->get('request'));

        $container->resetScope();

        self::assertNotSame($scoped, $container->get(Plain::class));
        self::assertFalse($container->has('request'));
    }

    #[Test]
    public function it_refuses_to_make_an_entry_that_is_only_a_scoped_instance(): void
    {
        $container = new Container();
        $container->scopedInstance('request', ['path' => '/']);

        $this->expectException(ResolutionException::class);

        $container->make('request');
    }

    #[Test]
    public function it_has_instances_bindings_and_instantiable_classes(): void
    {
        $container = new Container()
            ->instance('config', ['debug' => true])
            ->bind(Service::class, ServiceImplementation::class);

        self::assertTrue($container->has('config'));
        self::assertTrue($container->has(Service::class));
        self::assertTrue($container->has(Plain::class));
    }

    #[Test]
    public function it_extends_every_value_a_binding_builds_with_the_container(): void
    {
        $received = new ArrayObject();
        $container = new Container()
            ->bind('list', static fn(): ArrayObject => new ArrayObject(['built']))
            ->extend('list', static function (mixed $list, ContainerInterface $container) use ($received): mixed {
                $received->append($container);
                self::assertInstanceOf(ArrayObject::class, $list);
                $list->append('first');

                return $list;
            })
            ->extend('list', static function (mixed $list): mixed {
                self::assertInstanceOf(ArrayObject::class, $list);
                $list->append('second');

                return $list;
            });

        $first = $container->get('list');
        $second = $container->make('list');

        self::assertInstanceOf(ArrayObject::class, $first);
        self::assertSame(['built', 'first', 'second'], $first->getArrayCopy());
        self::assertNotSame($first, $second);
        self::assertSame([$container, $container], $received->getArrayCopy());
    }

    #[Test]
    public function it_extends_a_singleton_once_and_an_autowired_class_and_an_alias_target(): void
    {
        $calls = new ArrayObject();
        $container = new Container()
            ->singleton(Service::class, ServiceImplementation::class)
            ->bind('alias', Plain::class)
            ->extend(Service::class, static function (mixed $service) use ($calls): mixed {
                $calls->append(Service::class);

                return $service;
            })
            ->extend(Plain::class, static fn(): stdClass => new stdClass());

        self::assertSame($container->get(Service::class), $container->get(Service::class));
        self::assertSame([Service::class], $calls->getArrayCopy());
        self::assertInstanceOf(stdClass::class, $container->get(Plain::class));
        self::assertInstanceOf(stdClass::class, $container->get('alias'));
    }

    #[Test]
    public function it_does_not_extend_an_instance(): void
    {
        $instance = new Plain();
        $container = new Container()
            ->instance(Plain::class, $instance)
            ->extend(Plain::class, static fn(): stdClass => new stdClass());

        self::assertSame($instance, $container->get(Plain::class));
    }

    #[Test]
    public function it_wraps_what_an_extender_throws_but_passes_a_logic_exception_through(): void
    {
        $previous = new RuntimeException('failed');
        $container = new Container()
            ->extend(Plain::class, static fn(): never => throw $previous)
            ->extend(Service::class, static fn(): never => throw new LogicException('bug'))
            ->bind(Service::class, ServiceImplementation::class);

        try {
            $container->get(Plain::class);
            self::fail('Expected a ResolutionException.');
        } catch (ResolutionException $exception) {
            self::assertSame(
                'An extender of entry "' . Plain::class . '" failed with RuntimeException.',
                $exception->getMessage(),
            );
            self::assertSame($previous, $exception->getPrevious());
            self::assertSame(['id' => Plain::class, 'exceptionClass' => RuntimeException::class], $exception->context);
        }

        $this->expectException(LogicException::class);

        $container->get(Service::class);
    }

    #[Test]
    public function it_refuses_to_extend_once_it_is_locked(): void
    {
        $container = new Container();
        $container->lock();

        $this->expectException(ContainerLockedException::class);

        $container->extend(Plain::class, static fn(mixed $plain): mixed => $plain);
    }

    #[Test]
    public function it_finds_a_class_that_is_defined_after_the_container_first_looked_for_it(): void
    {
        $container = new Container();

        self::assertFalse($container->has(DefinedLate::class));

        require_once __DIR__ . '/Fixtures/Late/DefinedLate.php';

        self::assertTrue($container->has(DefinedLate::class));
        self::assertInstanceOf(DefinedLate::class, $container->get(DefinedLate::class));
    }

    #[Test]
    public function it_has_no_unknown_identifiers_or_classes_that_cannot_be_instantiated(): void
    {
        $container = new Container();

        self::assertFalse($container->has('config'));
        self::assertFalse($container->has(Service::class));
        self::assertFalse($container->has(AbstractService::class));
        self::assertFalse($container->has(Suit::class));
    }

    #[Test]
    public function it_throws_not_found_for_an_unknown_identifier(): void
    {
        try {
            new Container()->get('config');
            self::fail('Expected an EntryNotFoundException.');
        } catch (EntryNotFoundException $exception) {
            self::assertInstanceOf(NotFoundExceptionInterface::class, $exception);
            self::assertSame(
                'No entry "config" was found: it is not bound, not registered as an instance, and not an instantiable class.',
                $exception->getMessage(),
            );
            self::assertSame(['id' => 'config'], $exception->context);
        }
    }

    #[Test]
    public function it_throws_not_found_for_an_unbound_interface(): void
    {
        $this->expectException(EntryNotFoundException::class);

        new Container()->get(Service::class);
    }

    #[Test]
    public function it_escapes_control_characters_of_an_identifier_in_the_message(): void
    {
        $this->expectExceptionMessage('No entry "config\nERROR forged" was found');

        new Container()->get("config\nERROR forged");
    }

    #[Test]
    public function it_autowires_a_class_and_its_dependencies(): void
    {
        $container = new Container()->bind(Service::class, ServiceImplementation::class);

        $nested = $container->get(Nested::class);

        self::assertInstanceOf(Nested::class, $nested);
        self::assertInstanceOf(ServiceImplementation::class, $nested->needsService->service);
        self::assertInstanceOf(Plain::class, $nested->plain);
    }

    #[Test]
    public function it_builds_a_new_autowired_instance_each_time(): void
    {
        $container = new Container();

        self::assertNotSame($container->get(Plain::class), $container->get(Plain::class));
    }

    #[Test]
    public function it_resolves_a_bound_factory_with_the_container_each_time(): void
    {
        $container = new Container();
        $container->bind('service', static fn(ContainerInterface $received): stdClass => (object) [
            'container' => $received,
        ]);

        $first = $container->get('service');

        self::assertInstanceOf(stdClass::class, $first);
        self::assertSame($container, $first->container);
        self::assertNotSame($first, $container->get('service'));
    }

    #[Test]
    public function it_shares_a_singleton_factory(): void
    {
        $container = new Container()->singleton('service', static fn(): stdClass => new stdClass());

        self::assertSame($container->get('service'), $container->get('service'));
    }

    #[Test]
    public function it_shares_a_singleton_class(): void
    {
        $container = new Container()->singleton(Plain::class);

        self::assertInstanceOf(Plain::class, $container->get(Plain::class));
        self::assertSame($container->get(Plain::class), $container->get(Plain::class));
    }

    #[Test]
    public function it_shares_a_singleton_alias_without_sharing_its_target(): void
    {
        $container = new Container()->singleton(Service::class, ServiceImplementation::class);

        self::assertSame($container->get(Service::class), $container->get(Service::class));
        self::assertNotSame($container->get(Service::class), $container->get(ServiceImplementation::class));
    }

    #[Test]
    public function it_resolves_a_class_bound_to_itself(): void
    {
        $container = new Container()->bind(Plain::class);

        self::assertInstanceOf(Plain::class, $container->get(Plain::class));
        self::assertNotSame($container->get(Plain::class), $container->get(Plain::class));
    }

    #[Test]
    public function it_resolves_a_binding_through_another_binding(): void
    {
        $container = new Container()
            ->bind('service', Service::class)
            ->bind(Service::class, ServiceImplementation::class);

        self::assertInstanceOf(ServiceImplementation::class, $container->get('service'));
    }

    #[Test]
    public function it_refuses_a_binding_to_itself_that_cannot_be_instantiated(): void
    {
        $container = new Container()->bind(Service::class);

        try {
            $container->get(Service::class);
            self::fail('Expected a ResolutionException.');
        } catch (ResolutionException $exception) {
            self::assertNotInstanceOf(NotFoundExceptionInterface::class, $exception);
            self::assertSame(
                'Entry "'
                . Service::class
                . '" is bound to "'
                . Service::class
                . '", which is neither a known entry nor an instantiable class.',
                $exception->getMessage(),
            );
            self::assertSame(['id' => Service::class, 'concrete' => Service::class], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_a_binding_to_an_unknown_entry(): void
    {
        $container = new Container()->bind(Service::class, 'missing');

        try {
            $container->get(Service::class);
            self::fail('Expected a ResolutionException.');
        } catch (ResolutionException $exception) {
            self::assertSame(['id' => Service::class, 'concrete' => 'missing'], $exception->context);
        }
    }

    #[Test]
    public function it_returns_a_registered_instance(): void
    {
        $plain = new Plain();
        $container = new Container()
            ->instance(Plain::class, $plain)
            ->instance('config', ['debug' => true])
            ->instance('nothing', null);

        self::assertSame($plain, $container->get(Plain::class));
        self::assertSame(['debug' => true], $container->get('config'));
        self::assertNull($container->get('nothing'));
        self::assertTrue($container->has('nothing'));
    }

    #[Test]
    public function it_replaces_a_binding_with_an_instance_and_an_instance_with_a_binding(): void
    {
        $implementation = new ServiceImplementation();
        $container = new Container()
            ->bind(Service::class, static fn(): ServiceImplementation => new ServiceImplementation())
            ->instance(Service::class, $implementation);

        self::assertSame($implementation, $container->get(Service::class));

        $container->bind(Service::class, ServiceImplementation::class);

        self::assertNotSame($implementation, $container->get(Service::class));
    }

    #[Test]
    public function it_forgets_a_resolved_singleton_when_the_entry_is_bound_again(): void
    {
        $container = new Container()->singleton('service', static fn(): stdClass => new stdClass());
        $first = $container->get('service');

        $container->singleton('service', static fn(): stdClass => new stdClass());

        self::assertNotSame($first, $container->get('service'));
    }

    #[Test]
    public function it_reports_a_missing_dependency_as_a_resolution_failure_rather_than_not_found(): void
    {
        try {
            new Container()->get(NeedsService::class);
            self::fail('Expected a ResolutionException.');
        } catch (ResolutionException $exception) {
            self::assertNotInstanceOf(NotFoundExceptionInterface::class, $exception);
            self::assertSame(
                'Unable to resolve parameter "$service" of "'
                . NeedsService::class
                . '": no entry "'
                . Service::class
                . '" was found, and the parameter has no default value and is not nullable.',
                $exception->getMessage(),
            );
            self::assertSame(
                ['target' => NeedsService::class, 'parameter' => 'service', 'dependency' => Service::class],
                $exception->context,
            );
        }
    }

    #[Test]
    public function it_gives_an_optional_dependency_that_has_no_entry_its_default(): void
    {
        $optional = new Container()->get(OptionalService::class);

        self::assertInstanceOf(OptionalService::class, $optional);
        self::assertNull($optional->service);
    }

    #[Test]
    public function it_gives_a_nullable_dependency_that_has_no_entry_null(): void
    {
        $nullable = new Container()->get(NullableService::class);

        self::assertInstanceOf(NullableService::class, $nullable);
        self::assertNull($nullable->service);
    }

    #[Test]
    public function it_resolves_an_optional_dependency_that_has_an_entry(): void
    {
        $container = new Container()->bind(Service::class, ServiceImplementation::class);

        $optional = $container->get(OptionalService::class);

        self::assertInstanceOf(OptionalService::class, $optional);
        self::assertInstanceOf(ServiceImplementation::class, $optional->service);
    }

    #[Test]
    public function it_uses_defaults_and_null_for_parameters_without_a_class_type_and_skips_variadics(): void
    {
        $scalars = new Container()->get(Scalars::class);

        self::assertInstanceOf(Scalars::class, $scalars);
        self::assertSame(3, $scalars->number);
        self::assertSame('union', $scalars->union);
        self::assertNull($scalars->nullable);
        self::assertSame([], $scalars->rest);
    }

    #[Test]
    public function it_refuses_a_required_parameter_without_a_class_type(): void
    {
        try {
            new Container()->get(RequiresNumber::class);
            self::fail('Expected a ResolutionException.');
        } catch (ResolutionException $exception) {
            self::assertSame(
                'Unable to resolve parameter "$number" of "'
                . RequiresNumber::class
                . '": it has no class type, no default value, and is not nullable.',
                $exception->getMessage(),
            );
            self::assertSame(['target' => RequiresNumber::class, 'parameter' => 'number'], $exception->context);
        }
    }

    #[Test]
    public function it_resolves_self_to_the_class_that_declares_the_constructor(): void
    {
        $selfTyped = new ReflectionClass(SelfTyped::class)->newInstanceWithoutConstructor();
        $container = new Container()->instance(SelfTyped::class, $selfTyped);

        $inherits = $container->get(InheritsSelfTyped::class);

        self::assertInstanceOf(InheritsSelfTyped::class, $inherits);
        self::assertSame($selfTyped, $inherits->other);
    }

    #[Test]
    public function it_resolves_parent_to_the_parent_of_the_class_that_declares_the_constructor(): void
    {
        $selfTyped = new ReflectionClass(SelfTyped::class)->newInstanceWithoutConstructor();
        $container = new Container()->instance(SelfTyped::class, $selfTyped);

        $parentTyped = $container->get(ParentTyped::class);

        self::assertInstanceOf(ParentTyped::class, $parentTyped);
        self::assertSame($selfTyped, $parentTyped->parent);
    }

    #[Test]
    public function it_tracks_what_each_fiber_is_resolving_separately(): void
    {
        $container = new Container()->bind('slow', static function (): stdClass {
            if (Fiber::getCurrent() !== null) {
                Fiber::suspend();
            }

            return new stdClass();
        });
        $first = new Fiber(static fn(): mixed => $container->get('slow'));
        $second = new Fiber(static fn(): mixed => $container->get('slow'));

        $first->start();
        $second->start();
        $fromOutsideAFiber = $container->get('slow');
        $first->resume();
        $second->resume();

        self::assertInstanceOf(stdClass::class, $fromOutsideAFiber);
        self::assertInstanceOf(stdClass::class, $first->getReturn());
        self::assertInstanceOf(stdClass::class, $second->getReturn());
    }

    #[Test]
    public function it_detects_a_circular_dependency_inside_a_fiber(): void
    {
        $container = new Container();
        $fiber = new Fiber(static fn(): mixed => $container->get(First::class));

        $this->expectException(CircularDependencyException::class);

        $fiber->start();
    }

    #[Test]
    public function it_detects_a_circular_dependency_and_names_the_chain(): void
    {
        $container = new Container();

        try {
            $container->get(First::class);
            self::fail('Expected a CircularDependencyException.');
        } catch (CircularDependencyException $exception) {
            self::assertInstanceOf(ContainerException::class, $exception);
            self::assertSame(
                'Circular dependency detected while resolving entry "'
                . First::class
                . '": '
                . First::class
                . ' -> '
                . Second::class
                . ' -> '
                . First::class
                . '.',
                $exception->getMessage(),
            );
            self::assertSame(
                ['id' => First::class, 'chain' => [First::class, Second::class, First::class]],
                $exception->context,
            );
        }

        self::assertInstanceOf(Plain::class, $container->get(Plain::class));
    }

    #[Test]
    public function it_detects_a_factory_that_requires_its_own_entry(): void
    {
        $container = new Container()->bind('service', static fn(ContainerInterface $c): mixed => $c->get('service'));

        $this->expectException(CircularDependencyException::class);

        $container->get('service');
    }

    #[Test]
    public function it_wraps_what_a_factory_throws_without_its_message(): void
    {
        $previous = new RuntimeException('secret token abc123');
        $container = new Container()->bind('service', static fn(): never => throw $previous);

        try {
            $container->get('service');
            self::fail('Expected a ResolutionException.');
        } catch (ResolutionException $exception) {
            self::assertSame('The factory for entry "service" failed with RuntimeException.', $exception->getMessage());
            self::assertSame($previous, $exception->getPrevious());
            self::assertSame(['id' => 'service', 'exceptionClass' => RuntimeException::class], $exception->context);
        }
    }

    #[Test]
    public function it_wraps_an_entry_a_factory_cannot_find_so_the_factorys_entry_is_not_reported_missing(): void
    {
        $container = new Container()->bind('service', static fn(ContainerInterface $c): mixed => $c->get('missing'));

        try {
            $container->get('service');
            self::fail('Expected a ResolutionException.');
        } catch (ResolutionException $exception) {
            self::assertNotInstanceOf(NotFoundExceptionInterface::class, $exception);
            self::assertInstanceOf(EntryNotFoundException::class, $exception->getPrevious());
            self::assertSame(
                ['id' => 'service', 'exceptionClass' => EntryNotFoundException::class],
                $exception->context,
            );
        }
    }

    #[Test]
    public function it_passes_its_own_exceptions_from_a_factory_through(): void
    {
        $thrown = ResolutionException::unresolvableParameter(Plain::class, 'value');
        $container = new Container()->bind('service', static fn(): never => throw $thrown);

        try {
            $container->get('service');
            self::fail('Expected a ResolutionException.');
        } catch (ResolutionException $exception) {
            self::assertSame($thrown, $exception);
        }
    }

    #[Test]
    public function it_passes_a_logic_exception_from_a_factory_through(): void
    {
        $thrown = new LogicException('a bug in the factory');
        $container = new Container()->bind('service', static fn(): never => throw $thrown);

        try {
            $container->get('service');
            self::fail('Expected a LogicException.');
        } catch (LogicException $exception) {
            self::assertSame($thrown, $exception);
        }
    }

    #[Test]
    public function it_passes_an_error_from_a_factory_through(): void
    {
        $container = new Container()->bind('service', static fn(): never => throw new TypeError('a bug'));

        $this->expectException(Error::class);

        $container->get('service');
    }

    #[Test]
    public function it_resolves_an_entry_again_after_its_factory_failed(): void
    {
        $container = new Container()->bind('service', static fn(): never => throw new RuntimeException('first'));

        try {
            $container->get('service');
            self::fail('Expected the first attempt to fail.');
        } catch (ResolutionException $exception) {
            self::assertSame(['id' => 'service', 'exceptionClass' => RuntimeException::class], $exception->context);
        }

        $container->bind('service', static fn(): string => 'second');

        self::assertSame('second', $container->get('service'));
    }

    #[Test]
    public function it_refuses_to_bind_for_something_that_is_not_a_class(): void
    {
        try {
            new Container()->when([Plain::class, Service::class]);
            self::fail('Expected an InvalidContextualBindingException.');
        } catch (InvalidContextualBindingException $exception) {
            self::assertSame(
                'Unable to add a contextual binding for "' . Service::class . '": it is not an existing class.',
                $exception->getMessage(),
            );
            self::assertSame(['class' => Service::class], $exception->context);
        }
    }

    #[Test]
    public function it_makes_a_new_instance_of_an_unregistered_class_each_time(): void
    {
        $container = new Container();

        $first = $container->make(Plain::class);

        self::assertInstanceOf(Plain::class, $first);
        self::assertNotSame($first, $container->make(Plain::class));
    }

    #[Test]
    public function it_makes_a_new_instance_of_a_singleton_without_replacing_the_shared_one(): void
    {
        $container = new Container()->singleton(Plain::class);
        $shared = $container->get(Plain::class);

        self::assertNotSame($shared, $container->make(Plain::class));
        self::assertSame($shared, $container->get(Plain::class));
    }

    #[Test]
    public function it_makes_a_new_instance_of_a_class_registered_as_an_instance(): void
    {
        $plain = new Plain();
        $container = new Container()->instance(Plain::class, $plain);

        self::assertNotSame($plain, $container->make(Plain::class));
    }

    #[Test]
    public function it_makes_a_new_instance_through_a_binding_even_when_its_target_is_shared(): void
    {
        $container = new Container()
            ->singleton(Service::class, ServiceImplementation::class)
            ->singleton(ServiceImplementation::class);

        $made = $container->make(Service::class);

        self::assertInstanceOf(ServiceImplementation::class, $made);
        self::assertNotSame($container->get(Service::class), $made);
        self::assertNotSame($container->get(ServiceImplementation::class), $made);
    }

    #[Test]
    public function it_refuses_to_make_an_entry_registered_only_as_an_instance(): void
    {
        $container = new Container()->instance('config', ['debug' => true]);

        try {
            $container->make('config');
            self::fail('Expected a ResolutionException.');
        } catch (ResolutionException $exception) {
            self::assertSame(
                'Unable to make a new "config": it is registered only as an instance, which the container cannot build again.',
                $exception->getMessage(),
            );
            self::assertSame(['id' => 'config'], $exception->context);
        }
    }

    #[Test]
    public function it_throws_not_found_when_making_an_unknown_entry(): void
    {
        $this->expectException(EntryNotFoundException::class);

        new Container()->make(Service::class);
    }

    #[Test]
    public function it_makes_an_instance_with_the_given_parameters_and_autowires_the_rest(): void
    {
        $primary = new OtherServiceImplementation();
        $container = new Container()->bind(Service::class, ServiceImplementation::class);

        $mailer = $container->make(Mailer::class, ['primary' => $primary, 'retries' => 9]);

        self::assertSame($primary, $mailer->primary);
        self::assertInstanceOf(ServiceImplementation::class, $mailer->fallback);
        self::assertSame(9, $mailer->retries);
        self::assertSame(4, $container->make(RequiresNumber::class, ['number' => 4])->number);
    }

    #[Test]
    public function it_prefers_given_parameters_over_contextual_bindings(): void
    {
        $container = new Container();
        $container->when(Mailer::class)->needs('$retries')->giveValue(5);
        $container->when(Mailer::class)->needs(Service::class)->give(ServiceImplementation::class);

        self::assertSame(7, $container->make(Mailer::class, ['retries' => 7])->retries);
        self::assertSame(5, $container->make(Mailer::class)->retries);
    }

    #[Test]
    public function it_spreads_a_given_variadic_parameter(): void
    {
        $container = new Container();

        self::assertSame(['a', 'b'], $container->make(Scalars::class, ['rest' => ['x' => 'a', 'y' => 'b']])->rest);
        self::assertSame(['a'], $container->make(Scalars::class, ['rest' => 'a'])->rest);
    }

    #[Test]
    public function it_refuses_parameters_the_constructor_does_not_have(): void
    {
        try {
            new Container()->make(RequiresNumber::class, ['number' => 1, 'numbr' => 2, 'other' => 3]);
            self::fail('Expected a ResolutionException.');
        } catch (ResolutionException $exception) {
            self::assertSame(
                'Unable to resolve "' . RequiresNumber::class . '": it has no parameters named "numbr", "other".',
                $exception->getMessage(),
            );
            self::assertSame(
                ['target' => RequiresNumber::class, 'parameters' => ['numbr', 'other']],
                $exception->context,
            );
        }
    }

    #[Test]
    public function it_passes_the_given_parameters_to_a_bound_factory(): void
    {
        $container = new Container()->bind(
            'service',
            static fn(ContainerInterface $container, array $parameters): array => $parameters,
        );

        self::assertSame(['name' => 'made'], $container->make('service', ['name' => 'made']));
        self::assertSame([], $container->get('service'));
    }

    #[Test]
    public function it_detects_a_circular_dependency_when_making(): void
    {
        $this->expectException(CircularDependencyException::class);

        new Container()->make(First::class);
    }

    #[Test]
    public function it_calls_a_closure_with_autowired_and_given_parameters(): void
    {
        $container = new Container()->bind(Service::class, ServiceImplementation::class);

        self::assertSame(ServiceImplementation::class . ' ' . Plain::class . ' 2', $container->call(
            static fn(Service $service, Plain $plain, int $count): string => (
                $service::class . ' ' . $plain::class . ' ' . $count
            ),
            ['count' => 2],
        ));
    }

    #[Test]
    public function it_calls_a_method_on_an_object(): void
    {
        $service = new ServiceImplementation();
        $container = new Container()->instance(Service::class, $service);

        self::assertSame([$service, 1], $container->call([new Handler(), 'handle']));
        self::assertSame([$service, 3], $container->call([new Handler(), 'handle'], ['count' => 3]));
    }

    #[Test]
    public function it_calls_a_method_on_a_class_resolved_from_the_container(): void
    {
        $resolved = new ArrayObject();
        $service = new ServiceImplementation();
        $container = new Container()->bind(Handler::class, static function () use ($resolved): Handler {
            $handler = new Handler();
            $resolved->append($handler);

            return $handler;
        });

        self::assertSame([$service, 1], $container->call(Handler::class . '::handle', ['service' => $service]));
        self::assertSame([$service, 1], $container->call([Handler::class, 'handle'], ['service' => $service]));
        self::assertCount(2, $resolved);
    }

    #[Test]
    public function it_calls_a_static_method_without_resolving_the_class(): void
    {
        $container = new Container();
        $expected = 'static:' . Plain::class;

        self::assertSame($expected, $container->call([Handler::class, 'describe'], ['name' => 'static']));
        self::assertSame($expected, $container->call(Handler::class . '::describe', ['name' => 'static']));
    }

    #[Test]
    public function it_calls_an_invokable_object_and_an_invokable_class(): void
    {
        $plain = new Plain();
        $container = new Container()->instance(Plain::class, $plain);

        self::assertSame($plain, $container->call(new Handler()));
        self::assertSame($plain, $container->call(Handler::class));
    }

    #[Test]
    public function it_calls_a_function_by_name(): void
    {
        self::assertSame(3, new Container()->call('strlen', ['string' => 'abc']));
    }

    #[Test]
    public function it_calls_a_method_with_a_given_variadic_parameter(): void
    {
        self::assertSame(['a', 'b'], new Container()->call([new Handler(), 'collect'], ['items' => ['a', 'b']]));
        self::assertSame([], new Container()->call([new Handler(), 'collect']));
    }

    #[Test]
    public function it_leaves_what_the_callable_throws_unwrapped(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('handler failed');

        new Container()->call([new Handler(), 'fail']);
    }

    #[Test]
    public function it_does_not_apply_contextual_bindings_to_a_call(): void
    {
        $container = new Container()->singleton(Service::class, ServiceImplementation::class);
        $container->when(Handler::class)->needs(Service::class)->give(OtherServiceImplementation::class);

        self::assertSame([$container->get(Service::class), 1], $container->call([Handler::class, 'handle']));
    }

    #[Test]
    public function it_names_the_method_when_a_call_cannot_resolve_a_parameter(): void
    {
        try {
            new Container()->call([new Handler(), 'handle']);
            self::fail('Expected a ResolutionException.');
        } catch (ResolutionException $exception) {
            self::assertSame(
                ['target' => Handler::class . '::handle', 'parameter' => 'service', 'dependency' => Service::class],
                $exception->context,
            );
        }
    }

    #[Test]
    public function it_refuses_parameters_a_closure_does_not_have(): void
    {
        try {
            new Container()->call(static fn(): null => null, ['extra' => 1]);
            self::fail('Expected a ResolutionException.');
        } catch (ResolutionException $exception) {
            self::assertSame(['target' => 'Closure', 'parameters' => ['extra']], $exception->context);
        }
    }

    /**
     * @param array{0: object|string, 1: string}|string|object $callable
     */
    #[Test]
    #[DataProvider('uncallables')]
    public function it_refuses_what_it_cannot_call(array|string|object $callable, string $description): void
    {
        try {
            new Container()->call($callable);
            self::fail('Expected an InvalidCallableException.');
        } catch (InvalidCallableException $exception) {
            self::assertSame(
                'Unable to call "'
                . $description
                . '": it is not a closure, a function, a public method, or an invokable class.',
                $exception->getMessage(),
            );
            self::assertSame(['callable' => $description], $exception->context);
        }
    }

    /**
     * @return iterable<string, array{array{0: object|string, 1: string}|string|object, string}>
     */
    public static function uncallables(): iterable
    {
        yield 'private method' => [[new Handler(), 'hidden'], Handler::class . '::hidden'];
        yield 'missing method' => [[Handler::class, 'missing'], Handler::class . '::missing'];
        yield 'missing method as a string' => [Handler::class . '::missing', Handler::class . '::missing'];
        yield 'unknown class' => [['Missing\\Handler', 'handle'], 'Missing\\Handler::handle'];
        yield 'class that is not invokable' => [Plain::class, Plain::class . '::__invoke'];
        yield 'object that is not invokable' => [new Plain(), Plain::class . '::__invoke'];
        yield 'unknown function' => ['missing_function', 'missing_function::__invoke'];
    }
}
