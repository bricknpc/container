<?php

declare(strict_types=1);

namespace Dirthara\Container\Exception;

use Exception;
use PHPUnit\Event\Code\Throwable;

class ResolutionException extends Exception implements ContainerException
{
    /**
     * @var array<string, mixed>
     */
    public array $context {
        get => $this->context;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function addContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
    }

    public static function invalidParentType(string $parentType): self
    {
        return new self(sprintf('Invalid parent type: %s', $parentType), context: [
            'parentType' => $parentType,
        ]);
    }

    public static function unresolvableParameter(string $class, string $parameter): self
    {
        return new self(sprintf('Unresolvable parameter: %s', $parameter), context: [
            'class' => $class,
            'parameter' => $parameter,
        ]);
    }
}
