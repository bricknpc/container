<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Dirthara\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Container\Tests\Fixtures\ScopedService;

final class ScopedTest extends TestCase
{
    #[Test]
    public function it_shares_an_autowired_class_that_is_scoped_until_the_scope_is_reset(): void
    {
        $container = new Container();

        $scoped = $container->get(ScopedService::class);

        self::assertSame($scoped, $container->get(ScopedService::class));

        $container->resetScope();

        self::assertNotSame($scoped, $container->get(ScopedService::class));
    }

    #[Test]
    public function it_prefers_a_singleton_registration_over_the_attribute(): void
    {
        $container = new Container()->singleton(ScopedService::class);
        $shared = $container->get(ScopedService::class);

        $container->resetScope();

        self::assertSame($shared, $container->get(ScopedService::class));
    }
}
