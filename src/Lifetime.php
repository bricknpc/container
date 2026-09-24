<?php

declare(strict_types=1);

namespace Dirthara\Container;

/**
 * @internal
 */
enum Lifetime: string
{
    case Transient = 'transient';
    case Shared = 'shared';
    case Scoped = 'scoped';
}
