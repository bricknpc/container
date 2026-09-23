---
id: contextual-bindings
title: Contextual bindings
sidebar_position: 5
description: Give one class a different dependency, or a value for a parameter, without changing it for the rest.
---

## Give one class something else

A binding with `bind()` applies everywhere. A contextual binding applies only while the container builds the classes it
names, and says what to give them when their constructor needs something.

```php
$container->bind(LoggerInterface::class, FileLogger::class);

$container->when(Mailer::class)
    ->needs(LoggerInterface::class)
    ->give(MailLogger::class);
```

A `Mailer` receives a `MailLogger`. Every other class that asks for a `LoggerInterface` still receives a `FileLogger`,
and `$container->get(LoggerInterface::class)` is unaffected too.

`give()` and `giveValue()` return the container, so a contextual binding can be chained with other registrations.

## What a class can need

`needs()` takes one of two things:

| Need | Example | Matches |
| --- | --- | --- |
| A class or interface | `needs(LoggerInterface::class)` | Every constructor parameter with exactly that type. |
| A parameter name, prefixed with `$` | `needs('$retries')` | The constructor parameter with that name, whatever its type. |

A parameter name is how a parameter without a class type, such as an `int` or a `string`, gets a value from the
container. It also singles out one of two parameters that share a type:

```php
final readonly class Mailer
{
    public function __construct(
        public TransportInterface $primary,
        public TransportInterface $fallback,
        public int $retries = 3,
    ) {}
}

$container->when(Mailer::class)->needs(TransportInterface::class)->give(SmtpTransport::class);
$container->when(Mailer::class)->needs('$fallback')->give(SendmailTransport::class);
$container->when(Mailer::class)->needs('$retries')->giveValue(5);
```

`$primary` is an `SmtpTransport`, `$fallback` a `SendmailTransport`, and `$retries` is `5`.

:::note
When a binding for the parameter's name and one for its type both match, the name wins, because it is more specific.
:::

A type binding matches the type as it is written in the constructor. A `?TransportInterface` parameter matches a binding
for `TransportInterface`, but a parameter with a union or intersection type only matches by name.

`needs()` throws an `InvalidContextualBindingException` for anything that is neither an existing class or interface
nor a valid parameter name, so a forgotten `$`, as in `needs('retries')`, fails when it is registered instead of never
matching.

## What to give

| Method | Gives |
| --- | --- |
| `give(string $entry)` | The entry, resolved from the container with `get()` each time a parameter needs it. It can be a class, an interface, or any other identifier, and follows that entry's own bindings and singletons. |
| `give(Closure $factory)` | The result of the factory, called with the container each time a parameter needs it. |
| `giveValue(mixed $value)` | The value as it is: a string, a number, an array, `null`, or an object that already exists. |

`give()` always treats a string as an identifier to resolve. To give a parameter a literal string, use `giveValue()`:

```php
$container->when(FileLogger::class)->needs('$path')->giveValue('/var/log/app.log');
```

A factory and a given entry are resolved again for each parameter they fill in, so two parameters of the same type get
two objects, unless the entry is a singleton:

```php
$container->singleton('mail.transport', SmtpTransport::class);
$container->when(Mailer::class)->needs(TransportInterface::class)->give('mail.transport');
```

Both `$primary` and `$fallback` now receive the same `SmtpTransport`.

## Which classes a contextual binding applies to

`when()` takes one class or a list of classes, and throws an `InvalidContextualBindingException` for anything that is
not an existing class. That includes an interface, which the container never builds: name the class it is bound to.

```php
$container->when([Mailer::class, Newsletter::class])
    ->needs(LoggerInterface::class)
    ->give(MailLogger::class);
```

A contextual binding applies whenever the container builds one of those classes by reading its constructor: when the
class is autowired, bound to itself, or the target of another binding, such as `bind('mailer', Mailer::class)`. It
does not apply:

- to a class built by a factory, because the factory calls the constructor itself, or
- to a subclass of a class it names, unless the subclass is named as well.

A contextual binding takes precedence over the container's entries and over the parameter's default value, including
when it gives `null` with `giveValue(null)`.

Registering the same class and need again replaces the earlier contextual binding. A singleton that was already built
keeps what it received.

## Errors

`when()` and `needs()` are checked when the binding is registered, but what `give()` names is only checked when it is
used:

- `give('missing')` for an entry the container does not have throws a
  `ResolutionException::unresolvableContextualBinding()` when the class is built.
- A factory that throws is wrapped in a `ResolutionException::contextualFactoryFailed()`, following the same rules as
  [factories registered with `bind()`](error-handling.md#factories-that-throw).
- Giving the class that is being built, directly or through its dependencies, throws a `CircularDependencyException`.
