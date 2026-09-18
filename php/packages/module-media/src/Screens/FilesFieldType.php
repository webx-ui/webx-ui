<?php

declare(strict_types=1);

namespace WebxUi\Media\Screens;

use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-files`: a list of files, as cards, in the order they were dragged into.
 *
 * The list a page attaches its downloads to. What may go in it is whatever `props.accept` says —
 * unlike a gallery, which is pictures by definition.
 */
final class FilesFieldType implements FieldType
{
    public function __construct(private readonly MediaValues $values) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return $this->values->listRules($node, $this->values->accept($node));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        return $this->values->storeList($value);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $this->values->resolveList($stored, $locale);
    }
}
