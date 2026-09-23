<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

final readonly class Nested
{
    public function __construct(
        public NeedsService $needsService,
        public Plain $plain,
    ) {}
}
