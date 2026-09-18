<?php

declare(strict_types=1);

namespace WebxUi\Media\Screens;

use WebxUi\Admin\Screens\FieldType;
use WebxUi\Media\Support\MediaType;

/**
 * `wx-gallery`: a list of pictures, in an order somebody dragged them into.
 *
 * Pictures and nothing else, whatever `props` says: a gallery that quietly holds a PDF is a
 * template printing an `<img>` at a document. A list of files that takes anything is `wx-files`,
 * and it is a separate type because that is a different thing to choose from a menu.
 */
final class GalleryFieldType implements FieldType
{
    public function __construct(private readonly MediaValues $values) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return $this->values->listRules($node, MediaType::IMAGE);
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
