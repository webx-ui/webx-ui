<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use WebxUi\Admin\Screens\FieldType;

/** `wx-switch`, `wx-checkbox`. */
final class BooleanType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'boolean'];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        return $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node): mixed
    {
        return $stored;
    }
}
