<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests\Fixtures;

use RuntimeException;

use function array_values;

final class Handler
{
    /**
     * @return array{Service, int}
     */
    public function handle(Service $service, int $count = 1): array
    {
        return [$service, $count];
    }

    public static function describe(Plain $plain, string $name): string
    {
        return $name . ':' . $plain::class;
    }

    /**
     * @return list<string>
     */
    public function collect(string ...$items): array
    {
        return array_values($items);
    }

    public function fail(): never
    {
        throw new RuntimeException('handler failed');
    }

    public function __invoke(Plain $plain): Plain
    {
        return $plain;
    }

    // @mago-expect analysis:unused-method
    private function hidden(): void {}
}
