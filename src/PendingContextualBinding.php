<?php

declare(strict_types=1);

namespace Dirthara\Container;

use Closure;
use Psr\Container\ContainerInterface;
use Dirthara\Container\Contract\ContainerConfigurator;
use Dirthara\Container\Exception\ContainerLockedException;
use Dirthara\Container\Contract\PendingContextualBinding as PendingContextualBindingContract;

/**
 * @template-covariant TConfigurator of ContainerConfigurator
 *
 * @implements PendingContextualBindingContract<TConfigurator>
 */
final readonly class PendingContextualBinding implements PendingContextualBindingContract
{
    /**
     * @internal
     *
     * @param TConfigurator $configurator
     * @param list<class-string> $classes
     * @param Closure(list<class-string>, ContextualBinding): void $register
     */
    public function __construct(
        private ContainerConfigurator $configurator,
        private array $classes,
        private string $need,
        private Closure $register,
    ) {}

    /**
     * @param string|Closure(ContainerInterface): mixed $concrete
     *
     * @throws ContainerLockedException
     *
     * @return TConfigurator
     */
    public function give(string|Closure $concrete): ContainerConfigurator
    {
        ($this->register)($this->classes, new ContextualBinding(need: $this->need, concrete: $concrete));

        return $this->configurator;
    }

    /**
     * @throws ContainerLockedException
     *
     * @return TConfigurator
     */
    public function giveValue(mixed $value): ContainerConfigurator
    {
        ($this->register)($this->classes, new ContextualBinding(need: $this->need, concrete: null, value: $value));

        return $this->configurator;
    }
}
