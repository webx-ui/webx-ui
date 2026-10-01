<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Models;

use Carbon\CarbonInterface;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\History\RecordsHistory;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Storefront\HasDefaultSort;
use WebxUi\Catalog\Storefront\HasListingTexts;
use WebxUi\Catalog\Storefront\ListingSubject;
use WebxUi\CatalogLandings\Catalog\LandingSet;
use WebxUi\Localization\HasTranslations;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\HasSeo;
use WebxUi\Seo\Rendering\SeoData;

/**
 * A landing (§1 of the landings spec): a category's list — or the whole catalogue's — with a set
 * of filters chosen in advance, under an address, an SEO card and texts of its own.
 *
 * To the core it is the owner of a page of the list ({@see ListingSubject}): the storefront draws
 * it with the category's template, starting from its set, and asks it for the heading, the trail,
 * the card, the two texts and the order. Its address is flat from the site's root, in the same
 * space as the categories' (§6.1).
 *
 * Not `final`: PHPStan does not take the `$this` of a final class for the `$this` the trait's
 * relation promises (docs/pitfalls/laravel-and-php.md).
 *
 * @property int $id
 * @property int|null $category_id
 * @property array<string, mixed>|null $filters
 * @property string|null $filters_hash
 * @property array<string, string>|string|null $name
 * @property array<string, string>|string|null $slug
 * @property array<string, string>|string|null $h1
 * @property array<string, string>|string|null $text_above
 * @property array<string, string>|string|null $text_below
 * @property string|null $sort
 * @property bool $on_category
 * @property int $position
 * @property bool $is_published
 * @property string|null $attention
 * @property int|null $products_count
 * @property Carbon|null $counted_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category|null $category
 */
class Landing extends Model implements HasDefaultSort, HasListingTexts, ListingSubject, Visible
{
    use HasSeo {
        seoData as storedSeoData;
    }
    use HasTranslations;
    use HasUrl;
    use RecordsHistory;
    use SoftDeletes;

    /** The type the address registry and the journal know it by. */
    public const TYPE = 'catalog.landing';

    /** The recommended products, in their order (§4). */
    public const PRODUCTS = 'catalog_landing_products';

    /** A value of the set was deleted and dropped out of it (§9). */
    public const VALUE_REMOVED = 'value_removed';

    /** A merge made the set another landing's: unpublished until an editor decides (§9). */
    public const DUPLICATE = 'duplicate';

    /** Nothing is left of the set: unpublished (§9). */
    public const EMPTY_SET = 'empty_set';

    /** The fields the journal names in words. */
    public const HISTORY = [
        'category_id', 'filters', 'name', 'slug', 'h1', 'text_above', 'text_below', 'sort',
        'on_category', 'is_published', 'attention',
    ];

    protected $table = 'catalog_landings';

