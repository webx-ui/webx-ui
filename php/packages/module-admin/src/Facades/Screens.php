<?php

declare(strict_types=1);

namespace WebxUi\Admin\Facades;

use Illuminate\Support\Facades\Facade;
use WebxUi\Admin\Screens\ScreenRegistry;

/**
 * @method static void register(string $name, string|array<int|string, mixed> $screen)
 * @method static void extend(string $name, string|list<array<string, mixed>> $patch)
 * @method static bool has(string $name)
 * @method static list<string> names()
 * @method static list<array<string, mixed>> tree(string $name)
 * @method static list<array<string, mixed>> render(string $name, ?callable $can = null, ?callable $translate = null)
 * @method static list<array<string, mixed>> fields(string $name)
 *
 * @see ScreenRegistry
 */
final class Screens extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ScreenRegistry::class;
    }
}
