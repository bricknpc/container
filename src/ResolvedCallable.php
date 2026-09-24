<?php

declare(strict_types=1);

namespace Dirthara\Container;

use Closure;
use ReflectionFunctionAbstract;

/**
 * @internal
 */
final readonly class ResolvedCallable
{
    public function __construct(
        public ReflectionFunctionAbstract $reflection,
        public Closure $closure,
        public string $name,
    ) {}
}
