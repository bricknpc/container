<?php

declare(strict_types=1);

namespace Dirthara\Container\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class BoundTo
{
    /**
     * @param class-string $concrete
     */
    public function __construct(
        public string $concrete,
    ) {}
}
