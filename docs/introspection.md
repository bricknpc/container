---
id: introspection
title: Introspection
sidebar_position: 12
description: List what is registered, and describe where an entry comes from and how it is built.
---

The `Dirthara\Container\Contract\Inspector` interface answers what the container holds and why it resolves an entry the
way it does, such as for a debug command or an error page. Nothing it does builds an entry.

## List the registered entries

`registered()` returns the identifiers registered with `bind()`, `singleton()`, `scoped()`, `instance()`, and
`scopedInstance()`, sorted, including the container's own entries such as `Psr\Container\ContainerInterface`. An entry
that only comes from an attribute or from autowiring is not registered, so it is not listed.

```php
foreach ($container->registered() as $id) {
    echo $id, PHP_EOL;
}
```

## Describe an entry

`describe()` returns an `EntryDescription` of how the container would resolve an identifier, or `null` when it has no
entry for it:

```php
$description = $container->describe(UserRepository::class);

$description->source;     // EntrySource::Attribute
$description->lifetime;   // Lifetime::Singleton
$description->concrete;   // DatabaseUserRepository::class
$description->decorators; // [CachingUserRepository::class]
```

| Property | Type | Meaning |
| --- | --- | --- |
| `id` | `string` | The identifier that was described. |
| `source` | `EntrySource` | Where the entry comes from; see below. |
| `lifetime` | `?Lifetime` | `Lifetime::Transient`, `Lifetime::Singleton`, or `Lifetime::Scoped`, and `null` for an instance or a delegate. |
| `concrete` | `?string` | The entry or class it resolves to, and `null` for a factory, an instance, or a delegate. |
| `factory` | `bool` | Whether a factory builds it. |
| `lazy` | `bool` | Whether the identifier is a class the container builds [lazily](lazy.md). |
| `tags` | `list<string>` | The [tags](tags.md) it has, in the order they were first added. |
| `decorators` | `list<class-string>` | The decorators its [`#[DecoratedBy]`](decorators.md#declare-decorators-with-an-attribute) attributes name. |
| `extenders` | `int` | How many [extenders](decorators.md#extend-an-entry) are registered for it. |

`source` follows the order in which the container looks an identifier up:

| `EntrySource` | The entry is |
| --- | --- |
| `ScopedInstance` | A value registered with `scopedInstance()` for the current scope. |
| `Instance` | A value registered with `instance()`. |
| `Binding` | Registered with `bind()`, `singleton()`, or `scoped()`. |
| `Delegate` | Found in a [delegate container](delegates.md). |
| `Attribute` | Declared with `#[BoundTo]`, `#[Singleton]`, or `#[Scoped]` on the type. |
| `Autowired` | An instantiable class nothing is registered for. |

`describe()` reads a type's attributes, so an attribute used incorrectly throws the same `InvalidAttributeException` as
resolving the type would. Both enums are string-backed, with values such as `'scoped-instance'` and `'singleton'`, for
output that has to be text.
