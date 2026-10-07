<?php

declare(strict_types=1);

namespace WebxUi\Admin\Versions;

use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An entity that is edited in a draft and published on purpose.
 *
 * Three places, and one rule that keeps them apart: the columns are what is on the site now,
 * the `draft` column is what is being prepared, and the versions (when the model has them) are
 * the site's past. The site reads the columns; the preview reads the draft; publishing copies
 * the draft into the columns and writes a version. A record that was never published works by
 * the same rule with no special case — its columns are empty, its draft is full, and
 * `published_at` is null, which is what a handler answers 404 by.
 *
 *     Schema::table('pages', fn (Blueprint $table) => $table->draft());
 *
 *     class Page extends Model
 *     {
 *         use HasDraft, HasVersions;
 *     }
 *
 *     $page->saveDraft(['title' => 'New', 'blocks' => [...]]);   // the panel, on every save
 *     $page->withDraft()->title;                                  // 'New' — what the preview shows
 *     $page->title;                                               // still what the site shows
 *     $page->publish(authorId: 7);                                // now the site shows 'New'
 *
 * There is exactly one draft. Two people editing one page at once is a problem this does not
 * solve, and does not pretend to.
 *
 * @mixin Model
 */
trait HasDraft
{
    public function initializeHasDraft(): void
    {
        $this->mergeCasts([
            $this->draftColumn() => 'array',
            $this->publishedAtColumn() => 'datetime',
        ]);
    }

    /** Which column holds the draft. Override in a model that spells it differently. */
    public function draftColumn(): string
    {
        return 'draft';
    }

    public function publishedAtColumn(): string
    {
        return 'published_at';
    }

    public function hasDraft(): bool
    {
        return $this->draftValues() !== [];
    }

    /**
     * What is being prepared, keyed by attribute; translatable attributes as their whole map.
     *
     * @return array<string, mixed>
     */
    public function draftValues(): array
    {
        $draft = $this->getAttribute($this->draftColumn());

        return is_array($draft) ? $draft : [];
    }

    public function isPublished(): bool
    {
        return $this->getAttribute($this->publishedAtColumn()) instanceof Carbon;
    }

    /**
     * Replace the draft. The whole of it, not a merge: what the form sends is the form, and a
     * field the editor cleared has to come out empty, not keep its last value.
     *
     * With {@see HasVersions} on the model this also writes an autosave into the ring.
     *
     * @param  array<string, mixed>  $values
     */
    public function saveDraft(array $values, ?int $authorId = null, string $source = EntityVersion::SOURCE_PANEL): static
    {
        // A draft that says what the site already says is no draft: a letter typed and taken back
        // between two autosaves left the page «modified» with nothing to publish, and publishing
        // it wrote a version identical to the one before.
        if ($values !== [] && $this->isPublished() && $this->matchesLive($values)) {
            $values = [];
        }

        $this->setAttribute($this->draftColumn(), $values === [] ? null : $values);
        $this->save();

        if ($values !== [] && $this->hasVersions()) {
            $this->writeVersion(EntityVersion::KIND_AUTOSAVE, $authorId, $source, null, $values);
        }

        return $this;
    }

    public function discardDraft(): static
    {
        $this->setAttribute($this->draftColumn(), null);
        $this->save();

        return $this;
    }

    /**
     * A copy of the entity with the draft laid over its columns — what a preview renders.
     *
     * A copy, and never saved: the columns of the real row stay what the site shows, and
     * whoever holds the copy cannot publish by accident with a `save()`.
     */
    public function withDraft(): static
    {
        $copy = clone $this;

        $copy->applyDraft($this->draftValues());

        return $copy;
    }

