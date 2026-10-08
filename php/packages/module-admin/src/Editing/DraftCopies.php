<?php

declare(strict_types=1);

namespace WebxUi\Admin\Editing;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Support\Authors;
use WebxUi\Admin\Versions\EntityVersion;

/**
 * The copies of a draft between publications, newest first, each with what it changed.
 *
 * A copy is the whole draft, so its fields are always the same few — `title, slug, blocks` on
 * every line — and the person looking for the edit that a colleague's save wrote over learns
 * nothing from them. What tells the copies apart is the difference from the one before: each
 * is compared with the next older copy, and the oldest with the publication it started from.
 * Shared by the panel's «Drafts» list and the agents' `*_versions`.
 */
final class DraftCopies
{
    /**
     * @return list<array{id: int, kind: string, created_at: string|null, author: string|null, source: string, fields: list<string>, paths: list<list<array{field: string}|array{block: string, type: string}>>}>
     */
    public static function of(Model $model, mixed $reader = null): array
    {
        if (! method_exists($model, 'draftVersions')) {
            return [];
        }

        /** @var Collection<int, EntityVersion> $versions */
        $versions = $model->draftVersions()->get();
        $authors = Authors::names($reader, $versions->map(static fn (EntityVersion $version): ?int => $version->author_id));

        $published = method_exists($model, 'publishedVersions') ? $model->publishedVersions()->first() : null;
        // The publication the copies started from, over the columns for what it did not keep.
        $older = [...self::live($model), ...($published instanceof EntityVersion && is_array($published->payload) ? $published->payload : [])];

        $list = [];
        $last = [];

        // Oldest first, so that each is compared with the one before it.
        foreach ($versions->reverse() as $version) {
            $payload = is_array($version->payload) ? $version->payload : [];
            $paths = ChangedPaths::between(array_intersect_key($older, $payload), $payload);

            // A draft kept aside when somebody saved over it is the same draft as the autosave
            // before it: it says what that one changed, which is the edit it is there to save.
            if ($paths === [] && $list !== []) {
                $paths = $last;
            }

            $last = $paths;
            $fields = array_values(array_unique(array_map(
                static fn (array $path): string => isset($path[0]['field']) ? $path[0]['field'] : '',
                $paths,
            )));

            $list[] = [
                'id' => (int) $version->id,
                'kind' => (string) $version->kind,
                'created_at' => $version->created_at?->toAtomString(),
                'author' => $version->author_id === null ? null : ($authors[$version->author_id] ?? null),
                'source' => (string) $version->source,
                'fields' => array_values(array_filter($fields, static fn (string $field): bool => $field !== '')),
                'paths' => $paths,
            ];

            $older = $payload;
        }

        return array_reverse($list);
    }

    /**
     * What the columns hold, as stored — a record published before it kept a history has no
     * version to compare the first copy with, and every field of it read as changed.
     *
     * @return array<string, mixed>
     */
    private static function live(Model $model): array
    {
        $live = [];

        foreach ($model->getAttributes() as $key => $value) {
            if (is_string($value) && ($value[0] ?? '') !== '' && in_array($value[0], ['{', '['], true)) {
                $decoded = json_decode($value, true);
                $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            }

            $live[$key] = $value;
        }

        return $live;
    }
}
