<?php

declare(strict_types=1);

namespace Dirthara\Container;

use Closure;
use Generator;
use IteratorAggregate;

/**
 * @internal
 *
 * @implements IteratorAggregate<string, mixed>
 */
final readonly class TaggedEntries implements IteratorAggregate
{
    /**
     * @param Closure(): list<array-key> $ids
     * @param Closure(string): mixed $resolve
     */
    public function __construct(
        private Closure $ids,
        private Closure $resolve,
    ) {}

    /**
     * @return Generator<string, mixed>
     */
    public function getIterator(): Generator
    {
        foreach (($this->ids)() as $id) {
            yield (string) $id => ($this->resolve)((string) $id);
        }
    }
}
