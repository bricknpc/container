<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

final readonly class Second
{
    public function __construct(
        public First $first,
    ) {}
}
