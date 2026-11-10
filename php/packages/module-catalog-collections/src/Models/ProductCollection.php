<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Models;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\History\RecordsHistory;
use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Screens\Types\LinkType;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Filter\FacetSet;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Localization\HasCodes;
use WebxUi\Localization\HasTranslations;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Slug;

/**
 * A collection (§2 of the collections spec): a named block of products a manager puts together
 * once and the site prints wherever it is placed. It has no address: it is a piece of a page.
 *
 * Named `ProductCollection` because `Collection` is taken twice over, by Laravel and by the
 * `wx-collection` field. Its rules live here so the form and an agent hit the same wall.
 *
 * Not `final`: PHPStan does not take the `$this` of a final class for the `$this` the trait's
 * relation promises (docs/pitfalls/laravel-and-php.md).
 *
 * @property int $id
 * @property string $key
 * @property array<string, string>|string|null $title
 * @property string $source
 * @property int|null $category_id
 * @property array<string, mixed>|null $filters
 * @property bool $on_sale
 * @property string|null $sort
 * @property bool $only_purchasable
 * @property int $limit
 * @property string $view
 * @property array<string, mixed>|null $link
 * @property array<string, string>|string|null $link_text
 * @property bool $is_enabled
 * @property string|null $attention
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category|null $category
 */
class ProductCollection extends Model
{
    use HasCodes;
    use HasTranslations;
    use RecordsHistory;
    use SoftDeletes;

    /** The type the journal knows it by. */
    public const TYPE = 'catalog.collection';

    /** The form of the panel. */
    public const SCREEN = 'catalog.collection-form';

    /** The hand-picked products, in their order (§2.3). */
    public const PRODUCTS = 'catalog_collection_products';

    /** What the key may be: it is written into templates and texts by hand (§2.2). */
    public const KEY = '/^[a-z0-9-]{1,64}$/D';

    public const KEY_LENGTH = 64;

    /** Put before a key made of a title with no letters in it. */
    public const KEY_PREFIX = 'collection';

    public const MANUAL = 'manual';

    public const FILTER = 'filter';

    /** The ways of filling a collection (§3). */
    public const SOURCES = [self::MANUAL, self::FILTER];

    public const GRID = 'grid';

    public const CAROUSEL = 'carousel';

    /** The ways of printing one (§5.4). */
    public const VIEWS = [self::GRID, self::CAROUSEL];

    /** The link «All products» as the screen draws it (`collection-form.json`): a file of the library is a target. */
    private const LINK_NODE = ['type' => 'wx-link', 'props' => ['files' => true]];

    /** `attention`: a value of the set was deleted (§3.5). Saving the form clears it. */
    public const VALUE_DELETED = 'value-deleted';

    /** The fields the journal names in words. */
    public const HISTORY = [
        'key', 'title', 'source', 'category_id', 'filters', 'on_sale', 'sort', 'only_purchasable',
        'limit', 'view', 'link', 'link_text', 'is_enabled', 'attention',
    ];

    protected $table = 'catalog_collections';

    /** @var list<string> */
    protected $fillable = [
        'key', 'title', 'source', 'category_id', 'filters', 'on_sale', 'sort', 'only_purchasable',
        'limit', 'view', 'link', 'link_text', 'is_enabled', 'attention',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'source' => self::MANUAL,
        'on_sale' => false,
        'only_purchasable' => true,
        'limit' => 8,
        'view' => self::GRID,
        'is_enabled' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'filters' => 'array',
            'on_sale' => 'boolean',
            'only_purchasable' => 'boolean',
            'limit' => 'integer',
            'link' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * The key is brought to its shape rather than refused (#125): «Летние шины» is kept as
     * `letnie-shiny` before {@see settleKey()} looks at it, whatever door it came in by.
     *
     * @return array<string, array{max: int}>
     */
    protected static function codes(): array
    {
        return ['key' => ['max' => self::KEY_LENGTH]];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $collection): void {
            $collection->settleTitle();
            $collection->settleKey();
            $collection->settleChoices();
            $collection->settleLimit();
            $collection->settleLink();

            $filters = FacetSet::from($collection->filters)->all();
            $collection->filters = $filters === [] ? null : $filters;
        });
    }

    /** The ceiling of every collection's limit (decision 11). */
    public static function maxLimit(): int
    {
        return (int) Container::getInstance()->make(Repository::class)->get('webx-catalog-collections.max_limit', 48);
    }

    /**
     * What `saving` would say of a view and a limit, for a preview, which never saves. Only what
     * was sent is judged: a limit saved under a higher ceiling is no mistake of the caller's.
     *
     * @param  array<string, mixed>  $values  Past the form's rules, so the view is a string.
     */
    public static function refuseValues(array $values): void
    {
        $collection = new self(array_intersect_key($values, array_flip(['view', 'limit'])));
        $collection->settleChoices();

        if (array_key_exists('limit', $values)) {
            $collection->settleLimit();
        }
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'link_text'];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    /**
     * The hand-picked list (§2.3), in order. A product in the bin keeps its row, so it is back in
     * its place when restored; `withTrashed()` is how the form sees it meanwhile.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, self::PRODUCTS, 'collection_id', 'product_id')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    public function set(): FacetSet
    {
        return FacetSet::from($this->filters);
    }

    /** The set as the catalogue reads it; facets the registry lost are left out. */
    public function state(): FilterState
    {
        return $this->set()->state(Container::getInstance()->make(Facets::class));
    }

