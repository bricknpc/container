<?php

declare(strict_types=1);

namespace Dirthara\Container;

final readonly class EntryDescription
{
    /**
     * @param list<string> $tags
     * @param list<class-string> $decorators
     */
    public function __construct(
        public string $id,
        public EntrySource $source,
        public ?Lifetime $lifetime,
        public ?string $concrete,
        public bool $factory,
        public bool $lazy,
        public array $tags,
        public array $decorators,
        public int $extenders,
    ) {}
}
