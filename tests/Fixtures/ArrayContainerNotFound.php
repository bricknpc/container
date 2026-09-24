<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

use RuntimeException;
use Psr\Container\NotFoundExceptionInterface;

final class ArrayContainerNotFound extends RuntimeException implements NotFoundExceptionInterface {}
