<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests;

use Error;
use stdClass;
use TypeError;
use LogicException;
use ReflectionClass;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use Dirthara\Container\Container;
use Psr\Container\ContainerInterface;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Container\Tests\Fixtures\Suit;
use Dirthara\Container\Tests\Fixtures\First;
use Dirthara\Container\Tests\Fixtures\Plain;
use Dirthara\Container\Tests\Fixtures\Nested;
use Dirthara\Container\Tests\Fixtures\Second;
use Psr\Container\NotFoundExceptionInterface;
use Dirthara\Container\Tests\Fixtures\Scalars;
use Dirthara\Container\Tests\Fixtures\Service;
use Dirthara\Container\Tests\Fixtures\SelfTyped;
use Dirthara\Container\Tests\Fixtures\ParentTyped;
use Dirthara\Container\Tests\Fixtures\NeedsService;
use Dirthara\Container\Exception\ContainerException;
use Dirthara\Container\Exception\ResolutionException;
use Dirthara\Container\Tests\Fixtures\RequiresNumber;
use Dirthara\Container\Tests\Fixtures\AbstractService;
use Dirthara\Container\Tests\Fixtures\NullableService;
use Dirthara\Container\Tests\Fixtures\OptionalService;
use Dirthara\Container\Exception\EntryNotFoundException;
use Dirthara\Container\Tests\Fixtures\InheritsSelfTyped;
use Dirthara\Container\Tests\Fixtures\ServiceImplementation;
use Dirthara\Container\Exception\CircularDependencyException;
use Dirthara\Container\Exception\InvalidContextualBindingException;

final class ContainerTest extends TestCase
{
    #[Test]
    public function it_resolves_itself_as_the_container(): void
    {
        $container = new Container();

        self::assertSame($container, $container->get(Container::class));
        self::assertSame($container, $container->get(ContainerInterface::class));
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
                ['class' => NeedsService::class, 'parameter' => 'service', 'dependency' => Service::class],
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
            self::assertSame(['class' => RequiresNumber::class, 'parameter' => 'number'], $exception->context);
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
}
