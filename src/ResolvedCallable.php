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
    /**
     * @param class-string|null $class
     */
    public function __construct(
        public ReflectionFunctionAbstract $reflection,
        public Closure $closure,
        public string $name,
        public ?string $class,
    ) {}
}
