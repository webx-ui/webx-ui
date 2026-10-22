<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Panel;

use Closure;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Admin\Screens\Types\OptionType;
use WebxUi\Catalog\Dictionaries\Dictionary;

/**
 * `wx-catalog-tone`: the tone of a reference book's record (labels, stock) — one of the screen's
 * options, drawn by the panel as a tag of that tone rather than as a word. A node with
 * `props.custom` also takes a colour of the record's own, `#rrggbb`; the model has the last word
 * on whether its kind may have one ({@see Dictionary::customColors()}).
 */
final class ToneFieldType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        $tones = OptionType::values($node);
        $custom = ($node['props']['custom'] ?? false) === true;

        return ['nullable', static function (string $attribute, mixed $value, Closure $fail) use ($tones, $custom): void {
            if ($value === null || ($tones === [] && ! $custom) || OptionType::holds($tones, $value)) {
                return;
            }

            if (! $custom || ! is_string($value) || preg_match(Dictionary::HEX, strtolower(trim($value))) !== 1) {
                $fail('validation.in')->translate();
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        return is_string($value) && str_starts_with(trim($value), '#') ? strtolower(trim($value)) : $value;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
