<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Categories\Category;
use WebxUi\Admin\Categories\CategoryKind;
use WebxUi\Admin\Categories\IsCategory;
use WebxUi\Admin\History\RecordsHistory;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Localization\HasTranslations;

/**
 * A group of properties on the card — «Screen», «Processor» (§2 of the properties spec, decision
 * 13): one for the whole site, only a heading. The shared category code of the panel, like the
 * labels; its `slug` stays empty, a group has no page.
 *
 * A property belongs to one group by a column rather than through a link table, so the count the
 * shared code shows and refuses a delete by is the properties', not the pivot's it expects.
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property int $position
 * @property bool $is_visible
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $deleted_at
 */
class PropertyGroup extends Model implements Category
{
    use HasExtra;
    use HasTranslations;
    use IsCategory;
    use RecordsHistory;
    use SoftDeletes;

    public const TYPE = 'catalog.property-group';

    public const SCREEN = 'catalog.property-group-form';

    protected $table = 'catalog_property_groups';

    /** @var list<string> */
    protected $fillable = ['title', 'is_visible', 'position'];

    public static function categoryKind(): CategoryKind
    {
        return new CategoryKind(
            model: self::class,
            screen: self::SCREEN,
            view: ['catalog.view', 'catalog.manage'],
            manage: 'catalog.manage',
            noun: 'group',
            plural: 'groups',
            items: 'properties',
        );
    }

    protected static function booted(): void
    {
        // Past the translations, which would read null as "empty in this language".
        static::saving(static function (self $group): void {
            $group->attributes['slug'] = null;
        });
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug'];
    }

    public function extraScreen(): string
    {
        return self::SCREEN;
    }

    /**
     * @return list<string>
     */
    public function categoryFields(): array
    {
        return ['title', 'is_visible'];
    }

    /**
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'group_id');
    }

    /**
     * What the shared code's interface asks for. A property's group is a column, so nothing here
     * is read through it: {@see itemCount()} and {@see scopeWithItemCount()} count the column.
     *
     * @return BelongsToMany<Property, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'catalog_properties', 'group_id', 'id', 'id', 'group_id');
    }

    public function itemCount(): int
    {
        return $this->properties()->count();
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeWithItemCount(Builder $query): Builder
    {
        return $query->withCount('properties as '.static::categoryKind()->countKey());
    }

    public function inUseMessage(int $count): string
    {
        return (string) __('webx-catalog-properties::errors.group-in-use', ['count' => $count]);
    }

    /**
     * @return list<string>
     */
    public function historySkipped(): array
    {
        return ['slug', 'position'];
    }
}