    /** @var list<string> */
    protected $fillable = [
        'category_id', 'filters', 'name', 'slug', 'h1', 'text_above', 'text_below', 'sort',
        'on_category', 'position', 'is_published', 'attention',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'filters' => 'array',
            'on_category' => 'boolean',
            'position' => 'integer',
            'is_published' => 'boolean',
            'products_count' => 'integer',
            'counted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $landing): void {
            // `_` marks the filter in an address, so a slug holding it would be read as a filter
            // (the categories' rule). In the model, so the form and an agent hit the same wall.
            foreach ($landing->getTranslations('slug') as $slug) {
                if (is_string($slug) && str_contains($slug, '_')) {
                    throw ValidationException::withMessages(['slug' => [(string) __('webx-catalog-landings::errors.slug-underscore')]]);
                }
            }

            $set = LandingSet::from($landing->filters);
            $landing->filters = $set->all();

            // A set waiting for an editor holds no key, so the landing it collided with keeps its.
            $landing->filters_hash = in_array($landing->attention, [self::DUPLICATE, self::EMPTY_SET], true)
                ? null
                : $set->hash($landing->category_id);
        });
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['name', 'slug', 'h1', 'text_above', 'text_below'];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    /**
     * The hand-picked strip over the list (§6.2), in order.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function recommended(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, self::PRODUCTS, 'landing_id', 'product_id')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    public function set(): LandingSet
    {
        return LandingSet::from($this->filters);
    }

    /** The set as the storefront starts from it; facets the registry lost are left out. */
    public function state(): FilterState
    {
        return $this->set()->state(Container::getInstance()->make(Facets::class));
    }

    /** A landing has an address in a language when it names a slug in it. */
    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }

    /**
     * Published, out of the bin, and on a base the site shows: a landing on a hidden category is
     * hidden with it — and back when the category is (§9, no mark: nothing in it broke).
     */
    public function isVisible(?string $locale = null): bool
    {
        if (! $this->is_published || $this->trashed()) {
            return false;
        }

        if ($this->category_id === null) {
            return true;
        }

        $category = $this->category;

        return $category instanceof Category && $category->isVisible($locale);
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeVisible(Builder $query, ?string $locale = null): Builder
    {
        $table = $this->getTable();

        return $query
            ->where($table.'.is_published', true)
            ->where(static function (Builder $base) use ($table, $locale): void {
                $base->whereNull($table.'.category_id')
                    ->orWhereIn($table.'.category_id', Category::query()->visible($locale)->select('catalog_categories.id'));
            });
    }

    /**
     * Published landings whose list is not known to be empty: the only ones that get links (§7).
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeLinkable(Builder $query, ?string $locale = null): Builder
    {
        return $this->scopeVisible($query, $locale)->where(static function (Builder $counted): void {
            $counted->whereNull('products_count')->orWhere('products_count', '>', 0);
        });
    }

    public function visibleUpdatedAt(): ?CarbonInterface
    {
        return $this->updated_at;
    }

    /** The name in the panel and in the links of the collections. */
    public function label(?string $locale = null): string
    {
        $name = $this->getTranslation('name', $locale ?? app()->getLocale());

        return is_string($name) ? $name : '';
    }

    /** The page's heading: the H1, or the name when it has none. */
    public function displayName(string $locale): string
    {
        $h1 = $this->getTranslation('h1', $locale, false);

        return is_string($h1) && trim($h1) !== '' ? $h1 : $this->label($locale);
    }

    /**
     * The card of the plain landing (§6.3): what the editor filled in, over the defaults — the
     * title from the H1, the H1 from the name — and `noindex` while the list is known to be empty
     * (decision 9). Said here rather than by the page, so the `<head>`, the sitemap and
     * `test-url` give one answer.
     */
    public function seoData(?string $locale = null): SeoData
    {
        $locale ??= app()->getLocale();
        $heading = $this->displayName($locale);

        $defaults = SeoData::make([
            'title' => (string) __('webx-catalog-landings::seo.title', ['h1' => $heading], $locale),
            'h1' => $heading,
            'robots' => $this->products_count === 0 ? 'noindex, follow' : null,
        ]);

        $stored = $this->storedSeoData($locale);

        return $stored === null ? $defaults : $stored->mergeOver($defaults);
    }

    /**
     * The base's trail, then the landing. A landing on the whole catalogue has only itself.
     *
     * @return list<Crumb>
     */
    public function breadcrumbs(string $locale): array
    {
        $base = $this->category_id === null ? null : $this->category;
        $crumbs = $base instanceof Category ? $base->breadcrumbs($locale) : [];

        $crumbs[] = new Crumb($this->label($locale), $this->url($locale));

        return $crumbs;
    }

    public function textAbove(string $locale): ?string
    {
        return $this->richText('text_above', $locale);
    }

    public function textBelow(string $locale): ?string
    {
        return $this->richText('text_below', $locale);
    }

    public function defaultSort(): ?string
    {
        return $this->sort === null || $this->sort === '' ? null : $this->sort;
    }

    /**
     * The order and the count are not edits of the landing: a drag, or a recount of the list.
     *
     * @return list<string>
     */
    public function historySkipped(): array
    {
        return ['position', 'products_count', 'counted_at', 'filters_hash'];
    }

    /**
     * The base by its name rather than a key nobody reads.
     */
    public function historyValue(string $field, mixed $value): mixed
    {
        if ($field === 'category_id' && $value !== null) {
            $category = Category::withTrashed()->find($value);

            return $category instanceof Category ? $category->displayName() : '#'.$value;
        }

        return $value;
    }

    /**
     * A text as a page prints it: a document with every library picture pointed at where it lives
     * now, as a brand's description is.
     */
    private function richText(string $field, string $locale): ?string
    {
        $stored = $this->getTranslation($field, $locale, false);

        if (! is_string($stored) || trim($stored) === '') {
            return null;
        }

        $type = Container::getInstance()->make(FieldTypes::class)->get('wx-rich-text');
        $resolved = $type === null ? $stored : $type->resolve($stored, [], $locale);

        return is_string($resolved) ? $resolved : $stored;
    }
}
