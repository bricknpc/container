<?php

declare(strict_types=1);

namespace Dirthara\Container;

use Closure;
use Exception;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionFunctionAbstract;
use Psr\Container\ContainerInterface;
use Dirthara\Container\Exception\ContainerException;
use Dirthara\Container\Exception\ResolutionException;
use Dirthara\Container\Exception\EntryNotFoundException;
use Dirthara\Container\Exception\InvalidCallableException;
use Dirthara\Container\Exception\CircularDependencyException;
use Dirthara\Container\Exception\InvalidContextualBindingException;

use function strpos;
use function substr;
use function is_array;
use function array_map;
use function is_object;
use function is_string;
use function array_flip;
use function array_keys;
use function array_push;
use function array_values;
use function class_exists;
use function method_exists;
use function array_diff_key;
use function function_exists;
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
     * @param string|Closure(ContainerInterface, array<string, mixed>): mixed|null $concrete
     */
    public function bind(string $abstract, string|Closure|null $concrete = null): self
    {
        $this->bindings[$abstract] = new Binding(concrete: $concrete ?? $abstract, shared: false);

        unset($this->instances[$abstract]);

        return $this;
    }

    /**
     * @param string|Closure(ContainerInterface, array<string, mixed>): mixed|null $concrete
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
        $resolved = $this->resolve($id, $binding ?? $class, [], $this->get(...));

        if ($binding?->shared === true) {
            $this->instances[$id] = $resolved;
        }

        return $resolved;
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|string $id
     * @param array<string, mixed> $parameters
     *
     * @return ($id is class-string<T> ? T : mixed)
     *
     * @throws EntryNotFoundException
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    public function make(string $id, array $parameters = []): mixed
    {
        $binding = $this->bindings[$id] ?? null;
        $class = $binding === null ? $this->instantiableClass($id) : null;

        if ($binding === null && $class === null) {
            throw array_key_exists($id, $this->instances)
                ? ResolutionException::notBuildable($id)
                : EntryNotFoundException::forId($id);
        }

        return $this->resolve($id, $binding ?? $class, $parameters, fn(string $concrete): mixed => $this->make(
            $concrete,
            $parameters,
        ));
    }

    /**
     * @param array{0: object|string, 1: string}|string|object $callable
     * @param array<string, mixed> $parameters
     *
     * @throws InvalidCallableException
     * @throws EntryNotFoundException
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    public function call(array|string|object $callable, array $parameters = []): mixed
    {
        [$function, $invoke, $target] = $this->reflectCallable($callable);

        return $invoke(...$this->resolveArguments($target, $function->getParameters(), $parameters, null));
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
     * @param array<string, mixed> $parameters
     * @param Closure(string): mixed $resolveAlias
     *
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    private function resolve(
        string $id,
        Binding|ReflectionClass $target,
        array $parameters,
        Closure $resolveAlias,
    ): mixed {
        if (array_key_exists($id, $this->resolving)) {
            throw CircularDependencyException::forEntry($id, [...array_keys($this->resolving), $id]);
        }

        $this->resolving[$id] = true;

        try {
            return $target instanceof Binding
                ? $this->resolveBinding($id, $target, $parameters, $resolveAlias)
                : $this->build($target, $parameters);
        } finally {
            unset($this->resolving[$id]);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     * @param Closure(string): mixed $resolveAlias
     *
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    private function resolveBinding(string $id, Binding $binding, array $parameters, Closure $resolveAlias): mixed
    {
        $concrete = $binding->concrete;

        if ($concrete instanceof Closure) {
            return $this->callFactory(
                $concrete,
                $parameters,
                static fn(Exception $exception): ResolutionException => ResolutionException::factoryFailed(
                    $id,
                    $exception,
                ),
            );
        }

        if ($concrete === $id) {
            $class = $this->instantiableClass($id);

            return $class === null
                ? throw ResolutionException::unresolvableBinding($id, $concrete)
                : $this->build($class, $parameters);
        }

        if (!$this->has($concrete)) {
            throw ResolutionException::unresolvableBinding($id, $concrete);
        }

        return $resolveAlias($concrete);
    }

    /**
     * @param Closure(ContainerInterface, array<string, mixed>): mixed $factory
     * @param array<string, mixed> $parameters
     * @param Closure(Exception): ResolutionException $wrap
     *
     * @throws ResolutionException
     */
    private function callFactory(Closure $factory, array $parameters, Closure $wrap): mixed
    {
        try {
            return $factory($this, $parameters);
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
     * @param array<string, mixed> $parameters
     *
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    private function build(ReflectionClass $class, array $parameters): object
    {
        $name = $class->getName();

        return $class->newInstance(...$this->resolveArguments(
            $name,
            $class->getConstructor()?->getParameters() ?? [],
            $parameters,
            $name,
        ));
    }

    /**
     * @param array<array-key, ReflectionParameter> $reflectionParameters
     * @param array<string, mixed> $parameters
     * @param class-string|null $contextualClass
     *
     * @throws CircularDependencyException
     * @throws ResolutionException
     *
     * @return list<mixed>
     */
    private function resolveArguments(
        string $target,
        array $reflectionParameters,
        array $parameters,
        ?string $contextualClass,
    ): array {
        $unknown = array_diff_key(
            $parameters,
            array_flip(array_map(
                static fn(ReflectionParameter $parameter): string => $parameter->getName(),
                $reflectionParameters,
            )),
        );

        if ($unknown !== []) {
            throw ResolutionException::unknownParameters($target, array_keys($unknown));
        }

        $arguments = [];

        foreach ($reflectionParameters as $parameter) {
            $name = $parameter->getName();

            if ($parameter->isVariadic()) {
                array_push($arguments, ...$this->variadicArguments($parameters, $name));

                continue;
            }

            $arguments[] = array_key_exists($name, $parameters)
                ? $parameters[$name]
                : $this->resolveParameter($target, $parameter, $contextualClass);
        }

        return $arguments;
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return list<mixed>
     */
    private function variadicArguments(array $parameters, string $name): array
    {
        if (!array_key_exists($name, $parameters)) {
            return [];
        }

        return is_array($parameters[$name]) ? array_values($parameters[$name]) : [$parameters[$name]];
    }

    /**
     * @param class-string|null $contextualClass
     *
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    private function resolveParameter(string $target, ReflectionParameter $parameter, ?string $contextualClass): mixed
    {
        $dependency = $this->dependencyOf($parameter);
        $contextual = $contextualClass === null
            ? null
            : $this->contextualBindingFor($contextualClass, $parameter->getName(), $dependency);

        if ($contextualClass !== null && $contextual !== null) {
            return $this->resolveContextualBinding($contextualClass, $contextual);
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
            ? ResolutionException::unresolvableParameter($target, $parameter->getName())
            : ResolutionException::missingDependency($target, $parameter->getName(), $dependency);
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
            return $this->callFactory(
                $concrete,
                [],
                static fn(Exception $exception): ResolutionException => ResolutionException::contextualFactoryFailed(
                    $class,
                    $binding->need,
                    $exception,
                ),
            );
        }

        return $this->has($concrete)
            ? $this->get($concrete)
            : throw ResolutionException::unresolvableContextualBinding($class, $binding->need, $concrete);
    }

    /**
     * @param array{0: object|string, 1: string}|string|object $callable
     *
     * @throws InvalidCallableException
     * @throws EntryNotFoundException
     * @throws CircularDependencyException
     * @throws ResolutionException
     *
     * @return array{ReflectionFunctionAbstract, Closure, string}
     */
    private function reflectCallable(array|string|object $callable): array
    {
        if ($callable instanceof Closure) {
            return [new ReflectionFunction($callable), $callable, 'Closure'];
        }

        if (is_string($callable) && function_exists($callable)) {
            return [new ReflectionFunction($callable), $callable(...), $callable];
        }

        if (is_array($callable)) {
            return $this->reflectMethod($callable[0], $callable[1]);
        }

        if (is_object($callable)) {
            return $this->reflectMethod($callable, '__invoke');
        }

        $separator = strpos($callable, needle: '::');

        return $separator === false
            ? $this->reflectMethod($callable, '__invoke')
            : $this->reflectMethod(substr($callable, offset: 0, length: $separator), substr($callable, $separator + 2));
    }

    /**
     * @throws InvalidCallableException
     * @throws EntryNotFoundException
     * @throws CircularDependencyException
     * @throws ResolutionException
     *
     * @return array{ReflectionFunctionAbstract, Closure, string}
     */
    private function reflectMethod(object|string $target, string $method): array
    {
        $class = is_object($target) ? $target::class : $target;
        $name = $class . '::' . $method;

        if (!class_exists($class) || !method_exists($class, $method)) {
            throw InvalidCallableException::notCallable($name);
        }

        $reflection = new ReflectionMethod($class, $method);

        if (!$reflection->isPublic()) {
            throw InvalidCallableException::notCallable($name);
        }

        if ($reflection->isStatic()) {
            return [$reflection, $reflection->getClosure(), $name];
        }

        return [$reflection, $reflection->getClosure(is_object($target) ? $target : $this->get($class)), $name];
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
