<?php

declare(strict_types=1);

namespace Dirthara\Container;

use Fiber;
use Closure;
use WeakMap;
use Exception;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Dirthara\Container\Attribute\Tag;
use Psr\Container\ContainerInterface;
use Dirthara\Container\Attribute\Lazy;
use Dirthara\Container\Contract\Scope;
use Dirthara\Container\Attribute\Inject;
use Dirthara\Container\Attribute\Scoped;
use Dirthara\Container\Attribute\Tagged;
use Dirthara\Container\Contract\Invoker;
use Dirthara\Container\Attribute\BoundTo;
use Dirthara\Container\Attribute\Singleton;
use Dirthara\Container\Contract\TagResolver;
use Dirthara\Container\Attribute\DecoratedBy;
use Dirthara\Container\Contract\InstanceFactory;
use Dirthara\Container\Exception\ContainerException;
use Dirthara\Container\Exception\ResolutionException;
use Dirthara\Container\Contract\ContainerConfigurator;
use Dirthara\Container\Exception\EntryNotFoundException;
use Dirthara\Container\Exception\ContainerLockedException;
use Dirthara\Container\Exception\InvalidCallableException;
use Dirthara\Container\Exception\InvalidAttributeException;
use Dirthara\Container\Exception\CircularDependencyException;
use Dirthara\Container\Exception\InvalidRegistrationException;
use Dirthara\Container\Exception\InvalidContextualBindingException;

use function is_array;
use function array_map;
use function is_object;
use function is_string;
use function array_flip;
use function array_keys;
use function array_push;
use function array_values;
use function class_exists;
use function array_diff_key;
use function is_subclass_of;
use function array_key_exists;
use function interface_exists;

