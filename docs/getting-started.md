---
id: getting-started
title: Getting started
sidebar_position: 3
description: Register instances, bindings, and singletons, and resolve entries from the container.
---

## Create a container

```php
use Dirthara\Container\Container;

$container = new Container();
```

A new container already holds itself under `Container::class`, `Psr\Container\ContainerInterface`, and each interface
in [depend on the interfaces](#depend-on-the-interfaces), so a class that needs the container can ask for it in its
constructor.

The registration methods, `bind()`, `singleton()`, `scoped()`, and `instance()`, return the container, so calls can be
chained.

## Depend on the interfaces

The container implements one interface for each part of its work:

| Interface | Methods | Use it for |
| --- | --- | --- |
| `Psr\Container\ContainerInterface` | `get()`, `has()` | Resolving entries. |
| `Dirthara\Container\Contract\ContainerConfigurator` | `bind()`, `singleton()`, `scoped()`, `instance()`, `when()`, `extend()`, `lazy()`, `tag()`, `tagByAttribute()` | Registering entries, [contextual bindings](contextual-bindings.md), [decorators](decorators.md), [lazy services](lazy.md), and [tags](tags.md). |
| `Dirthara\Container\Contract\InstanceFactory` | `make()` | [Building a new instance](making-and-calling.md#make-a-new-instance) with some constructor parameters given. |
| `Dirthara\Container\Contract\Invoker` | `call()` | [Calling a method or a closure](making-and-calling.md#call-a-method-or-a-closure) with its parameters filled in. |
| `Dirthara\Container\Contract\TagResolver` | `tagged()` | Resolving every entry with a [tag](tags.md). |
| `Dirthara\Container\Contract\Scope` | `scopedInstance()`, `resetScope()` | Providing per-request values and [ending a scope](#scope-an-entry). |

Type against the interface that matches what the code does, rather than against `Container`:

```php
use Dirthara\Container\Contract\ContainerConfigurator;

final readonly class MailServices
{
    public function register(ContainerConfigurator $configurator): void
    {
        $configurator
            ->singleton(TransportInterface::class, SmtpTransport::class)
            ->when(Mailer::class)
            ->needs('$retries')
            ->giveValue(5);
    }
}
```

Through `ContainerConfigurator`, a contextual binding is typed against interfaces in the same namespace as well:
`when()` returns a `Contract\ContextualBindingBuilder`, its `needs()` a `Contract\PendingContextualBinding`, and
`give()` and `giveValue()` a `ContainerConfigurator` again, as do the registration methods. A chain of registrations
never reaches a concrete class.

:::note
None of the interfaces extends another. Code that both registers and resolves entries asks for both.
:::

## Resolve an entry

`get()` returns the entry for an identifier, and `has()` says whether the container can find one.

```php
if ($container->has(Mailer::class)) {
    $mailer = $container->get(Mailer::class);
}
```

An identifier is any string. A class or interface name is the usual choice, because autowiring looks entries up by the
type of a constructor parameter, but a name such as `'config'` works as well.

`has()` returns `true` for a registered instance, a binding, and any class the container could autowire. It does not
build anything, so `get()` can still fail for an entry `has()` reports: a binding can point at something that does not
exist, and a class can have a dependency the container cannot resolve. [Error handling](error-handling.md) lists what
`get()` throws in each case.

:::tip
When the identifier is a class or interface name, static analysers read the return type of `get()` as that class, so
`$container->get(Mailer::class)` is typed as a `Mailer`.
:::

## Register an instance

`instance()` stores a value that the container returns as it is, every time. The value can be anything, including an
array or `null`.

```php
$container->instance('config', ['debug' => true]);
$container->instance(Clock::class, new SystemClock());
```

## Bind an entry

`bind()` tells the container how to build an entry. The container builds it again on every `get()`.

```php
use Psr\Container\ContainerInterface;

// A class or interface resolved as another entry, which can itself be bound or autowired.
$container->bind(LoggerInterface::class, FileLogger::class);

// A factory, called with the container and the parameters given to make(), which get() leaves empty.
$container->bind(Connection::class, static fn(ContainerInterface $container): Connection => new Connection(
    $container->get('config')['dsn'],
));

// A class bound to itself, autowired. This only changes behaviour together with singleton().
$container->bind(Mailer::class);
```

| Parameter | Type | Default | Meaning |
| --- | --- | --- | --- |
| `abstract` | `string` | | The identifier to register. |
| `concrete` | `string`, `Closure`, or `null` | `null` | Another entry or class to resolve instead, a factory that receives the container and an `array<string, mixed>` of parameters, or `null` to autowire `abstract` itself. |

A factory can declare only the container, as above, and ignore the parameters. See
[making new instances](making-and-calling.md) for where they come from.

## Share an entry

`singleton()` takes the same arguments as `bind()`, but builds the entry once, on the first `get()`, and returns that
same value afterwards.

```php
$container->singleton(Connection::class, static fn(ContainerInterface $container): Connection => new Connection(
    $container->get('config')['dsn'],
));

$container->get(Connection::class) === $container->get(Connection::class); // true
```

Only the entry that was registered as a singleton is shared. After
`$container->singleton(LoggerInterface::class, FileLogger::class)`, every `LoggerInterface` is the same object, but a
`get(FileLogger::class)` still builds a new one, because `FileLogger` itself is not shared.

## Scope an entry

A long-running process, such as a worker that handles many requests, needs entries that are shared while one request is
handled and built again for the next. `scoped()` takes the same arguments as `bind()` and shares the entry like
`singleton()` does, until `resetScope()` ends the scope:

```php
$container->scoped(UnitOfWork::class);

$container->get(UnitOfWork::class) === $container->get(UnitOfWork::class); // true

$container->resetScope();
$container->get(UnitOfWork::class); // a new UnitOfWork
```

A value that is created outside the container, such as the request itself, goes in with `scopedInstance()`:

```php
use Dirthara\Container\Contract\Scope;

function handle(Scope $scope, Request $request): void
{
    $scope->scopedInstance(Request::class, $request);

    try {
        // handle the request
    } finally {
        $scope->resetScope();
    }
}
```

`resetScope()` forgets every scoped instance and every value a `scoped()` entry built. Singletons, instances, and
bindings stay as they are. A scoped instance takes precedence over whatever is registered for the identifier, and does
not replace it: after `resetScope()`, a binding or an instance for the same identifier applies again.

:::caution
A singleton that depends on a scoped entry receives it once, when the singleton is built, and keeps it after the scope
ends. Make everything that depends on a scoped entry scoped as well, or give it the container and resolve the entry
when it is needed.
:::

## Lock the container

Once an application has registered everything it needs, `lock()` stops the container from accepting more. After it,
every method of `ContainerConfigurator` throws a `ContainerLockedException`, and so do `give()` and `giveValue()` on a
contextual binding that was started before the lock.

```php
$container->singleton(Connection::class, PdoConnection::class);
$container->scoped(UnitOfWork::class);
$container->lock();

$container->bind(Clock::class, SystemClock::class); // throws a ContainerLockedException
```

A registration made while the application is already handling requests would change entries that other classes have
already received, so the lock turns that mistake into an error at the call that made it.

Resolving is unaffected, and so is the scope: `scopedInstance()` and `resetScope()` keep working, because a locked
container still has to take in each request and forget it afterwards. Locking twice does nothing, and there is no way to
unlock.

:::note
`lock()` is only on `Container`, not on any of the interfaces: the code that creates the container decides when
registration is over, and code that received a `ContainerConfigurator` cannot close it for everyone else.
:::

## Replace an entry

Registering an identifier again replaces what was there. `bind()`, `singleton()`, and `scoped()` discard a registered
instance, a scoped instance, and any value the entry already built, and `instance()` discards a binding and a scoped
instance.

```php
$container->singleton(Clock::class, SystemClock::class);
$container->get(Clock::class);  // builds and keeps a SystemClock

$container->instance(Clock::class, new FrozenClock('2026-01-01'));
$container->get(Clock::class);  // the FrozenClock from now on
```

This also applies to the container's own entries: binding `ContainerInterface::class` to something else changes what
autowired classes receive when they ask for it.
