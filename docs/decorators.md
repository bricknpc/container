---
id: decorators
title: Decorators
sidebar_position: 9
description: Wrap or replace what the container builds for an entry with extend() and the #[DecoratedBy] attribute.
---

A decorator wraps an entry in another object that adds behaviour, such as caching or logging, without changing how the
entry itself is registered. A package can decorate a service another package registered, and the decoration survives
when that registration changes.

## Extend an entry

`extend()` registers a closure that receives what the container built for an identifier and returns what the container
hands out instead:

```php
use Psr\Container\ContainerInterface;

$container->extend(
    LoggerInterface::class,
    static fn(LoggerInterface $logger, ContainerInterface $container): LoggerInterface => new ContextLogger(
        $logger,
        $container->get(RequestContext::class),
    ),
);
```

| Parameter | Type | Meaning |
| --- | --- | --- |
| `abstract` | `string` | The identifier whose value to extend. It does not have to be registered yet. |
| `extender` | `Closure` | Called with the built value and the container, and returns the value to use. For an identifier that names a class or interface, that value has to be an instance of it. |

- Extenders run in the order they were registered, each receiving what the one before it returned.
- They run every time the container builds the entry: on every `get()` for a binding, once for a singleton, once per
  scope for a scoped entry, and on every `make()`.
- They apply however the entry is built: from a binding, from an attribute, or by autowiring. For an entry bound to
  another, such as `bind(LoggerInterface::class, FileLogger::class)`, extenders of `FileLogger` run when it is built,
  and those of `LoggerInterface` run on the result.
- An exception an extender throws follows the same rules as [a factory that throws](error-handling.md#factories-that-throw),
  and is wrapped in a `ResolutionException::extenderFailed()`.

:::caution
An entry registered with `instance()` or `scopedInstance()` is returned as it was registered, so its extenders do not
run. Decorate the value before registering it.
:::

## Declare decorators with an attribute

`#[DecoratedBy]` on an interface or a class names a class that decorates it, and can be repeated:

```php
use Dirthara\Container\Attribute\BoundTo;
use Dirthara\Container\Attribute\DecoratedBy;

#[BoundTo(DatabaseUserRepository::class)]
#[DecoratedBy(CachingUserRepository::class)]
#[DecoratedBy(LoggingUserRepository::class)]
interface UserRepository {}

final readonly class CachingUserRepository implements UserRepository
{
    public function __construct(
        private UserRepository $inner,
        private CacheInterface $cache,
    ) {}
}

$container->get(UserRepository::class); // LoggingUserRepository(CachingUserRepository(DatabaseUserRepository))
```

The first decorator wraps the entry, and each next one wraps the decorator before it. The container builds each
decorator with [`make()`](making-and-calling.md), passing what it decorates to the constructor parameter whose type is
the decorated type, and autowiring the rest.

| Parameter | Type | Meaning |
| --- | --- | --- |
| `decorator` | `class-string` | An instantiable class that extends or implements the type, and has a constructor parameter of that type. |

Attribute decorators run before the extenders of the same identifier, so an extender receives the decorated value. They
follow the same rules as extenders otherwise: they apply every time the entry is built, and not to an instance.

A decorator that is not an instantiable class extending or implementing the type throws an
`InvalidAttributeException::notADecorator()`, and one without a constructor parameter of the type an
`InvalidAttributeException::decoratorWithoutParameter()`, when the type is resolved.
