<?php

declare(strict_types=1);

namespace Dirthara\Container\Contract;

use Closure;
use Psr\Container\ContainerInterface;
use Dirthara\Container\Exception\ContainerLockedException;
use Dirthara\Container\Exception\InvalidAttributeException;
use Dirthara\Container\Exception\InvalidRegistrationException;
use Dirthara\Container\Exception\InvalidContextualBindingException;

interface ContainerConfigurator
{
    /**
     * @param string|Closure(ContainerInterface, array<string, mixed>): mixed|null $concrete
     *
     * @throws ContainerLockedException
     * @throws InvalidRegistrationException
     */
    public function bind(string $abstract, string|Closure|null $concrete = null): self;

    /**
     * @param string|Closure(ContainerInterface, array<string, mixed>): mixed|null $concrete
     *
     * @throws ContainerLockedException
     * @throws InvalidRegistrationException
     */
    public function singleton(string $abstract, string|Closure|null $concrete = null): self;

    /**
     * @param string|Closure(ContainerInterface, array<string, mixed>): mixed|null $concrete
     *
     * @throws ContainerLockedException
     * @throws InvalidRegistrationException
     */
    public function scoped(string $abstract, string|Closure|null $concrete = null): self;

    /**
     * @throws ContainerLockedException
     * @throws InvalidRegistrationException
     */
    public function instance(string $abstract, mixed $instance): self;

    /**
     * @throws ContainerLockedException
     */
    public function delegate(ContainerInterface $container): self;

    /**
     * @param Closure(mixed, ContainerInterface): mixed $extender
     *
     * @throws ContainerLockedException
     */
    public function extend(string $abstract, Closure $extender): self;

    /**
     * @param Closure(object, ContainerInterface): mixed $callback
     *
     * @throws ContainerLockedException
     */
    public function afterResolving(string $type, Closure $callback): self;

    /**
     * @throws ContainerLockedException
     * @throws InvalidRegistrationException
     */
    public function lazy(string $class): self;

    /**
     * @param string|list<string> $abstracts
     *
     * @throws ContainerLockedException
     */
    public function tag(string|array $abstracts, string $tag): self;

    /**
     * @throws ContainerLockedException
     * @throws InvalidAttributeException
     */
    public function tagByAttribute(string ...$classes): self;

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
