<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

use Dirthara\Container\Attribute\Scoped;
use Dirthara\Container\Attribute\Singleton;

#[Singleton]
#[Scoped]
final class ConflictingLifetimes {}
