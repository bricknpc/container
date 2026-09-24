<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

use Dirthara\Container\Attribute\Lazy;

#[Lazy]
final class LazyWithoutProperties
{
    public static int $instances = 0;

    public function __construct(Recorder $recorder)
    {
        $recorder->calls->append(self::class);
    }
}
