<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

final readonly class First
{
    public function __construct(
        public Second $second,
    ) {}
}
