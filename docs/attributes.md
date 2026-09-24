---
id: attributes
title: Attributes
sidebar_position: 7
description: Declare a type's default implementation, its lifetime, and what a parameter receives with attributes.
---

Attributes let a class or interface declare how the container resolves it, so the declaration lives next to the code it
is about instead of in a registration somewhere else. An attribute only applies while nothing is registered for the
type: `bind()`, `singleton()`, `scoped()`, `instance()`, and `scopedInstance()` always take precedence.

## Bind a type to its default implementation

`#[BoundTo]` on an interface or a class names the class the container resolves when the type is asked for:

```php
use Dirthara\Container\Attribute\BoundTo;

#[BoundTo(SystemClock::class)]
interface Clock
{
    public function now(): DateTimeImmutable;
}

$container->get(Clock::class); // a new SystemClock
```

It behaves as `$container->bind(Clock::class, SystemClock::class)` would: every `get()` resolves `SystemClock` again,
`make(Clock::class, $parameters)` passes the parameters on to it, and anything that `SystemClock` itself is registered
as, such as a singleton, applies. `has(Clock::class)` returns `true`.

| Parameter | Type | Meaning |
| --- | --- | --- |
| `concrete` | `class-string` | The class or interface to resolve instead. It has to extend or implement the type the attribute is on. |

Registering something for the type replaces the attribute, which is how a test or an application swaps the default:

```php
$container->instance(Clock::class, new FrozenClock('2026-01-01'));
```

A `concrete` that does not extend or implement the type throws an `InvalidAttributeException::notASubtype()` when the
type is resolved, rather than handing out an object of the wrong type.

## Give a type a lifetime

`#[Singleton]` and `#[Scoped]` give a type the lifetime that `singleton()` and `scoped()` would:

```php
use Dirthara\Container\Attribute\Scoped;
use Dirthara\Container\Attribute\Singleton;

#[Singleton]
final class RouteCollection {}

#[Scoped]
final class UnitOfWork {}

$container->get(RouteCollection::class) === $container->get(RouteCollection::class); // true

$container->resetScope();
$container->get(UnitOfWork::class); // a new UnitOfWork
```

Neither takes parameters. `make()` still builds a new instance, as it does for a registered singleton.

The lifetime belongs to the identifier the attribute is on. Together with `#[BoundTo]`, it shares the entry for the
type, while the class it is bound to keeps its own lifetime:

```php
#[BoundTo(SystemClock::class)]
#[Singleton]
interface Clock {}

$container->get(Clock::class) === $container->get(Clock::class);             // true
$container->get(SystemClock::class) === $container->get(SystemClock::class); // false
```

A type can have one lifetime. One with both attributes throws an `InvalidAttributeException::conflictingLifetimes()`
when it is resolved.

:::caution
A lifetime attribute on an interface or an abstract class without `#[BoundTo]` has nothing to build, so resolving the
type throws a `ResolutionException::unresolvableBinding()`.
:::

## Inject a named entry

`#[Inject]` on a parameter tells the container which entry to resolve for it, instead of looking up the parameter's
type. It works for any parameter the container fills in: in a constructor, and in a method or closure given to
[`call()`](making-and-calling.md#call-a-method-or-a-closure).

```php
use Dirthara\Container\Attribute\Inject;

final readonly class Mailer
{
    public function __construct(
        #[Inject('mail.transport')]
        public TransportInterface $transport,
        #[Inject('config.mail')]
        public array $config,
    ) {}
}

$container->singleton('mail.transport', SmtpTransport::class);
$container->instance('config.mail', ['from' => 'noreply@example.com']);
```

| Parameter | Type | Meaning |
| --- | --- | --- |
| `id` | `string` | The identifier of the entry to resolve with `get()`. |

This is how a parameter without a class type, such as the `array` above, gets a value from the container without a
[contextual binding](contextual-bindings.md). When the container has no entry for the identifier, the parameter gets
its default value or `null` if it has one, and resolution fails with a `ResolutionException::missingDependency()`
otherwise.

A value given to `make()` or `call()` and a contextual binding for the class both take precedence over the attribute,
so the class's own declaration can still be overridden from outside. See
[how each parameter is filled in](autowiring.md#how-each-parameter-is-filled-in) for the full order.
