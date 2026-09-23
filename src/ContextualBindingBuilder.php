<?php

declare(strict_types=1);

namespace Dirthara\Container;

use Closure;
use Dirthara\Container\Exception\InvalidContextualBindingException;

use function preg_match;
use function class_exists;
use function interface_exists;

final readonly class ContextualBindingBuilder
{
    /**
     * @internal
     *
     * @param list<class-string> $classes
     * @param Closure(list<class-string>, ContextualBinding): void $register
     */
    public function __construct(
        private Container $container,
        private array $classes,
        private Closure $register,
    ) {}

    /**
     * @return PendingContextualBinding
     */
    public function needs(string $need): PendingContextualBinding
    {
        $isParameterName = preg_match('/^\$[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $need) === 1;

        if (!$isParameterName && !class_exists($need) && !interface_exists($need)) {
            throw InvalidContextualBindingException::invalidNeed($need);
        }

        return new PendingContextualBinding($this->container, $this->classes, $need, $this->register);
    }
}
