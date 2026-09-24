<?php

declare(strict_types=1);

namespace Dirthara\Container\Contract;

use Closure;
use Psr\Container\ContainerInterface;
use Dirthara\Container\Exception\InvalidContextualBindingException;

interface ContainerConfigurator
{
    /**
     * @param string|Closure(ContainerInterface, array<string, mixed>): mixed|null $concrete
     */
    public function bind(string $abstract, string|Closure|null $concrete = null): self;

    /**
     * @param string|Closure(ContainerInterface, array<string, mixed>): mixed|null $concrete
     */
    public function singleton(string $abstract, string|Closure|null $concrete = null): self;

    /**
     * @param string|Closure(ContainerInterface, array<string, mixed>): mixed|null $concrete
     */
    public function scoped(string $abstract, string|Closure|null $concrete = null): self;

    public function instance(string $abstract, mixed $instance): self;

    /**
     * @param class-string|list<class-string> $classes
     *
     * @throws InvalidContextualBindingException
     *
     * @return ContextualBindingBuilder<ContainerConfigurator>
     */
    public function when(string|array $classes): ContextualBindingBuilder;
}
