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
use Dirthara\Container\Exception\InvalidContextualBindingException;

use function is_string;
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
     * @var array<class-string, array<string, ContextualBinding>>
     */
    private array $contextualBindings = [];

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
     * @param string|Closure(ContainerInterface): mixed|null $concrete
     */
    public function bind(string $abstract, string|Closure|null $concrete = null): self
    {
        $this->bindings[$abstract] = new Binding(concrete: $concrete ?? $abstract, shared: false);

        unset($this->instances[$abstract]);

        return $this;
    }

    /**
     * @param string|Closure(ContainerInterface): mixed|null $concrete
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
     * @param class-string|list<class-string> $classes
     *
     * @throws InvalidContextualBindingException
     */
    public function when(string|array $classes): ContextualBindingBuilder
    {
        $classes = is_string($classes) ? [$classes] : $classes;

        foreach ($classes as $class) {
            if (!class_exists($class)) {
                throw InvalidContextualBindingException::notAClass($class);
            }
        }

        return new ContextualBindingBuilder($this, $classes, $this->addContextualBinding(...));
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|string $id
     *
     * @return ($id is class-string<T> ? T : mixed)
     *
     * @throws EntryNotFoundException
     * @throws CircularDependencyException
     * @throws ResolutionException
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
            return $this->callFactory($concrete, static fn(Exception $exception): ResolutionException => ResolutionException::factoryFailed(
                $id,
                $exception,
            ));
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
     * @param Closure(ContainerInterface): mixed $factory
     * @param Closure(Exception): ResolutionException $wrap
     *
     * @throws ResolutionException
     */
    private function callFactory(Closure $factory, Closure $wrap): mixed
    {
        try {
            return $factory($this);
        } catch (EntryNotFoundException $exception) {
            throw $wrap($exception);
        } catch (ContainerException|LogicException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw $wrap($exception);
        }
    }

    /**
     * @param list<class-string> $classes
     */
    private function addContextualBinding(array $classes, ContextualBinding $binding): void
    {
        foreach ($classes as $class) {
            $this->contextualBindings[$class][$binding->need] = $binding;
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
     * @param class-string $class
     *
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    private function resolveParameter(string $class, ReflectionParameter $parameter): mixed
    {
        $dependency = $this->dependencyOf($parameter);
        $contextual = $this->contextualBindingFor($class, $parameter->getName(), $dependency);

        if ($contextual !== null) {
            return $this->resolveContextualBinding($class, $contextual);
        }

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
     * @param class-string $class
     */
    private function contextualBindingFor(string $class, string $parameter, ?string $dependency): ?ContextualBinding
    {
        $bindings = $this->contextualBindings[$class] ?? [];

        return $bindings['$' . $parameter] ?? ($dependency === null ? null : $bindings[$dependency] ?? null);
    }

    /**
     * @param class-string $class
     *
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    private function resolveContextualBinding(string $class, ContextualBinding $binding): mixed
    {
        $concrete = $binding->concrete;

        if ($concrete === null) {
            return $binding->value;
        }

        if ($concrete instanceof Closure) {
            return $this->callFactory($concrete, static fn(Exception $exception): ResolutionException => ResolutionException::contextualFactoryFailed(
                $class,
                $binding->need,
                $exception,
            ));
        }

        return $this->has($concrete)
            ? $this->get($concrete)
            : throw ResolutionException::unresolvableContextualBinding($class, $binding->need, $concrete);
    }

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
