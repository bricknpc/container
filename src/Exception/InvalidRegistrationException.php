<?php

declare(strict_types=1);

namespace Dirthara\Container\Exception;

use Throwable;
use InvalidArgumentException;

use function sprintf;

final class InvalidRegistrationException extends InvalidArgumentException implements ContainerException
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

    public static function notALazyClass(string $class): self
    {
        return new self(
            message: sprintf('Unable to make "%s" lazy: it is not an instantiable class.', self::printable($class)),
            context: ['class' => $class],
        );
    }

    public static function incompatibleInstance(string $id, string $type): self
    {
        return new self(
            message: sprintf(
                'Unable to register %s as entry "%s": an entry named after a class or interface has to be an instance of it.',
                self::printable($type),
                self::printable($id),
            ),
            context: ['id' => $id, 'type' => $type],
        );
    }

    public static function incompatibleConcrete(string $id, string $concrete): self
    {
        return new self(
            message: sprintf(
                'Unable to bind entry "%s" to "%s": it does not extend or implement "%s".',
                self::printable($id),
                self::printable($concrete),
                self::printable($id),
            ),
            context: ['id' => $id, 'concrete' => $concrete],
        );
    }
}
