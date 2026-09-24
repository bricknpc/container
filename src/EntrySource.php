<?php

declare(strict_types=1);

namespace Dirthara\Container;

enum EntrySource: string
{
    case ScopedInstance = 'scoped-instance';
    case Instance = 'instance';
    case Binding = 'binding';
    case Delegate = 'delegate';
    case Attribute = 'attribute';
    case Autowired = 'autowired';
}
