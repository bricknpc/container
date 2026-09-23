<?php

declare(strict_types=1);

namespace Dirthara\Container\Exception;

use RuntimeException;
use Psr\Container\NotFoundExceptionInterface;

final class EntryNotFoundException extends RuntimeException implements ContainerException, NotFoundExceptionInterface
{
    public protected(set) array $context {
        get {
            return $this->context;
        }
    }

    public function addContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }
}
