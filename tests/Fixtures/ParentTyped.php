<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

final class ParentTyped extends SelfTyped
{
    public function __construct(
        public parent $parent,
    ) {
        parent::__construct($parent);
    }
}
