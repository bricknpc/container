<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

use Dirthara\Container\Attribute\Inject;

final readonly class InjectsEntries
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        #[Inject('primary.service')]
        public Service $service,
        #[Inject('config')]
        public array $config,
        #[Inject('missing')]
        public ?Service $optional = null,
    ) {}
}
