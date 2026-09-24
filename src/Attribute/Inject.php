<?php

declare(strict_types=1);

namespace Dirthara\Container\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Inject
{
    public function __construct(
        public string $id,
    ) {}
}
