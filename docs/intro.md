---
id: intro
title: Dirthara Container
sidebar_position: 1
description: A PSR-11 dependency injection container with autowiring for the Dirthara framework.
---

Dirthara Container is a dependency injection container that implements the
[PSR-11](https://www.php-fig.org/psr/psr-11/) `ContainerInterface`. Any library that accepts a PSR-11 container can use
it, and code written against the interface can swap it for another implementation. Registering entries has an
interface of its own, `ContainerConfigurator`; see [depending on the interfaces](getting-started.md#depend-on-the-interfaces).

The container resolves an entry from one of three sources, checked in this order:

| Source | Registered with | Resolves to |
| --- | --- | --- |
| Instance | `instance()` | The value that was registered, every time. |
| Binding | `bind()` or `singleton()` | A factory's result, another entry, or a class, built on each request or once. |
| Autowiring | Nothing | Any instantiable class, built with its constructor dependencies resolved from the container. |

```php
use Dirthara\Container\Container;

$container = new Container();
$container->bind(LoggerInterface::class, FileLogger::class);

$mailer = $container->get(Mailer::class);
```

`Mailer` is not registered, so the container autowires it: it reads the constructor, resolves a `LoggerInterface`
argument to a new `FileLogger`, and builds the `Mailer`.

[Contextual bindings](contextual-bindings.md) change what one class receives without changing it for the rest:
`$container->when(Mailer::class)->needs(LoggerInterface::class)->give(MailLogger::class)`.

`$container->make(Report::class, ['title' => 'Sales'])` builds a new instance with some constructor parameters given,
and `$container->call([$controller, 'show'])` calls a method with its parameters filled in; see
[making and calling](making-and-calling.md).

Start with [installation](installation.md) and the [getting started guide](getting-started.md), then read how
[autowiring](autowiring.md) fills in constructor parameters and how the container reports
[errors](error-handling.md).
