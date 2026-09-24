<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

final readonly class CachingRepository implements Repository
{
    public function __construct(
        public Repository $inner,
    ) {}
}
