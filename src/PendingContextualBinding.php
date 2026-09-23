<?php

declare(strict_types=1);

namespace Dirthara\Container;

use Closure;
use Psr\Container\ContainerInterface;

final readonly class PendingContextualBinding
{
    /**
     * @internal
     *
     * @param list<class-string> $classes
     * @param Closure(list<class-string>, ContextualBinding): void $register
     */
    public function __construct(
        private Container $container,
        private array $classes,
        private string $need,
        private Closure $register,
    ) {}

    /**
     * @param string|Closure(ContainerInterface): mixed $concrete
     */
    public function give(string|Closure $concrete): Container
    {
        ($this->register)($this->classes, new ContextualBinding(need: $this->need, concrete: $concrete));

        return $this->container;
    }

    public function giveValue(mixed $value): Container
    {
        ($this->register)($this->classes, new ContextualBinding(need: $this->need, concrete: null, value: $value));

        return $this->container;
    }
}
