<?php

declare(strict_types=1);

namespace WebxUi\Blog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Categories\HasCategories;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Admin\Versions\HasDraft;
use WebxUi\Admin\Versions\HasVersions;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\HasBlocks;
use WebxUi\Blog\Seo\Trail;
use WebxUi\Localization\HasTranslations;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\Contracts\HasStructuredData;
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
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $published_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Article extends Model implements HasBreadcrumbs, HasStructuredData, Visible
{
    use HasBlocks;
    use HasCategories;
    use HasCover;
    use HasDraft;
    use HasExtra;
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
     * On the site now and not in the bin — the handler and the sitemap ask this and nothing else
     * (§17.1 of the SEO spec), so that "not yet" is a 404 and a missing line in the map at the
     * same minute.
     */
    public function isVisible(?string $locale = null): bool
    {
        return $this->isPublished() && ! $this->trashed();
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeVisible(Builder $query, ?string $locale = null): Builder
    {
        $column = $this->qualifyColumn($this->publishedAtColumn());

        return $query->whereNotNull($column)->where($column, '<=', Carbon::now());
    }

    /**
     * The later of the date it is published under and its last publication.
     *
     * The date alone is not enough: it is the date a reader is shown, and republishing an
     * article with a corrected paragraph keeps it — a backdated date is the whole point of it.
     * The history knows when the text last reached the site. One query per article, which the
     * sitemap pays once per build rather than per visit.
     */
    public function visibleUpdatedAt(): ?CarbonInterface
    {
        $at = $this->published_at;
        $last = $this->versions()->published()->max('created_at');
        $republished = is_string($last) ? Carbon::parse($last) : null;

        if ($at === null || $republished === null) {
            return $at ?? $republished;
        }

        return $republished->greaterThan($at) ? $republished : $at;
    }

    /**
     * Feed → main rubric → the article (§17.5 of the SEO spec).
     *
     * The rubric is {@see mainRubric()} — the same one the page names above the title — and it
     * drops out of the trail when it is hidden or has no address in this language: a step that
     * leads to a 404 is worse than one step fewer.
     *
     * @return list<Crumb>
     */
    public function breadcrumbs(string $locale): array
    {
        $rubric = $this->mainRubric();
        $rubricCrumb = $rubric !== null && $rubric->isVisible($locale) && $rubric->hasUrlIn($locale)
            ? new Crumb((string) $rubric->getTranslation('title', $locale), $rubric->url($locale))
            : null;

        return Trail::of($locale, $rubricCrumb, new Crumb((string) $this->getTranslation('title', $locale), $this->url($locale)));
    }

    /**
     * A `BlogPosting`: what it is called, when it came out and last changed, who wrote it, its
     * cover. Only what the article actually has — an empty `image` is a warning in every
     * validator, a missing one is not.
     *
     * @return list<array<string, mixed>>
     */
    public function structuredData(string $locale): array
    {
        $url = $this->url($locale);
        $posting = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => (string) $this->getTranslation('title', $locale),
            'url' => $url,
            'mainEntityOfPage' => $url,
        ];

        $lead = $this->getTranslation('lead', $locale);
        $description = is_string($lead) ? trim(html_entity_decode(strip_tags($lead), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';

        if ($description !== '') {
            $posting['description'] = $description;
        }

        if ($this->published_at !== null) {
            $posting['datePublished'] = $this->published_at->toAtomString();
        }

        $modified = $this->visibleUpdatedAt();

        if ($modified !== null) {
            $posting['dateModified'] = $modified->toAtomString();
        }

        $cover = $this->coverUrl();

        if ($cover !== null) {
            $posting['image'] = $cover;
        }

        $author = $this->author;

        if ($author instanceof CmsUser) {
            $posting['author'] = ['@type' => 'Person', 'name' => $author->name];
        }

        return [$posting];
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
        /** @var BelongsToMany<Rubric, $this> $relation */
        $relation = $this->belongsToCategories(Rubric::class, 'article_rubric');

        return $relation;
    }

    public function categoryRelation(): string
    {
        return 'rubrics';
    }

    /**
     * Newest first: the order a rubric page lists its articles in. An article filed into a rubric
     * takes its place there by this order — the blog never lets anybody drag them.
     *
     * @return array<string, 'asc'|'desc'>
     */
    public function categoryItemOrder(): array
    {
        return [$this->publishedAtColumn() => 'desc', $this->getKeyName() => 'desc'];
    }

    /** The fields a project patches onto the editor live in `extra`, and go through the draft. */
    public function extraScreen(): string
    {
        return 'blog.article-form';
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
        $rubric = $this->mainCategory();

        return $rubric;
    }

    /** Has this article ever been on the site? Asked of the history, which is what remembers. */
    private function wasEverPublished(): bool
    {
        return $this->versions()->published()->exists();
    }
}
