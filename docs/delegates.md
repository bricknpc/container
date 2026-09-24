---
id: delegates
title: Delegate containers
sidebar_position: 13
description: Resolve entries the container does not have from other PSR-11 containers.
---

`delegate()` adds another [PSR-11](https://www.php-fig.org/psr/psr-11/) container that the container asks for entries
it does not have itself. It is how a module that brings its own container, or a legacy container during a migration,
shares its entries without registering each of them again.

```php
$container->delegate($legacyContainer);

$container->get(LegacyMailer::class); // from $legacyContainer, when nothing is registered for it here
```

## When a delegate is asked

The container asks the delegates after its own registrations, and before attributes and autowiring:

1. A scoped instance, an instance, or a binding registered for the identifier.
2. The first delegate, in the order they were added, whose `has()` returns `true`.
3. An [attribute](attributes.md) on the type, then autowiring.

A delegate is asked before autowiring because it can hold a configured instance of a class that autowiring would build
without its configuration. `has()` returns `true` for an identifier a delegate has.

A dependency of an autowired class is looked up the same way, so a class this container builds can receive an entry
from a delegate.

## What a delegate's entry is

What a delegate returns is its own value, which this container does not change or keep:

- it is not stored, so every `get()` asks the delegate again, which decides whether it is shared;
- [extenders](decorators.md#extend-an-entry), `#[DecoratedBy]` decorators, and [callbacks](callbacks.md) do not run
  for it, because this container did not build it;
- `make()` never asks a delegate, because a PSR-11 container cannot build a new instance. For an identifier only a
  delegate has, it throws a `ResolutionException::notBuildable()`, and for a class it builds a new instance itself.

Only its type is checked: for an identifier that names a class or interface, a value of another type throws a
`ResolutionException::incompatibleType()`, as it would for [any other entry](getting-started.md#entries-named-after-a-type).

An exception the delegate throws while resolving the entry follows the same rules as
[a factory that throws](error-handling.md#factories-that-throw), and is wrapped in a
`ResolutionException::delegateFailed()` that names the delegate's class.

:::note
Two containers can delegate to each other. While the container asks its delegates for an identifier, a delegate that
asks back for the same identifier is told this container does not have it, so the lookup ends instead of repeating.
:::

After [`lock()`](getting-started.md#lock-the-container), `delegate()` throws a
`ContainerLockedException::cannotConfigure()`.
