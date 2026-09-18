<?php

declare(strict_types=1);

namespace WebxUi\Media\Screens;

use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-media` on the server: one picture, in a frame.
 *
 * The field stores what `WxMediaField` edits — the library key and the captions — and the site
 * reads it back with everything known about the file filled in. The address is never stored: a
 * library that moves from a public directory to S3 does not have to rewrite a single article.
 */
final class MediaFieldType implements FieldType
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
}
