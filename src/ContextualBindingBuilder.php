<?php

declare(strict_types=1);

namespace Dirthara\Container;

use Closure;
use Dirthara\Container\Contract\ContainerConfigurator;
use Dirthara\Container\Exception\InvalidContextualBindingException;
use Dirthara\Container\Contract\ContextualBindingBuilder as ContextualBindingBuilderContract;

use function preg_match;
use function class_exists;
use function interface_exists;

/**
 * @template-covariant TConfigurator of ContainerConfigurator
 *
 * @implements ContextualBindingBuilderContract<TConfigurator>
 */
final readonly class ContextualBindingBuilder implements ContextualBindingBuilderContract
{
    /**
     * @internal
     *
     * @param TConfigurator $configurator
     * @param list<class-string> $classes
     * @param Closure(list<class-string>, ContextualBinding): void $register
     */
    public function __construct(
        private ContainerConfigurator $configurator,
        private array $classes,
        private Closure $register,
    ) {}

    /**
     * @throws InvalidContextualBindingException
     *
     * @return PendingContextualBinding<TConfigurator>
     */
    public function needs(string $need): PendingContextualBinding
    {
        $isParameterName = preg_match('/^\$[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $need) === 1;

        if (!$isParameterName && !class_exists($need) && !interface_exists($need)) {
            throw InvalidContextualBindingException::invalidNeed($need);
        }

        return new PendingContextualBinding($this->configurator, $this->classes, $need, $this->register);
    }
}
