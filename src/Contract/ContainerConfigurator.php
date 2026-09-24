<?php

declare(strict_types=1);

namespace Dirthara\Container\Contract;

use Closure;
use Psr\Container\ContainerInterface;
use Dirthara\Container\Exception\ContainerLockedException;
use Dirthara\Container\Exception\InvalidContextualBindingException;

interface ContainerConfigurator
{
    /**
     * @param string|Closure(ContainerInterface, array<string, mixed>): mixed|null $concrete
     *
     * @throws ContainerLockedException
     */
    public function bind(string $abstract, string|Closure|null $concrete = null): self;

    /**
     * @param string|Closure(ContainerInterface, array<string, mixed>): mixed|null $concrete
     *
     * @throws ContainerLockedException
     */
    public function singleton(string $abstract, string|Closure|null $concrete = null): self;

    /**
     * @param string|Closure(ContainerInterface, array<string, mixed>): mixed|null $concrete
     *
     * @throws ContainerLockedException
     */
    public function scoped(string $abstract, string|Closure|null $concrete = null): self;

    /**
     * @throws ContainerLockedException
     */
    public function instance(string $abstract, mixed $instance): self;

    /**
     * @param class-string|list<class-string> $classes
     *
     * @throws ContainerLockedException
     * @throws InvalidContextualBindingException
     *
     * @return ContextualBindingBuilder<ContainerConfigurator>
     */
    public function when(string|array $classes): ContextualBindingBuilder;
}
