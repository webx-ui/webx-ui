<?php

declare(strict_types=1);

namespace WebxUi\Media\Screens;

use WebxUi\Admin\Screens\FieldType;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileUrls;

/**
 * `wx-media` on the server: the field stores what `WxMediaField` edits — the library key and
 * the captions — and the site reads it back with the address filled in. The address is never
 * stored: a library that moves from a public directory to S3 does not have to rewrite a setting.
 */
final class MediaFieldType implements FieldType
{
    public function __construct(private readonly FileUrls $urls) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'array:path,alt,title,url'];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        if (! is_array($value) || ! is_string($value['path'] ?? null) || $value['path'] === '') {
            return null;
        }

        $stored = ['path' => $value['path']];

        foreach (['alt', 'title'] as $caption) {
            if (isset($value[$caption]) && $value[$caption] !== '') {
                $stored[$caption] = $value[$caption];
            }
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        if (! is_array($stored) || ! is_string($stored['path'] ?? null)) {
            return null;
        }

        $file = MediaFile::query()->where('path', $stored['path'])->first();

        return $stored + ['url' => $file instanceof MediaFile ? $this->urls->url($file) : null];
    }
}
