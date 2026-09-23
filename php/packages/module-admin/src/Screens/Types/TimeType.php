<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-time-picker`: a time of day as the picker writes it, `HH:mm`, or `HH:mm:ss` with
 * `props.seconds`. No date and no zone — it is the hour on a sign, not a moment.
 */
final class TimeType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/'];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
