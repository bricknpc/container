<?php

declare(strict_types=1);

namespace Dirthara\Container;

use Closure;
use ReflectionMethod;
use ReflectionFunction;
use Dirthara\Container\Exception\ResolutionException;
use Dirthara\Container\Exception\EntryNotFoundException;
use Dirthara\Container\Exception\InvalidCallableException;
use Dirthara\Container\Exception\CircularDependencyException;

use function strpos;
use function substr;
use function is_array;
use function is_object;
use function is_string;
use function class_exists;
use function method_exists;
use function function_exists;

/**
 * @internal
 */
final readonly class CallableResolver
{
    /**
     * @param Closure(class-string): object $instanceOf
     */
    public function __construct(
        private Closure $instanceOf,
    ) {}

    /**
     * @param array{0: object|string, 1: string}|string|object $callable
     *
     * @throws InvalidCallableException
     * @throws EntryNotFoundException
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    public function resolve(array|string|object $callable): ResolvedCallable
    {
        if ($callable instanceof Closure) {
            return new ResolvedCallable(new ReflectionFunction($callable), $callable, 'Closure');
        }

        if (is_string($callable) && function_exists($callable)) {
            return new ResolvedCallable(new ReflectionFunction($callable), $callable(...), $callable);
        }

        if (is_array($callable)) {
            return $this->resolveMethod($callable[0], $callable[1]);
        }

        if (is_object($callable)) {
            return $this->resolveMethod($callable, '__invoke');
        }

        $separator = strpos($callable, needle: '::');

        return $separator === false
            ? $this->resolveMethod($callable, '__invoke')
            : $this->resolveMethod(substr($callable, offset: 0, length: $separator), substr($callable, $separator + 2));
    }

    /**
     * @throws InvalidCallableException
     * @throws EntryNotFoundException
     * @throws CircularDependencyException
     * @throws ResolutionException
     */
    private function resolveMethod(object|string $target, string $method): ResolvedCallable
    {
        $class = is_object($target) ? $target::class : $target;
        $name = $class . '::' . $method;

        if (!class_exists($class) || !method_exists($class, $method)) {
            throw InvalidCallableException::notCallable($name);
        }

        $reflection = new ReflectionMethod($class, $method);

        if (!$reflection->isPublic()) {
            throw InvalidCallableException::notCallable($name);
        }

        if ($reflection->isStatic()) {
            return new ResolvedCallable($reflection, $reflection->getClosure(), $name);
        }

        $instance = is_object($target) ? $target : ($this->instanceOf)($class);

        return new ResolvedCallable($reflection, $reflection->getClosure($instance), $name);
    }
}
