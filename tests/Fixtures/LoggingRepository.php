<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

final readonly class LoggingRepository implements Repository
{
    public function __construct(
        public Plain $plain,
        public Repository $inner,
    ) {}
}
