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
    /**
     * Files already looked up in this request, by key — including the ones that were not found.
     *
     * A page of blocks asks for the same picture as often as it prints it, and a gallery asks
     * for a dozen in a row; one query each is a dozen queries for one screenful. What is kept
     * is the row, never the address: a private bucket's address is signed and expires, so it
     * has to be worked out again every time it is asked for.
     *
     * @var array<string, MediaFile|null>
     */
    private array $found = [];

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

        $file = $this->file($stored['path']);

        return $stored + ['url' => $file instanceof MediaFile ? $this->urls->url($file) : null];
    }

    /** Between two responses of one process the library may well have changed. */
    public function flush(): void
    {
        $this->found = [];
    }

    private function file(string $path): ?MediaFile
    {
        if (! array_key_exists($path, $this->found)) {
            $this->found[$path] = MediaFile::query()->where('path', $path)->first();
        }

        return $this->found[$path];
    }
}
