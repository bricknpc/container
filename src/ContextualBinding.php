<?php

declare(strict_types=1);

namespace Dirthara\Container;

use Closure;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
final readonly class ContextualBinding
{
    /**
     * @param string|Closure(ContainerInterface): mixed|null $concrete
     */
    public function __construct(
        public string $need,
        public string|Closure|null $concrete,
        public mixed $value = null,
    ) {}
}
