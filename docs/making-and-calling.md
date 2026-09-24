---
id: making-and-calling
title: Making instances and calling methods
sidebar_label: Making and calling
sidebar_position: 6
description: Build new instances with make(), pass constructor parameters, and call methods and closures with call().
---

`make()` is also available through the `Dirthara\Container\Contract\InstanceFactory` interface, and `call()` through
`Dirthara\Container\Contract\Invoker`, for code that should not depend on `Container`; see
[depend on the interfaces](getting-started.md#depend-on-the-interfaces).

## Make a new instance

`make()` builds a new instance every time it is called, for any class the container can build, whether or not it is
registered.

```php
$first = $container->make(ReportBuilder::class);
$second = $container->make(ReportBuilder::class);

$first === $second; // false
```

Where `get()` returns what the container already holds, `make()` never does:

| Registered as | `get()` returns | `make()` returns |
| --- | --- | --- |
| Nothing, an instantiable class | A new instance | A new instance |
| `bind()` | A new instance | A new instance |
| `singleton()` | The shared instance | A new instance, which is not kept and does not replace the shared one |
| `scoped()` | The instance shared in the current scope | A new instance, which is not kept and does not replace the scoped one |
| `scopedInstance()` | The registered value, until the scope is reset | A new instance from the identifier's binding or class, or a `ResolutionException` when it has neither |
| `instance()`, with a class name | The registered value | A new instance of the class |
| `instance()`, with any other identifier | The registered value | A `ResolutionException`, because there is nothing to build |

A binding is followed: after `bind(ReportBuilder::class, PdfReportBuilder::class)`, `make(ReportBuilder::class)` builds
a new `PdfReportBuilder`, even when `PdfReportBuilder` is itself a singleton. A factory binding is called again.

Only the instance itself is new. Its dependencies are resolved as `get()` resolves them, so a singleton dependency is
still the shared one.

:::tip
Like `get()`, `make(ReportBuilder::class)` is typed as returning a `ReportBuilder` for static analysers.
:::

## Pass constructor parameters

The second argument to `make()` gives constructor parameters by name. The container resolves the rest as usual.

```php
final readonly class ReportBuilder
{
    public function __construct(
        public LoggerInterface $logger,
        public string $title,
        public int $pageSize = 50,
    ) {}
}

$builder = $container->make(ReportBuilder::class, ['title' => 'Sales', 'pageSize' => 20]);
```

`$logger` comes from the container, and `$title` and `$pageSize` from the array. A given parameter takes precedence over
everything else, including a [contextual binding](contextual-bindings.md) for it.

| Parameter | Type | Default | Meaning |
| --- | --- | --- | --- |
| `id` | `string` | | The class or entry to make. |
| `parameters` | `array<string, mixed>` | `[]` | Values for constructor parameters, keyed by parameter name without the `$`. |

- The parameters apply to the class being made, not to its dependencies.
- A name the constructor does not have throws a `ResolutionException::unknownParameters()`, so a typo fails instead of
  being ignored.
- A value for a variadic parameter is spread: an array fills in one argument per element, and anything else is a single
  argument.
- When the entry is bound to a factory, the factory receives the parameters as its second argument, and decides what to
  do with them. A factory called through `get()` receives an empty array.

```php
$container->bind(Report::class, static fn(ContainerInterface $container, array $parameters): Report => new Report(
    $parameters['title'] ?? 'Untitled',
));

$container->make(Report::class, ['title' => 'Sales']);
```

A factory receives the container as a `ContainerInterface`, which has no `make()`. To build another instance with
parameters from inside a factory, get the `InstanceFactory` from the container:

```php
use Dirthara\Container\Contract\InstanceFactory;

$container->bind(SalesReport::class, static function (ContainerInterface $container): SalesReport {
    $factory = $container->get(InstanceFactory::class);
    assert($factory instanceof InstanceFactory);

    return new SalesReport($factory->make(Report::class, ['title' => 'Sales']));
});
```

`ContainerInterface::get()` is declared to return `mixed`, so the `assert()` tells a static analyser what it holds.

## Call a method or a closure

`call()` calls something and fills in its parameters by the same rules as a constructor's; see
[how each parameter is filled in](autowiring.md#how-each-parameter-is-filled-in). Like `make()`, it takes parameters by
name as its second argument, which take precedence.

```php
$report = $container->call([$controller, 'show'], ['id' => 7]);
```

`call()` accepts:

| Callable | Example | Calls |
| --- | --- | --- |
| A closure | `static fn(Mailer $mailer): bool => $mailer->ping()` | The closure. |
| An object and a method | `[$controller, 'show']` | The method on that object. |
| A class and a method | `[ReportController::class, 'show']` or `'ReportController::show'` | A static method directly, and any other method on the instance the container resolves with `get()`. |
| An invokable object | `$handler` | Its `__invoke()` method. |
| An invokable class | `SendReport::class` | `__invoke()` on the instance the container resolves with `get()`. |
| A function name | `'array_sum'` | The function. |

`call()` returns what the callable returns. An exception the callable throws is not wrapped; only a failure to resolve a
parameter throws one of this package's exceptions.

- A method has to be public. A private or protected method, a method that does not exist, and a class without
  `__invoke()` throw an `InvalidCallableException::notCallable()`.
- A parameter that cannot be filled in throws a `ResolutionException` whose `target` names the method, such as
  `ReportController::show`, or `Closure` for a closure.
- [Contextual bindings](contextual-bindings.md) for the class of a method apply to its parameters, so
  `when(ReportController::class)` changes what its methods receive through `call()`. They do not apply to a closure or
  a function.
