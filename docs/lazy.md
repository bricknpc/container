---
id: lazy
title: Lazy services
sidebar_position: 10
description: Hand out a lazy object that builds a class only when it is first used, with lazy() or the #[Lazy] attribute.
---

A lazy service costs nothing until it is used. The container hands out a
[lazy object](https://www.php.net/manual/en/language.oop5.lazy-objects.php) of the class, and resolves the constructor's
dependencies and calls the constructor the first time a property is read or a method is called. A request that never
touches the database connection never opens it.

## Make a class lazy

`#[Lazy]` on a class, or `lazy()` for a class you cannot change, makes the container build it lazily:

```php
use Dirthara\Container\Attribute\Lazy;

#[Lazy]
final class ReportGenerator
{
    public function __construct(
        private Connection $connection,
    ) {}
}

$container->lazy(PdoConnection::class);

$generator = $container->get(ReportGenerator::class); // nothing is built yet
$generator->generate();                                 // builds the Connection, then the ReportGenerator
```

The lazy object is an instance of the class itself, so it passes every type check. Everything else about the entry stays
the same: a singleton is still built once, the parameters given to `make()` still reach the constructor, and
[contextual bindings](contextual-bindings.md) still apply, when the object is initialized.

| Parameter of `lazy()` | Type | Meaning |
| --- | --- | --- |
| `class` | `class-string` | An instantiable class. Anything else throws an `InvalidRegistrationException::notALazyClass()`. |

## When a class is lazy

Laziness applies whenever the container builds the class by calling its constructor: when it is autowired, bound to
itself, or the target of a binding such as `bind(ConnectionInterface::class, PdoConnection::class)`. It does not apply
to a class a factory builds, because the factory calls the constructor itself.

A class without instance properties is built right away. PHP considers a lazy object without properties initialized from
the start, and would never call its constructor.

:::caution
Because the constructor runs later, so does everything that can go wrong in it. A dependency that cannot be resolved
throws its `ResolutionException` where the object is first used, rather than where it was resolved.
:::

## Break a circular dependency

A lazy class does not resolve its dependencies when it is built, so it breaks a cycle that would otherwise throw a
`CircularDependencyException`:

```php
#[Lazy]
final readonly class EventDispatcher
{
    public function __construct(private ListenerProvider $listeners) {}
}

final readonly class ListenerProvider
{
    public function __construct(private EventDispatcher $dispatcher) {}
}

$container->get(ListenerProvider::class); // builds, with a lazy EventDispatcher
```

After [`lock()`](getting-started.md#lock-the-container), `lazy()` throws a `ContainerLockedException::cannotConfigure()`.
