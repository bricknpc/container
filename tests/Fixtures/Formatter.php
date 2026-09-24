<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

use Dirthara\Container\Attribute\BoundTo;
use Dirthara\Container\Attribute\Singleton;

#[BoundTo(JsonFormatter::class)]
#[Singleton]
interface Formatter {}
