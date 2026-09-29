<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Panel;

use Closure;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Catalog\Models\Category;

/**
 * `wx-catalog-category`: one category of the catalogue, or several with `props.multiple`.
 *
 * Only live categories: filing a product under one in the bin would hide it behind a category
 * nobody can open. An unknown id is refused rather than dropped, because a product silently put
 * in no category is a product silently off the site. One query for the whole list.
 */
final class CategoryFieldType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        $multiple = self::multiple($node);

        return [
            'nullable',
            $multiple ? 'array' : 'integer',
            static function (string $attribute, mixed $value, Closure $fail): void {
                $ids = self::ids($value);

                if ($ids !== [] && Category::query()->whereKey($ids)->count() !== count($ids)) {
                    $fail((string) __('webx-catalog::errors.unknown-category'));
                }
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<int>|int|null
     */
    public function store(mixed $value, array $node): array|int|null
    {
        $ids = self::ids($value);

        return self::multiple($node) ? $ids : ($ids[0] ?? null);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<int>|int|null
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): array|int|null
    {
        return $this->store($stored, $node);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function multiple(array $node): bool
    {
        return ($node['props']['multiple'] ?? false) === true;
    }

    /**
     * @return list<int>
     */
    private static function ids(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $ids = [];

        foreach ($values as $one) {
            if (is_int($one) || (is_string($one) && ctype_digit($one))) {
                $ids[] = (int) $one;
            }
        }

        return array_values(array_unique($ids));
    }
}
