---
id: attributes
title: Attributes
sidebar_position: 7
description: Declare a type's default implementation with an attribute instead of registering it.
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
