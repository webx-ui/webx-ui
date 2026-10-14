<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Fields;

use Closure;
use Illuminate\Container\Container;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Blocks\Regions;

/**
 * `wx-fragment` — the name of a shared block, the value of the block «Shared block» (§3.2 of the
 * shared blocks spec).
 *
 * Only a region declared with `fragment` will do: the header and the footer are printed by the
 * layout on every page already, and placed in the middle of one they would be printed twice. A
 * name that stopped being a shared block after the page was written resolves to nothing, for
 * the same reason — the block prints nothing rather than a second header.
 *
 * The regions are asked at the moment of the question, not when the type is built: the field
 * types are built while providers still register (see `ScreenRegistry`).
 */
final class FragmentType implements FieldType
{
    public function rules(array $node): array
    {
        return ['nullable', 'string', static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && $value !== '' && ! self::regions()->isFragment($value)) {
                $fail((string) __('webx-blocks::regions.not-a-fragment', ['name' => $value]));
            }
        }];
    }

    public function store(mixed $value, array $node): mixed
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return is_string($stored) && $stored !== '' && self::regions()->isFragment($stored) ? $stored : null;
    }

    private static function regions(): Regions
    {
        return Container::getInstance()->make(Regions::class);
    }
}
