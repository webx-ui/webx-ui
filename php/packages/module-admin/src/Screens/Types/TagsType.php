<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Closure;
use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-tags-input`: a list of short strings. `props.max` counts them; a repeated tag is refused
 * unless `props.duplicates`; with `props.allowCreate: false` every tag must be one of
 * `props.suggestions` — the component lets nothing else in, and neither does the server.
 */
final class TagsType implements FieldType
{
    public function __construct(private readonly int $length = 255) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $rules = ['nullable', 'array', 'list'];

        if (is_numeric($props['max'] ?? null)) {
            $rules[] = 'max:'.(int) $props['max'];
        }

        $length = $this->length;
        $duplicates = ($props['duplicates'] ?? false) === true;
        $closed = ($props['allowCreate'] ?? true) === false && is_array($props['suggestions'] ?? null)
            ? array_values(array_filter($props['suggestions'], 'is_string'))
            : null;

        $rules[] = static function (string $attribute, mixed $value, Closure $fail) use ($length, $duplicates, $closed): void {
            $tags = is_array($value) ? $value : [];

            foreach ($tags as $tag) {
                if (! is_string($tag) || trim($tag) === '') {
                    $fail('validation.string')->translate();

                    return;
                }

                if (mb_strlen($tag) > $length) {
                    $fail('validation.max.string')->translate(['max' => (string) $length]);

                    return;
                }

                if ($closed !== null && ! in_array($tag, $closed, true)) {
                    $fail('validation.in')->translate();

                    return;
                }
            }

            if (! $duplicates && count(array_unique($tags, SORT_REGULAR)) !== count($tags)) {
                $fail('validation.distinct')->translate();
            }
        };

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        $tags = [];

        foreach ($value as $tag) {
            if (is_string($tag) && trim($tag) !== '') {
                $tags[] = trim($tag);
            }
        }

        return $tags === [] ? null : $tags;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
