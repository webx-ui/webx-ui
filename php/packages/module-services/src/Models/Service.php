<?php

declare(strict_types=1);

namespace WebxUi\Services\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Categories\HasCategories;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Admin\Versions\HasDraft;
use WebxUi\Admin\Versions\HasVersions;
use WebxUi\Blocks\HasBlocks;
use WebxUi\Localization\HasTranslations;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\Contracts\HasStructuredData;
use WebxUi\Seo\HasSeo;
use WebxUi\Seo\Panel\DefaultsSource;
use WebxUi\Services\Seo\Trail;

/**
 * A service.
 *
 * Built like a page (§4): the content is blocks, the address is the registry's, the draft and the
 * history are `module-admin`'s, what it says about itself is `module-seo`'s. What is its own is
 * the catalogue around it — several categories, the first of them the main one, and two orders:
 * `position` here for the whole list, `item_position` on the link for its place inside each
 * category (§2.5).
 *
 * Unlike an article it has no date to be published under: live is live, and there is no
 * "scheduled" (§4.6).
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $slug
 * @property array<string, string>|string|null $lead
 * @property int|null $cover_id
 * @property int $position
 * @property list<array<string, mixed>>|null $blocks
 * @property array<string, mixed>|null $draft
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $published_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Service extends Model implements HasBreadcrumbs, HasStructuredData, Visible
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

    /** The screen a service is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'services.form';

    /** Never on the site. */
    public const STATUS_DRAFT = 'draft';

    /** On the site, with nothing waiting. */
    public const STATUS_PUBLISHED = 'published';

    /** On the site, with edits that are not on it yet. */
    public const STATUS_MODIFIED = 'modified';

    /** Was on the site and was taken off it. */
    public const STATUS_UNPUBLISHED = 'unpublished';

    /** @var list<string> */
    protected $fillable = ['title', 'slug', 'lead', 'cover_id', 'position', 'blocks'];

    protected static function booted(): void
    {
        static::creating(static function (Service $service): void {
            // At the end of the list: the only place a new service can go without moving one
            // somebody else put where it is.
            if (! array_key_exists('position', $service->getAttributes())) {
                $service->position = (int) static::query()->withTrashed()->max('position') + 1;
            }
        });
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug', 'lead'];
    }

    /**
     * @return list<string>
     */
    public function unversionedAttributes(): array
    {
        // Where a service stands in the list is a decision about the catalogue, not about the
        // text: restoring an older version must not move it.
        return [...$this->contentlessAttributes(), 'position'];
    }

    /**
     * A service has an address in a language when it names a slug in it (§4.10). Without this
     * the formatter reads an empty slug and the service claims the index's own address.
     */
    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }

    /** Published and not in the bin — the handler's 404 and the sitemap's line alike. */
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
        return $query->whereNotNull($this->qualifyColumn($this->publishedAtColumn()));
    }

    /** Every publication stamps the date again, so it is when the page last changed. */
    public function visibleUpdatedAt(): ?CarbonInterface
    {
        return $this->published_at;
    }

    /**
     * Never published · on the site · on the site with edits · taken off it (§4.6).
     *
     * Never published and taken off look the same in the columns; the history tells them apart.
     */
    public function status(): string
    {
        if (! $this->isPublished()) {
            return $this->versions()->published()->exists() ? self::STATUS_UNPUBLISHED : self::STATUS_DRAFT;
        }

        return $this->hasDraft() ? self::STATUS_MODIFIED : self::STATUS_PUBLISHED;
    }

    /**
     * The categories, in the order the editor dragged them into. The first is the main one.
     *
     * @return BelongsToMany<ServiceCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        /** @var BelongsToMany<ServiceCategory, $this> $relation */
        $relation = $this->belongsToCategories(ServiceCategory::class, 'service_category', 'service_id', 'category_id');

        return $relation;
    }

    public function categoryRelation(): string
    {
        return 'categories';
    }

    /** The first category — the one the breadcrumbs go through — or none. */
    public function mainServiceCategory(): ?ServiceCategory
    {
        $category = $this->mainCategory();

        return $category instanceof ServiceCategory ? $category : null;
    }

    public function extraScreen(): string
    {
        return self::SCREEN;
    }

    /**
     * Index → main category → the service (§4.5).
     *
     * The category drops out when it is hidden or has no address in this language: a step that
     * leads to a 404 is worse than one step fewer. The service itself stays reachable either way
     * — it is not the category's property.
     *
     * @return list<Crumb>
     */
    public function breadcrumbs(string $locale): array
    {
        $category = $this->mainServiceCategory();
        $categoryCrumb = $category !== null && $category->isVisible($locale) && $category->hasUrlIn($locale)
            ? new Crumb((string) $category->getTranslation('title', $locale), $category->url($locale))
            : null;

        return Trail::of($locale, $categoryCrumb, new Crumb((string) $this->getTranslation('title', $locale), $this->url($locale)));
    }

    /**
     * A `Service`: its name, the announcement, the address, the cover, and the site's organisation
     * as the provider — by `@id`, not a copy of its fields (§4.5).
     *
     * @return list<array<string, mixed>>
     */
    public function structuredData(string $locale): array
    {
        $service = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => (string) $this->getTranslation('title', $locale),
            'url' => $this->url($locale),
        ];

        $lead = $this->getTranslation('lead', $locale);
        $description = is_string($lead) ? trim($lead) : '';

        if ($description !== '') {
            $service['description'] = $description;
        }

        $cover = $this->coverUrl();

        if ($cover !== null) {
            $service['image'] = $cover;
        }

        $organisation = app(DefaultsSource::class)->organizationId($locale);

        if ($organisation !== null) {
            $service['provider'] = ['@id' => $organisation];
        }

        return [$service];
    }
}
