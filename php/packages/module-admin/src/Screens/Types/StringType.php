<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use WebxUi\Admin\Screens\FieldType;

/**
 * Text: `wx-input`, `wx-textarea`. The limit is the node's `props.maxlength` when it says one,
 * else what a text column comfortably holds.
 */
final class StringType implements FieldType
{
    public function __construct(private readonly int $max = 65535) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        $max = $node['props']['maxlength'] ?? $this->max;

        return ['nullable', 'string', 'max:'.(int) $max];
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
