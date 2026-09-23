<?php

declare(strict_types=1);

namespace Dirthara\Container\Exception;

use Throwable;
use Psr\Container\ContainerExceptionInterface;

interface ContainerException extends Throwable, ContainerExceptionInterface
{
    /**
     * @var array<string, mixed>
     */
    public array $context { get; }

    /**
     * @param array<string, mixed> $context
     */
    public function addContext(array $context): static;
}
