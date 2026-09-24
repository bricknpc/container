<?php

declare(strict_types=1);

namespace Dirthara\Container\Contract;

use Dirthara\Container\Exception\ResolutionException;
use Dirthara\Container\Exception\EntryNotFoundException;
use Dirthara\Container\Exception\CircularDependencyException;

interface InstanceFactory
{
    /**
     * @template T of object
     *
     * @param class-string<T>|string $id
     * @param array<string, mixed> $parameters
     *
     * @throws EntryNotFoundException
     * @throws CircularDependencyException
     * @throws ResolutionException
     *
     * @return ($id is class-string<T> ? T : mixed)
     */
    public function make(string $id, array $parameters = []): mixed;
}
