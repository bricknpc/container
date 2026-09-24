<?php

declare(strict_types=1);

namespace Dirthara\Container\Contract;

use Dirthara\Container\EntryDescription;
use Dirthara\Container\Exception\InvalidAttributeException;

interface Inspector
{
    /**
     * @return list<string>
     */
    public function registered(): array;

    /**
     * @throws InvalidAttributeException
     */
    public function describe(string $id): ?EntryDescription;
}
