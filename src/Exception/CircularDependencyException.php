<?php

declare(strict_types=1);

namespace Dirthara\Container\Exception;

use Throwable;
use RuntimeException;

use function implode;
use function sprintf;
use function array_map;

final class CircularDependencyException extends RuntimeException implements ContainerException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
    }

    /**
     * @param list<string> $chain The entries being resolved, from the first requested to the one that repeats.
     */
    public static function forEntry(string $id, array $chain): self
    {
        return new self(
            message: sprintf(
                'Circular dependency detected while resolving entry "%s": %s.',
                self::printable($id),
                implode(' -> ', array_map(self::printable(...), $chain)),
            ),
            context: ['id' => $id, 'chain' => $chain],
        );
    }
}
