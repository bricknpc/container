<?php

declare(strict_types=1);

namespace Dirthara\Container\Contract;

use Closure;
use Psr\Container\ContainerInterface;

/**
 * @template-covariant TConfigurator of ContainerConfigurator
 */
interface PendingContextualBinding
{
    /**
     * @param string|Closure(ContainerInterface): mixed $concrete
     *
     * @return TConfigurator
     */
    public function give(string|Closure $concrete): ContainerConfigurator;

    /**
     * @return TConfigurator
     */
    public function giveValue(mixed $value): ContainerConfigurator;
}
