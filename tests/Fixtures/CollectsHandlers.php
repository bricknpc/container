<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

use Dirthara\Container\Attribute\Tagged;

final readonly class CollectsHandlers
{
    /**
     * @param iterable<string, mixed> $handlers
     */
    public function __construct(
        #[Tagged('handlers')]
        public iterable $handlers,
    ) {}
}
