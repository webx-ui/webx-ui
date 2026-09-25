<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Fields;

use WebxUi\Admin\Screens\FieldType;
use WebxUi\Blocks\Schema;

/**
 * `wx-data` — a component's input that the code hands over rather than an editor types: a
 * recipe card, a list of links. Nothing to cast and nothing to look up, so the value goes in and
 * comes out as it is.
 *
 * Registered all the same, and not only for the rules: a node of a type the server knows is a
 * field to every walk over a schema ({@see Schema::fields()}), and the walk has
 * to stop at it — what is under `card` is the card's, not the component's.
 */
final class DataType implements FieldType
{
    public function rules(array $node): array
    {
        return ['nullable'];
    }

    public function store(mixed $value, array $node): mixed
    {
        return $value;
    }

    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
