<?php

declare(strict_types=1);

namespace Dirthara\Container\Exception;

use Throwable;
use RuntimeException;

use function implode;
use function sprintf;
use function array_map;

final class ContainerLockedException extends RuntimeException implements ContainerException
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

    public static function cannotRegister(string $id): self
    {
        return new self(
            message: sprintf(
                'Unable to register entry "%s": the container is locked and accepts no new registrations.',
                self::printable($id),
            ),
            context: ['id' => $id],
        );
    }

    /**
     * @param list<string> $classes
     */
    public static function cannotAddContextualBinding(array $classes): self
    {
        return new self(
            message: sprintf('Unable to add a contextual binding for "%s": the container is locked and accepts no new registrations.', implode('", "', array_map(
                self::printable(...),
                $classes,
            ))),
            context: ['classes' => $classes],
        );
    }

    public static function cannotConfigure(string $method): self
    {
        return new self(
            message: sprintf(
                'Unable to call %s(): the container is locked and accepts no new registrations.',
                self::printable($method),
            ),
            context: ['method' => $method],
        );
    }
}
