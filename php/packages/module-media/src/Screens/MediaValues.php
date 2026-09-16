<?php

declare(strict_types=1);

namespace WebxUi\Media\Screens;

use Closure;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileUrls;
use WebxUi\Media\Support\MediaType;

/**
 * One element of a media field: what may arrive, what is kept, and what the site reads back.
 *
 * All four types hold the same element — `{ path, alt, title }` — so the rules, the store and
 * the resolve are written once here. What differs between `wx-media`, `wx-gallery`, `wx-file`
 * and `wx-files` is how many elements there are and what the field looks like, and neither of
 * those is the server's business.
 */
final class MediaValues
{
    /** The keys one element may arrive with; `url` is taken and then dropped by {@see self::store()}. */
    public const SHAPE = 'array:path,alt,title,url';

    public function __construct(
        private readonly MediaFiles $files,
        private readonly FileUrls $urls,
    ) {}

    /**
     * Rules for a field that holds one file.
     *
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node, ?string $accept): array
    {
        if ($this->localized($node)) {
            return [$this->refusal()];
        }

        return ['nullable', self::SHAPE, function (string $attribute, mixed $value, Closure $fail) use ($accept): void {
            $problem = $this->problem($value, $accept);

            if ($problem !== null) {
                $fail($problem);
            }
        }];
    }

    /**
     * Rules for a field that holds a list of them.
     *
     * The list arrives under one field name, so what is wrong with the seventh file has to say
     * "the seventh": "This field takes images only" on its own tells nobody which of twenty
     * pictures to go and find.
     *
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function listRules(array $node, ?string $accept): array
    {
        if ($this->localized($node)) {
            return [$this->refusal()];
        }

        $rules = ['nullable', 'array'];

        foreach (['max', 'min'] as $limit) {
            $value = $node['props'][$limit] ?? null;

            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $rules[] = $limit.':'.(int) $value;
            }
        }

        $rules[] = function (string $attribute, mixed $value, Closure $fail) use ($accept): void {
            if (! is_array($value)) {
                return;
            }

            // The kind of file is read off the row, and the row is fetched for the resolve
            // anyway — so the whole list is fetched here at once rather than a query per item.
            $this->files->load($this->paths($value));

            foreach (array_values($value) as $position => $item) {
                $problem = $this->problem($item, $accept);

                if ($problem !== null) {
                    $fail((string) __('webx-media::validation.item', [
                        'number' => $position + 1,
                        'message' => $problem,
                    ]));
                }
            }
        };

        return $rules;
    }

    /**
     * What is kept: the key, the captions, and nothing else.
     *
     * The address is never stored. A library that moves from a public directory to a bucket
     * would otherwise mean rewriting every article that ever used a picture.
     *
     * @return array<string, mixed>|null
     */
    public function store(mixed $value): ?array
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
     * The same for a list, in the order it was given and without the gaps.
     *
     * @return list<array<string, mixed>>
     */
    public function storeList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach (array_values($value) as $item) {
            $stored = $this->store($item);

            if ($stored !== null) {
                $items[] = $stored;
            }
        }

        return $items;
    }

    /**
     * What the site reads: the captions this entity stored, plus everything known about the file.
     *
     * The whole set rather than the address alone — a template needs `width` and `height` to
     * print an `<img>` that does not shift the page, and `size` with `extension` to label a link
     * to a document. There is nothing in a template to reach the library with, so what is not
     * handed over here is not available at all.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(mixed $stored): ?array
    {
        if (! is_array($stored) || ! is_string($stored['path'] ?? null)) {
            return null;
        }

        return $stored + $this->details($this->files->find($stored['path']));
    }

    /**
     * The same for a list, in one query rather than one per picture.
     *
     * @return list<array<string, mixed>>
     */
    public function resolveList(mixed $stored): array
    {
        if (! is_array($stored)) {
            return [];
        }

        $items = array_values($stored);

        $this->files->load($this->paths($items));

        $resolved = [];

        foreach ($items as $item) {
            $one = $this->resolve($item);

            if ($one !== null) {
                $resolved[] = $one;
            }
        }

        return $resolved;
    }

    /**
     * The library keys a list mentions, in one go.
     *
     * @param  array<int|string, mixed>  $items
     * @return list<string>
     */
    private function paths(array $items): array
    {
        $paths = [];

        foreach ($items as $item) {
            $path = is_array($item) ? ($item['path'] ?? null) : null;

            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * The kind of file a node says it takes, when it names one the library knows about.
     *
     * @param  array<string, mixed>  $node
     */
    public function accept(array $node): ?string
    {
        $accept = $node['props']['accept'] ?? null;

        return is_string($accept) && in_array($accept, MediaType::all(), true) ? $accept : null;
    }

    /**
     * What is wrong with one element, in words — `null` when nothing is.
     *
     * Existence is not checked. A file deleted from the library leaves the value it was written
     * into alone: `resolve` hands back no address, the field draws a broken card, and whoever is
     * editing sees which one fell out and takes it out. The other way round — a 422 — would mean
     * one deleted picture stops a page from being saved until it is found among twenty.
     */
    private function problem(mixed $value, ?string $accept): ?string
    {
        if (! is_array($value) || ! is_string($value['path'] ?? null) || $value['path'] === '') {
            return (string) __('webx-media::validation.shape');
        }

        if ($accept === null) {
            return null;
        }

        $file = $this->files->find($value['path']);

        // Checked against the row that is fetched for the resolve anyway, so there is no query
        // here that was not going to happen. A key the library does not have is nothing to check.
        if (! $file instanceof MediaFile || MediaType::of($file->mime) === $accept) {
            return null;
        }

        return (string) __('webx-media::validation.accept', [
            'kind' => (string) __('webx-media::validation.kind.'.$accept),
        ]);
    }

    /**
     * Everything known about the file behind a key, empty-handed when it is not there any more.
     *
     * `source` is deliberately not among them: those are bytes the panel itself serves, and the
     * one thing that needs them is the image editor.
     *
     * @return array<string, mixed>
     */
    private function details(?MediaFile $file): array
    {
        if (! $file instanceof MediaFile) {
            return [
                'url' => null,
                'thumb' => null,
                'name' => null,
                'extension' => null,
                'mime' => null,
                'size' => null,
                'width' => null,
                'height' => null,
            ];
        }

        return [
            'url' => $this->urls->url($file),
            'thumb' => $this->urls->thumbUrl($file),
            'name' => $file->name,
            'extension' => $file->extension,
            'mime' => $file->mime,
            'size' => $file->size,
            'width' => $file->width,
            'height' => $file->height,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function localized(array $node): bool
    {
        return ($node['localized'] ?? false) === true;
    }

    /**
     * A translated list of files would be a different set of pictures per language, which is a
     * decision about the entity and not about the field. What is translated here is the captions
     * inside the value, exactly as `wx-media` has always done it.
     */
    private function refusal(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            $fail((string) __('webx-media::validation.localized'));
        };
    }
}
