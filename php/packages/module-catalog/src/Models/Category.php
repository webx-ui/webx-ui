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
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\History\RecordsHistory;
use WebxUi\Catalog\Exceptions\CatalogException;
use WebxUi\Catalog\Storefront\HasListingTexts;
use WebxUi\Localization\HasTranslations;
use WebxUi\Media\Models\MediaFile;
use WebxUi\NestedSet\HasNestedSet;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\HasSeo;

/**
 * A category of the catalogue: a node of one tree whose slugs are flat (§6).
 *
 * The address is `/{slug}` at the root of the site whatever the depth, so moving a branch changes
 * no address and the crumbs are the tree's business. A slug is unique across the site in its
 * language, which `routing` checks on save — a taken one is a 422 naming whoever holds it.
 *
 * Unpublished hides the whole branch below it (§6.3): a category is visible only when it and
 * every ancestor are published. That is one query rather than a walk up the tree, so a list of
 * products can ask it of thousands of rows ({@see scopeVisible()}).
 *
 * A category is deleted only empty — no live product names it, as the main or an additional one,
 * and no live category stands under it. Deleted, it keeps its place in the tree
 * ({@see softDeletesInTree()}), so a restore puts it back where it was.
 *
 * @property int $id
 * @property array<string, string>|string|null $name
 * @property array<string, string>|string|null $slug
 * @property array<string, string>|string|null $description
 * @property int|null $cover_id
 * @property bool $is_published
 * @property int $lft
 * @property int $rgt
 * @property int $depth
 * @property int|null $parent_id
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Category extends Model implements HasBreadcrumbs, HasListingTexts, Visible
{
    use HasNestedSet;
    use HasSeo;
    use HasTranslations;
    use HasUrl;
    use RecordsHistory;
    use SoftDeletes;

    /** The type the journal and the address registry know it by. */
    public const TYPE = 'catalog.category';

    /** The screen a category is edited on, and the one a project patches its fields onto. */
    public const SCREEN = 'catalog.category-form';

    protected $table = 'catalog_categories';

    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'description', 'cover_id', 'is_published'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'cover_id' => 'integer',
        ];
    }

    /**
     * What the saves since the last {@see takeHistoryChanges()} changed, held rather than written.
     *
     * @var list<array<string, mixed>>
     */
    private array $heldHistory = [];

    /**
     * `created`, `deleted` and `restored` from the trait as they are; an update is held, not
     * written. A save is written by the form, which knows whether it was a publication or an
     * edit — the module's word rather than a column's (§4 of the history spec) — and a move by the
     * move, which the trait never hears: nested-set rewrites bounds without an `updated`.
     */
    public static function bootRecordsHistory(): void
    {
        static::updated(static function (self $category): void {
            $category->heldHistory = [...$category->heldHistory, ...$category->historyChanges()];
        });
        static::created(static fn (self $category) => $category->writeHistory(HistoryEntry::CREATED));
        static::deleted(static fn (self $category) => $category->writeHistory(HistoryEntry::DELETED));
        static::registerModelEvent('restored', static fn (self $category) => $category->writeHistory(HistoryEntry::RESTORED));
    }

    protected static function booted(): void
    {
        static::deleting(static function (self $category): void {
            if ($category->isForceDeleting()) {
                return;
            }

            $products = $category->productCount(withDescendants: false);
            $children = $category->children()->count();

            if ($products > 0 || $children > 0) {
                throw CatalogException::categoryNotEmpty($products, $children);
            }
        });
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['name', 'slug', 'description'];
    }

    /**
     * A deleted category keeps its bounds, so a restore puts it back where it was. What stands
     * under it is never trashed along with it: a category with anything live below is refused
     * the delete in the first place.
     */
    public function softDeletesInTree(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public function historySkipped(): array
    {
        return ['parent_id'];
    }

    /** A category has an address in a language when it names a slug in it. */
    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * The products that name this one as an additional category.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function extraProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'catalog_category_product', 'category_id', 'product_id');
    }

    /** @return BelongsTo<MediaFile, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'cover_id');
    }

    /**
     * Published, not in the bin, and nothing above it unpublished or in the bin (§6.3).
     */
    public function isVisible(?string $locale = null): bool
    {
        if (! $this->exists || $this->trashed() || ! $this->is_published) {
            return false;
        }

        return static::query()->whereKey($this->getKey())->visible()->exists();
    }

    /**
     * The same as a query: this row published, and no ancestor that is not.
     *
     * An ancestor in the bin counts as hidden as well. A category cannot be deleted with anything
     * live under it, but it can be restored under a parent that went to the bin afterwards.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeVisible(Builder $query, ?string $locale = null): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->where($table.'.is_published', true)
            ->where($table.'.rgt', '>', 0)
            ->whereNotExists(function (QueryBuilder $above) use ($table): void {
                $above->selectRaw('1')
                    ->from($this->getTable().' as hidden_above')
                    ->whereColumn('hidden_above.lft', '<', $table.'.lft')
                    ->whereColumn('hidden_above.rgt', '>', $table.'.rgt')
                    ->where(static function (QueryBuilder $hidden): void {
                        $hidden->where('hidden_above.is_published', false)
                            ->orWhereNotNull('hidden_above.deleted_at');
                    });
            });
    }

    public function visibleUpdatedAt(): ?CarbonInterface
    {
        return $this->updated_at;
    }

    /**
     * The ids of every visible category, as a subquery for `whereIn`.
     *
     * Built on the connection of the query it goes into rather than the default one: the two
     * halves of one statement have to be quoted and prefixed alike (docs/pitfalls).
     *
     * @return Builder<static>
     */
    public static function visibleIds(?string $connection = null): Builder
    {
        return static::on($connection)->visible()->select('catalog_categories.id');
    }

    /**
     * The products that would be left without this category: live ones naming it as the main or
     * an additional one. With the descendants, it is the number the tree shows beside the node.
     */
    public function productCount(bool $withDescendants = true): int
    {
        $ids = $withDescendants
            ? $this->subtree()->pluck('id')->all()
            : [$this->getKey()];

        return Product::query()->inCategories($ids)->count();
    }

    /**
     * This category and every live one under it.
     *
     * From a fresh copy of the bounds: nodes added under this one since it was loaded widened its
     * `rgt` in the table and nowhere else (docs/pitfalls).
     *
     * @return Builder<static>
     */
    public function subtree(): Builder
    {
        $fresh = static::withTrashed()->find($this->getKey()) ?? $this;

        return static::query()
            ->where('lft', '>=', $fresh->getLft())
            ->where('rgt', '<=', $fresh->getRgt());
    }

    /** The name to show, and something to show when there is none in this language. */
    public function displayName(?string $locale = null): string
    {
        $name = $this->getTranslation('name', $locale);

        return is_string($name) && trim($name) !== '' ? $name : '#'.$this->getKey();
    }

    /** The description, above the products of the plain page. */
    public function textAbove(string $locale): ?string
    {
        $description = $this->getTranslation('description', $locale);

        return is_string($description) && trim($description) !== '' ? $description : null;
    }

    /** A category has one text; the place under the pages is a landing's (decision 6 of the landings spec). */
    public function textBelow(string $locale): ?string
    {
        return null;
    }

    /**
     * The visible categories above this one, then this one. The site's home is `module-seo`'s to
     * put in front.
     *
     * @return list<Crumb>
     */
    public function breadcrumbs(string $locale): array
    {
        $crumbs = [];

        foreach ($this->pathFromRoot() as $node) {
            if (! $node->is($this) && (! $node->isVisible($locale) || ! $node->hasUrlIn($locale))) {
                continue;
            }

            $crumbs[] = new Crumb($node->displayName($locale), $node->url($locale));
        }

        return $crumbs;
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
     * Put a history row down for this category, with the words the form or the move chose.
     *
     * @param  list<array<string, mixed>>  $changes
     */
    public function recordHistory(string $event, array $changes = []): void
    {
        $this->writeHistory($event, $changes);
    }
}
