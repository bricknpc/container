<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

use Dirthara\Container\Attribute\Inject;

final readonly class RequiresInjected
{
    public function __construct(
        #[Inject('missing')]
        public Service $service,
    ) {}
}
