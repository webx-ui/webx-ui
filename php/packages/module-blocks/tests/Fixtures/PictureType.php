<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests\Fixtures;

use WebxUi\Admin\Screens\FieldType;

/**
 * A field type of the shape `wx-media` has: it stores a key and hands the site the address
 * worked out from it. The real one lives in the media module, which the blocks do not depend
 * on — what is tested here is that the renderer asks the type at all.
 */
final class PictureType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'array'];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        return $value;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        if (! is_array($stored) || ! is_string($stored['path'] ?? null)) {
            return null;
        }

        return $stored + ['url' => 'https://files.example.test/'.$stored['path']];
    }
}
