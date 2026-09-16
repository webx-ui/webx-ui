<?php

declare(strict_types=1);

namespace WebxUi\Admin\Versions;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

/**
 * An entity with a history.
 *
 * The history is made of publications: every `publish()` of {@see HasDraft} writes a numbered
 * snapshot here, and intermediate saves do not — a page with blocks weighs kilobytes, an
 * autosave every half minute would be thousands of rows a month, and a history without events
 * in it cannot be read. What a save writes instead is an autosave, a ring of the last few copies
 * of the draft, kept apart from the history and never shown as part of it.
 *
 * The snapshot is `versionPayload()`: every attribute except the key, the timestamps, the draft
 * and whatever describes the entity's place rather than its content — its slug, its position in
 * a tree. Structure is applied at once, not at publication (a draft that moves when published
 * is a tree in two states, which no screen can show), so it has no business in a version.
 * Override `unversionedAttributes()` on a model that spells those columns differently, or
 * `versionedAttributes()` to name the snapshot outright.
 *
 * Rolling back is not "restore": `restoreVersion()` puts the old snapshot into the draft, and
 * publishing it is the ordinary way — with a new number, so that the history stays a line.
 * Which is why this trait goes with {@see HasDraft}: the history is written by `publish()`,
 * and a version is restored into the draft.
 *
 * @mixin Model
 */
trait HasVersions
{
    /** @return MorphMany<EntityVersion, $this> */
    public function versions(): MorphMany
    {
        return $this->morphMany(EntityVersion::class, 'versionable', 'versionable_type', 'versionable_id')
            ->orderByDesc('id');
    }

    /**
     * The history: the publications, newest first.
     *
     * @return MorphMany<EntityVersion, $this>
     */
    public function publishedVersions(): MorphMany
    {
        return $this->versions()->published()->reorder()->orderByDesc('number');
    }

    /**
     * What a version is a snapshot of: every attribute the row has, less the unversioned ones.
     *
     * @return list<string>
     */
    public function versionedAttributes(): array
    {
        $skip = [
            $this->getKeyName(),
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
            'deleted_at',
            ...$this->unversionedAttributes(),
        ];

        return array_values(array_diff(array_keys($this->getAttributes()), array_filter($skip)));
    }

    /**
     * What is never in a version: the draft and its stamp, the entity's place in a tree, its
     * slug. By name rather than by asking the other traits, so that a model without a tree or
     * an address is not asked about columns it does not have.
     *
     * @return list<string>
     */
    public function unversionedAttributes(): array
    {
        return ['draft', 'published_at', 'lft', 'rgt', 'depth', 'parent_id', 'slug'];
    }

    /**
     * The snapshot, as it would be written: a translatable attribute as its whole map, a
     * JSON column as its array.
     *
     * @return array<string, mixed>
     */
    public function versionPayload(): array
    {
        $payload = [];

        foreach ($this->versionedAttributes() as $attribute) {
            $payload[$attribute] = $this->versionedValue($attribute);
        }

        return $payload;
    }

    /**
     * Write a snapshot of what the columns hold right now.
     *
     * A publication takes the next number and, once written, prunes the history to the limit;
     * an autosave takes no number and trims the ring instead.
     *
     * @param  array<string, mixed>|null  $payload  What to write instead of the columns.
     */
    public function writeVersion(
        string $kind = EntityVersion::KIND_PUBLISHED,
        ?int $authorId = null,
        string $source = EntityVersion::SOURCE_PANEL,
        ?string $comment = null,
        ?array $payload = null,
    ): EntityVersion {
        $published = $kind === EntityVersion::KIND_PUBLISHED;

        return $this->getConnection()->transaction(function () use ($kind, $authorId, $source, $comment, $payload, $published): EntityVersion {
            $number = $published
                ? ((int) $this->versions()->published()->max('number')) + 1
                : null;

            /** @var EntityVersion $version */
            $version = $this->versions()->create([
                'number' => $number,
                'kind' => $kind,
                'payload' => $payload ?? $this->versionPayload(),
                'author_id' => $authorId,
                'source' => $source,
                'comment' => $comment,
            ]);

            $this->pruneVersions();

            return $version;
        });
    }

    /**
     * Put an old snapshot into the draft, to be published the ordinary way.
     *
     * @param  EntityVersion|int  $version  The version, or the number of a publication.
     */
    public function restoreVersion(EntityVersion|int $version): EntityVersion
    {
        $found = $version instanceof EntityVersion
            ? $version
            : $this->versions()->published()->where('number', $version)->first();

        if (! $found instanceof EntityVersion || (int) $found->versionable_id !== (int) $this->getKey()) {
            throw new LogicException(sprintf('%s has no version %s.', static::class, $version instanceof EntityVersion ? $version->getKey() : $version));
        }

        $this->saveDraft($found->payload, $found->author_id, EntityVersion::SOURCE_PANEL);

        return $found;
    }

    /**
     * Trim the history to the limit and the ring to its size. Pinned versions do not count and
     * are never removed; the newest ones stay.
     *
     * @return int How many rows went.
     */
    public function pruneVersions(): int
    {
        return Container::getInstance()->make(VersionPruner::class)->prune(
            $this->getMorphClass(),
            $this->getKey(),
        );
    }

    /**
     * The raw column decoded rather than the attribute read: a translatable attribute reads as
     * one language and is stored as the map of all of them, and the map is what a version is.
     */
    private function versionedValue(string $attribute): mixed
    {
        $raw = $this->getAttributes()[$attribute] ?? null;

        if (is_string($raw) && $this->hasCast($attribute, ['array', 'json'])) {
            return json_decode($raw, true);
        }

        return $this->getAttribute($attribute);
    }
}
