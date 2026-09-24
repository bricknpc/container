<?php

declare(strict_types=1);

namespace Dirthara\Container;

enum Lifetime: string
{
    case Transient = 'transient';
    case Singleton = 'singleton';
    case Scoped = 'scoped';
}
