<?php

declare(strict_types=1);

namespace WebxUi\Blog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Categories\Category;
use WebxUi\Admin\Categories\CategoryKind;
use WebxUi\Admin\Categories\IsCategory;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Blog\Seo\Trail;
use WebxUi\Localization\HasTranslations;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\HasSeo;

/**
 * A rubric: a section of the blog, flat, ordered by hand.
 *
 * Flat on purpose (§2.4). A tree of rubrics gets filled to one level out of three, and the
 * cross-cutting themes it was built for are what tags already do. One consequence of that
 * decision does the rest of the work: since an article can be in three rubrics at once, none of
 * them can be in its address (§2.5) — "which of the three" has no answer, and any answer would
 * be a hidden main rubric that moved the article when somebody reordered the checkboxes.
 *
 * No draft and no history either: what a rubric has instead is `is_visible`. Hidden, it drops
 * out of the menu and answers 404; its articles go on answering at their own addresses, because
 * they are not its property.
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $slug
 * @property array<string, string>|string|null $lead
 * @property int|null $cover_id
 * @property bool $is_visible
 * @property int $position
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Rubric extends Model implements Category, HasBreadcrumbs, Visible
{
    use HasCover;
    use HasExtra;
    use HasSeo;
    use HasTranslations;
    use HasUrl;
    use IsCategory {
        categoryValue as sharedCategoryValue;
        writeCategoryValue as writeSharedCategoryValue;
    }
    use SoftDeletes;

    /** The screen a rubric is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'blog.category-form';

    /** @var list<string> */
    protected $fillable = ['title', 'slug', 'lead', 'cover_id', 'is_visible', 'position'];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug', 'lead'];
    }

    /**
     * A rubric has an address in a language when it names a slug in it (§9), for the same
     * reason an article does.
     */
    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }

    /**
     * The introduction as a page prints it: a document, with every library picture pointed at
     * where it lives now.
     *
     * The column holds what the editor wrote, and what it wrote records a picture by its key
     * rather than by its address (CLAUDE.md §4) — a signed link expires within the hour and a
     * cropped picture keeps its key. So the addresses are worked out on every read, by the
     * same field type that cleaned the document on the way in. A panel with no `module-admin`
     * types registered at all gets the document as it was stored, which is the honest fallback:
     * the words are right and the pictures are wherever they were.
     */
    public function leadHtml(?string $locale = null): string
    {
        $stored = $this->getTranslation('lead', $locale ?? app()->getLocale());

        if (! is_string($stored) || trim($stored) === '') {
            return '';
        }

        $type = app(FieldTypes::class)->get('wx-rich-text');
        $resolved = $type === null ? $stored : $type->resolve($stored, [], $locale);

        return is_string($resolved) ? $resolved : $stored;
    }

    /** Shown, and not in the bin — the handler's 404 and the sitemap's line (§17.1 of the SEO spec). */
    public function isVisible(?string $locale = null): bool
    {
        return $this->is_visible && ! $this->trashed();
    }

    /**
     * Feed → the rubric (§17.5 of the SEO spec).
     *
     * @return list<Crumb>
     */
    public function breadcrumbs(string $locale): array
    {
        return Trail::of($locale, new Crumb((string) $this->getTranslation('title', $locale), $this->url($locale)));
    }

    /** A rubric has no publication of its own: its page changes when the rubric is saved. */
    public function visibleUpdatedAt(): ?CarbonInterface
    {
        return $this->updated_at;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInMenuOrder(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    /**
     * @return BelongsToMany<Article, $this>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_rubric')->withPivot(['position', 'item_position']);
    }

    /**
     * @return BelongsToMany<Article, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->articles();
    }

    /** How many articles would be left without this rubric — the number the refusal names. */
    public function articleCount(): int
    {
        return $this->itemCount();
    }

    /**
     * What a rubric is to the code every module's categories share: edited on its own screen,
     * read by anybody who may open an article (the article form lists them), written by whoever
     * looks after the taxonomy.
     */
    public static function categoryKind(): CategoryKind
    {
        return new CategoryKind(
            model: self::class,
            screen: self::SCREEN,
            view: ['blog.articles.view', 'blog.articles.manage'],
            manage: 'blog.taxonomy.manage',
            prefix: static fn (): string => (string) config('webx-blog.prefix', 'blog'),
            noun: 'rubric',
            plural: 'rubrics',
            items: 'articles',
        );
    }

    public function extraScreen(): string
    {
        return self::SCREEN;
    }

    /**
     * The shared fields and the blog's own three: the introduction, the picture, and the SEO
     * card `module-seo` patches onto the screen.
     *
     * @return list<string>
     */
    public function categoryFields(): array
    {
        return ['title', 'slug', 'is_visible', 'lead', 'cover', 'seo'];
    }

    /**
     * The picture as `wx-media` holds it — a library key, never an address (CLAUDE.md §4) — and
     * the SEO card as its field type edits it.
     */
    public function categoryValue(string $field): mixed
    {
        return match ($field) {
            'cover' => $this->cover instanceof MediaFile ? ['path' => $this->cover->path] : null,
            'seo' => $this->seoValue(),
            default => $this->sharedCategoryValue($field),
        };
    }

    public function writeCategoryValue(string $field, mixed $value): void
    {
        match ($field) {
            'cover' => $this->cover_id = $this->coverIdOf($value),
            // Into its own table, after the save: see categorySaved().
            'seo' => null,
            default => $this->writeSharedCategoryValue($field, $value),
        };
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function categorySaved(array $values): void
    {
        // Only when the card travelled: a save of the rest of the form must not empty a card
        // nobody opened.
        if (array_key_exists('seo', $values)) {
            $this->saveSeo(is_array($values['seo']) && $values['seo'] !== [] ? $values['seo'] : null);
        }
    }

    /** A rubric with articles in it (§6): deleting it would leave them without a section. */
    public function inUseMessage(int $count): string
    {
        return (string) trans('webx-blog::errors.rubric-in-use', ['count' => $count]);
    }

    /**
     * The library key the media field holds, as the id of a row: the address is never stored.
     */
    private function coverIdOf(mixed $value): ?int
    {
        $path = is_array($value) ? $value['path'] ?? null : null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        $file = app(MediaFiles::class)->find($path);

        return $file === null ? null : (int) $file->getKey();
    }
}
