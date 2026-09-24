<?php

declare(strict_types=1);

namespace Dirthara\Container\Exception;

use Throwable;
use InvalidArgumentException;

use function sprintf;

final class InvalidAttributeException extends InvalidArgumentException implements ContainerException
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

    public static function notASubtype(string $class, string $concrete): self
    {
        return new self(
            message: sprintf(
                'The #[BoundTo] attribute of "%s" names "%s", which is not a class or interface that extends or implements it.',
                self::printable($class),
                self::printable($concrete),
            ),
            context: ['class' => $class, 'concrete' => $concrete],
        );
    }
}
