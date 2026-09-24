<?php

declare(strict_types=1);

namespace Dirthara\Container\Exception;

use Throwable;
use RuntimeException;

use function implode;
use function sprintf;
use function array_map;

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

    public static function unresolvableParameter(string $target, string $parameter): self
    {
        return new self(
            message: sprintf(
                'Unable to resolve parameter "$%s" of "%s": it has no class type, no default value, and is not nullable.',
                self::printable($parameter),
                self::printable($target),
            ),
            context: ['target' => $target, 'parameter' => $parameter],
        );
    }

    public static function missingDependency(string $target, string $parameter, string $dependency): self
    {
        return new self(
            message: sprintf(
                'Unable to resolve parameter "$%s" of "%s": no entry "%s" was found, and the parameter has no default value and is not nullable.',
                self::printable($parameter),
                self::printable($target),
                self::printable($dependency),
            ),
            context: ['target' => $target, 'parameter' => $parameter, 'dependency' => $dependency],
        );
    }

    /**
     * @param list<array-key> $parameters
     */
    public static function unknownParameters(string $target, array $parameters): self
    {
        return new self(
            message: sprintf(
                'Unable to resolve "%s": it has no parameters named "%s".',
                self::printable($target),
                implode('", "', array_map(static fn(int|string $name): string => self::printable(
                    (string) $name,
                ), $parameters)),
            ),
            context: ['target' => $target, 'parameters' => $parameters],
        );
    }

    public static function notBuildable(string $id): self
    {
        return new self(
            message: sprintf(
                'Unable to make a new "%s": it is registered only as an instance, which the container cannot build again.',
                self::printable($id),
            ),
            context: ['id' => $id],
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

    public static function extenderFailed(string $id, Throwable $previous): self
    {
        return new self(
            message: sprintf('An extender of entry "%s" failed with %s.', self::printable($id), $previous::class),
            previous: $previous,
            context: ['id' => $id, 'exceptionClass' => $previous::class],
        );
    }

    public static function callbackFailed(string $type, string $class, Throwable $previous): self
    {
        return new self(
            message: sprintf(
                'A callback for "%s" failed with %s after building "%s".',
                self::printable($type),
                $previous::class,
                self::printable($class),
            ),
            previous: $previous,
            context: ['type' => $type, 'class' => $class, 'exceptionClass' => $previous::class],
        );
    }

    public static function delegateFailed(string $id, string $delegate, Throwable $previous): self
    {
        return new self(
            message: sprintf(
                'The delegate container %s failed to resolve entry "%s" with %s.',
                self::printable($delegate),
                self::printable($id),
                $previous::class,
            ),
            previous: $previous,
            context: ['id' => $id, 'delegate' => $delegate, 'exceptionClass' => $previous::class],
        );
    }
}
