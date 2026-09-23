<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

use function array_values;

final readonly class Scalars
{
    /**
     * @var list<string>
     */
    public array $rest;

    public function __construct(
        public int $number = 3,
        public int|string $union = 'union',
        public ?string $nullable = null,
        string ...$rest,
    ) {
        $this->rest = array_values($rest);
    }
}
