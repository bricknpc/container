<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

final readonly class OptionalService
{
    public function __construct(
        public ?Service $service = null,
    ) {}
}
