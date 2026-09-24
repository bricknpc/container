<?php

declare(strict_types=1);

namespace Dirthara\Container\Contract;

use Dirthara\Container\Exception\InvalidContextualBindingException;

/**
 * @template-covariant TConfigurator of ContainerConfigurator
 */
interface ContextualBindingBuilder
{
    /**
     * @throws InvalidContextualBindingException
     *
     * @return PendingContextualBinding<TConfigurator>
     */
    public function needs(string $need): PendingContextualBinding;
}
