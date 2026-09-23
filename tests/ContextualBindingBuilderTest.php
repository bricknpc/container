<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests;

use PHPUnit\Framework\TestCase;
use Dirthara\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Dirthara\Container\Tests\Fixtures\Plain;
use Dirthara\Container\Tests\Fixtures\Mailer;
use Dirthara\Container\Tests\Fixtures\Service;
use Dirthara\Container\PendingContextualBinding;
use Dirthara\Container\Exception\InvalidContextualBindingException;

final class ContextualBindingBuilderTest extends TestCase
{
    #[Test]
    #[TestWith([Mailer::class])]
    #[TestWith([Service::class])]
    #[TestWith(['$retries'])]
    #[TestWith(['$_näme2'])]
    public function it_needs_a_class_an_interface_or_a_parameter_name(string $need): void
    {
        self::assertInstanceOf(
            PendingContextualBinding::class,
            new Container()
                ->when(Plain::class)
                ->needs($need),
        );
    }

    #[Test]
    #[TestWith(['retries'])]
    #[TestWith(['$'])]
    #[TestWith(['$2retries'])]
    #[TestWith(['$re-tries'])]
    #[TestWith(['Missing\\Service'])]
    public function it_refuses_a_need_that_is_neither_a_known_type_nor_a_parameter_name(string $need): void
    {
        try {
            new Container()
                ->when(Plain::class)
                ->needs($need);
            self::fail('Expected an InvalidContextualBindingException.');
        } catch (InvalidContextualBindingException $exception) {
            self::assertSame(['need' => $need], $exception->context);
        }
    }

    #[Test]
    public function it_describes_an_invalid_need(): void
    {
        $this->expectExceptionMessage(
            'Unable to add a contextual binding that needs "retries": it is neither an existing class or interface nor a '
            . 'parameter name prefixed with $.',
        );

        new Container()
            ->when(Plain::class)
            ->needs('retries');
    }
}
