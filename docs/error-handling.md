---
id: error-handling
title: Error handling
sidebar_position: 10
description: The exception interface, the exception classes, what get() throws when, diagnostic context, and logging.
---

## Catching exceptions

Every exception the package throws implements `Dirthara\Container\Exception\ContainerException`, which extends the
PSR-11 `ContainerExceptionInterface`, so one `catch` covers them all. Each also extends the SPL exception that
describes the failure: an invalid registration throws an `InvalidArgumentException`, and a failure to resolve an entry
or a registration on a locked container throws a `RuntimeException`.

```php
use Dirthara\Container\Exception\ContainerException;
use Dirthara\Container\Exception\EntryNotFoundException;

try {
    $mailer = $container->get(Mailer::class);
} catch (EntryNotFoundException $exception) {
    // nothing is registered for Mailer, and it cannot be autowired
} catch (ContainerException $exception) {
    // Mailer exists but could not be built
}
```

| Exception | Extends | Also implements | Thrown when |
| --- | --- | --- | --- |
| `EntryNotFoundException` | `RuntimeException` | `NotFoundExceptionInterface` | The requested identifier is not registered and is not an instantiable class. |
| `ResolutionException` | `RuntimeException` | | The entry exists but cannot be built: a binding points at nothing, a parameter cannot be filled in, or a factory failed. |
| `CircularDependencyException` | `RuntimeException` | | Resolving the entry requires the entry itself. |
| `ContainerLockedException` | `RuntimeException` | | A registration method is called after [`lock()`](getting-started.md#lock-the-container). |
| `InvalidContextualBindingException` | `InvalidArgumentException` | | `when()` names something that is not a class, or `needs()` something that is neither a class or interface nor a parameter name. |
| `InvalidAttributeException` | `InvalidArgumentException` | | An [attribute](attributes.md) on the type being resolved is used incorrectly. |
| `InvalidCallableException` | `InvalidArgumentException` | | `call()` is given something that is not a closure, a function, a public method, or an invokable class. |

All exception classes are `final`; catch them by class or by `ContainerException`.

## Not found means the requested entry

`get()` throws `EntryNotFoundException` only when the identifier you asked for has no entry, as PSR-11 requires. When
the entry exists but something it depends on does not, `get()` throws a `ResolutionException` instead, so a caller that
catches `NotFoundExceptionInterface` to fall back to a default never mistakes a broken dependency for a missing entry.

| Situation | `get()` throws |
| --- | --- |
| `get(Mailer::class)` when `Mailer` is an unbound interface | `EntryNotFoundException` |
| `get(Mailer::class)` when `Mailer` needs an unbound `LoggerInterface` | `ResolutionException::missingDependency()` |
| `get(Mailer::class)` when `Mailer` needs an `int` without a default | `ResolutionException::unresolvableParameter()` |
| `get(Mailer::class)` after `bind(Mailer::class, 'missing')` | `ResolutionException::unresolvableBinding()` |
| `get(Mailer::class)` when its factory calls `get('missing')` | `ResolutionException::factoryFailed()`, wrapping the `EntryNotFoundException` |
| `get(Mailer::class)` when a contextual binding for it gives `'missing'` | `ResolutionException::unresolvableContextualBinding()` |
| `get(Mailer::class)` when a contextual factory for it throws | `ResolutionException::contextualFactoryFailed()`, wrapping what it threw |

## Factories that throw

When a factory registered with `bind()`, `singleton()`, or a contextual binding's `give()` throws, the container wraps
the exception in a `ResolutionException` and passes the original as its previous exception, with three exceptions to
that rule:

- This package's own exceptions pass through unchanged, so a `CircularDependencyException` from inside a factory keeps
  its type. The one case that is wrapped is an `EntryNotFoundException`, which would otherwise claim that the factory's
  own entry is missing.
- A `LogicException` passes through unchanged. It signals a bug in the factory rather than a failure to resolve.
- An `Error`, such as a `TypeError`, passes through unchanged for the same reason.

```php
use Dirthara\Container\Exception\ResolutionException;

try {
    $connection = $container->get(Connection::class);
} catch (ResolutionException $exception) {
    $cause = $exception->getPrevious(); // the PDOException the factory threw
}
```

The message of the wrapping exception names the class of the original, not its message: a factory's failure can hold
anything the factory saw, such as a connection string with a password. Read the original through `getPrevious()`.

## Context

Every exception carries diagnostic metadata in its public, read-only `context` property, an `array<string, mixed>`.

| Factory | Context keys |
| --- | --- |
| `EntryNotFoundException::forId()` | `id` |
| `CircularDependencyException::forEntry()` | `id`, and `chain`: the list of entries from the one requested to the one that repeats |
| `ResolutionException::missingDependency()` | `target`, `parameter`, and `dependency`: the type the container had no entry for |
| `ResolutionException::unresolvableParameter()` | `target`, `parameter` |
| `ResolutionException::unknownParameters()` | `target`, and `parameters`: the given names the target has no parameter for |
| `ResolutionException::notBuildable()` | `id` |
| `ResolutionException::unresolvableBinding()` | `id`, `concrete` |
| `ResolutionException::factoryFailed()` | `id`, and `exceptionClass`: the class of the exception the factory threw |
| `ResolutionException::unresolvableContextualBinding()` | `class`, `need`, `concrete` |
| `ResolutionException::contextualFactoryFailed()` | `class`, `need`, and `exceptionClass` |
| `InvalidContextualBindingException::notAClass()` | `class` |
| `InvalidContextualBindingException::invalidNeed()` | `need` |
| `InvalidCallableException::notCallable()` | `callable` |
| `InvalidAttributeException::notASubtype()` | `class`, and `concrete`: the class its `#[BoundTo]` names |
| `InvalidAttributeException::conflictingLifetimes()` | `class` |
| `ContainerLockedException::cannotRegister()` | `id` |
| `ContainerLockedException::cannotAddContextualBinding()` | `classes`: the classes given to `when()` |

A `target` is what the parameters belong to: a class for a constructor, `Class::method` for a method, `Closure` for a
closure, or the name of a function.

Code that catches an exception and knows more can add to it with `addContext()`, which merges the given array into the
context, replacing matching keys, and returns the exception:

```php
throw $exception->addContext(['route' => 'users.store']);
```

Every exception can also be built directly with the constructor, as the named factories do:

| Constructor parameter | Type | Default | Meaning |
| --- | --- | --- | --- |
| `message` | `string` | `''` | The exception message. |
| `code` | `int` | `0` | The exception code. |
| `previous` | `?Throwable` | `null` | The exception this one wraps. |
| `context` | `array<string, mixed>` | `[]` | The diagnostic context. |

When a message quotes an identifier, class, or parameter name, control characters in it are escaped, so a line break
shows as `\n` and cannot forge a line in a log. The context holds the value as it was given.

## Logging

Exceptions do not log themselves or depend on a logger. An application handler can pass the context to a PSR-3 logger.
Add the caught exception under the `exception` key after reading the context:

```php
use Dirthara\Container\Exception\ContainerException;

try {
    $mailer = $container->get(Mailer::class);
} catch (ContainerException $exception) {
    $context = $exception->context;
    $context['exception'] = $exception;

    $logger->error($exception->getMessage(), $context);
}
```
