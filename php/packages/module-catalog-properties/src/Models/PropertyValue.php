<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Models;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\History\RecordsHistory;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Filter\FilterAliases;
use WebxUi\Catalog\Models\Product;
use WebxUi\Localization\HasTranslations;
use WebxUi\Media\Models\MediaFile;
use WebxUi\NestedSet\HasNestedSet;

/**
 * A value of a reference book (§2 of the properties spec): «Black», «Steel». A tree scoped by its
 * property — a flat book is a tree without depth, and one table holds both; «by hand» is the
 * tree's `lft`, so dragging is one mechanism for both.
 *
 * A slug is per language and unique within the property in it; an empty one is made of the name.
 * A slug renamed leaves the old one as an alias of the filter, leading to this value's id — the id
 * never changes, so the alias never needs to follow it (§4.2). There is no bin: a value with
 * products is merged rather than deleted (§3.3), and one without goes for good, its slugs leading
 * to the page without it.
 *
 * @property int $id
 * @property int $property_id
 * @property int|null $parent_id
 * @property int $lft
 * @property int $rgt
 * @property int $depth
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $slug
 * @property string|null $color
 * @property int|null $image_id
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PropertyValue extends Model
{
    use HasExtra;
    use HasNestedSet;
    use HasTranslations;
    use RecordsHistory {
        writeHistory as private journal;
    }

    public const TYPE = 'catalog.property-value';

    /** A value merged away writes nothing of its own: the merge is one row, at the target (§7.4). */
    public bool $quietly = false;

    public const SLUG_LENGTH = 64;

    protected $table = 'catalog_property_values';

    /** @var list<string> */
    protected $fillable = ['property_id', 'title', 'slug', 'color', 'image_id'];

    /** @var array<array-key, mixed>|null the slugs a save in progress is replacing */
    private ?array $slugsBefore = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['property_id' => 'integer', 'image_id' => 'integer', 'parent_id' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $value): void {
            $value->fillSlugs();
            $value->assertValid();
        });

        static::updating(static function (self $value): void {
            $value->slugsBefore = $value->isDirty('slug') ? (array) json_decode((string) $value->getRawOriginal('slug'), true) : null;
        });

        // A new name or slug is new words in the document and the filter of every product of it.
        static::updated(static function (self $value): void {
            if (is_array($value->slugsBefore)) {
                $aliases = Container::getInstance()->make(FilterAliases::class);
                $key = 'p.'.$value->property_id;

                foreach ($value->slugsBefore as $locale => $old) {
                    if (is_string($old) && $old !== '' && $old !== $value->getTranslation('slug', (string) $locale, false)) {
                        $aliases->record($key, (string) $locale, FilterAliases::VALUE, $old, (string) $value->getKey());
                    }
                }

                $value->slugsBefore = null;
            }

            if ($value->wasChanged(['title', 'slug', 'color', 'image_id'])) {
                Container::getInstance()->make(Catalog::class)->touchQuery($value->affectedProducts());
            }
        });

        // A value with products is merged, never deleted from under them (§3.3).
        static::deleting(static function (self $value): void {
            $count = $value->productCount();

            if ($count > 0) {
                throw ValidationException::withMessages(['value' => [(string) __('webx-catalog-properties::errors.value-in-use', ['count' => $count])]]);
            }
        });

        static::deleted(static function (self $value): void {
            $aliases = Container::getInstance()->make(FilterAliases::class);
            $key = 'p.'.$value->property_id;

            foreach ($value->getTranslations('slug') as $locale => $slug) {
                if (is_string($slug) && $slug !== '') {
                    $aliases->record($key, (string) $locale, FilterAliases::VALUE, $slug, null);
                }
            }

            $aliases->retarget($key, FilterAliases::VALUE, (string) $value->getKey(), null);
        });
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug'];
    }

    /**
     * One tree per property.
     *
     * @return list<string>
     */
    public function getNestedSetScopeAttributes(): array
    {
        return ['property_id'];
    }

    public function extraScreen(): string
    {
        return Property::SCREEN;
    }

    /**
     * @return list<string>
     */
    public function historySkipped(): array
    {
        return ['lft', 'rgt', 'depth', 'parent_id', 'property_id'];
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     */
    protected function writeHistory(string $event, array $changes = []): void
    {
        if (! $this->quietly) {
            $this->journal($event, $changes);
        }
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id')->withTrashed();
    }

    /** @return BelongsTo<MediaFile, $this> */
    public function image(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'image_id');
    }

    public function displayName(?string $locale = null): string
    {
        foreach ([$this->getTranslation('title', $locale ?? app()->getLocale()), $this->getTranslation('slug', $locale ?? app()->getLocale())] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '#'.$this->getKey();
    }

    /** The products that hold this value or one under it — what a delete would leave without it. */
    public function productCount(): int
    {
        return (int) DB::table('catalog_product_property_values')
            ->whereIn('value_id', $this->subtreeIds())
            ->distinct()
            ->count('product_id');
    }

    /**
     * This value and every one under it, with fresh bounds (docs/pitfalls: bounds in memory go
     * stale).
     *
     * @return list<int>
     */
    public function subtreeIds(): array
    {
        $fresh = static::query()->find($this->getKey(), ['id', 'lft', 'rgt', 'property_id']);

        if (! $fresh instanceof self) {
            return [(int) $this->getKey()];
        }

        return static::query()
            ->where('property_id', $fresh->property_id)
            ->where('lft', '>=', $fresh->lft)
            ->where('rgt', '<=', $fresh->rgt)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return Builder<Product>
     */
    public function affectedProducts(): Builder
    {
        return Product::withTrashed()->whereIn(
            'catalog_products.id',
            DB::table('catalog_product_property_values')->select('product_id')->whereIn('value_id', $this->subtreeIds()),
        );
    }

    /**
     * A language with a name and no slug gets one made of the name; one taken in the property gets
     * `-2`, which a value is allowed (it has no address of its own), unlike a code.
     */
    private function fillSlugs(): void
    {
        $slugs = $this->getTranslations('slug');
        $changed = false;

        foreach ($this->getTranslations('title') as $locale => $title) {
            $slug = $slugs[$locale] ?? null;

            if ((! is_string($slug) || trim($slug) === '') && is_string($title) && trim($title) !== '') {
                $base = substr(Str::slug($title, '-', (string) $locale), 0, self::SLUG_LENGTH - 4);
                $base = $base === '' ? 'value' : $base;
                $free = $base;

                for ($n = 2; $this->slugTaken($free, (string) $locale); $n++) {
                    $free = $base.'-'.$n;
                }

                $slugs[$locale] = $free;
                $changed = true;
            }
        }

        foreach ($slugs as $locale => $slug) {
            if (is_string($slug) && $slug !== Str::lower(trim($slug))) {
                $slugs[$locale] = Str::lower(trim($slug));
                $changed = true;
            }
        }

        if ($changed) {
            $this->setAttribute('slug', $slugs);
        }
    }

    private function slugTaken(string $slug, string $locale): bool
    {
        return static::query()
            ->where('property_id', $this->property_id)
            ->whereKeyNot($this->getKey())
            ->whereTranslation('slug', $slug, $locale)
            ->exists();
    }

    /**
     * @throws ValidationException
     */
    private function assertValid(): void
    {
        $errors = [];

        foreach ($this->getTranslations('slug') as $locale => $slug) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }

            if (preg_match(Property::CODE, $slug) !== 1 || strlen($slug) > self::SLUG_LENGTH) {
                $errors['slug.'.$locale][] = (string) __('webx-catalog-properties::errors.slug');
            } elseif ($this->slugTaken($slug, (string) $locale)) {
                $errors['slug.'.$locale][] = (string) __('webx-catalog-properties::errors.slug-taken');
            }
        }

        $color = $this->getAttribute('color');

        if ($color !== null && $color !== '') {
            $color = Str::lower(trim((string) $color));
            $this->setAttribute('color', $color);

            if (preg_match('/^#[0-9a-f]{6}$/', $color) !== 1) {
                $errors['color'][] = (string) __('webx-catalog-properties::errors.color');
            }
        } else {
            $this->setAttribute('color', null);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
