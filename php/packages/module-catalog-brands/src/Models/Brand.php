<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Models;

use Carbon\CarbonInterface;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\Category;
use WebxUi\Admin\Categories\CategoryKind;
use WebxUi\Admin\Categories\IsCategory;
use WebxUi\Admin\History\RecordsHistory;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Filter\FilterAliases;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Storefront\ListingSubject;
use WebxUi\CatalogBrands\Catalog\BrandFacet;
use WebxUi\Localization\HasTranslations;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Media\Storage\FileUrls;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\SiteUrl;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\HasSeo;

/**
 * A brand: one per product, with a page of its own (§2.3 of the dictionaries spec).
 *
 * The shared category code of the panel ({@see IsCategory}) — a flat list ordered by hand, a
 * translated name, the refusal to delete a brand products still name — with what an entity that
 * has a page adds by the same traits the entities use: an address (`HasUrl`, `/brands/{slug}/`),
 * an SEO card (`HasSeo`), a logo from the media library and a journal. It is not the catalogue's
 * `Dictionary`: a brand has no code and no tone, and its slug — translated, live — is both its
 * address and its value in the filter's address.
 *
 * `is_visible` of the shared code is "published" here: hidden, the brand's page answers 404, the
 * filter leaves it out and a card does not print it, but its products keep it.
 *
 * Not `final`: PHPStan does not take the `$this` of a final class for the `$this` the trait's
 * relation promises (docs/pitfalls/laravel-and-php.md).
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $slug
 * @property array<string, string>|string|null $description
 * @property int|null $logo_id
 * @property bool $is_visible
 * @property bool $is_featured
 * @property int $position
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Brand extends Model implements Category, ListingSubject, Visible
{
    use HasExtra;
    use HasSeo;
    use HasTranslations;
    use HasUrl;
    use IsCategory {
        categoryValue as sharedCategoryValue;
        writeCategoryValue as writeSharedCategoryValue;
    }
    use RecordsHistory;
    use SoftDeletes;

    /** The type the journal and the address registry know it by. */
    public const TYPE = 'catalog.brand';

    /** The screen a brand is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'catalog.brand-form';

    /** Which brand each product is of; a product without a row has none. */
    public const LINKS = 'catalog_product_brand';

    /** The columns whose change changes what the products show or how they are found. */
    public const TOUCHING = ['title', 'slug', 'is_visible', 'logo_id'];

    /** @var array<array-key, mixed>|null the slugs a save in progress is replacing */
    private ?array $slugsBefore = null;

    /** The brand's own fields the journal names in words; the project's are named by their screen. */
    public const HISTORY = ['title', 'slug', 'description', 'logo_id', 'is_visible', 'is_featured'];

    protected $table = 'catalog_brands';

    /** @var list<string> */
    protected $fillable = ['title', 'slug', 'description', 'logo_id', 'is_visible', 'is_featured', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'logo_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // `_` marks the filter in an address (`/brands/apple/category_laptops`), so a slug holding
        // it would be read as a filter. In the model, so the form and an agent hit the same wall.
        static::saving(static function (self $brand): void {
            foreach ($brand->getTranslations('slug') as $slug) {
                if (is_string($slug) && str_contains($slug, '_')) {
                    throw ValidationException::withMessages(['slug' => [(string) __('webx-catalog-brands::errors.slug-underscore')]]);
                }
            }
        });

        // The slugs before the save, taken here: by the brand's own `updated` the registry of
        // addresses has already saved it again, and the raw originals hold the new ones.
        static::updating(static function (self $brand): void {
            $brand->slugsBefore = $brand->isDirty('slug') ? json_decode((string) $brand->getRawOriginal('slug'), true) : null;
        });

        // A rename, a new address, a publication: every product of the brand shows it, and its
        // document holds it — one statement marks them all, never a loop.
        static::updated(static function (self $brand): void {
            if ($brand->wasChanged(self::TOUCHING)) {
                Container::getInstance()->make(Catalog::class)->touchQuery($brand->affectedProducts());
            }

            if (is_array($brand->slugsBefore)) {
                $brand->recordFilterAliases($brand->slugsBefore);
                $brand->slugsBefore = null;
            }
        });
    }

    /** `brands`, as the registry spells it; the list's address and the start of every brand's. */
    public static function prefix(): string
    {
        return UrlNormaliser::key((string) (config('webx-catalog-brands.prefix') ?: 'brands'));
    }

    /** The list of brands, with the language prefix when the site uses one. */
    public static function listUrl(?string $locale = null): string
    {
        $prefix = Container::getInstance()->make(SiteUrl::class)->prefix($locale ?? app()->getLocale());

        return URL::to(UrlNormaliser::join($prefix, self::prefix()));
    }

    /**
     * Read by whoever may open the catalogue — the product form chooses from them — and written by
     * whoever may edit it (decision 8).
     */
    public static function categoryKind(): CategoryKind
    {
        return new CategoryKind(
            model: self::class,
            screen: self::SCREEN,
            view: ['catalog.view', 'catalog.manage'],
            manage: 'catalog.manage',
            prefix: static fn (): string => self::prefix(),
            noun: 'brand',
            plural: 'brands',
            items: 'products',
            single: true,
        );
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug', 'description'];
    }

    public function extraScreen(): string
    {
        return self::SCREEN;
    }

    /** A brand has an address in a language when it names a slug in it. */
    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }

    /** Published and out of the bin: the page's 404, the filter's value, the sitemap's line. */
    public function isVisible(?string $locale = null): bool
    {
        return $this->is_visible && ! $this->trashed();
    }

    public function visibleUpdatedAt(): ?CarbonInterface
    {
        return $this->updated_at;
    }

    /**
     * «Popular brands»: `brands()->featured()`.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_featured'), true);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, self::LINKS, 'brand_id', 'product_id');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->products();
    }

    /** @return BelongsTo<MediaFile, $this> */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'logo_id');
    }

    /**
     * The page's address in this language, or null when there is none — from the `routes` a list
     * loaded once rather than a query per brand.
     */
    public function pageUrl(string $locale): ?string
    {
        $canonical = $this->relationLoaded('routes')
            ? $this->routes->first(static fn (Route $route): bool => $route->locale === $locale && $route->kind === Route::CANONICAL)
            : $this->routeCanonical($locale);

        return $canonical instanceof Route ? $this->urlOf($canonical->path, $locale) : null;
    }

    /** What a template puts in `src`, or null — worked out each time, never stored. */
    public function logoUrl(): ?string
    {
        $logo = $this->logo;

        return $logo instanceof MediaFile ? Container::getInstance()->make(FileUrls::class)->url($logo) : null;
    }

    /**
     * The description as a page prints it: a document with every library picture pointed at where
     * it lives now, as the rubric's introduction is.
     */
    public function descriptionHtml(?string $locale = null): string
    {
        $stored = $this->getTranslation('description', $locale ?? app()->getLocale());

        if (! is_string($stored) || trim($stored) === '') {
            return '';
        }

        $type = Container::getInstance()->make(FieldTypes::class)->get('wx-rich-text');
        $resolved = $type === null ? $stored : $type->resolve($stored, [], $locale);

        return is_string($resolved) ? $resolved : $stored;
    }

    /**
     * Every product of the brand, for the engine to read again after an edit — a query, never a
     * list.
     *
     * @return Builder<Product>
     */
    public function affectedProducts(): Builder
    {
        return Product::withTrashed()->whereIn(
            'catalog_products.id',
            DB::table(self::LINKS)->select('product_id')->where('brand_id', $this->getKey()),
        );
    }

    /**
     * The list of brands, then this one.
     *
     * @return list<Crumb>
     */
    public function breadcrumbs(string $locale): array
    {
        return [
            new Crumb((string) __('webx-catalog-brands::module.title'), self::listUrl($locale)),
            new Crumb($this->displayName($locale), $this->url($locale)),
        ];
    }

    /**
     * The shared fields and the brand's own: the logo, the description, «featured» and the SEO
     * card.
     *
     * @return list<string>
     */
    public function categoryFields(): array
    {
        return ['title', 'slug', 'is_visible', 'is_featured', 'logo', 'description', 'seo'];
    }

    /**
     * The logo as `wx-media` holds it — a library key, never an address (CLAUDE.md §4) — and the
     * SEO card as its field type edits it.
     */
    public function categoryValue(string $field): mixed
    {
        return match ($field) {
            'logo' => $this->logo instanceof MediaFile ? ['path' => $this->logo->path] : null,
            'seo' => $this->seoValue(),
            default => $this->sharedCategoryValue($field),
        };
    }

    public function writeCategoryValue(string $field, mixed $value): void
    {
        match ($field) {
            'logo' => $this->logo_id = $this->logoIdOf($value),
            'is_featured' => $this->is_featured = (bool) $value,
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

    /** A brand products are of: deleting it would leave them with a brand that is not there. */
    public function inUseMessage(int $count): string
    {
        return (string) __('webx-catalog-brands::errors.in-use', ['count' => $count]);
    }

    /**
     * Everything but the place in the list: a drag is not an edit of the brand. The fields of the
     * project in `extra` stay followed.
     *
     * @return list<string>
     */
    public function historySkipped(): array
    {
        return ['position'];
    }

    /** The logo by the file's name rather than a key nobody reads. */
    public function historyValue(string $field, mixed $value): mixed
    {
        if ($field === 'logo_id' && $value !== null) {
            $file = MediaFile::query()->find($value);

            return $file instanceof MediaFile ? $file->name : '#'.$value;
        }

        return $value;
    }

    /**
     * The slugs this save replaced, as old spellings of the brand in the filter (§4.2 of the
     * properties spec): `/laptops/brand_old-name` keeps working, as a 301 to the new one.
     *
     * @param  array<array-key, mixed>  $before  language → slug, as the save found them
     */
    private function recordFilterAliases(array $before): void
    {
        $aliases = Container::getInstance()->make(FilterAliases::class);

        foreach ($before as $locale => $old) {
            $now = $this->getTranslation('slug', (string) $locale, false);

            if (is_string($old) && $old !== '' && $old !== $now) {
                $aliases->record(BrandFacet::KEY, (string) $locale, FilterAliases::VALUE, $old, (string) $this->getKey());
            }
        }
    }

    private function logoIdOf(mixed $value): ?int
    {
        $path = is_array($value) ? $value['path'] ?? null : null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        $file = Container::getInstance()->make(MediaFiles::class)->find($path);

        return $file === null ? null : (int) $file->getKey();
    }
}
