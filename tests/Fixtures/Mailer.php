<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

final readonly class Mailer
{
    public function __construct(
        public Service $primary,
        public Service $fallback,
        public int $retries = 3,
    ) {}
}
