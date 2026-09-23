<?php

declare(strict_types=1);

namespace Dirthara\Container\Exception;

use Throwable;
use RuntimeException;
use Psr\Container\NotFoundExceptionInterface;

use function sprintf;

final class EntryNotFoundException extends RuntimeException implements ContainerException, NotFoundExceptionInterface
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

    public static function forId(string $id): self
    {
        return new self(
            message: sprintf(
                'No entry "%s" was found: it is not bound, not registered as an instance, and not an instantiable class.',
                self::printable($id),
            ),
            context: ['id' => $id],
        );
    }
}
