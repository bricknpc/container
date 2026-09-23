<?php

declare(strict_types=1);

namespace Dirthara\Container;

use Closure;
use Psr\Container\ContainerInterface;

/**
 * @internal How the container stores a binding; register one with Container::bind() or Container::singleton().
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
