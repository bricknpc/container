<?php

declare(strict_types=1);

namespace Dirthara\Container\Exception;

use Exception;
use PHPUnit\Event\Code\Throwable;
use Psr\Container\ContainerExceptionInterface;

class CircularDependencyException extends Exception implements ContainerExceptionInterface
{
    public protected(set) array $context {
        get {
            return $this->context;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
    }

    public function addContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    public static function forEntry(string $id, array $resolving = []): self
    {
        return new static(message: sprintf('Circular dependency detected for entry "%s"', $id), context: [
            'id' => $id,
            'resolving' => $resolving,
        ]);
    }
}
