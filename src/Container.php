<?php

declare(strict_types=1);

namespace Dirthara\Container;

use Closure;
use Exception;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Psr\Container\ContainerInterface;
use Dirthara\Container\Exception\ContainerException;
use Dirthara\Container\Exception\ResolutionException;
use Dirthara\Container\Exception\EntryNotFoundException;
use Dirthara\Container\Exception\CircularDependencyException;

use function array_keys;
use function class_exists;
use function array_key_exists;

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
     * The entries being resolved right now, in the order they were requested.
     *
     * @var array<string, true>
     */
    private array $resolving = [];

    public function __construct()
    {
        $this->instances[self::class] = $this;
        $this->instances[ContainerInterface::class] = $this;
    }

    /**
     * @param string|Closure(ContainerInterface): mixed|null $concrete A class or another entry to resolve instead, or a
     *     factory; null resolves the abstract as a class.
     */
    public function bind(string $abstract, string|Closure|null $concrete = null): self
    {
        $this->bindings[$abstract] = new Binding(concrete: $concrete ?? $abstract, shared: false);

        unset($this->instances[$abstract]);

        return $this;
    }

    /**
     * @param string|Closure(ContainerInterface): mixed|null $concrete A class or another entry to resolve instead, or a
     *     factory; null resolves the abstract as a class.
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
     * @template T of object
     *
     * @param class-string<T>|string $id
     *
     * @return ($id is class-string<T> ? T : mixed)
     *
     * @throws EntryNotFoundException When the container has no entry for the identifier.
     * @throws CircularDependencyException When resolving the entry requires the entry itself.
     * @throws ResolutionException When the entry exists but cannot be built.
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        $binding = $this->bindings[$id] ?? null;
        $class = $binding === null ? $this->instantiableClass($id) : null;

        if ($binding === null && $class === null) {
            throw EntryNotFoundException::forId($id);
        }

        // @mago-expect analysis:mixed-assignment -- an entry can be any value, which the caller narrows
        $resolved = $this->resolve($id, $binding ?? $class);

        if ($binding?->shared === true) {
            $this->instances[$id] = $resolved;
        }

        return $resolved;
    }

    public function has(string $id): bool
    {
        return (
            array_key_exists($id, $this->instances)
            || array_key_exists($id, $this->bindings)
            || $this->instantiableClass($id) !== null
        );
    }

    /**
     * @param Binding|ReflectionClass<object> $target
     *
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    private function resolve(string $id, Binding|ReflectionClass $target): mixed
    {
        if (array_key_exists($id, $this->resolving)) {
            throw CircularDependencyException::forEntry($id, [...array_keys($this->resolving), $id]);
        }

        $this->resolving[$id] = true;

        try {
            return $target instanceof Binding ? $this->resolveBinding($id, $target) : $this->build($target);
        } finally {
            unset($this->resolving[$id]);
        }
    }

    /**
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    private function resolveBinding(string $id, Binding $binding): mixed
    {
        $concrete = $binding->concrete;

        if ($concrete instanceof Closure) {
            return $this->callFactory($id, $concrete);
        }

        if ($concrete === $id) {
            $class = $this->instantiableClass($id);

            return $class === null
                ? throw ResolutionException::unresolvableBinding($id, $concrete)
                : $this->build($class);
        }

        return $this->has($concrete)
            ? $this->get($concrete)
            : throw ResolutionException::unresolvableBinding($id, $concrete);
    }

    /**
     * Wraps what a factory throws, except this package's own exceptions, which pass through so their type survives,
     * and a LogicException, which is a bug in the factory rather than a failure to resolve.
     *
     * @param Closure(ContainerInterface): mixed $factory
     *
     * @throws ResolutionException
     */
    private function callFactory(string $id, Closure $factory): mixed
    {
        try {
            return $factory($this);
        } catch (EntryNotFoundException $exception) {
            // The missing entry is a dependency of this one, so rethrowing it would claim that this entry is missing.
            throw ResolutionException::factoryFailed($id, $exception);
        } catch (ContainerException|LogicException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw ResolutionException::factoryFailed($id, $exception);
        }
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    private function build(ReflectionClass $class): object
    {
        $arguments = [];

        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->isVariadic()) {
                continue;
            }

            $arguments[] = $this->resolveParameter($class->getName(), $parameter);
        }

        return $class->newInstance(...$arguments);
    }

    /**
     * Resolves a class-typed parameter from the container when the container has an entry for it, and otherwise
     * falls back to the default value, then to null.
     *
     * @param class-string $class
     *
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    private function resolveParameter(string $class, ReflectionParameter $parameter): mixed
    {
        $dependency = $this->dependencyOf($parameter);

        if ($dependency !== null && $this->has($dependency)) {
            return $this->get($dependency);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        throw $dependency === null
            ? ResolutionException::unresolvableParameter($class, $parameter->getName())
            : ResolutionException::missingDependency($class, $parameter->getName(), $dependency);
    }

    /**
     * The class a parameter is typed with, or null when it has no single class type. Reflection reports self and
     * parent as the classes they name, relative to the class that declares the constructor.
     */
    private function dependencyOf(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
    }

    /**
     * @return ReflectionClass<object>|null
     */
    private function instantiableClass(string $id): ?ReflectionClass
    {
        if (!class_exists($id)) {
            return null;
        }

        $class = new ReflectionClass($id);

        return $class->isInstantiable() ? $class : null;
    }
}
