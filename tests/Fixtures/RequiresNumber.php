<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

final readonly class RequiresNumber
{
    public function __construct(
        public int $number,
    ) {}
}
