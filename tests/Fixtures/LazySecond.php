<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

final readonly class LazySecond
{
    public function __construct(
        public LazyFirst $first,
    ) {}
}
