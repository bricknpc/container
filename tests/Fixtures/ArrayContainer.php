<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

use Closure;
use Psr\Container\ContainerInterface;

use function array_key_exists;

final readonly class ArrayContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $entries
     */
    public function __construct(
        private array $entries,
        private ?ContainerInterface $fallback = null,
    ) {}

    public function get(string $id): mixed
    {
        if (!array_key_exists($id, $this->entries)) {
            return $this->fallback === null ? throw new ArrayContainerNotFound($id) : $this->fallback->get($id);
        }

        return $this->entries[$id] instanceof Closure ? $this->entries[$id]() : $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries) || $this->fallback?->has($id) === true;
    }
}
