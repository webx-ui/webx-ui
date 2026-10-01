<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\History\RecordsHistory;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Exceptions\CatalogException;
use WebxUi\Catalog\Routing\CatalogMisses;
use WebxUi\Catalog\Seo\ProductMarkup;
use WebxUi\Localization\HasTranslations;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Routing\Models\Route;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\Contracts\HasStructuredData;
use WebxUi\Seo\HasSeo;

/**
 * A product (§3, §5).
 *
 * Three states and no draft: published, unpublished, deleted. Deleted is a soft delete for ever —
 * orders and a customer's account will name the product long after it left the shop — and the
 * address answers 301 to the category or 410 rather than 404 ({@see CatalogMisses}).
 *
 * Published is not the same as visible. A product is on the site when it is published **and** at
 * least one of its categories — the main one or an additional one — is visible with every
 * ancestor (§5). Published but invisible behaves as unpublished: a trimmed page, `noindex`,
 * nothing to buy.
 *
 * Two rules every door into the table obeys, so they live here rather than in a form: the article
 * number is unique across the deleted too, and a product without a main category cannot be
 * published (decisions 3 and 5).
 *
 * @property int $id
 * @property string|null $sku
 * @property string|null $barcode
 * @property string|null $external_id
 * @property array<string, string>|string|null $name
 * @property array<string, string>|string|null $slug
 * @property array<string, string>|string|null $summary
 * @property array<string, string>|string|null $description
 * @property int|null $category_id
 * @property string|null $price
 * @property string|null $old_price
 * @property string|null $unit
 * @property int $priority
 * @property bool $is_published
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Product extends Model implements HasBreadcrumbs, HasStructuredData, Visible
{
    use HasSeo;
    use HasTranslations;
    use HasUrl;
    use RecordsHistory;
    use SoftDeletes;

    /** The type the journal and the address registry know it by. */
    public const TYPE = 'catalog.product';

    /** The screen a product is edited on, and the one a satellite patches its tab onto. */
    public const SCREEN = 'catalog.product-form';

    public const STATE_PUBLISHED = 'published';

    public const STATE_UNPUBLISHED = 'unpublished';

    public const STATE_DELETED = 'deleted';

    protected $table = 'catalog_products';

    /** @var list<string> */
    protected $fillable = [
        'sku', 'barcode', 'external_id', 'name', 'slug', 'summary', 'description',
        'category_id', 'price', 'old_price', 'unit', 'priority', 'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'price' => 'decimal:2',
            'old_price' => 'decimal:2',
            'priority' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * What the saves since the last {@see takeHistoryChanges()} changed, held rather than written.
     *
     * @var list<array<string, mixed>>
     */
    private array $heldHistory = [];

    /**
     * `created`, `deleted` and `restored` from the trait as they are. An update is held instead of
     * written: a save through the form is one row with the core's changes and every satellite
     * part's together (§11.3), which the form writes itself — the trait's own row would be a
     * second one about half of it. Held in `updated` because that is the one moment the trait can
     * tell the old value from the new: the originals are synced right after it.
     */
    public static function bootRecordsHistory(): void
    {
        static::updated(static function (self $product): void {
            $product->heldHistory = [...$product->heldHistory, ...$product->historyChanges()];
        });
        static::created(static fn (self $product) => $product->writeHistory(HistoryEntry::CREATED));
        static::deleted(static fn (self $product) => $product->writeHistory(HistoryEntry::DELETED));
        static::registerModelEvent('restored', static fn (self $product) => $product->writeHistory(HistoryEntry::RESTORED));
    }

    protected static function booted(): void
    {
        static::saving(static function (self $product): void {
            $product->normaliseCodes();
            $product->fillSlugs();
            $product->assertSkuFree();

            if ($product->is_published && $product->category_id === null) {
                throw CatalogException::publishNeedsCategory();
            }
        });

        // Every door into the table marks the product for the engine — the form, an import, a
        // satellite's own save — so none of them has to remember to. A second mark from a caller
        // that marks as well is ignored by the queue; under `SqlEngine` nothing is written at all.
        $touch = static fn (self $product) => app(Catalog::class)->touch([$product->getKey()]);
        static::saved($touch);
        static::deleted($touch);
        static::registerModelEvent('restored', $touch);

        // A product whose main category went to the bin while it was in the bin itself comes back
        // without one, and therefore unpublished (§6.3): published without a category is the one
        // state a product cannot be in.
        static::restoring(static function (self $product): void {
            if ($product->category_id !== null && ! Category::query()->whereKey($product->category_id)->exists()) {
                $product->category_id = null;
                $product->is_published = false;
            }
        });
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['name', 'slug', 'summary', 'description'];
    }

    /** A product has an address in a language when it has a slug in it — made from the name. */
    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * The additional categories. The main one is never among them (decision 2).
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'catalog_category_product', 'product_id', 'category_id');
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('position')->orderBy('id');
    }

    /** The first picture of the gallery, which is the main one. */
    public function mainImage(): ?ProductImage
    {
        /** @var ProductImage|null $image */
        $image = $this->relationLoaded('images') ? $this->images->first() : $this->images()->first();

        return $image;
    }

    public function state(): string
    {
        if ($this->trashed()) {
            return self::STATE_DELETED;
        }

        return $this->is_published ? self::STATE_PUBLISHED : self::STATE_UNPUBLISHED;
    }

    /** On the site: published, not deleted, and in at least one visible category (§5). */
    public function isVisible(?string $locale = null): bool
    {
        if (! $this->exists || $this->trashed() || ! $this->is_published) {
            return false;
        }

        return static::query()->whereKey($this->getKey())->visible()->exists();
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeVisible(Builder $query, ?string $locale = null): Builder
    {
        $table = $query->getModel()->getTable();
        $connection = $query->getModel()->getConnectionName();

        return $query
            ->where($table.'.is_published', true)
            ->where(static function (Builder $placed) use ($table, $connection): void {
                $placed->whereIn($table.'.category_id', Category::visibleIds($connection))
                    ->orWhereExists(static function (QueryBuilder $extra) use ($table, $connection): void {
                        $extra->selectRaw('1')
                            ->from('catalog_category_product')
                            ->whereColumn('catalog_category_product.product_id', $table.'.id')
                            ->whereIn('catalog_category_product.category_id', Category::visibleIds($connection)->toBase());
                    });
            });
    }

    /**
     * Live products filed under any of these categories, as the main or an additional one.
     *
     * @param  Builder<covariant Model>  $query
     * @param  list<int|string|null>  $ids
     * @return Builder<covariant Model>
     */
    public function scopeInCategories(Builder $query, array $ids): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where(static function (Builder $filed) use ($table, $ids): void {
            $filed->whereIn($table.'.category_id', $ids)
                ->orWhereExists(static function (QueryBuilder $extra) use ($table, $ids): void {
                    $extra->selectRaw('1')
                        ->from('catalog_category_product')
                        ->whereColumn('catalog_category_product.product_id', $table.'.id')
                        ->whereIn('catalog_category_product.category_id', $ids);
                });
        });
    }

    /**
     * A code of the product — the article number, the barcode, the external id — that is the term
     * itself, as the database compares it.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeCoded(Builder $query, string $term): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where(static fn (Builder $any): Builder => $any->where($table.'.sku', $term)
            ->orWhere($table.'.barcode', $term)
            ->orWhere($table.'.external_id', $term));
    }

    /**
     * Name, article number, barcode or external id containing the words; the id itself when the
     * words are one.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeMatching(Builder $query, string $term): Builder
    {
        $table = $query->getModel()->getTable();
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(static function (Builder $nested) use ($table, $like, $term): void {
            $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('name', $like))
                ->orWhere($table.'.sku', 'like', $like)
                ->orWhere($table.'.barcode', 'like', $like)
                ->orWhere($table.'.external_id', 'like', $like);

            if (ctype_digit($term)) {
                $nested->orWhere($table.'.id', (int) $term);
            }
        });
    }

    public function visibleUpdatedAt(): ?CarbonInterface
    {
        return $this->updated_at;
    }

    /** The name to show, and something to show when there is none in this language. */
    public function displayName(?string $locale = null): string
    {
        $name = $this->getTranslation('name', $locale);

        return is_string($name) && trim($name) !== '' ? $name : '#'.$this->getKey();
    }

    /**
     * The trail of the main category, then the product. A product in no visible category still
     * gets its own step: the trimmed page has a trail too.
     *
     * @return list<Crumb>
     */
    public function breadcrumbs(string $locale): array
    {
        $category = $this->category;
        $trail = $category instanceof Category && $category->isVisible($locale) && $category->hasUrlIn($locale)
            ? $category->breadcrumbs($locale)
            : [];

        $trail[] = new Crumb($this->displayName($locale), $this->url($locale));

        return $trail;
    }

    /**
     * The address, from the registry rows a list loaded in one go when it did: a grid of cards
     * that asked `url()` of each would ask the registry once per card.
     */
    public function listedUrl(?string $locale = null): string
    {
        $locale ??= app(Locales::class)->current();

        if ($this->relationLoaded('routes')) {
            $row = $this->routes->first(static fn (Route $route): bool => $route->kind === Route::CANONICAL && $route->locale === $locale);

            if ($row instanceof Route) {
                return $this->urlOf($row->path, $locale);
            }
        }

        return $this->url($locale);
    }

    /**
     * `Product`, with an `Offer` when there is a price and a currency to put in it (§10.3).
     *
     * @return list<array<string, mixed>>
     */
    public function structuredData(string $locale): array
    {
        return app(ProductMarkup::class)->for($this, $locale);
    }

    /** An id in the journal is a name there: "Laptops → Tablets", not "3 → 7". */
    public function historyValue(string $field, mixed $value): mixed
    {
        if ($field === 'category_id' && is_numeric($value)) {
            $category = Category::withTrashed()->find((int) $value);

            return $category instanceof Category ? $category->displayName() : $value;
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    public function historySkipped(): array
    {
        return ['external_id'];
    }

    /**
     * The changes the saves since the last call made, for the one row the caller writes.
     *
     * @return list<array<string, mixed>>
     */
    public function takeHistoryChanges(): array
    {
        $held = $this->heldHistory;
        $this->heldHistory = [];

        return $held;
    }

    /**
     * Put a history row down for this product, with the words the form chose.
     *
     * @param  list<array<string, mixed>>  $changes
     */
    public function recordHistory(string $event, array $changes = []): void
    {
        $this->writeHistory($event, $changes);
    }

    /** An empty code is no code: `''` in a unique column would be a second product's clash. */
    private function normaliseCodes(): void
    {
        foreach (['sku', 'barcode', 'external_id'] as $column) {
            $value = $this->getAttribute($column);

            if (is_string($value)) {
                $value = trim($value);
                $this->setAttribute($column, $value === '' ? null : $value);
            }
        }
    }

    /**
     * A slug in every language that has a name and no slug of its own (§4): the address is the
     * name until somebody writes something else. Not unique, and it need not be.
     */
    private function fillSlugs(): void
    {
        $slugs = $this->getTranslations('slug');
        $changed = false;

        foreach ($this->getTranslations('name') as $locale => $name) {
            $current = $slugs[$locale] ?? null;

            if (is_string($current) && trim($current) !== '') {
                continue;
            }

            if (! is_string($name) || trim($name) === '') {
                continue;
            }

            $slug = Str::slug($name, '-', (string) $locale);

            if ($slug !== '') {
                $slugs[$locale] = $slug;
                $changed = true;
            }
        }

        if ($changed) {
            $this->setTranslations('slug', $slugs);
        }
    }

    /**
     * The article number is unique with the deleted counted in (decision 5), and the refusal
     * names the product that holds it — the editor's next move is to open that one.
     *
     * Checked here rather than left to the unique index alone: the index answers with a 500 and
     * no name. It stays as the guarantee behind two saves racing.
     */
    private function assertSkuFree(): void
    {
        if ($this->sku === null || ! $this->isDirty('sku')) {
            return;
        }

        $holder = static::withTrashed()
            ->where('sku', $this->sku)
            ->when($this->exists, fn (Builder $query): Builder => $query->whereKeyNot($this->getKey()))
            ->first();

        if ($holder instanceof self) {
            throw CatalogException::skuTaken($holder);
        }
    }
}
