<?php

declare(strict_types=1);

namespace WebxUi\Media\Screens;

use WebxUi\Admin\Screens\ChecksNewValues;
use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-file`: one file, shown as a card rather than as a picture.
 *
 * The same value as `wx-media`, kept and read the same way — what differs is that a document has
 * no `alt` worth asking for and no preview worth framing, so the field draws a glyph, a name and
 * a size instead. That is a decision about the field, which is why the server has nothing of its
 * own to say here.
 */
final class FileFieldType implements ChecksNewValues, FieldType
{
    public function __construct(private readonly MediaValues $values) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return $this->values->rules($node, $this->values->accept($node));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        return $this->values->store($value);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $this->values->resolve($stored, $locale);
    }

    /**
     * A key the library never had, in a value written now: a path typed rather than picked.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    public function newValueProblems(mixed $value, array $node): array
    {
        return $this->values->missing($value);
    }
}
