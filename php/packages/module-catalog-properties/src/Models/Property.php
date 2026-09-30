<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Models;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\History\RecordsHistory;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Filter\FilterAliases;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogProperties\Catalog\Properties;
use WebxUi\Localization\HasTranslations;

/**
 * A property of products (§2, §3 of the properties spec): a reference book, a number, a text or a
 * yes/no. The type is chosen once — the values of the products have its shape (decision 1).
 *
 * The flags a type has no use for are put out on save rather than refused: the form sends
 * everything it saw, and switching the type of a new property back and forth must not be a 422.
 * What is refused is what cannot be made sense of: a code that is not `[a-z0-9-]`, or that another
 * facet holds in the same language (§3.4) — checked here, at save, because a property lives in the
 * database and a refusal while the catalogue reads would be a catalogue that does not open.
 *
 * A code renamed in any language leaves the old one as an alias of the filter (§4.2 of the spec,
 * the core's {@see FilterAliases}); a property in the bin takes its codes off the addresses — the
 * old address leads to the page without the segment — and keeps the values of the products, so a
 * restore brings everything back (§3.3).
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $code
 * @property string $type
 * @property int|null $group_id
 * @property bool $is_multiple
 * @property bool $is_tree
 * @property bool $leaves_only
 * @property bool $is_filterable
 * @property string|null $filter_mode
 * @property bool $is_indexable
 * @property bool $is_searchable
 * @property bool $in_card
 * @property bool $on_page
 * @property bool $in_list
 * @property string $value_order
 * @property bool $has_color
 * @property bool $has_image
 * @property int $precision
 * @property int $position
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Property extends Model
{
    use HasExtra;
    use HasTranslations;
    use RecordsHistory;
    use SoftDeletes;

    public const TYPE = 'catalog.property';

    public const SCREEN = 'catalog.property-form';

    public const SELECT = 'select';

    public const NUMBER = 'number';

    public const TEXT = 'text';

    public const BOOL = 'bool';

    public const TYPES = [self::SELECT, self::NUMBER, self::TEXT, self::BOOL];

    public const SLIDER = 'slider';

    public const INTERVALS = 'intervals';

    public const ALPHA = 'alpha';

    public const MANUAL = 'manual';

    public const CODE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const CODE_LENGTH = 48;

    /** Every flag the table has; which of them a type keeps is {@see normaliseForType()}'s. */
    public const FLAGS = [
        'is_multiple', 'is_tree', 'leaves_only', 'is_filterable', 'is_indexable', 'is_searchable',
        'in_card', 'on_page', 'in_list', 'has_color', 'has_image',
    ];

    /** What a property's edit changes in the products that have it: their document and their facets. */
    public const TOUCHING = ['title', 'code', 'is_filterable', 'is_searchable', 'is_tree', 'filter_mode', 'deleted_at'];

    protected $table = 'catalog_properties';

    /** @var list<string> */
    protected $fillable = [
        'title', 'code', 'type', 'group_id', ...self::FLAGS, 'filter_mode', 'value_order',
        'unit_prefix', 'unit_suffix', 'precision', 'toggle_slug', 'seo_pattern', 'position',
    ];

    /** @var array<array-key, mixed>|null the codes a save in progress is replacing */
    private ?array $codesBefore = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...array_fill_keys(self::FLAGS, 'boolean'),
            'group_id' => 'integer',
            'precision' => 'integer',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (self $property): void {
            if (! array_key_exists('position', $property->getAttributes())) {
                $property->setAttribute('position', (int) static::withTrashed()->max('position') + 1);
            }
        });

        static::saving(static function (self $property): void {
            if (! $property->exists) {
                $property->applyDefaults();
            }

            $property->normaliseForType();
            $property->fillCodes();
            $property->assertValid();
        });

        // The codes before the save, taken here: by `updated` the raw originals are the new ones.
        static::updating(static function (self $property): void {
            $property->codesBefore = $property->isDirty('code') ? (array) json_decode((string) $property->getRawOriginal('code'), true) : null;
        });

        static::updated(static function (self $property): void {
            if (is_array($property->codesBefore)) {
                $aliases = Container::getInstance()->make(FilterAliases::class);

                foreach ($property->codesBefore as $locale => $old) {
                    $new = $property->getTranslation('code', (string) $locale, false);

                    if (is_string($old) && $old !== '' && $old !== $new) {
                        $aliases->record($property->facetKey(), (string) $locale, FilterAliases::CODE, $old, is_string($new) && $new !== '' ? $new : null);
                    }
                }

                $property->codesBefore = null;
            }

            if ($property->wasChanged(self::TOUCHING)) {
                $property->touchProducts();
            }

            Container::getInstance()->make(Properties::class)->flush();
        });

        static::created(static fn () => Container::getInstance()->make(Properties::class)->flush());

        // In the bin: the facet is gone, and so is its segment of every address (§3.3).
        static::deleted(static function (self $property): void {
            $aliases = Container::getInstance()->make(FilterAliases::class);

            foreach ($property->getTranslations('code') as $locale => $code) {
                if (is_string($code) && $code !== '') {
                    $aliases->record($property->facetKey(), (string) $locale, FilterAliases::CODE, $code, null);
                }
            }

            $property->touchProducts();
            Container::getInstance()->make(Properties::class)->flush();
        });

        static::registerModelEvent('restored', static function (self $property): void {
            $property->touchProducts();
            Container::getInstance()->make(Properties::class)->flush();
        });
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'code', 'unit_prefix', 'unit_suffix', 'toggle_slug', 'seo_pattern'];
    }

    public function extraScreen(): string
    {
        return self::SCREEN;
    }

    /**
     * @return list<string>
     */
    public function historySkipped(): array
    {
        return ['position'];
    }

    public function historyValue(string $field, mixed $value): mixed
    {
        if ($field === 'group_id' && is_numeric($value)) {
            return PropertyGroup::withTrashed()->find((int) $value)?->displayName(app()->getLocale()) ?? $value;
        }

        return $value;
    }

    /** The registry's key of its facet, `p.12`: one in every language, never in an address. */
    public function facetKey(): string
    {
        return 'p.'.$this->getKey();
    }

    public static function idOfFacet(string $key): ?int
    {
        return preg_match('/^p\.(\d+)$/', $key, $match) === 1 ? (int) $match[1] : null;
    }

    public function isSelect(): bool
    {
        return $this->type === self::SELECT;
    }

    public function isNumber(): bool
    {
        return $this->type === self::NUMBER;
    }

    public function isText(): bool
    {
        return $this->type === self::TEXT;
    }

    public function isBool(): bool
    {
        return $this->type === self::BOOL;
    }

    public function isTree(): bool
    {
        return $this->isSelect() && $this->is_tree;
    }

    public function usesIntervals(): bool
    {
        return $this->isNumber() && $this->filter_mode === self::INTERVALS;
    }

    /** @return BelongsTo<PropertyGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(PropertyGroup::class, 'group_id');
    }

    /** @return HasMany<PropertyValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(PropertyValue::class, 'property_id');
    }

    /** @return HasMany<PropertyInterval, $this> */
    public function intervals(): HasMany
    {
        return $this->hasMany(PropertyInterval::class, 'property_id')->orderBy('position')->orderBy('id');
    }

    /**
     * The name in this language, the code where there is none.
     */
    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        foreach ([$this->getTranslation('title', $locale), $this->getTranslation('code', $locale)] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '#'.$this->getKey();
    }

    public function codeIn(string $locale): string
    {
        $code = $this->getTranslation('code', $locale);

        return is_string($code) && $code !== '' ? $code : 'p'.$this->getKey();
    }

    /** The slug of «yes» in an address of this language: `wifi_yes`. */
    public function toggleSlug(string $locale): string
    {
        $slug = $this->getTranslation('toggle_slug', $locale);

        return is_string($slug) && $slug !== '' ? $slug : 'yes';
    }

    /**
     * A number as the property shows it: its precision, the language's decimal mark, the prefix
     * and the suffix — `M8`, `⌀12 mm`, `1.35 kg`.
     */
    public function formatNumber(float $number, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $formatted = null;

        if (extension_loaded('intl')) {
            $formatted = Number::format($number, precision: $this->precision, locale: $locale);
        }

        if (! is_string($formatted) || $formatted === '') {
            $formatted = number_format($number, $this->precision, '.', '');
        }

        $prefix = $this->getTranslation('unit_prefix', $locale);
        $suffix = $this->getTranslation('unit_suffix', $locale);

        return (is_string($prefix) ? $prefix : '').$formatted.(is_string($suffix) ? $suffix : '');
    }

    /**
     * How many products hold a value of it — as a subquery beside each row of a list.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithProductCount(Builder $query): Builder
    {
        return $query->addSelect(['products_count' => DB::table('catalog_product_property_values')
            ->selectRaw('count(distinct product_id)')
            ->whereColumn('property_id', $this->qualifyColumn('id'))]);
    }

    /**
     * Every product that holds a value of it, for the engine to read again — a query, never a list.
     *
     * @return Builder<Product>
     */
    public function affectedProducts(): Builder
    {
        return Product::withTrashed()->whereIn(
            'catalog_products.id',
            DB::table('catalog_product_property_values')->select('product_id')->where('property_id', $this->getKey()),
        );
    }

    public function touchProducts(): void
    {
        Container::getInstance()->make(Catalog::class)->touchQuery($this->affectedProducts());
    }

    /**
     * What a new property gets unless it says otherwise: a reference book or a text is searched,
     * a page of one value is open where the kind of facet deserves one (§5.1).
     */
    private function applyDefaults(): void
    {
        if ($this->getAttribute('is_searchable') === null) {
            $this->setAttribute('is_searchable', in_array($this->type, [self::SELECT, self::TEXT], true));
        }

        if ($this->getAttribute('is_indexable') === null) {
            $this->setAttribute('is_indexable', $this->isSelect() || $this->usesIntervals());
        }

        if ($this->isNumber() && $this->getAttribute('filter_mode') === null) {
            $this->setAttribute('filter_mode', self::SLIDER);
        }
    }

    /** Put out what the type has no use for (§2): the form sends every flag it drew. */
    private function normaliseForType(): void
    {
        if (! in_array($this->type, self::TYPES, true)) {
            throw ValidationException::withMessages(['type' => [(string) __('webx-catalog-properties::errors.type', ['types' => implode(', ', self::TYPES)])]]);
        }

        if ($this->exists && $this->isDirty('type')) {
            throw ValidationException::withMessages(['type' => [(string) __('webx-catalog-properties::errors.type-fixed')]]);
        }

        foreach (self::FLAGS as $flag) {
            if ($this->getAttribute($flag) === null) {
                $this->setAttribute($flag, $flag === 'on_page');
            }
        }

        if (! $this->isSelect()) {
            foreach (['is_multiple', 'is_tree', 'leaves_only', 'has_color', 'has_image'] as $flag) {
                $this->setAttribute($flag, false);
            }
        }

        if (! $this->is_tree) {
            $this->setAttribute('leaves_only', false);
        }

        // A text is never a facet (decision 4), and a range has no page of its own (§5.1).
        if ($this->isText()) {
            $this->setAttribute('is_filterable', false);
            $this->setAttribute('is_indexable', false);
        }

        if ($this->isBool() || ($this->isNumber() && ! $this->usesIntervals())) {
            $this->setAttribute('is_indexable', false);
        }

        if ($this->isNumber()) {
            if (! in_array($this->filter_mode, [self::SLIDER, self::INTERVALS], true)) {
                $this->setAttribute('filter_mode', self::SLIDER);
            }

            $this->setAttribute('precision', max(0, min(6, (int) $this->precision)));
            $this->setAttribute('value_order', self::ALPHA);
        } else {
            $this->setAttribute('filter_mode', null);
            $this->setAttribute('precision', 0);
            $this->attributes['unit_prefix'] = null;
            $this->attributes['unit_suffix'] = null;
        }

        if (! in_array($this->value_order, [self::ALPHA, self::MANUAL], true)) {
            $this->setAttribute('value_order', self::ALPHA);
        }

        if (! $this->isBool()) {
            $this->attributes['toggle_slug'] = null;
        }

        if (! $this->isSelect() && ! $this->usesIntervals()) {
            $this->attributes['seo_pattern'] = null;
        }
    }

    /**
     * A language with a name and no code gets one made of the name (§3.4): transliterated by the
     * language, `-` instead of anything else.
     */
    private function fillCodes(): void
    {
        $codes = $this->getTranslations('code');
        $titles = $this->getTranslations('title');
        $changed = false;

        foreach ($titles as $locale => $title) {
            $code = $codes[$locale] ?? null;

            if ((! is_string($code) || trim($code) === '') && is_string($title) && trim($title) !== '') {
                $codes[$locale] = substr(Str::slug($title, '-', (string) $locale), 0, self::CODE_LENGTH);
                $changed = true;
            }
        }

        foreach ($codes as $locale => $code) {
            if (is_string($code) && $code !== Str::lower(trim($code))) {
                $codes[$locale] = Str::lower(trim($code));
                $changed = true;
            }
        }

        if ($changed) {
            $this->setAttribute('code', $codes);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertValid(): void
    {
        $errors = [];
        $facets = Container::getInstance()->make(Facets::class);
        $own = $this->exists ? $this->facetKey() : null;

        foreach ($this->getTranslations('code') as $locale => $code) {
            if (! is_string($code) || $code === '') {
                continue;
            }

            if (preg_match(self::CODE, $code) !== 1 || strlen($code) > self::CODE_LENGTH) {
                $errors['code.'.$locale][] = (string) __('webx-catalog-properties::errors.code', ['max' => self::CODE_LENGTH]);

                continue;
            }

            $other = static::query()->whereKeyNot($this->getKey())->whereTranslation('code', $code, (string) $locale)->first();
            $holder = $other instanceof self ? $other->displayName((string) $locale) : $facets->taken($code, (string) $locale, $own)?->label();

            if ($holder !== null) {
                $errors['code.'.$locale][] = (string) __('webx-catalog-properties::errors.code-taken', ['holder' => $holder]);
            }
        }

        foreach ($this->getTranslations('toggle_slug') as $locale => $slug) {
            if (is_string($slug) && $slug !== '' && preg_match(self::CODE, $slug) !== 1) {
                $errors['toggle_slug.'.$locale][] = (string) __('webx-catalog-properties::errors.slug');
            }
        }

        if ($this->group_id !== null && ! PropertyGroup::query()->whereKey($this->group_id)->exists()) {
            $errors['group_id'][] = (string) __('webx-catalog-properties::errors.group');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
