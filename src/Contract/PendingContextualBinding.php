<?php

declare(strict_types=1);

namespace Dirthara\Container\Contract;

use Closure;
use Psr\Container\ContainerInterface;
use Dirthara\Container\Exception\ContainerLockedException;

/**
 * @template-covariant TConfigurator of ContainerConfigurator
 */
interface PendingContextualBinding
{
    /**
     * @param string|Closure(ContainerInterface): mixed $concrete
     *
     * @throws ContainerLockedException
     *
     * @return TConfigurator
     */
    public function give(string|Closure $concrete): ContainerConfigurator;

    /**
     * @throws ContainerLockedException
     *
     * @return TConfigurator
     */
    public function giveValue(mixed $value): ContainerConfigurator;
}
