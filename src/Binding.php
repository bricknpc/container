<?php

declare(strict_types=1);

namespace Dirthara\Container;

use Closure;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
final readonly class Binding
{
    /**
     * @param string|Closure(ContainerInterface): mixed $concrete
     */
    public function __construct(
        public string|Closure $concrete,
        public bool $shared,
    ) {}
}
