<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Closure;
use WebxUi\Admin\Screens\FieldType;

/**
 * The part of an address a record names itself: letters, digits, and single hyphens or
 * underscores between them.
 *
 * Whether the address is free is not checked here — only the registry sees every kind of page
 * at once, and it answers by refusing the save with a 422 under the field.
 */
final class SlugType implements FieldType
{
    private const PATTERN = '/^[\p{L}\p{N}]+(?:[-_][\p{L}\p{N}]+)*$/u';

    /**
     * The rules on their own, for a request that checks a slug outside a screen.
     *
     * @return list<mixed>
     */
    public static function checks(): array
    {
        return [
            'nullable',
            'string',
            'max:190',
            static function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && $value !== '' && preg_match(self::PATTERN, $value) !== 1) {
                    $fail((string) __('webx-admin::categories.slug-shape'));
                }
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return self::checks();
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