    /**
     * Copy the draft into the columns, stamp the publication, and clear the draft — then, with
     * {@see HasVersions}, write the publication into the history and drop the autosaves it was
     * insuring. One transaction: a version without the columns to match is a lie in the history.
     *
     * `$at` is the date the entity is published *under*, which is not always now: an article
     * dated next Tuesday, or one backdated to when it was actually written. It cannot travel
     * through the draft — {@see applyDraft()} skips this column on purpose, so that saving a
     * draft never puts anything on the site — and the alternative is a second save right after
     * publishing, which would leave a version in the history carrying the wrong date. Whether a
     * date in the future means published is the entity's own question to answer; all this does
     * is write what it was given.
     */
    public function publish(?int $authorId = null, string $source = EntityVersion::SOURCE_PANEL, ?string $comment = null, ?CarbonInterface $at = null): static
    {
        // Already on the site as it is, and no new date asked for: nothing to publish, and a
        // version identical to the last one would only make the history longer.
        if ($at === null && $this->isPublished() && $this->matchesLive($this->draftValues())) {
            if ($this->getAttribute($this->draftColumn()) !== null) {
                $this->setAttribute($this->draftColumn(), null);
                $this->save();
            }

            return $this;
        }

        // A trait that keeps something in the draft may refuse it here, before anything moves:
        // `module-blocks` checks the blocks of a draft written before its checks existed, so that
        // publishing is not the door an old invalid value walks onto the site through.
        $this->fireModelEvent('publishing');

        $this->getConnection()->transaction(function () use ($authorId, $source, $comment, $at): void {
            $this->applyDraft($this->draftValues());
            $this->setAttribute($this->draftColumn(), null);
            $this->setAttribute($this->publishedAtColumn(), $at ?? Carbon::now());
            $this->save();

            if ($this->hasVersions()) {
                $this->versions()->autosaves()->delete();
                $this->writeVersion(EntityVersion::KIND_PUBLISHED, $authorId, $source, $comment);
            }
        });

        return $this;
    }

    /** Take the entity off the site; the columns and the draft stay as they are. */
    public function unpublish(): static
    {
        $this->setAttribute($this->publishedAtColumn(), null);
        $this->save();

        return $this;
    }

    /**
     * Whether these draft values are what the columns already hold — each attribute compared as
     * stored, with maps compared regardless of key order, so a translation map written in another
     * order is still the same text.
     *
     * @param  array<string, mixed>  $values
     */
    public function matchesLive(array $values): bool
    {
        return $this->changedFields($values) === [];
    }

    /**
     * The draft's fields that differ from what the site shows — the draft's own when none are
     * given. What a discard would throw away, named the way the history names a change.
     *
     * @param  array<string, mixed>|null  $values
     * @return list<string>
     */
    public function changedFields(?array $values = null): array
    {
        $values ??= $this->draftValues();
        $drafted = clone $this;
        $drafted->applyDraft($values);
        $changed = [];

        foreach (array_keys($values) as $attribute) {
            $attribute = (string) $attribute;

            // A key that is not a column — relations, a choice another table keeps — is applied by
            // whoever put it there on publication, and this cannot tell whether it changed.
            if (! array_key_exists($attribute, $this->getAttributes())
                || self::comparable($drafted, $attribute) !== self::comparable($this, $attribute)) {
                $changed[] = $attribute;
            }
        }

        return $changed;
    }

    /**
     * An attribute as the model reads it, in one spelling, for comparing.
     *
     * Through the casts rather than as stored: a draft holds what the form sent — `false`, an ISO
     * date with an offset, `12.5` — and the column what the database gave back — `0`, a datetime
     * string, `"12.50"`. Compared raw, an event's untouched `all_day` read as a change. A
     * translatable attribute is the exception, since reading it gives one language: it is
     * compared as its whole map.
     */
    private static function comparable(Model $model, string $attribute): string
    {
        if (method_exists($model, 'isTranslatableAttribute') && $model->isTranslatableAttribute($attribute)) {
            $value = $model->getAttributes()[$attribute] ?? null;

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                $value = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : $value;
            }
        } else {
            $value = $model->getAttribute($attribute);
        }

        $normal = static function (mixed $value) use (&$normal): mixed {
            if ($value instanceof CarbonInterface) {
                return $value->getTimestamp();
            }

            if ($value instanceof BackedEnum) {
                return $value->value;
            }

            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            }

            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }

            if (! is_array($value)) {
                return $value;
            }

            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($normal, $value);
        };

        // Empty is empty however it was written: a page with no blocks stores null, and the form
        // sends that back as an empty list.
        if ($value === [] || $value === '') {
            $value = null;
        }

        return (string) json_encode($normal($value), JSON_UNESCAPED_UNICODE);
    }

    /** Whether the model keeps a history too. Asked of the class, not of a method, on purpose. */
    protected function hasVersions(): bool
    {
        return in_array(HasVersions::class, class_uses_recursive($this), true);
    }

    /**
     * Lay values over the attributes. Through `setAttribute()` rather than `fill()`, so that a
     * guarded attribute is not silently skipped and a translatable one gets its whole map.
     *
     * @param  array<string, mixed>  $values
     */
    protected function applyDraft(array $values): void
    {
        foreach ($values as $attribute => $value) {
            if ($attribute === $this->draftColumn() || $attribute === $this->publishedAtColumn() || $attribute === $this->getKeyName()) {
                continue;
            }

            $this->setAttribute($attribute, $value);
        }
    }
}
