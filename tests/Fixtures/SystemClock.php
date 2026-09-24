<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

final readonly class SystemClock implements Clock
{
    public function __construct(
        public string $zone = 'UTC',
    ) {}
}
