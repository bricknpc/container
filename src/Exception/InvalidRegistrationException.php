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
}
