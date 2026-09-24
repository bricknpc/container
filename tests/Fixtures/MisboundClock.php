<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

use Dirthara\Container\Attribute\BoundTo;

#[BoundTo(Plain::class)]
interface MisboundClock {}
