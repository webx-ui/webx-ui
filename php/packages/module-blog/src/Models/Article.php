<?php

declare(strict_types=1);

namespace WebxUi\Blog\Models;

use Carbon\CarbonInterface;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Categories\HasCategories;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Admin\Shortcodes\PageText;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Admin\Versions\HasDraft;
use WebxUi\Admin\Versions\HasVersions;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\HasBlocks;
use WebxUi\Blog\Seo\Trail;
use WebxUi\Blog\Support\PublishedAuthors;
use WebxUi\Localization\HasTranslations;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\Contracts\HasOpenGraph;
use WebxUi\Seo\Contracts\HasSeoFallback;
use WebxUi\Seo\Contracts\HasStructuredData;
use WebxUi\Seo\HasSeo;
use WebxUi\Seo\Panel\DefaultsSource;
use WebxUi\Seo\Rendering\SeoData;

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
class Article extends Model implements HasBreadcrumbs, HasOpenGraph, HasSeoFallback, HasStructuredData, Visible
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

    protected $table = 'blog_articles';

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

    protected static function booted(): void
    {
        // Who has something on the site, as this request knew it, is from before the write.
        $forget = static function (): void {
            Container::getInstance()->make(PublishedAuthors::class)->forget();
        };
        static::saved($forget);
        static::deleted($forget);
        static::restored($forget);
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
    /** The date of the last publication once read, or false until it was. */
    private string|null|false $lastPublishedAt = false;

    public function visibleUpdatedAt(): ?CarbonInterface
    {
        $at = $this->published_at;
        $republished = $this->lastPublishedAt();

        if ($at === null || $republished === null) {
            return $at ?? $republished;
        }

        return $republished->greaterThan($at) ? $republished : $at;
    }

    /**
     * When the text last reached the site. One query per article, asked once: the `<head>`
     * prints it twice (`article:modified_time`, `dateModified`). A list that loaded it as
     * `last_published_at` — the sitemap, through {@see scopeForSitemap()} — is not asked at all.
     */
    private function lastPublishedAt(): ?CarbonInterface
    {
        if ($this->lastPublishedAt === false) {
            $loaded = $this->attributes['last_published_at'] ?? $this->versions()->published()->max('created_at');
            $this->lastPublishedAt = is_string($loaded) ? $loaded : null;
        }

        return $this->lastPublishedAt === null ? null : Carbon::parse($this->lastPublishedAt);
    }

    /**
     * What a line of the sitemap asks of an article, for a chunk at once: the cover behind
     * `og:image` and the date of its last publication.
     *
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopeForSitemap(Builder $query): Builder
    {
        return $query
            ->with(['cover', 'routes'])
            ->withMax(['versions as last_published_at' => static fn (Builder $versions): Builder => $versions->where('kind', EntityVersion::KIND_PUBLISHED)], 'created_at');
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
     * The title, the lead and the cover — the same three the `BlogPosting` below names — for an
     * article whose SEO card leaves them empty.
     */
    public function seoFallback(?string $locale = null): ?SeoData
    {
        $title = $this->getTranslation('title', $locale);
        $lead = $this->getTranslation('lead', $locale);

        return SeoData::fallback(
            is_string($title) && trim($title) !== '' ? $title : null,
            is_string($lead) ? $lead : null,
            $this->coverUrl(),
        );
    }

    public function openGraphType(): string
    {
        return 'article';
    }

    /**
     * The dates the `BlogPosting` below names, the main rubric as the section, the tags — what a
     * network shows beside a shared article, from what the article already has.
     *
     * @return array<string, string|list<string>|null>
     */
    public function openGraphProperties(string $locale): array
    {
        $rubric = $this->mainRubric();
        $section = $rubric?->getTranslation('title', $locale);
        $tags = [];

        foreach ($this->tags as $tag) {
            $title = $tag->getTranslation('title', $locale);

            if (is_string($title) && trim($title) !== '') {
                $tags[] = trim($title);
            }
        }

        return [
            'article:published_time' => $this->published_at?->toAtomString(),
            'article:modified_time' => $this->visibleUpdatedAt()?->toAtomString(),
            'article:section' => is_string($section) ? $section : null,
            'article:tag' => $tags,
        ];
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
            'inLanguage' => $locale,
        ];

        $lead = $this->getTranslation('lead', $locale);
        // The lead takes shortcodes; JSON-LD is text, so each one is its plain rendering here.
        $description = is_string($lead) ? trim(Container::getInstance()->make(PageText::class)->plainFromHtml($lead)) : '';

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

        // The profile's Person where there is one — name, role, picture, and the address of the
        // page where it is open — the account's name where there is not (§19.4).
        $profile = Author::enabled() ? $this->authorProfile : null;

        if ($profile instanceof Author && $profile->name($locale) !== '') {
            $posting['author'] = $profile->person($locale);
        } elseif ($this->author instanceof CmsUser) {
            $posting['author'] = ['@type' => 'Person', 'name' => $this->author->name];
        }

        // The section it is filed under and the words it carries — the main rubric (§2.6) and the tags.
        $rubric = $this->mainRubric();
        $section = $rubric?->getTranslation('title', $locale);

        if (is_string($section) && trim($section) !== '') {
            $posting['articleSection'] = trim($section);
        }

        $keywords = $this->tags
            ->map(static fn (Tag $tag): string => trim((string) $tag->getTranslation('title', $locale)))
            ->filter(static fn (string $word): bool => $word !== '')
            ->values()
            ->all();

        if ($keywords !== []) {
            $posting['keywords'] = implode(', ', $keywords);
        }

        // Who publishes it: the site's organisation, by reference to the block `module-seo` prints
        // beside this one — and nothing where that block is not printed, since a reference to
        // nothing is a warning in every validator.
        $publisher = Container::getInstance()->make(DefaultsSource::class)->organizationId($locale);

        if ($publisher !== null) {
            $posting['publisher'] = ['@id' => $publisher];
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
     * What the site may say about the account that wrote it (§19) — found by the account, so the
     * article itself keeps pointing at `cms_users` and knows nothing about profiles.
     *
     * @return BelongsTo<Author, $this>
     */
    public function authorProfile(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id', 'user_id');
    }

    /**
     * The byline: the profile's name for the reader where there is a profile, the account's name
     * where there is not — which is what a site without author profiles has always printed
     * (§19.1, decision 4). Empty when nobody is named.
     */
    public function authorName(?string $locale = null): string
    {
        $profile = Author::enabled() ? $this->authorProfile : null;

        if ($profile instanceof Author && $profile->name($locale) !== '') {
            return $profile->name($locale);
        }

        return $this->author instanceof CmsUser ? (string) $this->author->name : '';
    }

    /**
     * Where the byline links to: the author's page, when the site allows them and the author asked
     * for one and it has an address in the language — null, and the name is text, otherwise
     * (decision 3). The one place that decides it; the views and the markup ask here.
     */
    public function authorUrl(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $profile = Author::enabled() ? $this->authorProfile : null;

        return $profile instanceof Author && $profile->isOpen() && $profile->hasUrlIn($locale)
            ? $profile->url($locale)
            : null;
    }

    /**
     * The rubrics, in the order the editor dragged them into. The first is the main one (§2.6).
     *
     * @return BelongsToMany<Rubric, $this>
     */
    public function rubrics(): BelongsToMany
    {
        /** @var BelongsToMany<Rubric, $this> $relation */
        $relation = $this->belongsToCategories(Rubric::class, 'blog_article_rubric');

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
        return $this->belongsToMany(Tag::class, 'blog_article_tag')->orderBy('blog_tags.id');
    }

    /**
     * The articles pinned to this one by hand, in the order they were dragged into (§8).
     *
     * @return BelongsToMany<static, $this>
     */
    public function related(): BelongsToMany
    {
        return $this->belongsToMany(static::class, 'blog_article_related', 'article_id', 'related_id')
            ->withPivot('position')
            ->orderBy('blog_article_related.position')
            ->orderBy('blog_articles.id');
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
