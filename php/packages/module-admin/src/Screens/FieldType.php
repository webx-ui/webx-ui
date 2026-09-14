<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * What the server needs to know about a field type: not the component, but what kind of value
 * it produces, how to check it, and what to hand the site when it asks.
 */
interface FieldType
{
    /**
     * Validation rules for one value of this type. `$node` is the whole node, so a select can
     * read its options and a textarea its maximum length from `props`.
     *
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array;

    /**
     * What to keep: the incoming value, cast.
     *
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed;

    /**
     * What the site reads: `settings('seo.default-og')` answers with this.
     *
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node): mixed;
}
