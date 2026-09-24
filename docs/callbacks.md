---
id: callbacks
title: Resolution callbacks
sidebar_position: 11
description: Run a callback on every object of a type the container builds, with afterResolving().
---

`afterResolving()` registers a callback that runs on every object of a type the container builds, whatever it is
registered as. It is how a framework sets up objects by what they are, such as giving a logger to everything that
implements `LoggerAwareInterface`:

```php
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerAwareInterface;

$container->afterResolving(
    LoggerAwareInterface::class,
    static function (LoggerAwareInterface $service, ContainerInterface $container): void {
        $service->setLogger($container->get(LoggerInterface::class));
    },
);
```

| Parameter | Type | Meaning |
| --- | --- | --- |
| `type` | `string` | A class or interface. The callback runs for every built object that is an instance of it. |
| `callback` | `Closure` | Called with the object and the container. What it returns is ignored. |

## Which objects a callback sees

A callback runs once for each object the container creates, right after creating it:

- an object the container builds by calling its constructor, including a [decorator](decorators.md) built for
  `#[DecoratedBy]`, on every `get()` for a binding and on every `make()`, and once for a singleton;
- an object a factory returns, including a contextual binding's factory;
- a [lazy object](lazy.md), when it is initialized rather than when it is handed out, so that the callback does not
  initialize it.

It does not run for an instance registered with `instance()` or `scopedInstance()`, for a value an
[extender](decorators.md#extend-an-entry) returns, or for a value that is not an object. An entry bound to another,
such as `bind(LoggerInterface::class, FileLogger::class)`, runs the callbacks once, for the `FileLogger` that was built.

Callbacks run in the order they were registered. An exception a callback throws follows the same rules as
[a factory that throws](error-handling.md#factories-that-throw), and is wrapped in a
`ResolutionException::callbackFailed()`.

After [`lock()`](getting-started.md#lock-the-container), `afterResolving()` throws a
`ContainerLockedException::cannotConfigure()`.
