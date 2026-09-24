<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

use ArrayObject;

final readonly class Recorder
{
    /**
     * @var ArrayObject<int, string>
     */
    public ArrayObject $calls;

    public function __construct()
    {
        $this->calls = new ArrayObject();
    }
}