    /** The name in the panel and over the block on the site. */
    public function label(?string $locale = null): string
    {
        $title = $this->getTranslation('title', $locale ?? app()->getLocale());

        return is_string($title) ? $title : '';
    }

    /**
     * The category by its name rather than a key nobody reads.
     */
    public function historyValue(string $field, mixed $value): mixed
    {
        if ($field === 'category_id' && $value !== null) {
            $category = Category::withTrashed()->find($value);

            return $category instanceof Category ? $category->displayName() : '#'.$value;
        }

        return $value;
    }

    private function settleTitle(): void
    {
        foreach ($this->getTranslations('title') as $title) {
            if (is_string($title) && trim($title) !== '') {
                return;
            }
        }

        $this->refuse('title', 'title-required');
    }

    /**
     * Made from the title when none is given — or when what was typed left nothing, `!!!` — and
     * fixed from then on: it lives in templates and texts, where nobody would change it (§2.2).
     * A key sent again as it was typed (`Popular` for `popular`) is brought to itself and is
     * no change. A key in the bin is as taken as any other; only
     * deleting the collection for good frees it.
     */
    private function settleKey(): void
    {
        if ($this->exists) {
            if ($this->isDirty('key')) {
                $this->refuse('key', 'key-fixed');
            }

            return;
        }

        $key = $this->getAttribute('key');

        if ($key === null || $key === '') {
            $this->setAttribute('key', $this->freeKey());

            return;
        }

        if (! is_string($key) || preg_match(self::KEY, $key) !== 1) {
            $this->refuse('key', 'key-shape', ['max' => self::KEY_LENGTH]);
        }

        if (self::withTrashed()->where('key', $key)->exists()) {
            $this->refuse('key', 'key-taken', ['key' => $key]);
        }
    }

    /** The title's slug in the site's default language first, numbered when it is taken. */
    private function freeKey(): string
    {
        $default = Container::getInstance()->make(Locales::class)->defaultCode();
        $base = Slug::first($this->getTranslations('title'), $default, self::KEY_LENGTH, self::KEY_PREFIX);
        $taken = array_flip(self::withTrashed()->where('key', 'like', $base.'%')->pluck('key')->all());

        $key = $base;

        for ($number = 2; isset($taken[$key]); $number++) {
            $suffix = '-'.$number;
            $key = rtrim(substr($base, 0, self::KEY_LENGTH - strlen($suffix)), '-').$suffix;
        }

        return $key;
    }

    private function settleChoices(): void
    {
        if (! in_array($this->source, self::SOURCES, true)) {
            $this->refuse('source', 'source-unknown', ['source' => (string) $this->source]);
        }

        if (! in_array($this->view, self::VIEWS, true)) {
            $this->refuse('view', 'view-unknown', ['view' => (string) $this->view]);
        }
    }

    /**
     * Checked when it is written, not on every save: a ceiling lowered in the config must not stop
     * a collection saved under the old one from being restored or repaired.
     */
    private function settleLimit(): void
    {
        if ($this->exists && ! $this->isDirty('limit')) {
            return;
        }

        $max = self::maxLimit();
        $limit = $this->limit;

        if (! is_int($limit) || $limit < 1 || $limit > $max) {
            $this->refuse('limit', 'limit-range', ['max' => $max]);
        }
    }

    /**
     * The link as `wx-link` keeps it, by the same reading as a block's, or nothing. Checked when it
     * is written: a link to a module that has gone since must not stop a repair of the set, which
     * would take the deletion of a brand down with it.
     *
     * A file of the library is a target, as on the screen (`props.files`). A key the library does
     * not have is refused only when the collection did not point at it before: a brochure deleted
     * since must not stop a save, a key nobody ever had is one typed rather than picked.
     */
    private function settleLink(): void
    {
        if ($this->exists && ! $this->isDirty('link')) {
            return;
        }

        $type = Container::getInstance()->make(LinkType::class);
        $checks = Validator::make(['link' => $this->link], ['link' => $type->rules(self::LINK_NODE)]);

        if ($checks->fails()) {
            throw ValidationException::withMessages(['link' => [(string) $checks->errors()->first('link')]]);
        }

        $was = $this->exists ? $this->getOriginal('link') : null;
        $path = is_array($this->link) ? Link::fromArray($this->link)->path : null;
        $held = is_array($was) ? Link::fromArray($was)->path : null;

        if ($path !== null && $path !== $held) {
            $problem = $type->newValueProblems($this->link, self::LINK_NODE)[0] ?? null;

            if ($problem !== null) {
                throw ValidationException::withMessages(['link' => [$problem]]);
            }
        }

        $stored = $type->store($this->link, self::LINK_NODE);
        $this->link = is_array($stored) ? $stored : null;
    }

    /**
     * @param  array<string, int|string>  $words
     */
    private function refuse(string $field, string $error, array $words = []): never
    {
        throw ValidationException::withMessages([$field => [(string) __('webx-catalog-collections::errors.'.$error, $words)]]);
    }
}
