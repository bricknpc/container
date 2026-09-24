<?php

declare(strict_types=1);

namespace Dirthara\Container\Exception;

use Throwable;
use InvalidArgumentException;

use function sprintf;

final class InvalidCallableException extends InvalidArgumentException implements ContainerException
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

    public static function notCallable(string $callable): self
    {
        return new self(
            message: sprintf(
                'Unable to call "%s": it is not a closure, a function, a public method, or an invokable class.',
                self::printable($callable),
            ),
            context: ['callable' => $callable],
        );
    }
}
