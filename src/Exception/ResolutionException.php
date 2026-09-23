<?php

declare(strict_types=1);

namespace Dirthara\Container\Exception;

use Throwable;
use RuntimeException;

use function sprintf;

final class ResolutionException extends RuntimeException implements ContainerException
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

    public static function unresolvableParameter(string $class, string $parameter): self
    {
        return new self(
            message: sprintf(
                'Unable to resolve parameter "$%s" of "%s": it has no class type, no default value, and is not nullable.',
                self::printable($parameter),
                self::printable($class),
            ),
            context: ['class' => $class, 'parameter' => $parameter],
        );
    }

    public static function missingDependency(string $class, string $parameter, string $dependency): self
    {
        return new self(
            message: sprintf(
                'Unable to resolve parameter "$%s" of "%s": no entry "%s" was found, and the parameter has no default value and is not nullable.',
                self::printable($parameter),
                self::printable($class),
                self::printable($dependency),
            ),
            context: ['class' => $class, 'parameter' => $parameter, 'dependency' => $dependency],
        );
    }

    public static function unresolvableBinding(string $id, string $concrete): self
    {
        return new self(
            message: sprintf(
                'Entry "%s" is bound to "%s", which is neither a known entry nor an instantiable class.',
                self::printable($id),
                self::printable($concrete),
            ),
            context: ['id' => $id, 'concrete' => $concrete],
        );
    }

    public static function factoryFailed(string $id, Throwable $previous): self
    {
        return new self(
            message: sprintf('The factory for entry "%s" failed with %s.', self::printable($id), $previous::class),
            previous: $previous,
            context: ['id' => $id, 'exceptionClass' => $previous::class],
        );
    }

    public static function unresolvableContextualBinding(string $class, string $need, string $concrete): self
    {
        return new self(
            message: sprintf(
                'The contextual binding of "%s" for "%s" gives "%s", which is neither a known entry nor an instantiable class.',
                self::printable($need),
                self::printable($class),
                self::printable($concrete),
            ),
            context: ['class' => $class, 'need' => $need, 'concrete' => $concrete],
        );
    }

    public static function contextualFactoryFailed(string $class, string $need, Throwable $previous): self
    {
        return new self(
            message: sprintf(
                'The contextual factory of "%s" for "%s" failed with %s.',
                self::printable($need),
                self::printable($class),
                $previous::class,
            ),
            previous: $previous,
            context: ['class' => $class, 'need' => $need, 'exceptionClass' => $previous::class],
        );
    }
}
