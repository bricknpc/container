<?php

declare(strict_types=1);

namespace Dirthara\Container\Contract;

use Dirthara\Container\Exception\ResolutionException;
use Dirthara\Container\Exception\EntryNotFoundException;
use Dirthara\Container\Exception\InvalidCallableException;
use Dirthara\Container\Exception\CircularDependencyException;

interface Invoker
{
    /**
     * @param array{0: object|string, 1: string}|string|object $callable
     * @param array<string, mixed> $parameters
     *
     * @throws InvalidCallableException
     * @throws EntryNotFoundException
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    public function call(array|string|object $callable, array $parameters = []): mixed;
}
