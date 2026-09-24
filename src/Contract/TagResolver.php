<?php

declare(strict_types=1);

namespace Dirthara\Container\Contract;

interface TagResolver
{
    /**
     * @return iterable<string, mixed>
     */
    public function tagged(string $tag): iterable;
}
