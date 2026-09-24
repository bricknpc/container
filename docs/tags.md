---
id: tags
title: Tags
sidebar_position: 8
description: Group entries under a tag, and resolve or inject every entry with that tag.
---

A tag groups entries that belong together, such as every middleware or every console command, so that the code that
uses them asks for the group instead of listing each entry.

## Tag entries

`tag()` adds one identifier or a list of identifiers to a tag, and `tagged()` returns every entry with the tag:

```php
$container->tag([AuthMiddleware::class, CsrfMiddleware::class], 'middleware');
$container->tag(SessionMiddleware::class, 'middleware');

foreach ($container->tagged('middleware') as $id => $middleware) {
    // $id is the identifier, $middleware the entry resolved with get()
}
```

| Parameter | Type | Meaning |
| --- | --- | --- |
| `abstracts` | `string` or `list<string>` | The identifiers to tag. They do not have to be registered yet. |
| `tag` | `string` | The name of the tag. |

- The entries come in the order they were tagged, keyed by their identifier. Tagging an identifier again does not add
  it twice.
- Each entry is resolved with `get()` only when the loop reaches it, so a loop that stops early does not build the rest,
  and a missing entry fails when it is reached. Every entry follows its own registration: a singleton is shared, and a
  binding is built again.
- The result can be iterated more than once, and resolves the entries again each time. It reads the tag when the
  iteration starts, so entries tagged after `tagged()` was called are included.
- A tag nothing was added to returns no entries.

`tagged()` is also on the `Dirthara\Container\Contract\TagResolver` interface.

:::note
`iterator_to_array()` turns an identifier that looks like an integer, such as `'123'`, into an integer key. Iterate with
`foreach` to keep every identifier a string.
:::

## Tag classes with an attribute

`#[Tag]` on a class declares the tags it belongs to, and can be repeated. The container cannot find classes on its own,
so `tagByAttribute()` reads the attribute from the classes it is given, such as the classes an application discovered in
its source directory:

```php
use Dirthara\Container\Attribute\Tag;

#[Tag('middleware')]
#[Tag('http')]
final class AuthMiddleware {}

$container->tagByAttribute(AuthMiddleware::class, CsrfMiddleware::class);
```

A class without the attribute is skipped, and a name that is not an existing class or interface throws an
`InvalidAttributeException::unknownClass()`.

## Inject a tag

`#[Tagged]` on a parameter gives it every entry with the tag:

```php
use Dirthara\Container\Attribute\Tagged;

final readonly class Pipeline
{
    /**
     * @param iterable<string, Middleware> $middleware
     */
    public function __construct(
        #[Tagged('middleware')]
        public iterable $middleware,
    ) {}
}
```

The parameter receives the same lazy result as `tagged()`, so type it as `iterable`. A contextual binding can give a
class a different tag, because `tagged()` reads the tag only when the result is iterated:

```php
$container->when(AdminPipeline::class)->needs('$middleware')->giveValue($container->tagged('admin.middleware'));
```

After [`lock()`](getting-started.md#lock-the-container), `tag()` and `tagByAttribute()` throw a
`ContainerLockedException::cannotConfigure()`.
