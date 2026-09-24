<?php

declare(strict_types=1);

namespace Dirthara\Container\Contract;

use Dirthara\Container\Exception\InvalidRegistrationException;

interface Scope
{
    /**
     * @throws InvalidRegistrationException
     */
    public function scopedInstance(string $abstract, mixed $instance): self;

    public function resetScope(): void;
}
