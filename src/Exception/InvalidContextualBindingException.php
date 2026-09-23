<?php

declare(strict_types=1);

namespace Dirthara\Container\Exception;

use Throwable;
use InvalidArgumentException;

use function sprintf;

final class InvalidContextualBindingException extends InvalidArgumentException implements ContainerException
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

    public static function notAClass(string $class): self
    {
        return new self(
            message: sprintf(
                'Unable to add a contextual binding for "%s": it is not an existing class.',
                self::printable($class),
            ),
            context: ['class' => $class],
        );
    }

    public static function invalidNeed(string $need): self
    {
        return new self(
            message: sprintf(
                'Unable to add a contextual binding that needs "%s": it is neither an existing class or interface nor a parameter name prefixed with $.',
                self::printable($need),
            ),
            context: ['need' => $need],
        );
    }
}
