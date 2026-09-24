<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

use Dirthara\Container\Attribute\BoundTo;
use Dirthara\Container\Attribute\DecoratedBy;

#[BoundTo(DatabaseRepository::class)]
#[DecoratedBy(CachingRepository::class)]
#[DecoratedBy(LoggingRepository::class)]
interface Repository {}
