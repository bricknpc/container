<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Dirthara\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Container\Attribute\Inject;
use Dirthara\Container\Tests\Fixtures\Service;
use Dirthara\Container\Exception\ResolutionException;
use Dirthara\Container\Tests\Fixtures\InjectsEntries;
use Dirthara\Container\Tests\Fixtures\RequiresInjected;
use Dirthara\Container\Tests\Fixtures\ServiceImplementation;
use Dirthara\Container\Tests\Fixtures\OtherServiceImplementation;

final class InjectTest extends TestCase
{
    #[Test]
    public function it_injects_the_named_entries_and_falls_back_to_the_default_for_a_missing_one(): void
    {
        $service = new ServiceImplementation();
        $container = new Container()
            ->bind(Service::class, OtherServiceImplementation::class)
            ->instance('primary.service', $service)
            ->instance('config', ['debug' => true]);

        $injects = $container->get(InjectsEntries::class);

        self::assertSame($service, $injects->service);
        self::assertSame(['debug' => true], $injects->config);
        self::assertNull($injects->optional);
    }

    #[Test]
    public function it_prefers_given_parameters_and_contextual_bindings_over_the_attribute(): void
    {
        $given = new ServiceImplementation();
        $container = new Container()
            ->instance('primary.service', new ServiceImplementation())
            ->instance('config', ['from' => 'entry']);
        $container->when(InjectsEntries::class)->needs('$config')->giveValue(['given' => 'contextually']);

        $injects = $container->make(InjectsEntries::class, ['service' => $given]);

        self::assertSame($given, $injects->service);
        self::assertSame(['given' => 'contextually'], $injects->config);
    }

    #[Test]
    public function it_injects_a_named_entry_into_a_called_closure(): void
    {
        $container = new Container()->instance('config', ['debug' => true]);

        self::assertSame(
            ['debug' => true],
            $container->call(static fn(#[Inject('config')] array $config): array => $config),
        );
    }

    #[Test]
    public function it_refuses_a_named_entry_that_is_missing_for_a_required_parameter(): void
    {
        try {
            new Container()->get(RequiresInjected::class);
            self::fail('Expected a ResolutionException.');
        } catch (ResolutionException $exception) {
            self::assertSame(
                ['target' => RequiresInjected::class, 'parameter' => 'service', 'dependency' => 'missing'],
                $exception->context,
            );
        }
    }
}
