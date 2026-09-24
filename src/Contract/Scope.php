<?php

declare(strict_types=1);

namespace Dirthara\Container\Contract;

interface Scope
{
    public function scopedInstance(string $abstract, mixed $instance): self;

    public function resetScope(): void;
}
