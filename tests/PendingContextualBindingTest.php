<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests;

use stdClass;
use LogicException;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use Dirthara\Container\Container;
use Psr\Container\ContainerInterface;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Container\Tests\Fixtures\Mailer;
use Dirthara\Container\Tests\Fixtures\Scalars;
use Dirthara\Container\Tests\Fixtures\Service;
use Dirthara\Container\Tests\Fixtures\NeedsService;
use Dirthara\Container\Exception\ResolutionException;
use Dirthara\Container\Tests\Fixtures\OptionalService;
use Dirthara\Container\Exception\EntryNotFoundException;
use Dirthara\Container\Tests\Fixtures\ServiceImplementation;
use Dirthara\Container\Exception\CircularDependencyException;
use Dirthara\Container\Tests\Fixtures\OtherServiceImplementation;

final class PendingContextualBindingTest extends TestCase
{
    #[Test]
    public function it_gives_an_entry_for_a_type_only_to_the_class_it_is_bound_for(): void
    {
        $container = new Container();
        $container->when(NeedsService::class)->needs(Service::class)->give(ServiceImplementation::class);

        $needsService = $container->get(NeedsService::class);

        self::assertInstanceOf(NeedsService::class, $needsService);
        self::assertInstanceOf(ServiceImplementation::class, $needsService->service);
        self::assertFalse($container->has(Service::class));
    }

    #[Test]
    public function it_gives_the_entry_to_every_parameter_of_the_type(): void
    {
        $container = new Container();
        $container->when(Mailer::class)->needs(Service::class)->give(ServiceImplementation::class);

        $mailer = $container->get(Mailer::class);

        self::assertInstanceOf(ServiceImplementation::class, $mailer->primary);
        self::assertInstanceOf(ServiceImplementation::class, $mailer->fallback);
        self::assertNotSame($mailer->primary, $mailer->fallback);
    }

    #[Test]
    public function it_prefers_a_binding_for_the_parameter_name_over_one_for_its_type(): void
    {
        $container = new Container();
        $container->when(Mailer::class)->needs(Service::class)->give(ServiceImplementation::class);
        $container->when(Mailer::class)->needs('$fallback')->give(OtherServiceImplementation::class);

        $mailer = $container->get(Mailer::class);

        self::assertInstanceOf(ServiceImplementation::class, $mailer->primary);
        self::assertInstanceOf(OtherServiceImplementation::class, $mailer->fallback);
    }

    #[Test]
    public function it_gives_a_value_to_a_parameter_by_name(): void
    {
        $service = new ServiceImplementation();
        $container = new Container()
            ->when(Mailer::class)
            ->needs(Service::class)
            ->giveValue($service)
            ->when(Mailer::class)
            ->needs('$retries')
            ->giveValue(5);

        $mailer = $container->get(Mailer::class);

        self::assertSame(5, $mailer->retries);
        self::assertSame($service, $mailer->primary);
        self::assertSame($service, $mailer->fallback);
    }

    #[Test]
    public function it_gives_values_to_parameters_without_a_class_type_but_leaves_a_variadic_one_empty(): void
    {
        $container = new Container()
            ->when(Scalars::class)
            ->needs('$union')
            ->giveValue(7)
            ->when(Scalars::class)
            ->needs('$nullable')
            ->giveValue('given')
            ->when(Scalars::class)
            ->needs('$rest')
            ->giveValue('ignored');

        $scalars = $container->get(Scalars::class);

        self::assertSame(7, $scalars->union);
        self::assertSame('given', $scalars->nullable);
        self::assertSame([], $scalars->rest);
    }

    #[Test]
    public function it_gives_null_as_a_value_even_when_the_container_has_an_entry(): void
    {
        $container = new Container()->bind(Service::class, ServiceImplementation::class);
        $container->when(OptionalService::class)->needs(Service::class)->giveValue(null);

        $optional = $container->get(OptionalService::class);

        self::assertInstanceOf(OptionalService::class, $optional);
        self::assertNull($optional->service);
    }

    #[Test]
    public function it_takes_precedence_over_a_binding_in_the_container_and_over_a_default(): void
    {
        $container = new Container()->bind(Service::class, ServiceImplementation::class);
        $container
            ->when([NeedsService::class, OptionalService::class])
            ->needs(Service::class)
            ->give(OtherServiceImplementation::class);

        $needsService = $container->get(NeedsService::class);
        $optional = $container->get(OptionalService::class);

        self::assertInstanceOf(OtherServiceImplementation::class, $needsService->service);
        self::assertInstanceOf(OtherServiceImplementation::class, $optional->service);
        self::assertInstanceOf(ServiceImplementation::class, $container->get(Service::class));
    }

