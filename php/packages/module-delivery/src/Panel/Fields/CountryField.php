<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Panel\Fields;

use WebxUi\Admin\Screens\FieldType;

/** `wx-delivery-country`: a country of the site by its ISO 3166-1 code. */
final class CountryField implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['required', 'string', 'size:2', 'exists:geo_countries,code'];
    }

    /** @param  array<string, mixed>  $node */
    public function store(mixed $value, array $node): mixed
    {
        return is_string($value) ? strtoupper($value) : null;
    }

    /** @param  array<string, mixed>  $node */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
