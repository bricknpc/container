<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

use Dirthara\Container\Attribute\Lazy;

#[Lazy]
final readonly class LazyReadonly
{
    public function __construct(
        public Plain $plain,
    ) {}
}
