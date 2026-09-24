<?php

declare(strict_types=1);

namespace WebxUi\Admin\Categories;

use Closure;
use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-categories`: the categories a record is filed under, in the order the editor put them in.
 *
 * The order is the value — the first one is the main one (decision 4 of the services spec) — so
 * the list is never sorted here, only stripped of what is not an id and of repeats. A repeat is
 * dropped rather than refused: it is a double click, not a mistake worth a red field.
 *
 * Which categories is the node's `props.source`, looked up in {@see CategorySources}. A source
 * nobody registered refuses every non-empty value rather than letting it through unchecked: a
 * screen that files records under a table the server cannot name is a screen that is wrong.
 *
 * One query for the whole list rather than `exists` per element, because `ScreenValues` checks a
 * field as one value and has no `*` to hang per-element rules off.
 */
final class CategoriesType implements FieldType
{
    public function __construct(private readonly CategorySources $sources) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'array', function (string $attribute, mixed $value, Closure $fail) use ($node): void {
            $ids = $this->ids($value);

            if ($ids === []) {
                return;
            }

            $source = $node['props']['source'] ?? null;
            $model = is_string($source) ? $this->sources->model($source) : null;

            // Trashed ones included: a category in the bin still holds its records, and an
            // article saved while its rubric waits there must not be refused for it.
            $found = $model === null ? 0 : $model::query()->withoutGlobalScopes()->whereKey($ids)->count();

            if ($found !== count($ids)) {
                $fail((string) __(is_string($source) ? $this->sources->unknown($source) : 'webx-admin::categories.unknown'));
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<int>
     */
    public function store(mixed $value, array $node): array
    {
        return $this->ids($value);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<int>
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): array
    {
        return $this->ids($stored);
    }

    /**
     * Whatever arrived, as the list of ids it was meant to be.
     *
     * @return list<int>
     */
    private function ids(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $one) {
            if (is_int($one) || (is_string($one) && ctype_digit($one))) {
                $ids[(int) $one] = true;
            }
        }

        return array_keys($ids);
    }
}
