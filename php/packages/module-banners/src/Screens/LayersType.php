<?php

declare(strict_types=1);

namespace WebxUi\Banners\Screens;

use WebxUi\Admin\Screens\FieldType;
use WebxUi\Banners\Panel\LayerRules;

/**
 * `wx-banner-layers`: the layers of a banner, as one field of `banners.form`.
 *
 * The screen checks only that it is a list: what is inside is {@see LayerRules}', which tidies it
 * before the screen ever sees it, so a refusal lands under the layer and the field that is wrong
 * rather than under the whole list.
 */
final class LayersType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'array'];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        return is_array($value) ? array_values($value) : null;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
