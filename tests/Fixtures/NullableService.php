<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

final readonly class NullableService
{
    public function __construct(
        public ?Service $service,
    ) {}
}