    #[Test]
    public function it_resolves_the_given_entry_through_the_container(): void
    {
        $container = new Container()->singleton('shared.service', ServiceImplementation::class);
        $container->when(Mailer::class)->needs(Service::class)->give('shared.service');

        $mailer = $container->get(Mailer::class);

        self::assertSame($container->get('shared.service'), $mailer->primary);
        self::assertSame($mailer->primary, $mailer->fallback);
    }

    #[Test]
    public function it_calls_a_given_factory_with_the_container_for_each_parameter(): void
    {
        $calls = new stdClass();
        $calls->received = [];
        $container = new Container();
        $container
            ->when(Mailer::class)
            ->needs(Service::class)
            ->give(static function (ContainerInterface $received) use ($calls): Service {
                $calls->received[] = $received;

                return new ServiceImplementation();
            });

        $mailer = $container->get(Mailer::class);

        self::assertNotSame($mailer->primary, $mailer->fallback);
        self::assertSame([$container, $container], $calls->received);
    }

    #[Test]
    public function it_applies_when_the_class_is_resolved_through_a_binding(): void
    {
        $container = new Container()->bind('mailer', Mailer::class);
        $container->when(Mailer::class)->needs(Service::class)->give(ServiceImplementation::class);

        $mailer = $container->get('mailer');

        self::assertInstanceOf(Mailer::class, $mailer);
        self::assertInstanceOf(ServiceImplementation::class, $mailer->primary);
    }

    #[Test]
    public function it_replaces_an_earlier_contextual_binding_for_the_same_need(): void
    {
        $container = new Container();
        $container->when(NeedsService::class)->needs(Service::class)->give(ServiceImplementation::class);
        $container->when(NeedsService::class)->needs(Service::class)->give(OtherServiceImplementation::class);

        $needsService = $container->get(NeedsService::class);

        self::assertInstanceOf(OtherServiceImplementation::class, $needsService->service);
    }

    #[Test]
    public function it_refuses_to_give_an_unknown_entry(): void
    {
        $container = new Container();
        $container->when(NeedsService::class)->needs(Service::class)->give('missing');

        try {
            $container->get(NeedsService::class);
            self::fail('Expected a ResolutionException.');
        } catch (ResolutionException $exception) {
            self::assertSame(
                'The contextual binding of "'
                . Service::class
                . '" for "'
                . NeedsService::class
                . '" gives "missing", which is neither a known entry nor an instantiable class.',
                $exception->getMessage(),
            );
            self::assertSame(
                ['class' => NeedsService::class, 'need' => Service::class, 'concrete' => 'missing'],
                $exception->context,
            );
        }
    }

    #[Test]
    public function it_wraps_what_a_given_factory_throws(): void
    {
        $previous = new RuntimeException('secret token abc123');
        $container = new Container();
        $container
            ->when(Mailer::class)
            ->needs('$primary')
            ->give(static fn(): never => throw $previous);

        try {
            $container->get(Mailer::class);
            self::fail('Expected a ResolutionException.');
        } catch (ResolutionException $exception) {
            self::assertSame(
                'The contextual factory of "$primary" for "' . Mailer::class . '" failed with RuntimeException.',
                $exception->getMessage(),
            );
            self::assertSame($previous, $exception->getPrevious());
            self::assertSame(
                ['class' => Mailer::class, 'need' => '$primary', 'exceptionClass' => RuntimeException::class],
                $exception->context,
            );
        }
    }

    #[Test]
    public function it_wraps_an_entry_a_given_factory_cannot_find(): void
    {
        $container = new Container();
        $container
            ->when(NeedsService::class)
            ->needs(Service::class)
            ->give(static fn(ContainerInterface $c): mixed => $c->get('missing'));

        try {
            $container->get(NeedsService::class);
            self::fail('Expected a ResolutionException.');
        } catch (ResolutionException $exception) {
            self::assertInstanceOf(EntryNotFoundException::class, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_passes_a_logic_exception_from_a_given_factory_through(): void
    {
        $thrown = new LogicException('a bug in the factory');
        $container = new Container();
        $container
            ->when(NeedsService::class)
            ->needs(Service::class)
            ->give(static fn(): never => throw $thrown);

        try {
            $container->get(NeedsService::class);
            self::fail('Expected a LogicException.');
        } catch (LogicException $exception) {
            self::assertSame($thrown, $exception);
        }
    }

    #[Test]
    public function it_detects_a_contextual_binding_that_requires_the_class_being_built(): void
    {
        $container = new Container();
        $container->when(NeedsService::class)->needs(Service::class)->give(NeedsService::class);

        $this->expectException(CircularDependencyException::class);

        $container->get(NeedsService::class);
    }
}