final class Container implements ContainerInterface, ContainerConfigurator, InstanceFactory, Invoker, Scope, TagResolver
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
     * @var array<string, mixed>
     */
    private array $scopedInstances = [];

    /**
     * @var array<class-string, array<string, ContextualBinding>>
     */
    private array $contextualBindings = [];

    /**
     * @var array<string, array<string, true>>
     */
    private array $tags = [];

    /**
     * @var array<string, list<Closure(mixed, ContainerInterface): mixed>>
     */
    private array $extenders = [];

    /**
     * @var array<string, list<array{class-string, string}>>
     */
    private array $decorators = [];

    /**
     * @var array<string, bool>
     */
    private array $lazy = [];

    /**
     * @var list<array{string, Closure(object, ContainerInterface): mixed}>
     */
    private array $callbacks = [];

    /**
     * @var array<string, true>
     */
    private array $resolving = [];

    /**
     * @var WeakMap<Fiber<mixed, mixed, mixed, mixed>, array<string, true>>
     */
    private readonly WeakMap $resolvingInFibers;

    /**
     * @var array<class-string, ReflectionClass<object>|null>
     */
    private array $classes = [];

    /**
     * @var array<string, array<array-key, ReflectionParameter>>
     */
    private array $constructorParameters = [];

    /**
     * @var array<class-string, Binding|null>
     */
    private array $attributeBindings = [];

    private readonly CallableResolver $callables;

    private bool $locked = false;

    public function __construct()
    {
        $this->callables = new CallableResolver($this->instanceOf(...));
        $this->resolvingInFibers = new WeakMap();
        $this->instances[self::class] = $this;
        $this->instances[ContainerInterface::class] = $this;
        $this->instances[ContainerConfigurator::class] = $this;
        $this->instances[InstanceFactory::class] = $this;
        $this->instances[Invoker::class] = $this;
        $this->instances[Scope::class] = $this;
        $this->instances[TagResolver::class] = $this;
    }

    /**
     * @param string|Closure(ContainerInterface, array<string, mixed>): mixed|null $concrete
     *
     * @throws ContainerLockedException
     */
    public function bind(string $abstract, string|Closure|null $concrete = null): self
    {
        return $this->register($abstract, $concrete, Lifetime::Transient);
    }

    /**
     * @param string|Closure(ContainerInterface, array<string, mixed>): mixed|null $concrete
     *
     * @throws ContainerLockedException
     */
    public function singleton(string $abstract, string|Closure|null $concrete = null): self
    {
        return $this->register($abstract, $concrete, Lifetime::Shared);
    }

    /**
     * @param string|Closure(ContainerInterface, array<string, mixed>): mixed|null $concrete
     *
     * @throws ContainerLockedException
     */
    public function scoped(string $abstract, string|Closure|null $concrete = null): self
    {
        return $this->register($abstract, $concrete, Lifetime::Scoped);
    }

    /**
     * @throws ContainerLockedException
     */
    public function instance(string $abstract, mixed $instance): self
    {
        $this->assertUnlocked($abstract);

        unset($this->bindings[$abstract], $this->scopedInstances[$abstract]);

        $this->instances[$abstract] = $instance;

        return $this;
    }

    public function scopedInstance(string $abstract, mixed $instance): self
    {
        $this->scopedInstances[$abstract] = $instance;

        return $this;
    }

    public function resetScope(): void
    {
        $this->scopedInstances = [];
    }

    public function lock(): void
    {
        $this->locked = true;
    }

    /**
     * @param Closure(mixed, ContainerInterface): mixed $extender
     *
     * @throws ContainerLockedException
     */
    public function extend(string $abstract, Closure $extender): self
    {
        $this->assertConfigurable('extend');

        $this->extenders[$abstract][] = $extender;

        return $this;
    }

    /**
     * @param Closure(object, ContainerInterface): mixed $callback
     *
     * @throws ContainerLockedException
     */
    public function afterResolving(string $type, Closure $callback): self
    {
        $this->assertConfigurable('afterResolving');

        $this->callbacks[] = [$type, $callback];

        return $this;
    }

    /**
     * @throws ContainerLockedException
     * @throws InvalidRegistrationException
     */
    public function lazy(string $class): self
    {
        $this->assertConfigurable('lazy');

        if ($this->instantiableClass($class) === null) {
            throw InvalidRegistrationException::notALazyClass($class);
        }

        $this->lazy[$class] = true;

        return $this;
    }

    /**
     * @param string|list<string> $abstracts
     *
     * @throws ContainerLockedException
     */
    public function tag(string|array $abstracts, string $tag): self
    {
        $this->assertConfigurable('tag');

        foreach (is_string($abstracts) ? [$abstracts] : $abstracts as $abstract) {
            $this->tags[$tag][$abstract] = true;
        }

        return $this;
    }

    /**
     * @throws ContainerLockedException
     * @throws InvalidAttributeException
     */
    public function tagByAttribute(string ...$classes): self
    {
        $this->assertConfigurable('tagByAttribute');

        foreach ($classes as $class) {
            if (!class_exists($class) && !interface_exists($class)) {
                throw InvalidAttributeException::unknownClass($class);
            }

            foreach (new ReflectionClass($class)->getAttributes(Tag::class) as $attribute) {
                $this->tags[$attribute->newInstance()->name][$class] = true;
            }
        }

        return $this;
    }

    /**
     * @return iterable<string, mixed>
     */
    public function tagged(string $tag): iterable
    {
        return new TaggedEntries(fn(): array => array_keys($this->tags[$tag] ?? []), $this->get(...));
    }

    /**
     * @param class-string|list<class-string> $classes
     *
     * @throws ContainerLockedException
     * @throws InvalidContextualBindingException
     *
     * @return ContextualBindingBuilder<self>
     */
    public function when(string|array $classes): ContextualBindingBuilder
    {
        $classes = is_string($classes) ? [$classes] : $classes;

        if ($this->locked) {
            throw ContainerLockedException::cannotAddContextualBinding($classes);
        }

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
     * @throws InvalidAttributeException
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->scopedInstances)) {
            return $this->scopedInstances[$id];
        }

        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        return $this->resolveEntry($id);
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
     * @throws InvalidAttributeException
     */
    public function make(string $id, array $parameters = []): mixed
    {
        $binding = $this->bindingFor($id);
        $class = $binding === null ? $this->instantiableClass($id) : null;

        if ($binding === null && $class === null) {
            throw array_key_exists($id, $this->instances) || array_key_exists($id, $this->scopedInstances)
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
     * @throws InvalidAttributeException
     */
    public function call(array|string|object $callable, array $parameters = []): mixed
    {
        $resolved = $this->callables->resolve($callable);

        return ($resolved->closure)(...$this->resolveArguments(
            $resolved->name,
            $resolved->reflection->getParameters(),
            $parameters,
            null,
        ));
    }

    public function has(string $id): bool
    {
        return (
            array_key_exists($id, $this->scopedInstances)
            || array_key_exists($id, $this->instances)
            || array_key_exists($id, $this->bindings)
            || $this->hasAttributeBinding($id)
            || $this->instantiableClass($id) !== null
        );
    }

    /**
     * @param string|Closure(ContainerInterface, array<string, mixed>): mixed|null $concrete
     *
     * @throws ContainerLockedException
     */
    private function register(string $abstract, string|Closure|null $concrete, Lifetime $lifetime): self
    {
        $this->assertUnlocked($abstract);

        $this->bindings[$abstract] = new Binding(concrete: $concrete ?? $abstract, lifetime: $lifetime);

        unset($this->instances[$abstract], $this->scopedInstances[$abstract]);

        return $this;
    }

    /**
     * @throws EntryNotFoundException
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    private function resolveEntry(string $id): mixed
    {
        $binding = $this->bindingFor($id);
        $class = $binding === null ? $this->instantiableClass($id) : null;

        if ($binding === null && $class === null) {
            throw EntryNotFoundException::forId($id);
        }

        // @mago-expect analysis:mixed-assignment -- an entry can be any value, which the caller narrows
        $resolved = $this->resolve($id, $binding ?? $class, [], $this->get(...));

        match ($binding?->lifetime) {
            Lifetime::Shared => $this->instances[$id] = $resolved,
            Lifetime::Scoped => $this->scopedInstances[$id] = $resolved,
            default => null,
        };

        return $resolved;
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
        $fiber = Fiber::getCurrent();
        $resolving = $fiber === null ? $this->resolving : $this->resolvingInFibers[$fiber] ?? [];

        if (array_key_exists($id, $resolving)) {
            throw CircularDependencyException::forEntry($id, [...array_keys($resolving), $id]);
        }

        $this->track($fiber, [...$resolving, $id => true]);

        try {
            return $this->decorate(
                $id,
                $target instanceof Binding
                    ? $this->resolveBinding($id, $target, $parameters, $resolveAlias)
                    : $this->build($target, $parameters),
            );
        } finally {
            $this->track($fiber, $resolving);
        }
    }

    /**
     * @param Fiber<mixed, mixed, mixed, mixed>|null $fiber
     * @param array<string, true> $resolving
     */
    private function track(?Fiber $fiber, array $resolving): void
    {
        if ($fiber === null) {
            $this->resolving = $resolving;

            return;
        }

        if ($resolving === []) {
            unset($this->resolvingInFibers[$fiber]);

            return;
        }

        $this->resolvingInFibers[$fiber] = $resolving;
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
            return $this->afterBuilding($this->guard(
                fn(): mixed => $concrete($this, $parameters),
                static fn(Exception $exception): ResolutionException => ResolutionException::factoryFailed(
                    $id,
                    $exception,
                ),
            ));
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
     * @param Closure(): mixed $call
     * @param Closure(Exception): ResolutionException $wrap
     *
     * @throws ResolutionException
     */
    private function guard(Closure $call, Closure $wrap): mixed
    {
        try {
            return $call();
        } catch (EntryNotFoundException $exception) {
            throw $wrap($exception);
        } catch (ContainerException|LogicException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw $wrap($exception);
        }
    }

    /**
     * @throws CircularDependencyException
     * @throws ResolutionException
     * @throws InvalidAttributeException
     */
    private function decorate(string $id, mixed $value): mixed
    {
        foreach ($this->decoratorsOf($id) as [$decorator, $parameter]) {
            $value = $this->make($decorator, [$parameter => $value]);
        }

        foreach ($this->extenders[$id] ?? [] as $extender) {
            // @mago-expect analysis:mixed-assignment -- an extender can return any value
            $value = $this->guard(
                fn(): mixed => $extender($value, $this),
                static fn(Exception $exception): ResolutionException => ResolutionException::extenderFailed(
                    $id,
                    $exception,
                ),
            );
        }

        return $value;
    }

    /**
     * @throws InvalidAttributeException
     *
     * @return list<array{class-string, string}>
     */
    private function decoratorsOf(string $id): array
    {
        if (array_key_exists($id, $this->decorators)) {
            return $this->decorators[$id];
        }

        if (!class_exists($id) && !interface_exists($id)) {
            return [];
        }

        $decorators = [];

        foreach (new ReflectionClass($id)->getAttributes(DecoratedBy::class) as $attribute) {
            $decorator = $attribute->newInstance()->decorator;
            $decorators[] = [$decorator, $this->decoratedParameter($id, $decorator)];
        }

        $this->decorators[$id] = $decorators;

        return $decorators;
    }

    /**
     * @param class-string $id
     * @param class-string $decorator
     *
     * @throws InvalidAttributeException
     */
    private function decoratedParameter(string $id, string $decorator): string
    {
        $class = $this->instantiableClass($decorator);

        if ($class === null || $decorator === $id || !$this->isSubtype($decorator, $id)) {
            throw InvalidAttributeException::notADecorator($id, $decorator);
        }

        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($this->dependencyOf($parameter) === $id) {
                return $parameter->getName();
            }
        }

        throw InvalidAttributeException::decoratorWithoutParameter($id, $decorator);
    }

    /**
     * @param list<class-string> $classes
     *
     * @throws ContainerLockedException
     */
    private function addContextualBinding(array $classes, ContextualBinding $binding): void
    {
        if ($this->locked) {
            throw ContainerLockedException::cannotAddContextualBinding($classes);
        }

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

        $this->lazy[$name] ??= $class->getAttributes(Lazy::class) !== [];

        if ($this->lazy[$name] && $this->hasInstanceProperties($class)) {
            return $class->newLazyGhost(function (object $object) use ($class, $parameters): void {
                $class->getConstructor()?->invokeArgs($object, $this->constructorArguments($class, $parameters));
                $this->afterBuilding($object);
            });
        }

        return $this->afterBuilding($class->newInstance(...$this->constructorArguments($class, $parameters)));
    }

    /**
     * @template T
     *
     * @param T $value
     *
     * @throws ResolutionException
     *
     * @return T
     */
    private function afterBuilding(mixed $value): mixed
    {
        if (!is_object($value)) {
            return $value;
        }

        foreach ($this->callbacks as [$type, $callback]) {
            if (!$value instanceof $type) {
                continue;
            }

            $this->guard(
                fn(): mixed => $callback($value, $this),
                static fn(Exception $exception): ResolutionException => ResolutionException::callbackFailed(
                    $type,
                    $value::class,
                    $exception,
                ),
            );
        }

        return $value;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function hasInstanceProperties(ReflectionClass $class): bool
    {
        foreach ($class->getProperties() as $property) {
            if (!$property->isStatic()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ReflectionClass<object> $class
     * @param array<string, mixed> $parameters
     *
     * @throws CircularDependencyException
     * @throws ResolutionException
     *
     * @return list<mixed>
     */
    private function constructorArguments(ReflectionClass $class, array $parameters): array
    {
        $name = $class->getName();

        return $this->resolveArguments(
            $name,
            $this->constructorParameters[$name] ??= $class->getConstructor()?->getParameters() ?? [],
            $parameters,
            $name,
        );
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
        $type = $this->dependencyOf($parameter);
        $contextual = $contextualClass === null
            ? null
            : $this->contextualBindingFor($contextualClass, $parameter->getName(), $type);

        if ($contextualClass !== null && $contextual !== null) {
            return $this->resolveContextualBinding($contextualClass, $contextual);
        }

        $tagged = $parameter->getAttributes(Tagged::class)[0] ?? null;

        if ($tagged !== null) {
            return $this->tagged($tagged->newInstance()->name);
        }

        $dependency = $this->injectedEntry($parameter) ?? $type;

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
            return $this->afterBuilding($this->guard(
                fn(): mixed => $concrete($this),
                static fn(Exception $exception): ResolutionException => ResolutionException::contextualFactoryFailed(
                    $class,
                    $binding->need,
                    $exception,
                ),
            ));
        }

        return $this->has($concrete)
            ? $this->get($concrete)
            : throw ResolutionException::unresolvableContextualBinding($class, $binding->need, $concrete);
    }

    /**
     * @param class-string $class
     *
     * @throws EntryNotFoundException
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    private function instanceOf(string $class): object
    {
        return $this->get($class);
    }

    /**
     * @throws ContainerLockedException
     */
    private function assertConfigurable(string $method): void
    {
        if ($this->locked) {
            throw ContainerLockedException::cannotConfigure($method);
        }
    }

    /**
     * @throws ContainerLockedException
     */
    private function assertUnlocked(string $id): void
    {
        if ($this->locked) {
            throw ContainerLockedException::cannotRegister($id);
        }
    }

    /**
     * @throws InvalidAttributeException
     */
    private function bindingFor(string $id): ?Binding
    {
        return $this->bindings[$id] ?? $this->attributeBinding($id);
    }

    private function hasAttributeBinding(string $id): bool
    {
        try {
            return $this->attributeBinding($id) !== null;
        } catch (InvalidAttributeException) {
            return true;
        }
    }

    /**
     * @throws InvalidAttributeException
     */
    private function attributeBinding(string $id): ?Binding
    {
        if (array_key_exists($id, $this->attributeBindings)) {
            return $this->attributeBindings[$id];
        }

        if (!class_exists($id) && !interface_exists($id)) {
            return null;
        }

        $class = new ReflectionClass($id);
        $boundTo = $class->getAttributes(BoundTo::class)[0] ?? null;
        $lifetime = $this->lifetimeOf($class);

        if ($boundTo === null && $lifetime === Lifetime::Transient) {
            $this->attributeBindings[$id] = null;

            return null;
        }

        $concrete = $boundTo === null ? $id : $boundTo->newInstance()->concrete;

        if (!$this->isSubtype($concrete, $id)) {
            throw InvalidAttributeException::notASubtype($id, $concrete);
        }

        $this->attributeBindings[$id] = new Binding(concrete: $concrete, lifetime: $lifetime);

        return $this->attributeBindings[$id];
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @throws InvalidAttributeException
     */
    private function lifetimeOf(ReflectionClass $class): Lifetime
    {
        $singleton = $class->getAttributes(Singleton::class) !== [];
        $scoped = $class->getAttributes(Scoped::class) !== [];

        if ($singleton && $scoped) {
            throw InvalidAttributeException::conflictingLifetimes($class->getName());
        }

        return match (true) {
            $singleton => Lifetime::Shared,
            $scoped => Lifetime::Scoped,
            default => Lifetime::Transient,
        };
    }

    private function isSubtype(string $class, string $of): bool
    {
        return $class === $of || is_subclass_of($class, $of);
    }

    private function injectedEntry(ReflectionParameter $parameter): ?string
    {
        $inject = $parameter->getAttributes(Inject::class)[0] ?? null;

        return $inject?->newInstance()->id;
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
        if (array_key_exists($id, $this->classes)) {
            return $this->classes[$id];
        }

        if (!class_exists($id)) {
            return null;
        }

        $class = new ReflectionClass($id);
        $this->classes[$id] = $class->isInstantiable() ? $class : null;

        return $this->classes[$id];
    }
}
