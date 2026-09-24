<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

use Dirthara\Container\Attribute\Lazy;

#[Lazy]
final class LazyService
{
    public function __construct(
        public Recorder $recorder,
        public int $number = 1,
    ) {
        $recorder->calls->append(self::class);
    }
}
