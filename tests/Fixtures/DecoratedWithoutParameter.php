<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

use Dirthara\Container\Attribute\DecoratedBy;

#[DecoratedBy(DecoratorWithoutParameter::class)]
class DecoratedWithoutParameter {}
