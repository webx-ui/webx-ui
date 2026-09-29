<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Panel;

use Closure;
use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-catalog-facets`: the category's own facet settings (§6.2) — null when it inherits, or the
 * facets in the order the editor dragged them into, each shown or hidden:
 *
 *     [{ "key": "brand", "visible": true }, { "key": "price", "visible": false }]
 *
 * Only the shape is checked here. Which keys exist is the facet registry's to say, and a key
 * whose module has since been removed is kept rather than refused: putting the module back
 * should bring the setting back with it.
 */
final class FacetsFieldType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'array', static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            foreach ($value as $row) {
                $key = is_array($row) ? ($row['key'] ?? null) : null;

                if (! is_string($key) || preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $key) !== 1) {
                    $fail((string) __('webx-catalog::errors.facet-shape'));

                    return;
                }
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<array{key: string, visible: bool}>|null
     */
    public function store(mixed $value, array $node): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $rows = [];

        foreach ($value as $row) {
            if (is_array($row) && is_string($row['key'] ?? null)) {
                $rows[$row['key']] = ['key' => $row['key'], 'visible' => (bool) ($row['visible'] ?? true)];
            }
        }

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<array{key: string, visible: bool}>|null
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): ?array
    {
        return $this->store($stored, $node);
    }
}
