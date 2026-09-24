---
id: autowiring
title: Autowiring
sidebar_position: 4
description: How the container builds classes that are not registered, and how it fills in each constructor parameter.
---

## Which classes are autowired

The container autowires any identifier that is not registered and names an instantiable class: not an interface, an
abstract class, an enum, or a class with a constructor that is not public. It also autowires a class that is bound to
itself with `bind(Mailer::class)` or `singleton(Mailer::class)`.

An autowired class is built again on every `get()`, unless it is registered with `singleton()` or `scoped()`, or has a
[lifetime attribute](attributes.md#give-a-type-a-lifetime).

## How each parameter is filled in

The container reads the constructor and resolves each parameter in turn, using the first rule that applies:

1. The parameter was given by name to [`make()`](making-and-calling.md): the given value. This applies only to the
   class being made, not to its dependencies.
2. A [contextual binding](contextual-bindings.md) for the class being built names the parameter, or its class or
   interface type: what that binding gives.
3. The parameter has an [`#[Inject]`](attributes.md#inject-a-named-entry) attribute, and the container has the entry it
   names: that entry, resolved with `get()`. The parameter's type is not looked up, so when the entry is missing, the
   parameter continues with rule 5.
4. The parameter has a class or interface type, and the container has an entry for it: that entry, resolved with
   `get()`.
5. The parameter has a default value: the default.
6. The parameter accepts `null`: `null`.
7. Otherwise, resolution fails with a `ResolutionException`.

A variadic parameter is left empty, unless a value for it is given to `make()`.

```php
final readonly class Mailer
{
    public function __construct(
        public LoggerInterface $logger,
        public ?Clock $clock = null,
        public int $retries = 3,
    ) {}
}

$container = new Container();
$container->bind(LoggerInterface::class, FileLogger::class);

$mailer = $container->get(Mailer::class);
```

`$logger` is a new `FileLogger` (rule 3). `$clock` is `null`, because nothing is registered for `Clock` and it is an
interface that cannot be autowired (rule 4). `$retries` is `3` (rule 4).

A parameter with a union or intersection type, or with a built-in type such as `int` or `string`, never comes from the
container. It gets its default or `null`, and resolution fails when it has neither. To fill such a parameter in, give it
a value with a [contextual binding](contextual-bindings.md) for its name, or bind the class to a factory:

```php
$container->bind(Mailer::class, static fn(ContainerInterface $container): Mailer => new Mailer(
    $container->get(LoggerInterface::class),
    retries: 5,
));
```

`self` and `parent` resolve to the class they name, as PHP reads them: relative to the class that declares the
constructor, which is not the class being built when the constructor is inherited.

## Optional dependencies

A default or `null` is only used when the container has no entry for the parameter's type. When it has one, the entry
is resolved, and a failure while building it is thrown rather than replaced by the default:

:::caution
A `?Clock $clock = null` parameter is `null` when nothing provides a `Clock`. It is not `null` when a `Clock` is bound
but cannot be built; the error is thrown, so a broken registration is never hidden behind the default.
:::

The same applies to a circular dependency. A class whose constructor takes an optional instance of itself, such as
`?self $parent = null`, cannot be autowired, because the container finds an entry for `self` and resolving it requires
the class being built. Bind such a class to a factory.

## Circular dependencies

When resolving an entry requires the same entry again, the container throws a `CircularDependencyException` instead of
recursing until PHP runs out of memory.

```php
final readonly class First
{
    public function __construct(public Second $second) {}
}

final readonly class Second
{
    public function __construct(public First $first) {}
}

$container->get(First::class);
```

The exception message names the chain, from the entry that was requested to the one that repeats:
`First -> Second -> First`. A factory that asks the container for its own entry is detected the same way.

## Fibers

The container keeps track of what it is resolving separately for each
[Fiber](https://www.php.net/manual/en/language.fibers.php). When a factory suspends its Fiber, another Fiber, or code
outside any Fiber, can resolve the same entry in the meantime without it being mistaken for a circular dependency, and
a cycle within one Fiber is still detected.

:::caution
The container does not wait for a Fiber that is building a singleton or a scoped entry. When two Fibers resolve an entry
that has not been built yet, and its factory suspends, both build it, and the one that finishes last replaces the other's
value. Build such entries before the Fibers start, or keep their factories from suspending.
:::
