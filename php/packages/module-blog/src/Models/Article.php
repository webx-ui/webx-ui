<?php

declare(strict_types=1);

namespace WebxUi\Blog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Versions\HasDraft;
use WebxUi\Admin\Versions\HasVersions;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\HasBlocks;
use WebxUi\Localization\HasTranslations;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\HasSeo;

/**
 * An article.
 *
 * Almost all of it is somebody else's: the address is `webx-ui/routing`, the content is
 * `webx-ui/module-blocks`, the draft and the history are `webx-ui/module-admin`, what it says
 * about itself is `webx-ui/module-seo`, the languages are `webx-ui/localization`. What is
 * written here is the three things that make an article an article rather than a page — it has
 * a date, it can be in several rubrics, and it carries tags.
 *
 * The date is the one worth reading twice. There is no `is_published` column and no scheduler:
 * `published_at` in the future means the article is waiting, in the past means it sits there in
 * the feed, and both are decided at the moment somebody reads it (§7).
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $slug
 * @property array<string, string>|string|null $lead
 * @property int|null $cover_id
 * @property int|null $author_id
 * @property bool $pinned
 * @property list<array<string, mixed>>|null $blocks
 * @property array<string, mixed>|null $draft
 * @property Carbon|null $published_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Article extends Model
{
    use HasBlocks;
    use HasCover;
    use HasDraft;
    use HasSeo;
    use HasTranslations;
    use HasUrl;
    use HasVersions {
        unversionedAttributes as private contentlessAttributes;
    }
    use SoftDeletes;

    /** Never on the site. */
    public const STATUS_DRAFT = 'draft';

    /** Dated ahead: written, finished, waiting for its day to come round (§7). */
    public const STATUS_SCHEDULED = 'scheduled';

    /** On the site, with nothing waiting. */
    public const STATUS_PUBLISHED = 'published';

    /** On the site, with edits that are not on it yet. */
    public const STATUS_MODIFIED = 'modified';

    /** Was on the site and was taken off it. */
    public const STATUS_UNPUBLISHED = 'unpublished';

    /** @var list<string> */
    protected $fillable = ['title', 'slug', 'lead', 'cover_id', 'author_id', 'pinned', 'blocks'];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug', 'lead'];
    }

    /**
     * On the site *now*, which is not the same as having a publication date (§7).
     *
     * `HasDraft` reads "published" as "`published_at` is not null", and for an article that is
     * only half the question: one dated next Tuesday has a stamp and is not on the site. Every
     * listing in this module therefore goes through {@see scopePublished()}, and every handler
     * through this — a general count of live records elsewhere in the panel will include a
     * scheduled article, and that is a thing to remember rather than a thing to fix here.
     */
    public function isPublished(): bool
    {
        $at = $this->getAttribute($this->publishedAtColumn());

        return $at instanceof Carbon && $at->lessThanOrEqualTo(Carbon::now());
    }

    /** Dated, but not yet. */
    public function isScheduled(): bool
    {
        $at = $this->getAttribute($this->publishedAtColumn());

        return $at instanceof Carbon && $at->greaterThan(Carbon::now());
    }

    /**
     * The articles a reader can see: stamped, and stamped no later than now.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereNotNull($this->publishedAtColumn())
            ->where($this->publishedAtColumn(), '<=', Carbon::now());
    }

    /**
     * The order every listing in the module uses: pinned first, newest first inside that (§5).
     *
     * `id` last so that two articles published in the same second do not swap places between
     * page one and page two, which is how a paginator loses a row and shows another twice.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInFeedOrder(Builder $query): Builder
    {
        return $query
            ->orderByDesc('pinned')
            ->orderByDesc($this->publishedAtColumn())
            ->orderByDesc('id');
    }

    /**
     * Never published · waiting · on the site · on the site with edits · taken off it (§10).
     *
     * The last one is the reason this is not two lines: an article that was never published and
     * one that was pulled both have an empty `published_at`, and only the history tells them
     * apart. An editor needs to — "draft" on something that was live this morning is a lie.
     */
    public function status(): string
    {
        if ($this->isScheduled()) {
            return self::STATUS_SCHEDULED;
        }

        if (! $this->isPublished()) {
            return $this->wasEverPublished() ? self::STATUS_UNPUBLISHED : self::STATUS_DRAFT;
        }

        return $this->hasDraft() ? self::STATUS_MODIFIED : self::STATUS_PUBLISHED;
    }

    /**
     * An article has an address in a language when it names a slug in it (§9).
     *
     * Without the override the trait says yes to every language, the formatter reads an empty
     * slug, and the article claims the address of the prefix itself — the feed's own. Saying no
     * is the honest answer: an article that was never translated has no Ukrainian address, not
     * an English one standing in front of Ukrainian nothing.
     */
    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }

    /**
     * @return list<string>
     */
    public function unversionedAttributes(): array
    {
        // Whether an article is pinned is a decision about the feed rather than about the text,
        // so restoring an older version must not unpin what somebody pinned this morning.
        return [...$this->contentlessAttributes(), 'pinned'];
    }

    /**
     * @return BelongsTo<CmsUser, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(CmsUser::class, 'author_id');
    }

    /**
     * The rubrics, in the order the editor dragged them into. The first is the main one (§2.6).
     *
     * @return BelongsToMany<Rubric, $this>
     */
    public function rubrics(): BelongsToMany
    {
        return $this->belongsToMany(Rubric::class, 'article_rubric')
            ->withPivot('position')
            ->orderBy('article_rubric.position')
            ->orderBy('rubrics.id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'article_tag')->orderBy('tags.id');
    }

    /**
     * The articles pinned to this one by hand, in the order they were dragged into (§8).
     *
     * @return BelongsToMany<static, $this>
     */
    public function related(): BelongsToMany
    {
        return $this->belongsToMany(static::class, 'article_related', 'article_id', 'related_id')
            ->withPivot('position')
            ->orderBy('article_related.position')
            ->orderBy('articles.id');
    }

    /**
     * Where this article belongs: the first rubric, or none when it is in none.
     *
     * Everything that needs one rubric asks here — the breadcrumbs, "more in this rubric" and
     * `<category>` in the RSS — so that the answer cannot differ between them.
     */
    public function mainRubric(): ?Rubric
    {
        /** @var Rubric|null $rubric */
        $rubric = $this->rubrics->first();

        return $rubric;
    }

    /** Has this article ever been on the site? Asked of the history, which is what remembers. */
    private function wasEverPublished(): bool
    {
        return $this->versions()->published()->exists();
    }
}
