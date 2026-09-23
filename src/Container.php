<?php

declare(strict_types=1);

namespace Dirthara\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Dirthara\Container\Exception\ResolutionException;
use Dirthara\Container\Exception\EntryNotFoundException;
use Dirthara\Container\Exception\CircularDependencyException;

final class Container implements ContainerInterface
{
    /**
     * @var array<string, Binding>
     */
    private array $bindings = [];

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * @var array<string, true>
     */
    private array $resolving = [];

    public function __construct()
    {
        $this->instances[self::class] = $this;
        $this->instances[ContainerInterface::class] = $this;
    }

    /**
     * @param class-string|string $abstract
     * @param class-string|Closure(ContainerInterface): mixed|null $concrete
     */
    public function bind(string $abstract, string|Closure|null $concrete = null): self
    {
        $this->bindings[$abstract] = new Binding(concrete: $concrete ?? $abstract, shared: false);

        unset($this->instances[$abstract]);

        return $this;
    }

    /**
     * @param class-string|string $abstract
     * @param class-string|Closure(ContainerInterface): mixed|null $concrete
     */
    public function singleton(string $abstract, string|Closure|null $concrete = null): self
    {
        $this->bindings[$abstract] = new Binding(concrete: $concrete ?? $abstract, shared: true);

        unset($this->instances[$abstract]);

        return $this;
    }

    public function instance(string $abstract, mixed $instance): self
    {
        unset($this->bindings[$abstract]);

        $this->instances[$abstract] = $instance;

        return $this;
    }

    /**
     * @throws CircularDependencyException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->resolving[$id])) {
            throw CircularDependencyException::forEntry($id, array_keys($this->resolving));
        }

        $this->resolving[$id] = true;

        try {
            if (isset($this->bindings[$id])) {
                return $this->resolveBinding($id, $this->bindings[$id]);
            }

            if (!class_exists($id)) {
                throw EntryNotFoundException::forId($id);
            }

            return $this->resolveClass($id);
        } finally {
            unset($this->resolving[$id]);
        }
    }

    public function has(string $id): bool
    {
        if (array_key_exists($id, $this->instances) || isset($this->bindings[$id])) {
            return true;
        }

        if (!class_exists($id)) {
            return false;
        }

        return new ReflectionClass($id)->isInstantiable();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    private function resolveBinding(string $id, Binding $binding): mixed
    {
        if ($binding->concrete instanceof Closure) {
            $resolved = ($binding->concrete)($this);
        } elseif ($binding->concrete === $id) {
            $resolved = $this->resolveClass($id);
        } else {
            $resolved = $this->get($binding->concrete);
        }

        if ($binding->shared) {
            $this->instances[$id] = $resolved;
        }

        return $resolved;
    }

    /**
     * @param class-string $class
     *
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws CircularDependencyException
     */
    private function resolveClass(string $class): object
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw EntryNotFoundException::forId($class);
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isVariadic()) {
                continue;
            }

            $arguments[] = $this->resolveParameter($class, $parameter);
        }

        return $reflection->newInstanceArgs($arguments);
    }

    /**
     * @param class-string $class
     *
     * @throws ResolutionException
     * @throws CircularDependencyException
     * @throws EntryNotFoundException
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     */
    private function resolveParameter(string $class, ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->get($this->resolveTypeName($class, $type->getName()));
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        throw ResolutionException::unresolvableParameter(class: $class, parameter: $parameter->getName());
    }

    /**
     * @param class-string $class
     *
     * @throws ResolutionException
     *
     * @return class-string
     */
    private function resolveTypeName(string $class, string $type): string
    {
        if ($type === 'self') {
            return $class;
        }

        if ($type === 'parent') {
            $parent = get_parent_class($class);

            if ($parent === false) {
                throw ResolutionException::invalidParentType($class);
            }

            return $parent;
        }

        return $type;
    }
}
