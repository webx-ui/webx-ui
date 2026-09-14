<?php

declare(strict_types=1);

namespace WebxUi\Admin\Facades;

use Illuminate\Support\Facades\Facade;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Admin\Screens\FieldTypes as Registry;

/**
 * @method static void register(string $type, FieldType $fieldType)
 * @method static bool has(string $type)
 * @method static FieldType|null get(string $type)
 * @method static list<string> names()
 *
 * @see Registry
 */
final class FieldTypes extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Registry::class;
    }
}
